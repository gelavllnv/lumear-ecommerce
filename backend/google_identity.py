"""Verify Google-issued ID tokens before touching local account records."""
import threading

import cachecontrol
import requests
from google.auth.exceptions import GoogleAuthError
from google.auth.transport.requests import Request
from google.oauth2 import id_token

from server import Problem, require, text


class BoundedRequest(Request):
    def __call__(self, url, method='GET', body=None, headers=None, timeout=8, **kwargs):
        return super().__call__(url, method=method, body=body, headers=headers, timeout=8, **kwargs)


class GoogleVerifier:
    def __init__(self, audience):
        self.audience=audience
        self.request=BoundedRequest(session=cachecontrol.CacheControl(requests.Session()))
        self.lock=threading.Lock()

    def verify(self, token):
        token=text(token,'Google ID token',20,12000)
        require(self.audience and self.audience.endswith('.apps.googleusercontent.com'),
                'The server Google client ID is not configured.',503)
        try:
            with self.lock:
                claims=id_token.verify_oauth2_token(token,self.request,audience=self.audience)
        except ValueError:
            raise Problem('Google could not verify this sign-in. Please choose your Google account again.',401)
        except (GoogleAuthError,requests.RequestException):
            raise Problem('Google verification is temporarily unavailable. Please try again.',503)
        require(claims.get('iss') in ('accounts.google.com','https://accounts.google.com') and
                claims.get('aud')==self.audience,'This Google sign-in is not for Lumear.',401)
        require(claims.get('email_verified') is True,'Use a verified Google email address.',401)
        require(isinstance(claims.get('sub'),str) and 1<=len(claims['sub'])<=255,'Invalid Google account identity.',401)
        email=text(claims.get('email'),'Google email',3,254).lower()
        require(email.endswith('@gmail.com') or bool(claims.get('hd')),
                'This email provider needs additional email verification. Use a Gmail or Google Workspace account for now.',422)
        return dict(sub=claims['sub'],email=email,name=claims.get('name') or email.split('@')[0])
