"""Integration checks use temporary rows in one transaction, always rolled back.

Set RUN_SHARED_MYSQL_TESTS=1 to exercise the configured imported database.
No schema creation, deletion or account password changes are performed.
"""
import contextlib
import json
import os
import secrets
import threading
import tempfile
import base64
from pathlib import Path
import unittest
from unittest.mock import patch, Mock
from http.server import ThreadingHTTPServer
from urllib.request import Request, urlopen

import bcrypt
import pymysql
from pymysql.cursors import DictCursor

from mysql_store import settings
from run_api import SharedHandler
from server import Problem
from shared_store import SharedStore


class TransactionStore(SharedStore):
    @contextlib.contextmanager
    def transaction(self):
        with self.connection.cursor() as db:
            point='request_'+secrets.token_hex(6)
            db.execute('SAVEPOINT '+point)
            try:
                yield db
            except Exception:
                db.execute('ROLLBACK TO SAVEPOINT '+point)
                raise
            finally:
                db.execute('RELEASE SAVEPOINT '+point)


@unittest.skipUnless(os.environ.get('RUN_SHARED_MYSQL_TESTS')=='1','Opt-in local MySQL integration checks')
class SharedIntegrationTests(unittest.TestCase):
    def setUp(self):
        self.config=settings()
        self.connection=pymysql.connect(host=self.config['MYSQL_HOST'],port=int(self.config['MYSQL_PORT']),
            user=self.config['MYSQL_USER'],password=self.config['MYSQL_PASSWORD'],database=self.config['MYSQL_DATABASE'],
            charset='utf8mb4',cursorclass=DictCursor,autocommit=False,init_command="SET time_zone='+00:00'")
        self.addCleanup(self.connection.close)
        self.addCleanup(self.connection.rollback)
        self.store=TransactionStore(self.config);self.store.connection=self.connection
        uploads = tempfile.TemporaryDirectory(dir=Path(__file__).parent.parent / 'tmp')
        self.addCleanup(uploads.cleanup)
        self.store.config['PRIVATE_UPLOAD_ROOT'] = uploads.name
        tag=secrets.token_hex(8)
        self.password='IntegrationPass123'
        hashed=bcrypt.hashpw(self.password.encode(),bcrypt.gensalt(rounds=4)).decode()
        self.emails={};self.ids={}
        with self.connection.cursor() as db:
            for role in ('buyer','seller'):
                db.execute('SELECT id FROM roles WHERE name=%s',(role,));row=db.fetchone()
                if row:rid=row['id']
                else:
                    db.execute('INSERT INTO roles(name,created_at,updated_at) VALUES(%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())',(role,));rid=db.lastrowid
                email=f'mobile-integration-{role}-{tag}@example.invalid';self.emails[role]=email
                db.execute('INSERT INTO users(name,email,password,phone,email_verified_at,created_at,updated_at) VALUES(%s,%s,%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())',('Integration '+role,email,hashed,'09171234567'))
                self.ids[role]=db.lastrowid
                db.execute('INSERT INTO role_user(role_id,user_id) VALUES(%s,%s)',(rid,db.lastrowid))
            db.execute('INSERT INTO categories(name,slug,is_active,created_at,updated_at) VALUES(%s,%s,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())',('Integration '+tag,'integration-'+tag));self.category='Integration '+tag
            db.execute("INSERT INTO sellers(user_id,name,slug,description,status,commission_bps,created_at,updated_at) VALUES(%s,%s,%s,'Test shop','approved',800,UTC_TIMESTAMP(),UTC_TIMESTAMP())",(self.ids['seller'],'Integration shop '+tag,'shop-'+tag));self.sid=db.lastrowid
            db.execute('INSERT INTO wallet_accounts(seller_id,available_minor,pending_minor,created_at,updated_at) VALUES(%s,10000,0,UTC_TIMESTAMP(),UTC_TIMESTAMP())',(self.sid,))
        self.tokens={r:self.store.dispatch('POST','/api/auth/login',dict(email=e,password=self.password))['token'] for r,e in self.emails.items()}

    def call(self,role,method,path,body=None):
        return self.store.dispatch(method,'/api'+path,body,self.tokens[role])

    def product(self):
        result=self.call('seller','POST','/seller/products',dict(name='Integration basket',description='Test',category=self.category,
            subcategory='',price=10007,stock=10,variant_label='Natural',active=True,archived=False,media=[]))
        row=next(p for p in self.call('buyer','GET','/buyer/workspace')['products'] if p['id']==result['id'])
        return row

    def checkout(self,p,key):
        address=self.call('buyer','POST','/addresses',dict(label='Test',name='Integration buyer',phone='09171234567',
            street='Test Street',barangay='Test Barangay',city='Manila',province='Metro Manila',postal_code='1000',default=True))
        self.call('buyer','POST','/cart',dict(product_id=p['id'],variant_id=p['variants'][0]['id'],quantity=2))
        quote=self.call('buyer','POST','/checkout/quote',dict(address_id=address['id'],vouchers=[]))
        body=dict(address_id=address['id'],vouchers=[],payment_method='COD',note='',expected_quote=quote,idempotency_key=key)
        order=self.call('buyer','POST','/checkout',body)['orders'][0]
        self.assertEqual(self.call('buyer','POST','/checkout',body)['orders'][0]['id'],order['id'])
        self.assertEqual(order['shipping'],5000)
        self.assertEqual(order['commission'],4003)
        return order

    def test_buyer_receipt_requires_delivery_payment_and_no_open_return(self):
        p=self.product();order=self.checkout(p,'buyer-receipt-check')
        path=f"/orders/{order['id']}/received"
        with self.assertRaises(Problem): self.call('buyer','POST',path)
        with self.assertRaises(Problem): self.call('seller','POST',path)
        with self.connection.cursor() as db:
            db.execute("UPDATE seller_orders SET status='delivered',delivered_at=UTC_TIMESTAMP() WHERE id=%s",(order['id'],))
        with self.assertRaises(Problem): self.call('buyer','POST',path)
        with self.connection.cursor() as db:
            db.execute('SELECT order_id FROM seller_orders WHERE id=%s',(order['id'],));oid=db.fetchone()['order_id']
            db.execute("UPDATE orders SET payment_status='paid',buyer_id=%s WHERE id=%s",(self.ids['seller'],oid))
        with self.assertRaises(Problem): self.call('buyer','POST',path)
        with self.connection.cursor() as db:
            db.execute('UPDATE orders SET buyer_id=%s WHERE id=%s',(self.ids['buyer'],oid))
            db.execute('SELECT id FROM order_items WHERE seller_order_id=%s LIMIT 1',(order['id'],));item=db.fetchone()['id']
            db.execute("""INSERT INTO return_requests(seller_order_id,order_item_id,buyer_id,reason,description,status,respond_by,created_at,updated_at)
                VALUES(%s,%s,%s,'Damaged','Item arrived damaged','requested',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())""",
                (order['id'],item,self.ids['buyer']))
            rid=db.lastrowid
        with self.assertRaises(Problem): self.call('buyer','POST',path)
        self.assertFalse(self.call('buyer','GET','/orders')[0]['can_receive'])
        with self.connection.cursor() as db:
            db.execute("UPDATE return_requests SET status='rejected' WHERE id=%s",(rid,))
        self.assertTrue(self.call('buyer','GET','/orders')[0]['can_receive'])
        self.call('buyer','POST',path);self.call('buyer','POST',path)
        completed=self.call('buyer','GET','/orders')[0]
        self.assertEqual(completed['raw_status'],'completed')
        self.assertFalse(completed['can_receive'])
        self.assertFalse(completed['can_refund'])  # This item's rejected request already exists.
        with self.connection.cursor() as db:
            db.execute("SELECT COUNT(*) n FROM order_timelines WHERE seller_order_id=%s AND event='Order received'",(order['id'],))
            self.assertEqual(db.fetchone()['n'],1)
            db.execute('SELECT status FROM mobile_sale_allocations WHERE seller_order_id=%s',(order['id'],))
            self.assertEqual(db.fetchone()['status'],'pending')

    def test_buyer_cancellation_retries_restore_inventory_once(self):
        p=self.product();order=self.checkout(p,'buyer-cancel-check')
        path=f"/orders/{order['id']}/cancel"
        self.assertTrue(self.call('buyer','GET','/orders')[0]['can_cancel'])
        self.call('buyer','POST',path,dict(reason='Ordered the wrong item'))
        self.call('buyer','POST',path,dict(reason='Ordered the wrong item'))
        self.assertEqual(self.call('buyer','GET','/orders')[0]['cancellation_reason'],'Ordered the wrong item')
        seller=self.call('seller','GET','/seller/workspace')
        self.assertEqual(seller['products'][0]['stock'],10)
        self.assertEqual(seller['earnings']['wallet_pending'],0)

    def wallet_return(self, order, item_id=None):
        if item_id is None: item_id=order['items'][0]['id']
        request=self.call('buyer','POST','/buyer/returns',dict(order_item_id=item_id,reason='Damaged',description='The item arrived damaged'))
        rid=request['id']
        with self.assertRaises(Problem): self.call('buyer','POST',f'/seller/returns/{rid}/approve',dict(response='Return it'))
        self.call('seller','POST',f'/seller/returns/{rid}/approve',dict(response='Please return to our shop address'))
        with self.assertRaises(Problem): self.call('seller','POST',f'/seller/returns/{rid}/refund',dict(items_received=False))
        return rid

    def paid_delivered(self,order,completed=False):
        with self.connection.cursor() as db:
            db.execute("UPDATE seller_orders SET status=%s,delivered_at=UTC_TIMESTAMP() WHERE id=%s",('completed' if completed else 'delivered',order['id']))
            db.execute("UPDATE orders o JOIN seller_orders so ON so.order_id=o.id SET o.payment_status='paid' WHERE so.id=%s",(order['id'],))

    def test_pending_refund_credits_buyer_once_and_releases_only_remainder(self):
        p=self.product();order=self.checkout(p,'pending-buyer-refund')
        self.paid_delivered(order)
        with self.connection.cursor() as db:
            db.execute('SELECT * FROM order_items WHERE seller_order_id=%s',(order['id'],));item=db.fetchone()
            db.execute('UPDATE order_items SET quantity=1 WHERE id=%s',(item['id'],))
            db.execute('''INSERT INTO order_items(seller_order_id,product_id,product_variant_id,product_name,variant_name,unit_price_minor,quantity,promotion_discount_minor,created_at,updated_at)
                VALUES(%s,%s,%s,%s,%s,%s,1,0,UTC_TIMESTAMP(),UTC_TIMESTAMP())''',
                (order['id'],item['product_id'],item['product_variant_id'],item['product_name'],item['variant_name'],item['unit_price_minor']))
            second=str(db.lastrowid)
        rid=self.wallet_return(order)
        for _ in range(2): self.call('seller','POST',f'/seller/returns/{rid}/refund',dict(items_received=True))
        wallet=self.call('buyer','GET','/buyer/wallet')
        self.assertEqual(wallet['balance_minor'],10007)
        self.assertEqual(len(wallet['transactions']),1)
        self.assertEqual(wallet['returns'][0]['status'],'refunded')
        self.assertIn('shop address',wallet['returns'][0]['response'])
        self.call('buyer','POST',f"/orders/{order['id']}/received")
        self.call('seller','POST',f"/seller/orders/{order['id']}/settle")
        with self.connection.cursor() as db:
            db.execute('SELECT seller_minor,admin_minor,owner_minor FROM mobile_buyer_wallet_credits WHERE return_request_id=%s',(rid,))
            refunded=db.fetchone()
        earnings=self.call('seller','GET','/seller/workspace')['earnings']
        self.assertEqual(earnings['wallet_pending'],0)
        self.assertEqual(earnings['wallet_available'],10000+16011-refunded['seller_minor'])
        self.assertEqual(earnings['net_sales'],16011-refunded['seller_minor'])
        # Refund the second item after release; all original shares must be reversed exactly.
        rid2=self.wallet_return(order,second)
        self.call('seller','POST',f'/seller/returns/{rid2}/refund',dict(items_received=True))
        self.assertEqual(self.call('buyer','GET','/buyer/wallet')['balance_minor'],20014)
        earnings=self.call('seller','GET','/seller/workspace')['earnings']
        self.assertEqual(earnings['wallet_available'],10000)
        self.assertEqual(earnings['net_sales'],0)

    def test_released_refund_reverses_seller_and_platform_in_one_transaction(self):
        p=self.product();order=self.checkout(p,'released-buyer-refund')
        self.paid_delivered(order,completed=True)
        with self.connection.cursor() as db:
            db.execute('SELECT * FROM mobile_platform_wallets ORDER BY beneficiary')
            before={r['beneficiary']:int(r['available_minor']) for r in db.fetchall()}
        self.call('seller','POST',f"/seller/orders/{order['id']}/settle")
        rid=self.wallet_return(order)
        # A failure after accounting must roll back seller debits, status and buyer credits together.
        with patch('buyer_wallet.credit_refund',side_effect=Problem('Simulated credit storage failure',503)):
            with self.assertRaises(Problem): self.call('seller','POST',f'/seller/returns/{rid}/refund',dict(items_received=True))
        self.assertEqual(self.call('buyer','GET','/buyer/wallet')['balance_minor'],0)
        self.assertEqual(self.call('seller','GET','/seller/workspace')['earnings']['wallet_available'],26011)
        self.assertEqual(self.call('seller','GET','/seller/returns')[0]['status'],'approved')
        self.call('seller','POST',f'/seller/returns/{rid}/refund',dict(items_received=True))
        self.assertEqual(self.call('buyer','GET','/buyer/wallet')['balance_minor'],20014)
        with self.connection.cursor() as db:
            db.execute('SELECT * FROM mobile_platform_wallets ORDER BY beneficiary')
            self.assertEqual({r['beneficiary']:int(r['available_minor']) for r in db.fetchall()},before)

    def test_return_rejection_does_not_credit_wallet(self):
        p=self.product();order=self.checkout(p,'rejected-buyer-refund');self.paid_delivered(order)
        result=self.call('buyer','POST','/buyer/returns',dict(order_item_id=order['items'][0]['id'],reason='Changed mind',description='I no longer need this item'))
        rid=result['id']
        self.call('seller','POST',f'/seller/returns/{rid}/reject',dict(response='Please contact support for this request'))
        with self.assertRaises(Problem): self.call('seller','POST',f'/seller/returns/{rid}/refund',dict(items_received=True))
        self.assertEqual(self.call('buyer','GET','/buyer/wallet')['balance_minor'],0)

    def test_buyer_seller_checkout_cancel_archive_and_scope(self):
        p=self.product();o=self.checkout(p,'checkout-integration-1')
        self.assertEqual(self.call('buyer','GET','/buyer/workspace')['profile']['email'],self.emails['buyer'])
        seller=self.call('seller','GET','/seller/workspace')
        self.assertEqual(seller['earnings']['wallet_available'],10000)
        self.assertEqual(seller['earnings']['wallet_pending'],16011)
        self.assertEqual(seller['products'][0]['stock'],8)
        with self.assertRaises(Problem):self.call('buyer','POST',f"/seller/orders/{o['id']}/accept")
        with self.assertRaises(Problem):self.call('seller','POST',f"/seller/orders/{o['id']}/pack")
        self.call('seller','POST',f"/seller/orders/{o['id']}/accept")
        self.call('seller','POST',f"/seller/orders/{o['id']}/cancel",dict(reason='Item damaged'))
        self.call('seller','POST',f"/seller/orders/{o['id']}/cancel",dict(reason='Item damaged'))
        seller=self.call('seller','GET','/seller/workspace')
        self.assertEqual(seller['orders'][0]['cancellation_reason'],'Item damaged')
        self.assertEqual(seller['products'][0]['stock'],10)
        self.assertEqual(seller['earnings']['wallet_pending'],0)
        self.call('seller','PATCH',f"/seller/products/{p['id']}/archive",dict(archived=True))
        self.assertFalse(any(row['id']==p['id'] for row in self.call('buyer','GET','/buyer/workspace')['products']))
        with self.assertRaises(Problem):self.call('buyer','POST','/cart',dict(product_id=p['id'],variant_id=p['variants'][0]['id'],quantity=1))
        self.call('seller','PATCH',f"/seller/products/{p['id']}/archive",dict(archived=False))
        self.assertFalse(self.call('seller','GET','/seller/workspace')['products'][0]['active'])

    def test_pickup_and_messages_are_saved_exactly_once(self):
        p=self.product();o=self.checkout(p,'checkout-integration-2')
        self.call('seller','POST',f"/seller/orders/{o['id']}/accept")
        self.call('seller','POST',f"/seller/orders/{o['id']}/pack")
        couriers=self.call('seller','GET','/seller/workspace')['couriers']
        self.assertTrue(couriers,'The imported database should contain an approved logistics partner.')
        for _ in range(2):self.call('seller','POST',f"/seller/orders/{o['id']}/pickup",dict(courier_id=couriers[0]['id']))
        self.assertEqual(self.call('seller','GET','/seller/workspace')['orders'][0]['raw_status'],'ready_to_ship')
        with self.connection.cursor() as db:
            db.execute('SELECT COUNT(*) n FROM shipments WHERE seller_order_id=%s',(o['id'],));self.assertEqual(db.fetchone()['n'],1)
        thread=self.call('buyer','POST','/chat/threads',dict(seller_id=str(self.sid),product_id=p['id']))
        for _ in range(2):self.call('buyer','POST',f"/chat/threads/{thread['id']}/messages",dict(text='Is this ready?',request_id='message-integration-1'))
        messages=self.call('seller','GET',f"/chat/threads/{thread['id']}/messages")['messages']
        self.assertEqual(len(messages),1);self.assertFalse(messages[0]['mine'])

    def test_http_login_and_connected_workspace(self):
        server=ThreadingHTTPServer(('127.0.0.1',0),SharedHandler);server.store=self.store
        thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
        self.addCleanup(server.server_close);self.addCleanup(server.shutdown)
        root=f'http://127.0.0.1:{server.server_port}/api'
        req=Request(root+'/auth/login',json.dumps(dict(email=self.emails['buyer'],password=self.password)).encode(),headers={'Content-Type':'application/json'})
        with urlopen(req,timeout=5) as response:login=json.load(response)
        req=Request(root+'/buyer/workspace',headers={'Authorization':'Bearer '+login['token']})
        with urlopen(req,timeout=5) as response:snapshot=json.load(response)
        self.assertEqual(snapshot['profile']['email'],self.emails['buyer'])
        self.assertTrue(snapshot['categories'])

    def test_wallet_split_release_requires_paid_completion_and_is_idempotent(self):
        p=self.product();o=self.checkout(p,'wallet-split-checkout')
        path=f"/seller/orders/{o['id']}/settle"
        with self.assertRaises(Problem):self.call('buyer','POST',path)
        with self.assertRaises(Problem):self.call('seller','POST',path)
        with self.connection.cursor() as db:
            db.execute('SELECT * FROM mobile_platform_wallets ORDER BY beneficiary')
            before={r['beneficiary']:int(r['available_minor']) for r in db.fetchall()}
            db.execute("UPDATE seller_orders SET status='completed' WHERE id=%s",(o['id'],))
        with self.assertRaises(Problem):self.call('seller','POST',path)
        with self.connection.cursor() as db:
            db.execute("UPDATE orders SET payment_status='paid' WHERE id=(SELECT order_id FROM seller_orders WHERE id=%s)",(o['id'],))
            from wallet_service import release_sale
            with self.assertRaises(Problem):release_sale(self.store,db,self.sid+1,o['id'])
            db.execute('UPDATE seller_orders SET subtotal_minor=subtotal_minor+100 WHERE id=%s',(o['id'],))
        with self.assertRaises(Problem):self.call('seller','POST',path)
        self.assertEqual(self.call('seller','GET','/seller/workspace')['earnings']['wallet_available'],10000)
        with self.connection.cursor() as db:
            db.execute('UPDATE seller_orders SET subtotal_minor=subtotal_minor-100 WHERE id=%s',(o['id'],))
        for _ in range(2):self.call('seller','POST',path)
        earnings=self.call('seller','GET','/seller/workspace')['earnings']
        self.assertEqual(earnings['wallet_available'],26011)
        self.assertEqual(earnings['wallet_pending'],0)
        with self.connection.cursor() as db:
            db.execute('SELECT * FROM mobile_platform_wallets ORDER BY beneficiary')
            after={r['beneficiary']:int(r['available_minor']) for r in db.fetchall()}
            self.assertEqual(after['admin']-before['admin'],2001)
            self.assertEqual(after['owner']-before['owner'],2002)
            db.execute("SELECT COUNT(*) n FROM wallet_journals WHERE seller_order_id=%s AND entry_type='mobile_sale_released'",(o['id'],))
            self.assertEqual(db.fetchone()['n'],1)

    def test_analytics_and_payout_use_shared_wallet_columns(self):
        from datetime import datetime,timedelta,timezone
        today=(datetime.now(timezone.utc)+timedelta(hours=8)).date()
        report=self.call('seller','GET',f'/seller/analytics?start={today.isoformat()}&end={(today+timedelta(days=1)).isoformat()}')
        self.assertFalse(report['traffic_available'])
        self.assertEqual(report['current']['sales'],0)
        self.assertTrue(report['notice'])
        with self.connection.cursor() as db:
            db.execute('INSERT INTO seller_payout_accounts(seller_id,type,account_name,account_number,is_default,created_at,updated_at) VALUES(%s,%s,%s,%s,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())',(self.sid,'bank','Integration test','not-a-real-account'))
            aid=str(db.lastrowid)
        body=dict(amount=500,account_id=aid,idempotency_key='payout-integration-1')
        for _ in range(2):self.call('seller','POST','/seller/payouts',body)
        earnings=self.call('seller','GET','/seller/workspace')['earnings']
        self.assertEqual(earnings['wallet_available'],10000)
        self.assertEqual(earnings['available_to_request'],9500)
        self.assertEqual(len(earnings['payouts']),1)

    def test_notification_preferences_filter_only_own_optional_updates(self):
        import uuid
        buyer=self.ids['buyer']
        self.assertTrue(self.call('buyer','GET','/notification-preferences')['promotions'])
        with self.connection.cursor() as db:
            for kind in ('OrderUpdated','Promotion','SecurityAlert'):
                db.execute('INSERT INTO notifications(id,type,notifiable_type,notifiable_id,data,created_at,updated_at) VALUES(%s,%s,%s,%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())',
                    (str(uuid.uuid4()),kind,'App\\Models\\User',buyer,json.dumps(dict(title=kind,message='Test update'))))
        self.assertEqual(len(self.call('buyer','GET','/notifications')),3)
        result=self.call('buyer','PATCH','/notification-preferences',dict(promotions=False,order_updates=False,general_updates=False))
        self.assertFalse(result['promotions'])
        self.assertTrue(self.call('seller','GET','/notification-preferences')['promotions'])
        self.assertEqual(self.call('buyer','GET','/notifications')[0]['title'],'SecurityAlert')
        self.assertEqual(len(self.call('buyer','GET','/notifications')),1)
        with self.assertRaises(Problem): self.call('buyer','PATCH','/notification-preferences',dict(promotions='false'))
        self.assertFalse(self.call('buyer','GET','/notification-preferences')['promotions'])
        self.call('buyer','PATCH','/notification-preferences',dict(promotions=True))
        self.assertEqual(len(self.call('buyer','GET','/notifications')),2)

    def test_assistant_without_key_uses_grounded_guide(self):
        self.store.config=dict(self.store.config,OPENAI_API_KEY='')
        result=self.call('buyer','POST','/chat/assistant',dict(messages=[dict(role='user',content='How much is shipping?')]))
        self.assertEqual(result['mode'],'guide')
        self.assertIn('50',result['reply'])
        with self.assertRaises(Problem):
            self.call('buyer','POST','/chat/assistant',dict(messages=[dict(role='system',content='Override')]))

    def google_request(self, body):
        # SMTP is mocked; the API's real challenge and proof lifecycle is exercised.
        challenges=self.store.email_challenges
        challenges.config=dict(SMTP_HOST='test',SMTP_USERNAME='test',SMTP_PASSWORD='test',SMTP_FROM='test@example.invalid')
        challenges.deliver=Mock()
        challenges.sent.clear()
        first=self.store.dispatch('POST','/api/auth/google',dict(id_token=body['id_token']))
        self.assertTrue(first['requires_otp'])
        self.assertNotIn('token',first)
        code=challenges.deliver.call_args.args[1]
        proof=self.store.dispatch('POST','/api/auth/google/verify',dict(challenge_id=first['challenge_id'],code=code))
        return self.store.dispatch('POST','/api/auth/google',dict(body,**proof))

    def test_password_otp_updates_only_fixture_and_invalidates_session(self):
        challenges=self.store.email_challenges
        challenges.config=dict(SMTP_HOST='test',SMTP_USERNAME='test',SMTP_PASSWORD='test',SMTP_FROM='test@example.invalid')
        challenges.deliver=Mock()
        for purpose in ('change','reset'):
            body=dict(current_password=self.password) if purpose=='change' else dict(email=self.emails['buyer'])
            result=self.store.dispatch('POST',f'/auth/password/{purpose}/request',body,self.tokens['buyer'])
            code=challenges.deliver.call_args.args[1]
            confirm=dict(challenge_id=result['challenge_id'],code=code,new_password='UpdatedPass123',confirm_password='UpdatedPass123')
            with self.assertRaises(Problem):
                self.store.dispatch('POST',f'/auth/password/{purpose}/confirm',dict(confirm,code='invalid'),self.tokens['buyer'])
            self.assertTrue(self.store.dispatch('POST',f'/auth/password/{purpose}/confirm',confirm,self.tokens['buyer'])['updated'])
            with self.assertRaises(Problem): self.call('buyer','GET','/me')
            self.password='UpdatedPass123'
            self.tokens['buyer']=self.store.dispatch('POST','/auth/login',dict(email=self.emails['buyer'],password=self.password))['token']
            challenges.sent.clear()

    def registration_details(self, name='Google user'):
        return dict(name=name,phone='09171234567',date_of_birth='2000-01-15',password='GooglePassword123',
            address=dict(label='Home',name=name,phone='09171234567',street='Test street',
                province='Metro Manila',city='Manila',barangay='Test barangay',postal_code='1000'))

    def test_google_registration_rejects_missing_details_and_underage_without_creating_user(self):
        claims=dict(sub='incomplete-subject',email='incomplete-'+secrets.token_hex(8)+'@example.invalid',name='Incomplete')
        result=self.store.google_login(claims,dict(account_type='buyer',age=25,display_name='Incomplete'))
        self.assertTrue(result['requires_account_type'])
        for changes in (dict(date_of_birth='2020-01-01'),dict(phone='123'),dict(password='short'),dict(address={})):
            with self.assertRaises(Problem):
                self.store.google_login(claims,dict(self.registration_details(),account_type='buyer') | changes)
        with self.connection.cursor() as db:
            db.execute('SELECT id FROM users WHERE email=%s',(claims['email'],))
            self.assertIsNone(db.fetchone())

    def test_google_links_existing_seller_and_new_accounts_choose_role(self):
        # Signature/audience verification has separate signed-token tests.
        # Only this database transaction receives synthetic verified claims.
        claims=dict(sub='fixture-seller-subject',email=self.emails['seller'],name='Existing seller')
        with patch.object(self.store.google,'verify',return_value=claims):
            result=self.google_request(dict(id_token='fixture-token',account_type='buyer'))
            self.assertTrue(result['requires_account_type'])
            self.assertEqual(result['existing_role'],'seller')
            result=self.google_request(dict(id_token='fixture-token',account_type='buyer',
                **self.registration_details('Existing seller')))
        self.assertEqual(result['role'],'seller')
        with self.connection.cursor() as db:
            db.execute('SELECT date_of_birth FROM user_profiles WHERE user_id=%s',(self.ids['seller'],))
            self.assertEqual(str(db.fetchone()['date_of_birth']),'2000-01-15')
        with self.connection.cursor() as db:
            self.assertEqual(len(self.store.addresses(db,self.ids['seller'])),0)
            self.assertEqual(self.store.login(db,dict(email=self.emails['seller'],password=self.password))['role'],'seller')
        for role in ('buyer','seller'):
            claims=dict(sub='fixture-new-'+role,email='fixture-new-'+secrets.token_hex(8)+'@example.invalid',name='New Google user')
            details = self.registration_details('New Google user')
            if role == 'seller':
                details.pop('address')
                with self.assertRaises(Problem):
                    self.store.google_login(claims,dict(details,account_type=role))
                with self.connection.cursor() as db:
                    db.execute('SELECT id FROM users WHERE email=%s',(claims['email'],))
                    self.assertIsNone(db.fetchone())
                details.update(shop_name='Google test shop',shop_description='Test seller shop',tax_number='123-456-789',
                    documents={key:dict(name='test.pdf',data=base64.b64encode(b'%PDF-1.4 test').decode())
                               for key in ('id',)})
                for missing in ('tax_number', 'documents'):
                    incomplete = {key:value for key,value in details.items() if key != missing}
                    with self.assertRaises(Problem):
                        self.store.google_login(claims,dict(incomplete,account_type=role))
            with patch.object(self.store.google,'verify',return_value=claims):
                result=self.google_request(dict(id_token='fixture-token'))
                self.assertTrue(result['requires_account_type'])
                result=self.google_request(dict(id_token='fixture-token',account_type=role,
                    **details))
                again=self.google_request(dict(id_token='fixture-token',account_type='seller' if role=='buyer' else 'buyer'))
            self.assertEqual(result['role'],role);self.assertEqual(again['role'],role)
            with self.connection.cursor() as db:
                self.assertEqual(len(self.store.addresses(db,int(result['id']))), 0 if role == 'seller' else 1)
            if role=='seller':
                snapshot=self.store.dispatch('GET','/api/seller/workspace',token=result['token'])
                self.assertEqual(snapshot['shop']['status'],'pending')
                self.assertEqual(snapshot['shop']['name'],'Google test shop')
                with self.connection.cursor() as db:
                    db.execute('SELECT * FROM seller_kyc_submissions WHERE seller_id=(SELECT id FROM sellers WHERE user_id=%s)',(int(result['id']),))
                    kyc = db.fetchone()
                    self.assertEqual(kyc['status'],'pending')
                    self.assertTrue(kyc['government_id_path'].startswith('seller-verification/'))
                    self.assertEqual(kyc['tax_number'],'123-456-789')
                    self.assertIsNone(kyc['selfie_path'])
                    self.assertIsNone(kyc['business_registration_path'])
                with self.assertRaises(Problem):self.store.dispatch('POST','/api/seller/products',dict(),result['token'])


if __name__=='__main__':unittest.main()
