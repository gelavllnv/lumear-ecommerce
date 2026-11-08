"""Purpose-bound, short-lived email challenges; no codes in API responses/logs.

Like mobile sessions, challenges live in this API process and expire on restart.
Run one API process until sessions/challenges move to shared durable storage.
"""
import hashlib
import hmac
import re
import secrets
import smtplib
import ssl
import time
from email.message import EmailMessage

import bcrypt
from server import Problem, require


class EmailChallenges:
    def __init__(self, config, lock):
        self.config, self.lock = config, lock
        self.records, self.sent, self.proofs = {}, {}, {}
        self.key = secrets.token_bytes(32)

    @property
    def enabled(self):
        return all(self.config.get(k, '').strip() for k in
                   ('SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM'))

    def deliver(self, email, code):
        message = EmailMessage()
        message['From'], message['To'] = self.config['SMTP_FROM'], email
        message['Subject'] = 'Your Lumear verification code'
        message.set_content(f'Your Lumear verification code is {code}. It expires in 10 minutes. '
                            'If you did not request this, ignore this email.')
        try:
            with smtplib.SMTP(self.config['SMTP_HOST'], int(self.config.get('SMTP_PORT', 587)), timeout=15) as smtp:
                smtp.ehlo()
                smtp.starttls(context=ssl.create_default_context())
                smtp.ehlo()
                smtp.login(self.config['SMTP_USERNAME'], self.config['SMTP_PASSWORD'])
                smtp.send_message(message)
        except Exception:
            raise Problem('Verification email could not be sent. Please try again later.', 503) from None

    def digest(self, code):
        return hmac.new(self.key, code.encode(), hashlib.sha256).digest()

    def request(self, purpose, email, data, deliver=True):
        require(self.enabled, 'Email verification needs SMTP configuration. Contact support.', 503)
        with self.lock:
            now = time.time()
            self.records = {k: v for k, v in self.records.items() if v['expires'] > now}
            self.proofs = {k: v for k, v in self.proofs.items() if v['expires'] > now}
            self.sent = {k: v for k, v in self.sent.items() if now-v < 3600}
            # Across purposes, one email every minute; a global ceiling also bounds memory/SMTP abuse.
            require(now-self.sent.get(email, 0) >= 60, 'Wait 60 seconds before requesting another code.', 429)
            require(len(self.records) < 1000, 'Too many verification requests. Try again later.', 429)
            cid, code = secrets.token_urlsafe(32), f'{secrets.randbelow(1000000):06d}'
            self.sent[email] = now
            if deliver:
                self.deliver(email, code)
            for key, record in list(self.records.items()):
                if record['email'] == email and record['purpose'] == purpose:
                    del self.records[key]
            self.records[cid] = dict(purpose=purpose, email=email, data=data,
                                     digest=self.digest(code), expires=now+600, attempts=0)
            return dict(challenge_id=cid, requires_otp=True, expires_in=600,
                        message='If the account is eligible, a verification code has been sent.')

    def verify(self, cid, code, purpose):
        # Caller holds the same lock through the protected operation and consumption.
        record = self.records.get(cid) if isinstance(cid, str) else None
        require(record and record['purpose'] == purpose and record['expires'] > time.time()
                and record['attempts'] < 5, 'Code expired or unavailable. Request a new code.', 422)
        record['attempts'] += 1
        require(isinstance(code, str) and re.fullmatch(r'\d{6}', code)
                and hmac.compare_digest(self.digest(code), record['digest']), 'Incorrect verification code.', 422)
        return record


def password_route(store, method, path, body, token):
    require(method == 'POST', 'Method not allowed.', 405)
    match = re.fullmatch(r'/auth/password/(reset|change)/(request|confirm)', path)
    require(match, 'Endpoint not found.', 404)
    purpose, action = match.groups()
    challenges = store.email_challenges
    require(challenges.enabled, 'Email verification needs SMTP configuration. Contact support.', 503)
    with store.lock, store.transaction() as db:
        user = None
        if purpose == 'change':
            profile, _ = store.identity(db, token)
            user = store.one(db, 'SELECT * FROM users WHERE id=%s FOR UPDATE', (profile['id'],))
        if action == 'request':
            if purpose == 'reset':
                email = body.get('email', '')
                require(isinstance(email, str) and re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+', email.strip())
                        and len(email) <= 254, 'Enter a valid email.')
                email = email.strip().lower()
                user = store.one(db, 'SELECT * FROM users WHERE email=%s FOR UPDATE', (email,))
            else:
                password = body.get('current_password', '')
                store.password_user(db, dict(email=user['email'], password=password))
                email = user['email'].lower()
            eligible = user and not user['is_suspended'] and user['email_verified_at']
            data = dict(uid=user['id'], version=user['password']) if eligible else None
            return challenges.request(purpose, email, data, deliver=bool(eligible))
        record = challenges.verify(body.get('challenge_id'), body.get('code'), purpose)
        data = record['data']
        require(data and (purpose != 'change' or str(user['id']) == str(data['uid'])), 'Code unavailable.', 422)
        user = store.one(db, 'SELECT * FROM users WHERE id=%s FOR UPDATE', (data['uid'],))
        require(user and not user['is_suspended'] and user['password'] == data['version'], 'Account changed. Request a new code.', 422)
        password = body.get('new_password')
        require(isinstance(password, str) and 8 <= len(password) and len(password.encode()) <= 72
                and re.search('[a-z]', password) and re.search('[A-Z]', password) and re.search('[0-9]', password),
                'Use 8 or more characters with uppercase, lowercase and a number (up to 72 bytes).')
        require(password == body.get('confirm_password'), 'Passwords do not match.')
        hashed = bcrypt.hashpw(password.encode(), bcrypt.gensalt()).decode().replace('$2b$', '$2y$', 1)
        db.execute('UPDATE users SET password=%s,updated_at=UTC_TIMESTAMP() WHERE id=%s', (hashed, user['id']))
        del challenges.records[body['challenge_id']]
        return dict(updated=True)
