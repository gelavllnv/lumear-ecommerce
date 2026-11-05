"""Integration checks use temporary rows in one transaction, always rolled back.

Set RUN_SHARED_MYSQL_TESTS=1 to exercise the configured imported database.
No schema creation, deletion or account password changes are performed.
"""
import contextlib
import json
import os
import secrets
import threading
import unittest
from unittest.mock import patch
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
            result=self.store.dispatch('POST','/api/auth/google',dict(id_token='fixture-token',account_type='buyer'))
            self.assertTrue(result['requires_account_type'])
            self.assertEqual(result['existing_role'],'seller')
            result=self.store.dispatch('POST','/api/auth/google',dict(id_token='fixture-token',account_type='buyer',
                **self.registration_details('Existing seller')))
        self.assertEqual(result['role'],'seller')
        with self.connection.cursor() as db:
            db.execute('SELECT date_of_birth FROM user_profiles WHERE user_id=%s',(self.ids['seller'],))
            self.assertEqual(str(db.fetchone()['date_of_birth']),'2000-01-15')
        with self.connection.cursor() as db:
            self.assertEqual(len(self.store.addresses(db,self.ids['seller'])),1)
            self.assertEqual(self.store.login(db,dict(email=self.emails['seller'],password=self.password))['role'],'seller')
        for role in ('buyer','seller'):
            claims=dict(sub='fixture-new-'+role,email='fixture-new-'+secrets.token_hex(8)+'@example.invalid',name='New Google user')
            with patch.object(self.store.google,'verify',return_value=claims):
                result=self.store.dispatch('POST','/api/auth/google',dict(id_token='fixture-token'))
                self.assertTrue(result['requires_account_type'])
                result=self.store.dispatch('POST','/api/auth/google',dict(id_token='fixture-token',account_type=role,
                    **self.registration_details('New Google user')))
                again=self.store.dispatch('POST','/api/auth/google',dict(id_token='fixture-token',account_type='seller' if role=='buyer' else 'buyer'))
            self.assertEqual(result['role'],role);self.assertEqual(again['role'],role)
            if role=='seller':
                snapshot=self.store.dispatch('GET','/api/seller/workspace',token=result['token'])
                self.assertEqual(snapshot['shop']['status'],'pending')
                with self.assertRaises(Problem):self.store.dispatch('POST','/api/seller/products',dict(),result['token'])


if __name__=='__main__':unittest.main()
