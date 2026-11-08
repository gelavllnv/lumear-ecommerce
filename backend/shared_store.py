"""Mobile API mapping for the user's existing shared MySQL schema.

Never imports, reseeds, or rewrites that schema. All resource IDs and access
checks are resolved on the server, and money remains integer centavos.
"""
import base64
import bcrypt
import hashlib
import json
import mimetypes
import re
import secrets
import time
from collections import Counter
from datetime import datetime, timedelta, timezone
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from mysql_store import MySQLStore, decode, timestamp
from server import Problem, require, text, integer


class SharedStore(MySQLStore):
    def __init__(self, config=None):
        super().__init__(config)
        self.code_tag = hashlib.sha256(b''.join(Path(__file__).with_name(n).read_bytes()
            for n in ('mysql_store.py','shared_store.py','shared_support.py','google_identity.py','support_service.py','wallet_service.py','buyer_wallet.py','account_security.py','seller_registration.py','server.py','run_api.py'))).hexdigest()[:12]
        self.code_tag = hashlib.sha256((self.code_tag + (Path(__file__).parent.parent/'assets/data/lumear_help.json').read_text(encoding='utf-8')).encode()).hexdigest()[:12]
        from google_identity import GoogleVerifier
        self.google = GoogleVerifier(self.config.get('GOOGLE_CLIENT_ID',''))
        self.assistant_attempts = {}
        from account_security import EmailChallenges
        self.email_challenges = EmailChallenges(self.config, self.lock)

    def profile(self, db, user):
        row=self.one(db,'SELECT * FROM user_profiles WHERE user_id=%s',(user['id'],))
        public={k:v for k,v in user.items() if k in ('id','name','email','phone','role')}
        public['id']=str(public['id'])
        return dict(public,display_name=row['display_name'] if row else user['name'],
            username=row['username'] if row else '',age=row['age'] if row else None,
            photo=self.media_object(row['profile_image_path']) if row else None)

    def check(self):
        return dict(super().check(),buyer_refund_wallet=True,email_otp_enabled=self.email_challenges.enabled)

    def identity(self, db, token):
        user,seller=super().identity(db,token)
        return self.profile(db,user),seller

    def save_profile(self, db, user, body, onboarding=False):
        self.one(db,'SELECT id FROM users WHERE id=%s FOR UPDATE',(user['id'],))
        old=self.one(db,'SELECT * FROM user_profiles WHERE user_id=%s FOR UPDATE',(user['id'],))
        name=text(body.get('name',user['name']),'Full name',1 if onboarding else 2,100)
        display=text(body.get('display_name',old['display_name'] if old else name),'Display name',2,100)
        username=body.get('username',old['username'] if old else 'user_'+str(user['id']))
        username=text(username,'Username',3,30).lower()
        require(re.fullmatch(r'[a-z0-9_]{3,30}',username),'Use 3–30 letters, numbers or underscores for the username.')
        conflict=self.one(db,'SELECT user_id FROM user_profiles WHERE username=%s AND user_id<>%s',(username,user['id']))
        require(not conflict,'That username is already taken. Choose another.',409)
        phone=body.get('phone',user.get('phone') or '')
        if isinstance(phone,str) and re.fullmatch(r'\+?639\d{9}',phone):
            phone='0'+phone.lstrip('+')[2:]
        require(isinstance(phone,str) and (re.fullmatch(r'09\d{9}',phone) or onboarding and not phone),'Enter an 11-digit mobile number starting with 09.')
        age=body.get('age',old['age'] if old else None)
        require(age is None and not onboarding or type(age) is int and 18<=age<=120,'Enter an age from 18 to 120.')
        key=old['profile_image_path'] if old else None
        if 'photo' in body:
            photo=body['photo']
            if photo is None:key=None
            else:
                require(isinstance(photo,dict) and photo.get('type')!='video','Use a JPG, PNG or WEBP profile picture.')
                key=self.save_media([photo],f'users/{user["id"]}',[key] if key else [])[0]
        db.execute('UPDATE users SET name=%s,phone=%s,updated_at=UTC_TIMESTAMP() WHERE id=%s',(name,phone or None,user['id']))
        if old:
            db.execute('''UPDATE user_profiles SET display_name=%s,username=%s,age=%s,
                age_recorded_at=UTC_DATE(),profile_image_path=%s WHERE user_id=%s''',
                (display,username,age,key,user['id']))
        else:
            db.execute('''INSERT INTO user_profiles(user_id,display_name,username,age,age_recorded_at,profile_image_path)
                VALUES(%s,%s,%s,%s,UTC_DATE(),%s)''',(user['id'],display,username,age,key))
        return self.profile(db,dict(user,name=name,phone=phone))

    def google_login(self, claims, body):
        from seller_registration import private_documents, validate_documents
        with private_documents(self.config) as save_documents, self.transaction() as db:
            # Stable Google subject is authoritative; email is only used for
            # linking a Google-hosted, verified address to an existing account.
            db.execute("SELECT GET_LOCK(%s,5) acquired",('lumear-google:'+hashlib.sha256((self.config['MYSQL_DATABASE']+'|'+claims['email']).encode()).hexdigest()[:40],))
            require(db.fetchone()['acquired']==1,'Another sign-in is in progress. Try again.',429)
            identity=self.one(db,"SELECT * FROM auth_identities WHERE provider='google' AND provider_user_id=%s FOR UPDATE",(claims['sub'],))
            if identity:
                user=self.one(db,'SELECT * FROM users WHERE id=%s FOR UPDATE',(identity['user_id'],))
            else:
                user=self.one(db,'SELECT * FROM users WHERE email=%s FOR UPDATE',(claims['email'],))
                if user:
                    linked=self.one(db,"SELECT provider_user_id FROM auth_identities WHERE provider='google' AND user_id=%s",(user['id'],))
                    require(not linked or linked['provider_user_id']==claims['sub'],'This email belongs to a different linked Google account. Use your original sign-in.',409)
            is_new = user is None
            profile = self.one(db,'SELECT * FROM user_profiles WHERE user_id=%s',(user['id'],)) if user else None
            address = self.one(db,'SELECT id FROM addresses WHERE user_id=%s AND is_default=1',(user['id'],)) if user else None
            needs_details = is_new or not profile or not profile['date_of_birth'] or not user['phone'] or not address
            if needs_details:
                seller=self.one(db,'SELECT id FROM sellers WHERE user_id=%s',(user['id'],)) if user else None
                existing_role = ('seller' if seller else 'buyer') if user else None
                if not all(key in body for key in ('name','phone','date_of_birth','address','account_type')):
                    return dict(requires_account_type=True,is_new_account=is_new,
                        name=user['name'] if user else claims['name'],email=claims['email'],existing_role=existing_role)
                role_name=existing_role or body['account_type']
                require(role_name in ('buyer','seller'),'Choose Buyer or Seller.')
                name=text(body.get('name'),'Full name',2,100)
                require(isinstance(body['phone'],str) and re.fullmatch(r'09\d{9}',body['phone']),
                    'Enter an 11-digit mobile number starting with 09.')
                try:
                    dob=datetime.strptime(body['date_of_birth'],'%Y-%m-%d').date()
                except (ValueError,TypeError):
                    raise Problem('Enter a valid date of birth.')
                today=datetime.now(timezone(timedelta(hours=8))).date()
                age=today.year-dob.year-((today.month,today.day)<(dob.month,dob.day))
                require(18<=age<=120,'You must be 18 or older (up to 120) to create an account.')
                require(isinstance(body['address'],dict),'Enter your default delivery address.')
                require(re.fullmatch(r'\d{4}',str(body['address'].get('postal_code',''))),'Enter a 4-digit postal code.')
                if is_new:
                    if role_name == 'seller':
                        shop_name = text(body.get('shop_name'), 'Shop name', 1, 150)
                        shop_description = text(body.get('shop_description'), 'Shop description', 1, 2000)
                        tax_number = text(body.get('tax_number'), 'TIN number', 1, 255)
                        documents = validate_documents(body.get('documents'))
                    password=body.get('password')
                    require(isinstance(password,str) and 8<=len(password) and len(password.encode())<=72,
                        'Use a password of at least 8 characters and at most 72 bytes.')
                    hashed=bcrypt.hashpw(password.encode(),bcrypt.gensalt()).decode().replace('$2b$','$2y$',1)
                    role=self.one(db,'SELECT id FROM roles WHERE LOWER(name)=%s',(role_name,))
                    require(role,'This account type is not configured in the shared database.',503)
                    db.execute('INSERT INTO users(name,email,password,phone,email_verified_at,created_at,updated_at) VALUES(%s,%s,%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())',
                        (name,claims['email'],hashed,body['phone']))
                    uid=db.lastrowid
                    db.execute('INSERT INTO role_user(role_id,user_id) VALUES(%s,%s)',(role['id'],uid))
                    if role_name=='seller':
                        slug=(re.sub(r'[^a-z0-9]+','-',name.lower()).strip('-') or 'shop')+'-'+secrets.token_hex(8)
                        db.execute("INSERT INTO sellers(user_id,name,slug,description,status,commission_bps,created_at,updated_at) VALUES(%s,%s,%s,%s,'pending',2000,UTC_TIMESTAMP(),UTC_TIMESTAMP())",(uid,shop_name,slug,shop_description))
                        sid = db.lastrowid
                        paths = save_documents(documents, sid)
                        db.execute("""INSERT INTO seller_kyc_submissions(seller_id,government_id_path,
                            tax_number,status,submitted_at,created_at,updated_at)
                            VALUES(%s,%s,%s,'pending',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())""",
                            (sid,paths['id'],tax_number))
                    user=self.one(db,'SELECT * FROM users WHERE id=%s',(uid,))
                require(user and not user['is_suspended'],'This account is unavailable.',403)
                self.save_profile(db,user,dict(body,age=age,display_name=name),onboarding=True)
                db.execute('UPDATE user_profiles SET date_of_birth=%s WHERE user_id=%s',(dob,user['id']))
                self.save_address(db,user['id'],dict(body['address'],default=True),str(address['id']) if address else None)
            require(user and not user['is_suspended'],'This account is unavailable.',403)
            if not user['email_verified_at']:
                db.execute('UPDATE users SET email_verified_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=%s',(user['id'],))
                user['email_verified_at']=datetime.now(timezone.utc)
            if not identity:
                db.execute("INSERT INTO auth_identities(user_id,provider,provider_user_id,provider_email,linked_at,last_used_at,created_at,updated_at) VALUES(%s,'google',%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())",(user['id'],claims['sub'],claims['email']))
            else:
                db.execute('UPDATE auth_identities SET last_used_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=%s',(identity['id'],))
            return self.issue_session(db,user)

    def categories(self, db):
        return [dict(id=str(r['id']),name=r['name'],parent_id=str(r['parent_id']) if r['parent_id'] else None)
            for r in self.rows(db,'SELECT id,name,parent_id FROM categories WHERE is_active=1 ORDER BY position,name')]

    def buyer_snapshot(self, db, user):
        result = super().buyer_snapshot(db,user)
        result['categories'] = self.categories(db)
        return result

    def catalog(self, db, seller_id=None):
        products = super().catalog(db, seller_id)
        for p in products:
            row = self.one(db, '''SELECT p.publication_status,c.parent_id,
                parent.name parent_name FROM products p JOIN categories c ON c.id=p.category_id
                LEFT JOIN categories parent ON parent.id=c.parent_id WHERE p.id=%s''', (p['id'],))
            p['archived'] = row['publication_status'] == 'archived'
            p['active'] = p['active'] and row['publication_status'] == 'active'
            p['subcategory'] = p['category'] if row['parent_id'] else ''
            if row['parent_name']:
                p['category'] = row['parent_name']
            p['variant_label'] = p['variants'][0]['label'] if len(p['variants']) == 1 else ''
            p['media'] = [self.media_object(r['path']) for r in self.rows(db,
                'SELECT path FROM product_images WHERE product_id=%s ORDER BY position,id', (p['id'],))]
            p['media'] = [m for m in p['media'] if m is not None]
            p['images'] = [m['url'] for m in p['media'] if m['type'] == 'image']
        return products if seller_id is not None else [p for p in products if p['active'] and not p['archived']]

    @property
    def media_root(self):
        return Path(self.config.get('PUBLIC_MEDIA_ROOT', str(Path(__file__).parent / 'private_uploads' / 'public'))).resolve()

    def media_object(self, key):
        if not key or not re.fullmatch(r'mobile/[a-z0-9/_.-]+', key):
            return None  # The SQL archive does not contain the website's image files.
        path = (self.media_root / key).resolve()
        if not path.is_relative_to(self.media_root) or not path.is_file():
            return None
        base = self.config.get('PUBLIC_API_URL', 'http://127.0.0.1:8000').rstrip('/')
        return dict(name=path.name, type='video' if path.suffix == '.mp4' else 'image', url=f'{base}/media/{key}')

    def save_media(self, rows, scope, current):
        require(isinstance(rows, list), 'Media must be a list.')
        require(sum(r.get('type') != 'video' for r in rows if isinstance(r, dict)) <= 5 and
                sum(r.get('type') == 'video' for r in rows if isinstance(r, dict)) <= 1,
                'Use up to five photos and one video.')
        result = []
        allowed = {m['url']: key for key in current if (m := self.media_object(key))}
        for row in rows:
            require(isinstance(row, dict), 'Invalid media.')
            if row.get('data') is None:
                require(row.get('url') in allowed, 'This media does not belong to this resource.')
                result.append(allowed[row['url']]); continue
            try:
                raw = base64.b64decode(row['data'], validate=True)
            except (ValueError, TypeError):
                raise Problem('Invalid media content.')
            video = row.get('type') == 'video'
            require(0 < len(raw) <= (20 if video else 5) * 1024 * 1024, 'Media exceeds the upload limit.')
            extension = '.mp4' if video and raw[4:8] == b'ftyp' else '.png' if not video and raw.startswith(b'\x89PNG\r\n\x1a\n') else '.jpg' if not video and raw.startswith(b'\xff\xd8\xff') else '.webp' if not video and raw[:4] == b'RIFF' and raw[8:12] == b'WEBP' else None
            require(extension, 'Choose valid JPG, PNG, WEBP or MP4 content.')
            key = f'mobile/{scope}/{secrets.token_hex(16)}{extension}'
            path = self.media_root / key
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(raw)
            result.append(key)
        return result

    def orders(self, db, uid=None, sid=None):
        result = super().orders(db, uid, sid)
        for o in result:
            row = self.one(db, '''SELECT o.payment_status,lp.name courier_name,sh.tracking_code
                FROM seller_orders so JOIN orders o ON o.id=so.order_id
                LEFT JOIN shipments sh ON sh.seller_order_id=so.id
                LEFT JOIN logistics_providers lp ON lp.id=COALESCE(sh.logistics_provider_id,so.logistics_provider_id)
                WHERE so.id=%s''', (o['id'],))
            o.update(payment_status=row['payment_status'], courier_name=row['courier_name'], tracking_number=row['tracking_code'])
            returns=self.rows(db,'SELECT id,order_item_id,status FROM return_requests WHERE seller_order_id=%s ORDER BY id',(o['id'],))
            active_return=any(r['status'] in ('requested','approved','in_dispute') for r in returns)
            by_item={str(r['order_item_id']):r for r in returns}
            for item in o['items']:
                returned=by_item.get(item['id'])
                item['return_status']=returned['status'] if returned else None
            o.update(can_cancel=o['raw_status'] in ('pending','accepted') and row['payment_status']=='pending',
                can_receive=o['raw_status']=='delivered' and row['payment_status']=='paid' and not active_return,
                can_refund=o['raw_status'] in ('delivered','completed') and row['payment_status']=='paid'
                    and any(not i['return_status'] for i in o['items']))
            completion = self.one(db, "SELECT created_at FROM order_timelines WHERE seller_order_id=%s AND status='completed' ORDER BY id DESC LIMIT 1", (o['id'],))
            o['completed_at'] = timestamp(completion['created_at']) if completion else None
            cancellation = self.one(db, "SELECT note FROM order_timelines WHERE seller_order_id=%s AND status='cancelled' ORDER BY id DESC LIMIT 1", (o['id'],))
            o['cancellation_reason'] = cancellation['note'] if cancellation else None
            refund = self.one(db, '''SELECT COALESCE(SUM(oi.unit_price_minor*oi.quantity-oi.promotion_discount_minor),0) amount
                FROM return_requests rr JOIN order_items oi ON oi.id=rr.order_item_id
                WHERE rr.seller_order_id=%s AND rr.status='refunded' ''', (o['id'],))
            # Item-granular refunds are shown only where the shared ledger records them.
            o['refund'] = int(refund['amount'])
            from buyer_wallet import refund_totals
            credited=refund_totals(self,db,o['id'])
            o['commission']-=credited['admin_minor']+credited['owner_minor']
        return result

    def earnings(self, db, sid):
        orders = self.orders(db, sid=sid)
        completed = [o for o in orders if o['raw_status'] == 'completed']
        wallet = self.one(db, 'SELECT * FROM wallet_accounts WHERE seller_id=%s', (sid,))
        payouts = self.rows(db, 'SELECT * FROM seller_payout_requests WHERE seller_id=%s ORDER BY id DESC', (sid,))
        accounts = self.rows(db, 'SELECT id,type,account_name,is_default FROM seller_payout_accounts WHERE seller_id=%s ORDER BY is_default DESC,id', (sid,))
        journals = self.rows(db, '''SELECT j.* FROM wallet_journals j JOIN wallet_accounts w ON w.id=j.wallet_account_id
            WHERE w.seller_id=%s ORDER BY j.id DESC LIMIT 100''', (sid,))
        gross = sum(o['subtotal'] for o in completed)
        fees = sum(o['commission'] for o in completed)
        refunds = sum(o['refund'] for o in completed)
        reserved = sum(int(p['amount_minor']) for p in payouts if p['paid_at'] is None and p['status'] not in ('rejected','cancelled','failed','paid'))
        available = int(wallet['available_minor']) if wallet else 0
        allocations = self.rows(db, '''SELECT a.* FROM mobile_sale_allocations a
            JOIN seller_orders so ON so.id=a.seller_order_id WHERE so.seller_id=%s''', (sid,))
        return dict(gross_sales=gross, fees=fees, net_sales=gross-fees-refunds,
            revenue_split=dict(seller_percent=80,admin_percent=10,owner_percent=10),
            releasable_orders=[str(a['seller_order_id']) for a in allocations if a['status']=='pending'
                and any(o['id']==str(a['seller_order_id']) and o['raw_status']=='completed'
                        and o['payment_status']=='paid' for o in orders)],
            pending_sales=sum(o['subtotal'] for o in orders if o['raw_status'] not in ('completed','cancelled')),
            wallet_available=available, available_to_request=max(0, available-reserved),
            wallet_pending=int(wallet['pending_minor']) if wallet else 0,
            paid_out=sum(int(p['amount_minor']) for p in payouts if p['paid_at']),
            payout_accounts=[dict(id=str(a['id']),type=a['type'],name=a['account_name'],default=bool(a['is_default'])) for a in accounts],
            payouts=[dict(id=str(p['id']),amount=int(p['amount_minor']),status=p['status'],date=timestamp(p['created_at'])) for p in payouts],
            journals=[dict(id=str(j['id']),type=j['entry_type'],available_delta=int(j['available_delta']),pending_delta=int(j['pending_delta']),reference=j['reference'] or '',date=timestamp(j['occurred_at'])) for j in journals])

    def checkout(self, db, uid, body):
        # The base checkout's request key returns its previous orders on replay.
        # Only initialize ledger entries for a genuinely new request.
        scope = 'mobile-checkout:' + uid
        previous = self.one(db, 'SELECT order_id FROM idempotency_keys WHERE scope=%s AND `key`=%s',
                            (scope, body.get('idempotency_key')))
        result = super().checkout(db, uid, body)
        if not previous:
            from wallet_service import record_sale
            for order in result['orders']:
                record_sale(self, db, order['id'])
        return result

    def cancel_wallet_sale(self, db, order):
        from wallet_service import cancel_sale
        cancel_sale(self, db, order)

    def request_payout(self, db, uid, sid, body):
        # Preserve the base method's locking, ownership, duplicate protection and
        # reservation logic, adapting only the existing wallet column name.
        from shared_support import request_payout
        return request_payout(self, db, uid, sid, body)

    def seller_snapshot(self, db, user, seller):
        result = super().seller_snapshot(db, user, seller)
        result['couriers'] = [dict(id=str(r['id']),name=r['name'],approved=True) for r in self.rows(db,
            "SELECT id,name FROM logistics_providers WHERE status='approved' ORDER BY name")]
        result['shop']['profile_image'] = self.media_object(seller['logo_path'])
        result['shop']['cover_image'] = self.media_object(seller['banner_path'])
        result['shop']['account_profile'] = user
        result['categories'] = self.categories(db)
        return result

    def capabilities(self):
        return dict(super().capabilities(), chat=True, analytics_traffic=False,
                    media=True, google_sign_in=bool(self.google.audience), email_verification=False,
                    password_otp=self.email_challenges.enabled, google_otp=True, buyer_wallet=True, returns=True)

    def notifications(self, db, uid):
        from support_service import preferences, notification_category
        settings = preferences(self, db, uid)
        rows = self.rows(db, "SELECT * FROM notifications WHERE notifiable_id=%s AND notifiable_type=%s ORDER BY created_at DESC LIMIT 100", (uid, 'App\\Models\\User'))
        result = []
        for row in rows:
            data = decode(row['data']) or {}
            category = notification_category(row, data)
            if category != 'security' and not settings[category]:
                continue
            result.append(dict(id=row['id'],title=str(data.get('title', 'Account notification')),
                message=str(data.get('message', data.get('body', ''))),date=timestamp(row['created_at']),read=row['read_at'] is not None))
        return result

    def save_product(self, db, uid, sid, body, pid=None):
        name = text(body.get('name'), 'Product name', 1, 100)
        description = text(body.get('description', ''), 'Description', 0, 1000)
        variety = text(body.get('variant_label', 'Default'), 'Variety', 1, 100)
        category_name = text(body.get('category'), 'Category', 1, 100)
        category = self.one(db, 'SELECT id,parent_id FROM categories WHERE name=%s AND is_active=1', (category_name,))
        require(category, 'This category is not configured in the shared database.')
        subcategory = body.get('subcategory', '')
        if subcategory:
            child = self.one(db, 'SELECT id FROM categories WHERE name=%s AND parent_id=%s AND is_active=1', (subcategory, category['id']))
            require(child, 'This subcategory is not configured under the chosen category.')
            category = child
        price = integer(body.get('price'), 'Price', 1, 999999999)
        stock = integer(body.get('stock'), 'Stock', 0, 999999)
        active, archived = body.get('active', True), body.get('archived', False)
        require(type(active) is bool and type(archived) is bool and not (active and archived), 'Archived listings cannot be active.')
        publication = 'archived' if archived else 'active' if active else 'hidden'
        current = []
        if pid:
            old = self.one(db, 'SELECT * FROM products WHERE id=%s AND seller_id=%s AND deleted_at IS NULL FOR UPDATE', (pid, sid))
            require(old, 'Product not found.', 404)
            variants = self.rows(db, 'SELECT * FROM product_variants WHERE product_id=%s AND deleted_at IS NULL ORDER BY id FOR UPDATE', (pid,))
            require(len(variants) == 1, 'Use the website to edit products with multiple varieties.')
            require(not active or old['moderation_status'] == 'active', 'This listing requires moderation approval.')
            v = variants[0]
            current = [r['path'] for r in self.rows(db,'SELECT path FROM product_images WHERE product_id=%s', (pid,))]
            db.execute('UPDATE products SET name=%s,description=%s,category_id=%s,is_active=%s,publication_status=%s,updated_at=UTC_TIMESTAMP() WHERE id=%s', (name,description,category['id'],active,publication,pid))
            db.execute('UPDATE product_variants SET name=%s,price_minor=%s,updated_at=UTC_TIMESTAMP() WHERE id=%s',(variety,price,v['id']))
            if stock != v['stock']:
                self.inventory(db,v,stock-v['stock'],uid)
        else:
            suffix = secrets.token_hex(8)
            slug = (re.sub(r'[^a-z0-9]+','-',name.lower()).strip('-') or 'product')+'-'+suffix
            db.execute("INSERT INTO products(seller_id,category_id,name,slug,description,is_active,publication_status,moderation_status,created_at,updated_at) VALUES(%s,%s,%s,%s,%s,%s,%s,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP())",(sid,category['id'],name,slug,description,active,publication))
            pid = str(db.lastrowid)
            db.execute('INSERT INTO product_variants(product_id,sku,name,price_minor,stock,is_active,created_at,updated_at) VALUES(%s,%s,%s,%s,0,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())',(pid,'MOB-'+suffix,variety,price))
            self.inventory(db,dict(id=db.lastrowid,stock=0),stock,uid)
        if 'media' in body:
            keys = self.save_media(body['media'],f'products/{pid}',current)
            db.execute('DELETE FROM product_images WHERE product_id=%s',(pid,))
            for position,key in enumerate(keys):
                db.execute('INSERT INTO product_images(product_id,path,position,created_at,updated_at) VALUES(%s,%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())',(pid,key,position))
        return dict(id=str(pid),message='Product saved.')

    def mutate_cart(self, db, uid, method, body, cid=None):
        if method != 'DELETE':
            if cid:
                row = self.one(db,'''SELECT p.id,p.publication_status FROM cart_items ci JOIN carts c ON c.id=ci.cart_id
                    JOIN product_variants v ON v.id=ci.product_variant_id JOIN products p ON p.id=v.product_id WHERE ci.id=%s AND c.user_id=%s''',(cid,uid))
            else:
                row = self.one(db,'SELECT id,publication_status FROM products WHERE id=%s',(body.get('product_id'),))
            require(row and row['publication_status']=='active','This listing is no longer available.',409)
        return super().mutate_cart(db,uid,method,body,cid)

    def quote(self, db, uid, body):
        bad = self.one(db,'''SELECT ci.id FROM cart_items ci JOIN carts c ON c.id=ci.cart_id
            JOIN product_variants v ON v.id=ci.product_variant_id JOIN products p ON p.id=v.product_id
            WHERE c.user_id=%s AND ci.selected=1 AND p.publication_status<>'active' LIMIT 1''',(uid,))
        require(not bad,'An item was hidden or archived. Remove it before checkout.',409)
        return super().quote(db,uid,body)

    def extra_route(self, db, method, path, url, body, token, user, seller):
        uid = user['id']
        received=re.fullmatch(r'/orders/(\d+)/received',path)
        if received:
            require(method=='POST','Method not allowed.',405)
            require(user['role']=='buyer','Buyer access required.',403)
            self.one(db,'SELECT id FROM users WHERE id=%s FOR UPDATE',(uid,))
            order=self.one(db,'''SELECT so.*,o.payment_status FROM seller_orders so
                JOIN orders o ON o.id=so.order_id WHERE so.id=%s AND o.buyer_id=%s FOR UPDATE''',(received[1],uid))
            require(order,'Order not found.',404)
            if order['status']=='completed':
                return True,dict(message='Order receipt already confirmed.')
            require(order['status']=='delivered','Confirm receipt only after the courier has marked your order delivered.',409)
            require(order['payment_status']=='paid','Payment must be confirmed before completing this order.',409)
            require(not self.one(db,"SELECT id FROM return_requests WHERE seller_order_id=%s AND status IN ('requested','approved','in_dispute') LIMIT 1",(order['id'],)),
                'Resolve the open return or refund request before confirming receipt.',409)
            db.execute("UPDATE seller_orders SET status='completed',updated_at=UTC_TIMESTAMP() WHERE id=%s",(order['id'],))
            self.timeline(db,order,uid,'completed','Order received','Buyer confirmed receipt of the order.')
            return True,dict(message='Order received. Thank you for confirming.')
        if path in ('/buyer/wallet','/buyer/returns'):
            require(user['role']=='buyer','Buyer access required.',403)
            tables=self.one(db,"""SELECT COUNT(*) AS count FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
                ('mobile_buyer_wallet_returns','mobile_buyer_wallet_credits')""")
            require(tables['count']==2,'Buyer e-wallet setup is pending. Apply backend/migrations/20261107_buyer_wallet.sql with a database administrator.',503)
            from buyer_wallet import wallet, request_return
            if path=='/buyer/wallet':
                require(method=='GET','Method not allowed.',405)
                return True,wallet(self,db,uid)
            require(method=='POST','Method not allowed.',405)
            return True,request_return(self,db,uid,body)
        if path=='/me':
            require(method in ('GET','PATCH'),'Method not allowed.',405)
            return True,user if method=='GET' else {'profile':self.save_profile(db,user,body)}
        if path == '/auth/password':
            raise Problem('Use the email-confirmed password change flow.', 410)
        if path == '/chat/assistant':
            require(method in ('GET','POST'), 'Method not allowed.',405)
            if method == 'GET':
                return True, dict(mode='ai' if self.config.get('OPENAI_API_KEY','').strip() else 'guide')
            from support_service import assistant
            return True, assistant(self, uid, body)
        if path == '/notification-preferences':
            require(method in ('GET','PATCH'), 'Method not allowed.',405)
            from support_service import preferences, save_preferences
            return True, preferences(self,db,uid) if method=='GET' else save_preferences(self,db,uid,body)
        if path.startswith('/chat/threads'):
            from shared_support import chat_route
            return True, chat_route(self,db,method,path,body,user,seller)
        if path == '/notifications' or re.fullmatch(r'/notifications/[a-zA-Z0-9-]+',path):
            require(method in ('GET','POST'), 'Method not allowed.',405)
            if method == 'POST':
                condition, args = ('',()) if path=='/notifications' else (' AND id=%s',(path.split('/')[2],))
                db.execute("UPDATE notifications SET read_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE notifiable_id=%s AND notifiable_type=%s"+condition,(uid,'App\\Models\\User')+args)
            return True,self.notifications(db,uid)
        if not path.startswith('/seller/'):
            return False,None
        require(seller,'Seller access required.',403)
        sid=seller['id']
        if path=='/seller/returns':
            require(method=='GET','Method not allowed.',405)
            from buyer_wallet import seller_returns
            return True,seller_returns(self,db,sid)
        review=re.fullmatch(r'/seller/returns/(\d+)/(approve|reject|refund)',path)
        if review:
            require(method=='POST','Method not allowed.',405)
            require(seller['status']=='approved','Seller approval is required.',403)
            from buyer_wallet import review_return
            return True,review_return(self,db,sid,uid,review[1],review[2],body)
        match=re.fullmatch(r'/seller/orders/(\d+)/settle',path)
        if match and method=='POST':
            require(seller['status']=='approved','Seller approval is required.',403)
            from wallet_service import release_sale
            return True,release_sale(self,db,sid,match[1])
        if path=='/seller/workspace' and method=='GET':
            return False,None
        if path == '/seller/analytics' and method=='GET':
            from shared_support import analytics
            return True,analytics(self,db,seller,parse_qs(urlparse(url).query))
        require(seller['status']=='approved' or (path=='/seller/shop' and method=='PATCH' and seller['status']=='pending'),
                'Your shop must be approved for this action.',403)
        match=re.fullmatch(r'/seller/products/(\d+)/archive',path)
        if match and method=='PATCH':
            row=self.one(db,'SELECT id FROM products WHERE id=%s AND seller_id=%s AND deleted_at IS NULL FOR UPDATE',(match[1],sid))
            require(row,'Product not found.',404)
            archived=body.get('archived');require(type(archived) is bool,'Choose archive or restore.')
            db.execute('UPDATE products SET publication_status=%s,is_active=0,updated_at=UTC_TIMESTAMP() WHERE id=%s',('archived' if archived else 'hidden',match[1]))
            return True,dict(message='Product archived.' if archived else 'Product restored as Hidden.')
        match=re.fullmatch(r'/seller/orders/(\d+)/(accept|pack|pickup)',path)
        if match and method=='POST':
            oid,action=match.groups()
            order=self.one(db,'SELECT * FROM seller_orders WHERE id=%s AND seller_id=%s FOR UPDATE',(oid,sid))
            require(order,'Order not found.',404)
            before,after,event={'accept':('pending','accepted','Order accepted'),'pack':('accepted','packed','Order packed'),'pickup':('packed','ready_to_ship','Courier pickup requested')}[action]
            if action=='pickup':
                partner=self.one(db,"SELECT id FROM logistics_providers WHERE id=%s AND status='approved' FOR SHARE",(body.get('courier_id'),))
                require(partner,'Choose an approved logistics partner.')
                if order['status']=='ready_to_ship':
                    require(str(order['logistics_provider_id'])==str(partner['id']),'Pickup was requested with another partner.',409)
                    return True,dict(message='Pickup already requested.')
            elif order['status']==after:
                return True,dict(message='Order already updated.')
            require(order['status']==before,'The order changed. Refresh before trying again.',409)
            if action=='pickup':
                db.execute('UPDATE seller_orders SET logistics_provider_id=%s WHERE id=%s',(partner['id'],oid))
                existing=self.one(db,'SELECT id FROM shipments WHERE seller_order_id=%s FOR UPDATE',(oid,))
                require(not existing,'A shipment already exists for this order.',409)
                parent=self.one(db,'SELECT payment_method FROM orders WHERE id=%s',(order['order_id'],))
                cod=order['subtotal_minor']+order['shipping_fee_minor'] if parent['payment_method']=='cod' else 0
                db.execute("INSERT INTO shipments(seller_order_id,logistics_provider_id,tracking_code,status,fee_minor,cod_amount_minor,created_at,updated_at) VALUES(%s,%s,%s,'unassigned',%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())",(oid,partner['id'],'LM-TRK-'+secrets.token_hex(8).upper(),order['shipping_fee_minor'],cod))
            db.execute('UPDATE seller_orders SET status=%s,updated_at=UTC_TIMESTAMP() WHERE id=%s',(after,oid))
            self.timeline(db,order,uid,after,event)
            return True,dict(message=event+'.')
        if path=='/seller/shop' and method=='PATCH':
            for field,column in [('profile_image','logo_path'),('cover_image','banner_path')]:
                if field in body:
                    value=body[field]
                    if value is None:key=None
                    else:
                        require(value.get('type')!='video','Shop photos must be images.')
                        keys=self.save_media([value],f'shops/{sid}',[seller[column]] if seller[column] else [])
                        key=keys[0]
                    db.execute(f'UPDATE sellers SET {column}=%s,updated_at=UTC_TIMESTAMP() WHERE id=%s',(key,sid))
            return False,None  # The base implementation validates and saves textual details.
        if path.startswith('/seller/promotions/') and method=='PATCH':
            match=re.fullmatch(r'/seller/promotions/(voucher|flash_sale):(\d+)',path)
            require(match and body.get('active') is False,'Only pausing a seller promotion is supported.')
            table='seller_vouchers' if match[1]=='voucher' else 'flash_sales'
            row=self.one(db,f'SELECT id FROM {table} WHERE id=%s AND seller_id=%s FOR UPDATE',(match[2],sid))
            require(row,'Promotion not found.',404)
            db.execute(f'UPDATE {table} SET is_paused=1,updated_at=UTC_TIMESTAMP() WHERE id=%s',(match[2],))
            return True,dict(message='Promotion paused.')
        if path.startswith('/seller/notifications/') and method=='PATCH':
            notification_id=path.rsplit('/',1)[1]
            db.execute('UPDATE notifications SET read_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=%s AND notifiable_id=%s AND notifiable_type=%s',(notification_id,uid,'App\\Models\\User'))
            return True,dict(message='Notification read.')
        if re.fullmatch(r'/seller/orders/\d+/prepare',path):
            raise Problem('Accept, pack and select a courier before requesting pickup.',410)
        return False,None

    def dispatch(self, method, url, body=None, token=''):
        path=urlparse(url).path.removeprefix('/api').rstrip('/')
        if path.startswith('/auth/password/'):
            from account_security import password_route
            require(isinstance(body,dict),'Send a password request.')
            return password_route(self,method,path,body,token)
        if path=='/auth/google/verify':
            require(method=='POST' and isinstance(body,dict),'Send a verification request.',405)
            with self.lock:
                record=self.email_challenges.verify(body.get('challenge_id'),body.get('code'),'google')
                proof=secrets.token_urlsafe(32)
                self.email_challenges.proofs[proof]=dict(data=record['data'],expires=time.time()+600)
                del self.email_challenges.records[body['challenge_id']]
                return dict(verification_token=proof)
        if path=='/auth/google':
            require(method=='POST' and isinstance(body,dict),'Send a Google sign-in request.',405)
            claims=self.google.verify(body.get('id_token'))
            with self.lock:
                proof=body.get('verification_token')
                record=self.email_challenges.proofs.get(proof) if isinstance(proof,str) else None
                if not proof:
                    return self.email_challenges.request('google',claims['email'],dict(sub=claims['sub'],email=claims['email']))
                require(record and record['expires']>time.time()
                    and record['data']==dict(sub=claims['sub'],email=claims['email']),
                    'Google verification expired. Start sign-in again.',401)
                result=self.google_login(claims,body)
                if result.get('token'):
                    del self.email_challenges.proofs[proof]
                return result
        if path in ('/auth/email/send','/auth/email/verify','/auth/register'):
            # Registration needs purpose-bound challenge storage and an actual
            # delivery service. Existing imported verified accounts can sign in.
            raise Problem('Email registration is not configured for this local API. Sign in with an existing verified account.',503)
        return super().dispatch(method,url,body,token)
