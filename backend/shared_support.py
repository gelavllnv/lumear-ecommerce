"""Seller reporting and messaging using the imported shared schema."""
import hashlib
import json
from collections import Counter
from datetime import date, datetime, timedelta, timezone

from mysql_store import timestamp
from server import Problem, require, text, integer


def request_payout(store, db, uid, sid, body):
    store.one(db,'SELECT id FROM users WHERE id=%s FOR UPDATE',(uid,))
    wallet=store.one(db,'SELECT * FROM wallet_accounts WHERE seller_id=%s FOR UPDATE',(sid,))
    require(wallet,'Your shop does not have a wallet account yet.')
    amount=integer(body.get('amount'),'Payout amount',100,2147483647)
    account_id=str(body.get('account_id',''))
    account=store.one(db,'SELECT id FROM seller_payout_accounts WHERE id=%s AND seller_id=%s',(account_id,sid))
    require(account,'Choose one of your saved payout accounts.')
    key=text(body.get('idempotency_key'),'Request key',8,120)
    scope='mobile-payout:'+str(sid)
    fingerprint=hashlib.sha256(json.dumps(dict(amount=amount,account_id=account_id),sort_keys=True).encode()).hexdigest()
    previous=store.one(db,'SELECT request_hash FROM idempotency_keys WHERE scope=%s AND `key`=%s FOR UPDATE',(scope,key))
    if previous:
        require(previous['request_hash']==fingerprint,'This key belongs to a different payout request.',409)
        return dict(message='Payout request received for manual review.')
    pending=store.one(db,"SELECT COALESCE(SUM(amount_minor),0) amount FROM seller_payout_requests WHERE seller_id=%s AND paid_at IS NULL AND status NOT IN ('rejected','cancelled','failed','paid')",(sid,))
    require(amount<=max(0,int(wallet['available_minor'])-int(pending['amount'])),'This exceeds the available balance after open payout requests.',409)
    db.execute("INSERT INTO seller_payout_requests(seller_id,seller_payout_account_id,amount_minor,status,created_at,updated_at) VALUES(%s,%s,%s,'requested',UTC_TIMESTAMP(),UTC_TIMESTAMP())",(sid,account_id,amount))
    db.execute('INSERT INTO idempotency_keys(scope,`key`,request_hash) VALUES(%s,%s,%s)',(scope,key,fingerprint))
    return dict(message='Payout request received for manual review.')


def chat_route(store, db, method, path, body, user, seller):
    import re
    uid=user['id']
    condition,args=('c.seller_id=%s',(seller['id'],)) if seller else ('c.buyer_id=%s',(uid,))
    def threads():
        return store.rows(db,f'''SELECT c.id,c.buyer_id,c.seller_id,s.name shop,u.name buyer
            FROM conversations c JOIN sellers s ON s.id=c.seller_id JOIN users u ON u.id=c.buyer_id
            WHERE {condition} ORDER BY c.last_message_at DESC,c.id DESC''',args)
    def thread_json(r):
        return dict(id=str(r['id']),name=r['buyer'] if seller else r['shop'])
    if path=='/chat/threads':
        if method=='GET':return dict(threads=[thread_json(r) for r in threads()])
        require(method=='POST' and not seller,'Only a buyer can start a seller conversation.',403)
        sid=body.get('seller_id');pid=body.get('product_id')
        require(store.one(db,"SELECT id FROM sellers WHERE id=%s AND status='approved'",(sid,)),'Seller unavailable.',404)
        require(store.one(db,'SELECT id FROM products WHERE id=%s AND seller_id=%s AND deleted_at IS NULL',(pid,sid)),'Product does not belong to this seller.',404)
        store.one(db,'SELECT id FROM users WHERE id=%s FOR UPDATE',(uid,))
        row=store.one(db,'SELECT id FROM conversations WHERE buyer_id=%s AND seller_id=%s',(uid,sid))
        if not row:
            db.execute('INSERT INTO conversations(buyer_id,seller_id,created_at,updated_at) VALUES(%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())',(uid,sid))
            row=dict(id=db.lastrowid)
        return next(thread_json(r) for r in threads() if str(r['id'])==str(row['id']))
    match=re.fullmatch(r'/chat/threads/(\d+)/messages',path)
    require(match,'Conversation endpoint not found.',404)
    thread=next((r for r in threads() if str(r['id'])==match[1]),None)
    require(thread,'Conversation not found.',404)
    if method=='POST':
        message=text(body.get('text'),'Message',1,2000)
        key=text(body.get('request_id'),'Request ID',8,120)
        scope='mobile-message:'+str(uid)
        fingerprint=hashlib.sha256(json.dumps([match[1],message]).encode()).hexdigest()
        store.one(db,'SELECT id FROM conversations WHERE id=%s FOR UPDATE',(match[1],))
        previous=store.one(db,'SELECT request_hash FROM idempotency_keys WHERE scope=%s AND `key`=%s',(scope,key))
        if previous:
            require(previous['request_hash']==fingerprint,'Message request ID already used.',409)
        else:
            db.execute('INSERT INTO messages(conversation_id,sender_id,body,created_at,updated_at) VALUES(%s,%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())',(match[1],uid,message))
            db.execute('UPDATE conversations SET last_message_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=%s',(match[1],))
            db.execute('INSERT INTO idempotency_keys(scope,`key`,request_hash) VALUES(%s,%s,%s)',(scope,key,fingerprint))
        return dict(message='Message sent.')
    require(method=='GET','Method not allowed.',405)
    rows=store.rows(db,'SELECT sender_id,body,created_at FROM messages WHERE conversation_id=%s ORDER BY id',(match[1],))
    return dict(messages=[dict(text=r['body'],mine=str(r['sender_id'])==uid,date=r['created_at'].replace(tzinfo=timezone.utc).isoformat()) for r in rows])


