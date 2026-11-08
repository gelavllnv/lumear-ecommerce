"""Buyer return requests and wallet credits from the trusted refund workflow.

No endpoint allows buyers to approve returns or credit their own wallet.
"""
from mysql_store import timestamp
from server import require, text


def refund_amount(item):
    amount = int(item['unit_price_minor']) * int(item['quantity']) - int(item['promotion_discount_minor'])
    require(amount > 0, 'This item has no refundable merchandise amount.')
    return amount


def credit_refund(store, db, return_id, allocation=None):
    """Call in the SAME transaction as refund accounting, instead of an external payout.

    The trusted refund processor must reverse seller/platform revenue first and
    set return_requests.status='refunded'. Caller owns that settlement workflow.
    Unique return key makes retries harmless. Never import historical refunds.
    """
    row = store.one(db, '''SELECT w.*,rr.status,rr.buyer_id AS return_buyer
        FROM mobile_buyer_wallet_returns w JOIN return_requests rr ON rr.id=w.return_request_id
        WHERE w.return_request_id=%s FOR UPDATE''', (return_id,))
    require(row and row['status'] == 'refunded' and row['buyer_id'] == row['return_buyer'],
            'Only a confirmed wallet refund can be credited.', 409)
    require(allocation and sum(allocation[k] for k in ('seller_minor','admin_minor','owner_minor')) == row['amount_minor'],
            'Refund accounting must be completed before crediting the wallet.',409)
    db.execute('''INSERT INTO mobile_buyer_wallet_credits(return_request_id,buyer_id,amount_minor,
        seller_order_id,seller_minor,admin_minor,owner_minor,allocation_status)
        VALUES(%s,%s,%s,%s,%s,%s,%s,%s)''',
        (return_id, row['buyer_id'], row['amount_minor'],allocation['seller_order_id'],
         allocation['seller_minor'],allocation['admin_minor'],allocation['owner_minor'],allocation['status']))


def refund_totals(store, db, oid):
    row=store.one(db,'''SELECT COALESCE(SUM(amount_minor),0) amount_minor,
        COALESCE(SUM(seller_minor),0) seller_minor,COALESCE(SUM(admin_minor),0) admin_minor,
        COALESCE(SUM(owner_minor),0) owner_minor FROM mobile_buyer_wallet_credits WHERE seller_order_id=%s''',(oid,))
    return {k:int(v) for k,v in row.items()}


