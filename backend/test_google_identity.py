import json
import time
import unittest

from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from google.auth import crypt, jwt

from google_identity import GoogleVerifier
from server import Problem


class GoogleVerificationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        key=rsa.generate_private_key(public_exponent=65537,key_size=2048)
        private=key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption())
        public=key.public_key().public_bytes(serialization.Encoding.PEM,serialization.PublicFormat.SubjectPublicKeyInfo).decode()
        cls.signer=crypt.RSASigner.from_string(private,key_id='test-key')
        cls.cert_data=json.dumps({'test-key':public}).encode()

    def verifier(self):
        verifier=GoogleVerifier('test-client.apps.googleusercontent.com')
        cert_data=self.cert_data
        class Response:
            status=200
            data=cert_data
        verifier.request=lambda *args,**kwargs:Response()
        return verifier

    def token(self,**changes):
        now=int(time.time())
        claims=dict(iss='https://accounts.google.com',aud='test-client.apps.googleusercontent.com',
            sub='test-subject',email='test@gmail.com',email_verified=True,name='Test Person',iat=now,exp=now+3600)
        claims.update(changes)
        return jwt.encode(self.signer,claims).decode()

    def test_google_signature_and_audience_are_verified(self):
        self.assertEqual(self.verifier().verify(self.token())['sub'],'test-subject')
        for changes in [dict(aud='another-client.apps.googleusercontent.com'),dict(exp=int(time.time())-3600),
                        dict(iss='https://not-google.example'),dict(email_verified=False),dict(sub=''),
                        dict(email='third-party@example.invalid')]:
            with self.subTest(changes=changes),self.assertRaises(Problem):self.verifier().verify(self.token(**changes))

    def test_tampered_signature_is_rejected(self):
        parts=self.token().split('.')
        parts[2]=('A' if parts[2][0]!='A' else 'B')+parts[2][1:]
        with self.assertRaises(Problem):self.verifier().verify('.'.join(parts))


if __name__=='__main__':unittest.main()