def analytics(store, db, seller, query):
    try:
        start=date.fromisoformat(query['start'][0]);end=date.fromisoformat(query['end'][0])
    except (KeyError,ValueError,IndexError,TypeError):
        raise Problem('Choose valid analytics dates.')
    require(0<(end-start).days<=3660,'Choose an analytics range of up to ten years.')
    orders=store.orders(db,sid=seller['id'])
    products=store.catalog(db,seller['id'])
    def day(o):
        return (datetime.fromtimestamp(o.get('completed_at') or o['date'],timezone.utc)+timedelta(hours=8)).date()
    def selected(a,b):return [o for o in orders if a<=day(o)<b]
    def metrics(rows):
        sales=[o for o in rows if o['raw_status']=='completed']
        revenue=sum(o['subtotal'] for o in sales)
        return dict(sales=revenue,orders=len(rows),earnings=sum(o['subtotal']-o['commission']-o['refund'] for o in sales),
            conversion=0,units=sum(i['quantity'] for o in sales for i in o['items']),aov=revenue/len(sales) if sales else 0,
            cancelled=sum(o['raw_status']=='cancelled' for o in rows),returned=sum(o['refund']>0 for o in sales))
    current=selected(start,end);sales=[o for o in current if o['raw_status']=='completed']
    daily=[];d=start
    while d<end:
        daily.append(dict(date=d.isoformat(),revenue=sum(o['subtotal'] for o in sales if day(o)==d)))
        d+=timedelta(days=1)
    product_rows=[]
    for p in products:
        matched=[(o,i) for o in sales for i in o['items'] if i['product_id']==p['id']]
        product_rows.append(dict(id=p['id'],name=p['name'],views=0,cart_rate=0,purchases=len({o['id'] for o,i in matched}),
            units=sum(i['quantity'] for o,i in matched),revenue=sum(i['quantity']*i['price'] for o,i in matched),low_performing=False,stock=p['stock']))
    product_rows.sort(key=lambda p:(-p['units'],p['id']))
    counts=Counter(o['buyer_id'] for o in sales)
    returning={uid for uid in counts if any(o['buyer_id']==uid and o['raw_status']=='completed' and day(o)<start for o in orders)}
    promotions=[]
    for table,kind,label in [('seller_vouchers','voucher','code'),('flash_sales','flash_sale',None)]:
        for r in store.rows(db,f'SELECT * FROM {table} WHERE seller_id=%s AND archived_at IS NULL',(seller['id'],)):
            promotions.append(dict(id=f"{kind}:{r['id']}",name=r[label] if label else f"Flash sale {r['id']}",kind=kind,
                active=not bool(r['is_paused']),views=0,claims=0,redemptions=r.get('used_count',r.get('quantity_sold',0)),
                revenue=0,conversion=0,warning='Attribution and traffic metrics are not tracked in this export.'))
    statuses=Counter(o['raw_status'] for o in orders)
    returns=store.rows(db,'''SELECT rr.id,rr.seller_order_id,rr.reason,rr.status FROM return_requests rr
        JOIN seller_orders so ON so.id=rr.seller_order_id WHERE so.seller_id=%s ORDER BY rr.id DESC''',(seller['id'],))
    notifications=store.notifications(db,seller['user_id'])
    return dict(current=metrics(current),previous=metrics(selected(start-(end-start),start)),daily=daily,products=product_rows,
        customers=dict(new=len(counts)-len(returning),returning=len(returning),repeat=sum(n>=2 for n in counts.values()),locations=[]),
        promotions=promotions,operations=dict(new=statuses['pending']+statuses['accepted'],packing=statuses['packed']+statuses['ready_to_ship'],
            shipped=statuses['shipped'],delivered=statuses['delivered']+statuses['completed'],delayed=0,returns=len(returns)),
        returns=[dict(id=str(r['id']),order_id=str(r['seller_order_id']),reason=r['reason'],status=r['status'],amount=0) for r in returns],
        notifications=[dict(id=n['id'],title=n['title'],type='account',read=n['read']) for n in notifications],
        traffic_available=False,
        notice='Visit, conversion and promotion attribution metrics are unavailable. Sales figures use recorded orders.')