def allocate_refund(amount, remaining):
    """Allocate integer centavos against remaining shares without negative rounding deltas."""
    keys=('seller_minor','admin_minor','owner_minor')
    total=sum(remaining[k] for k in keys)
    require(type(amount) is int and 0<amount<=total and all(remaining[k]>=0 for k in keys),
            'Refund amount exceeds the remaining order allocation.',409)
    split={k:amount*remaining[k]//total for k in keys}
    ranked=sorted(keys,key=lambda k: -(amount*remaining[k]%total))
    for key in ranked[:amount-sum(split.values())]:
        split[key]+=1
    return split


def seller_returns(store, db, sid):
    rows=store.rows(db,'''SELECT rr.*,w.amount_minor,oi.product_name,o.reference,u.name AS buyer
        FROM mobile_buyer_wallet_returns w JOIN return_requests rr ON rr.id=w.return_request_id
        JOIN order_items oi ON oi.id=rr.order_item_id JOIN seller_orders so ON so.id=rr.seller_order_id
        JOIN orders o ON o.id=so.order_id JOIN users u ON u.id=rr.buyer_id
        WHERE so.seller_id=%s ORDER BY rr.id DESC''',(sid,))
    return [dict(id=str(r['id']),order_id=str(r['seller_order_id']),reference=r['reference'],buyer=r['buyer'],
        product=r['product_name'],reason=r['reason'],description=r['description'],status=r['status'],
        amount_minor=int(r['amount_minor']),response=r['seller_response'] or '') for r in rows]


def review_return(store, db, sid, actor, rid, action, body):
    # Discover order without locking; then follow settlement's order -> return -> allocation lock order.
    found=store.one(db,'''SELECT rr.seller_order_id FROM return_requests rr
        JOIN seller_orders so ON so.id=rr.seller_order_id WHERE rr.id=%s AND so.seller_id=%s''',(rid,sid))
    require(found,'Return request not found.',404)
    order=store.one(db,'''SELECT so.*,o.payment_status FROM seller_orders so JOIN orders o ON o.id=so.order_id
        WHERE so.id=%s AND so.seller_id=%s FOR UPDATE''',(found['seller_order_id'],sid))
    row=store.one(db,'''SELECT rr.*,w.amount_minor FROM return_requests rr
        JOIN mobile_buyer_wallet_returns w ON w.return_request_id=rr.id AND w.buyer_id=rr.buyer_id
        WHERE rr.id=%s FOR UPDATE''',(rid,))
    require(row,'This request does not use the buyer wallet workflow.',409)
    if action in ('approve','reject'):
        target='approved' if action=='approve' else 'rejected'
        if row['status']==target:
            return dict(message='Return already '+target+'.')
        require(row['status']=='requested','This return has already been reviewed.',409)
        response=text(body.get('response'),'Seller response',3,2000)
        db.execute('''UPDATE return_requests SET status=%s,seller_response=%s,responded_at=UTC_TIMESTAMP(),
            updated_at=UTC_TIMESTAMP() WHERE id=%s''',(target,response,rid))
        store.timeline(db,order,actor,'return_'+target,'Return '+target,response)
        return dict(message='Return '+target+'.')
    require(action=='refund','Invalid return action.',404)
    credited=store.one(db,'SELECT return_request_id FROM mobile_buyer_wallet_credits WHERE return_request_id=%s',(rid,))
    if credited:
        require(row['status']=='refunded','Refund history needs reconciliation.',409)
        return dict(message='Refund already credited to the buyer e-wallet.')
    require(row['status']=='approved','Approve the return before processing its refund.',409)
    require(body.get('items_received') is True,'Confirm the returned items were received before refunding.')
    require(order['status'] in ('delivered','completed') and order['payment_status']=='paid',
            'Refunds require a paid, delivered order.',409)
    allocation=store.one(db,'SELECT * FROM mobile_sale_allocations WHERE seller_order_id=%s FOR UPDATE',(order['id'],))
    require(allocation and allocation['status'] in ('pending','released'),
            'This historical order needs its original settlement administrator.',409)
    keys=('seller_minor','admin_minor','owner_minor')
    require(sum(allocation[k] for k in keys)==order['subtotal_minor'], 'Order allocation needs reconciliation.',409)
    prior=refund_totals(store,db,order['id'])
    remaining={k:int(allocation[k])-prior[k] for k in keys}
    split=allocate_refund(int(row['amount_minor']),remaining)
    from wallet_service import account, journal
    wallet_row=account(store,db,sid)
    pending=allocation['status']=='pending'
    history=store.one(db,'''SELECT COALESCE(SUM(available_delta),0) available,
        COALESCE(SUM(pending_delta),0) pending FROM wallet_journals WHERE seller_order_id=%s''',(order['id'],))
    require(int(history['pending' if pending else 'available'])==remaining['seller_minor'],
            'Wallet history needs reconciliation.',409)
    column='pending_minor' if pending else 'available_minor'
    reserved=0
    if not pending:
        reserved=int(store.one(db,"""SELECT COALESCE(SUM(amount_minor),0) amount FROM seller_payout_requests
            WHERE seller_id=%s AND paid_at IS NULL AND status NOT IN ('rejected','cancelled','failed','paid')""",(sid,))['amount'])
    require(int(wallet_row[column])-reserved>=split['seller_minor'],'Available seller funds are insufficient. Resolve open payouts or contact support.',409)
    if not pending:
        for beneficiary in ('admin','owner'):
            platform=store.one(db,'SELECT available_minor FROM mobile_platform_wallets WHERE beneficiary=%s FOR UPDATE',(beneficiary,))
            require(platform and int(platform['available_minor'])>=split[beneficiary+'_minor'],
                    'Platform funds need reconciliation before refunding.',409)
            db.execute('UPDATE mobile_platform_wallets SET available_minor=available_minor-%s,updated_at=UTC_TIMESTAMP() WHERE beneficiary=%s',
                       (split[beneficiary+'_minor'],beneficiary))
    db.execute(f'UPDATE wallet_accounts SET {column}={column}-%s,updated_at=UTC_TIMESTAMP() WHERE id=%s',
               (split['seller_minor'],wallet_row['id']))
    journal(db,wallet_row,order['id'],'mobile_refund',0 if pending else -split['seller_minor'],
            -split['seller_minor'] if pending else 0,dict(split,return_request_id=str(rid)))
    db.execute("UPDATE return_requests SET status='refunded',refunded_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=%s",(rid,))
    credit_refund(store,db,rid,dict(split,seller_order_id=order['id'],status=allocation['status']))
    store.timeline(db,order,actor,'refunded','Refund credited','Return '+str(rid)+' credited to buyer e-wallet.')
    return dict(message='Refund credited to the buyer e-wallet.')


def wallet(store, db, uid):
    credits = store.rows(db, '''SELECT c.*,o.reference FROM mobile_buyer_wallet_credits c
        JOIN return_requests rr ON rr.id=c.return_request_id
        JOIN seller_orders so ON so.id=rr.seller_order_id JOIN orders o ON o.id=so.order_id
        WHERE c.buyer_id=%s ORDER BY c.created_at DESC,c.return_request_id DESC''', (uid,))
    returns = store.rows(db, '''SELECT rr.*,w.amount_minor,oi.product_name,o.reference
        FROM mobile_buyer_wallet_returns w JOIN return_requests rr ON rr.id=w.return_request_id
        JOIN order_items oi ON oi.id=rr.order_item_id
        JOIN seller_orders so ON so.id=rr.seller_order_id JOIN orders o ON o.id=so.order_id
        WHERE w.buyer_id=%s ORDER BY rr.id DESC''', (uid,))
    return dict(balance_minor=sum(int(r['amount_minor']) for r in credits), currency='PHP',
        transactions=[dict(id=str(r['return_request_id']), amount_minor=int(r['amount_minor']),
            reference=r['reference'], created_at=timestamp(r['created_at'])) for r in credits],
        returns=[dict(id=str(r['id']), status=r['status'], reason=r['reason'],
            product=r['product_name'], amount_minor=int(r['amount_minor']), reference=r['reference'],
            response=r.get('seller_response') or '') for r in returns])


def request_return(store, db, uid, body):
    item_id = text(body.get('order_item_id'), 'Order item', 1, 20)
    reason = text(body.get('reason'), 'Reason', 3, 100)
    description = text(body.get('description'), 'Description', 10, 3000)
    # Lock item to serialize duplicate requests, and obtain ownership from orders.
    item = store.one(db, '''SELECT oi.*,so.status,o.payment_status FROM order_items oi
        JOIN seller_orders so ON so.id=oi.seller_order_id JOIN orders o ON o.id=so.order_id
        WHERE oi.id=%s AND o.buyer_id=%s FOR UPDATE''', (item_id, uid))
    require(item, 'Order item not found.', 404)
    require(item['status'] in ('delivered', 'completed') and item['payment_status'] == 'paid',
            'Returns require a delivered, paid order.', 409)
    old = store.one(db, 'SELECT id FROM return_requests WHERE order_item_id=%s LIMIT 1', (item_id,))
    require(not old, 'A return already exists for this item. Contact support for help.', 409)
    amount = refund_amount(item)
    db.execute('''INSERT INTO return_requests(seller_order_id,order_item_id,buyer_id,reason,description,
        status,respond_by,created_at,updated_at) VALUES(%s,%s,%s,%s,%s,'requested',
        DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP())''',
        (item['seller_order_id'], item_id, uid, reason, description))
    rid = db.lastrowid
    db.execute('''INSERT INTO mobile_buyer_wallet_returns(return_request_id,buyer_id,amount_minor)
        VALUES(%s,%s,%s)''', (rid, uid, amount))
    return dict(id=str(rid), message='Return requested. Your refund will appear in your e-wallet after review and refund processing.')
