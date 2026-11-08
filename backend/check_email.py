"""Verify SMTP login, optionally send one explicitly requested delivery test.

Never prints credentials or verification codes. Uses ignored server configuration.
"""
import argparse
import re
import secrets
import smtplib
import ssl
from account_security import EmailChallenges
from shared_store import SharedStore
from server import Problem


if __name__ == '__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--send-to',help='Explicit recipient for one test verification email')
    args=parser.parse_args()
    store=SharedStore()
    if not store.email_challenges.enabled:
        raise SystemExit('Email sender is not configured. Run scripts/configure-email.ps1.')
    config=store.config
    try:
        with smtplib.SMTP(config['SMTP_HOST'],int(config.get('SMTP_PORT',587)),timeout=15) as smtp:
            smtp.ehlo();smtp.starttls(context=ssl.create_default_context());smtp.ehlo()
            smtp.login(config['SMTP_USERNAME'],config['SMTP_PASSWORD'])
        print('SMTP authentication verified. Credentials were not printed.')
        if args.send_to:
            if not re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+',args.send_to):
                raise SystemExit('Enter a valid test recipient email.')
            store.email_challenges.deliver(args.send_to,f'{secrets.randbelow(1000000):06d}')
            print('One test email sent. Check the recipient inbox and spam folder. This test code does not grant an account session.')
    except (smtplib.SMTPException,OSError,Problem):
        raise SystemExit('Email verification failed. Check SMTP settings, App Password and network connection.') from None
