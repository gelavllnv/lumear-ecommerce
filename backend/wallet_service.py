"""Integer-centavo allocation ledger for new mobile orders.

Call within the request transaction. Legacy/web orders stay with their original
settlement workflow; no historical balances or commissions are reconstructed.
"""
import json
import uuid

from server import require


def sale_split(subtotal):
    require(type(subtotal) is int and subtotal >= 0, 'Invalid merchandise subtotal.')
    commission = (subtotal * 20 + 50) // 100
    admin = (subtotal * 10 + 50) // 100
    return dict(seller_minor=subtotal-commission, admin_minor=admin,
                owner_minor=commission-admin)


def account(store, db, sid):
    db.execute('''INSERT INTO wallet_accounts(seller_id,available_minor,pending_minor,created_at,updated_at)
        VALUES(%s,0,0,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE seller_id=VALUES(seller_id)''', (sid,))
    return store.one(db, 'SELECT * FROM wallet_accounts WHERE seller_id=%s FOR UPDATE', (sid,))


def journal(db, wallet, oid, kind, available, pending, split):
    db.execute('''INSERT INTO wallet_journals(transaction_uuid,wallet_account_id,seller_order_id,
        entry_type,available_delta,pending_delta,reference,metadata,occurred_at,created_at,updated_at)
        VALUES(%s,%s,%s,%s,%s,%s,%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())''',
        (str(uuid.uuid4()), wallet['id'], oid, kind, available, pending,
         'MOBILE-SALE-'+str(oid), json.dumps(split)))


def record_sale(store, db, oid):
    order = store.one(db, 'SELECT * FROM seller_orders WHERE id=%s FOR UPDATE', (oid,))
    if store.one(db, 'SELECT * FROM mobile_sale_allocations WHERE seller_order_id=%s', (oid,)):
        return  # Checkout replay.
    # Never add another pending credit to an order managed by another ledger.
    require(not store.one(db, 'SELECT id FROM wallet_journals WHERE seller_order_id=%s LIMIT 1', (oid,)),
            'This order already has a settlement ledger.', 409)
    split = sale_split(int(order['subtotal_minor']))
    require(order['commission_minor'] == split['admin_minor']+split['owner_minor'],
            'Order commission does not match the revenue split.', 409)
    wallet = account(store, db, order['seller_id'])
    db.execute('''INSERT INTO mobile_sale_allocations(seller_order_id,seller_minor,admin_minor,owner_minor)
        VALUES(%s,%s,%s,%s)''', (oid, split['seller_minor'], split['admin_minor'], split['owner_minor']))
    db.execute('UPDATE wallet_accounts SET pending_minor=pending_minor+%s,updated_at=UTC_TIMESTAMP() WHERE id=%s',
               (split['seller_minor'], wallet['id']))
    journal(db, wallet, oid, 'mobile_sale_pending', 0, split['seller_minor'], split)


def cancel_sale(store, db, order):
    allocation = store.one(db, 'SELECT * FROM mobile_sale_allocations WHERE seller_order_id=%s FOR UPDATE', (order['id'],))
    if not allocation:
        require(not store.one(db, 'SELECT id FROM wallet_journals WHERE seller_order_id=%s LIMIT 1', (order['id'],)),
                'Use the original settlement workflow for this order.')
        return
    require(allocation['status'] == 'pending', 'Released earnings require the refund workflow.', 409)
    wallet = account(store, db, order['seller_id'])
    amount = allocation['seller_minor']
    require(wallet['pending_minor'] >= amount, 'Wallet history requires reconciliation.', 409)
    db.execute('UPDATE wallet_accounts SET pending_minor=pending_minor-%s,updated_at=UTC_TIMESTAMP() WHERE id=%s', (amount, wallet['id']))
    db.execute("UPDATE mobile_sale_allocations SET status='cancelled' WHERE seller_order_id=%s", (order['id'],))
    journal(db, wallet, order['id'], 'mobile_sale_cancelled', 0, -amount, {})


def release_sale(store, db, sid, oid):
    # A seller can request release, but cannot set payment or delivery status.
    order = store.one(db, '''SELECT so.*,o.payment_status FROM seller_orders so
        JOIN orders o ON o.id=so.order_id WHERE so.id=%s AND so.seller_id=%s FOR UPDATE''', (oid, sid))
    require(order, 'Order not found.', 404)
    allocation = store.one(db, 'SELECT * FROM mobile_sale_allocations WHERE seller_order_id=%s FOR UPDATE', (oid,))
    require(allocation, 'Use the original settlement workflow for this order.', 409)
    if allocation['status'] == 'released':
        return dict(message='Earnings already credited to your e-wallet.')
    require(allocation['status'] == 'pending', 'This sale was cancelled.', 409)
    require(order['status'] == 'completed' and order['payment_status'] == 'paid',
            'Earnings are released after completed delivery and confirmed payment.', 409)
    require(allocation['seller_minor'] + allocation['admin_minor'] + allocation['owner_minor'] == order['subtotal_minor']
            and allocation['admin_minor'] + allocation['owner_minor'] == order['commission_minor'],
            'Order totals changed and require wallet reconciliation.', 409)
    require(not store.one(db, """SELECT rr.id FROM return_requests rr
        LEFT JOIN mobile_buyer_wallet_credits c ON c.return_request_id=rr.id
        WHERE rr.seller_order_id=%s AND (rr.status IN ('requested','approved','in_dispute')
        OR rr.status='refunded' AND c.return_request_id IS NULL) LIMIT 1""", (oid,)),
            'Resolve open returns and reconcile external refunds before releasing earnings.', 409)
    require(not store.one(db, "SELECT id FROM wallet_journals WHERE seller_order_id=%s AND entry_type NOT IN ('mobile_sale_pending','mobile_refund') LIMIT 1", (oid,)),
            'This order has other wallet activity and requires reconciliation.', 409)
    from buyer_wallet import refund_totals
    refunded=refund_totals(store,db,oid)
    net={k:int(allocation[k])-refunded[k] for k in ('seller_minor','admin_minor','owner_minor')}
    require(all(v>=0 for v in net.values()),'Refund allocation needs reconciliation.',409)
    pending = store.one(db, '''SELECT COUNT(*) entries,COALESCE(SUM(pending_delta),0) amount
        FROM wallet_journals WHERE seller_order_id=%s AND entry_type IN ('mobile_sale_pending','mobile_refund') ''', (oid,))
    require(pending['entries'] >= 1 and pending['amount'] == net['seller_minor'],
            'Pending wallet history requires reconciliation.', 409)
    wallet = account(store, db, sid)
    amount = net['seller_minor']
    require(wallet['pending_minor'] >= amount, 'Wallet history requires reconciliation.', 409)
    db.execute('''UPDATE wallet_accounts SET pending_minor=pending_minor-%s,
        available_minor=available_minor+%s,updated_at=UTC_TIMESTAMP() WHERE id=%s''', (amount, amount, wallet['id']))
    for beneficiary in ('admin', 'owner'):
        require(store.one(db, 'SELECT beneficiary FROM mobile_platform_wallets WHERE beneficiary=%s FOR UPDATE', (beneficiary,)),
                'Platform wallet is not configured.', 503)
        db.execute('''UPDATE mobile_platform_wallets SET available_minor=available_minor+%s,
            updated_at=UTC_TIMESTAMP() WHERE beneficiary=%s''', (net[beneficiary+'_minor'], beneficiary))
    db.execute("UPDATE mobile_sale_allocations SET status='released',released_at=UTC_TIMESTAMP() WHERE seller_order_id=%s", (oid,))
    journal(db, wallet, oid, 'mobile_sale_released', amount, -amount,
            net)
    return dict(message='Confirmed seller earnings, after refunds, credited to your e-wallet.')
