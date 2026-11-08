import threading
import time
import unittest
from unittest.mock import Mock, patch

from account_security import EmailChallenges
from server import Problem
from shared_store import SharedStore


class ChallengeTests(unittest.TestCase):
    def setUp(self):
        self.challenges = EmailChallenges(dict(SMTP_HOST='mail', SMTP_USERNAME='user', SMTP_PASSWORD='secret', SMTP_FROM='test@example.com'), threading.RLock())
        self.challenges.deliver = Mock()

    def issue(self, purpose='google', email='test@example.com'):
        response = self.challenges.request(purpose, email, {'sub': 'google-id'})
        return response['challenge_id'], self.challenges.deliver.call_args.args[1]

    def test_no_plaintext_code_and_purpose_binding(self):
        cid, code = self.issue()
        self.assertNotIn(code, str(self.challenges.records))
        with self.assertRaises(Problem): self.challenges.verify(cid, code, 'reset')
        self.assertEqual(self.challenges.verify(cid, code, 'google')['data'], {'sub': 'google-id'})

    def test_five_attempt_limit_and_expiry(self):
        cid, code = self.issue()
        for _ in range(5):
            with self.assertRaises(Problem): self.challenges.verify(cid, 'wrong', 'google')
        with self.assertRaises(Problem): self.challenges.verify(cid, code, 'google')
        cid, code = self.issue(email='other@example.com')
        self.challenges.records[cid]['expires'] = time.time()-1
        with self.assertRaises(Problem): self.challenges.verify(cid, code, 'google')

    def test_resend_invalidates_old_code_and_is_limited(self):
        cid, _ = self.issue()
        with self.assertRaises(Problem): self.issue()
        self.challenges.sent.clear()
        self.issue()
        self.assertNotIn(cid, self.challenges.records)

    def test_missing_email_config_never_grants_verification(self):
        self.challenges.config.clear()
        with self.assertRaises(Problem): self.issue()
        self.assertEqual(self.challenges.records, {})

    def test_google_never_issues_session_before_otp_and_proof_is_single_use(self):
        store = SharedStore({})
        store.email_challenges = self.challenges
        store.google = Mock()
        store.google.verify.return_value = dict(sub='google-id', email='test@example.com')
        store.google_login = Mock(return_value=dict(token='session'))
        first = store.dispatch('POST', '/auth/google', {'id_token': 'signed'})
        store.google_login.assert_not_called()
        code = self.challenges.deliver.call_args.args[1]
        proof = store.dispatch('POST', '/auth/google/verify', dict(challenge_id=first['challenge_id'], code=code))['verification_token']
        with self.assertRaises(Problem):
            store.dispatch('POST', '/auth/google/verify', dict(challenge_id=first['challenge_id'], code=code))
        body = dict(id_token='signed', verification_token=proof)
        self.assertEqual(store.dispatch('POST', '/auth/google', body)['token'], 'session')
        with self.assertRaises(Problem): store.dispatch('POST', '/auth/google', body)

    def test_google_proof_cannot_be_used_for_another_account(self):
        store = SharedStore({})
        store.email_challenges = self.challenges
        self.challenges.proofs['proof'] = dict(data=dict(sub='first', email='first@example.com'), expires=time.time()+60)
        store.google = Mock()
        store.google.verify.return_value = dict(sub='second', email='second@example.com')
        with self.assertRaises(Problem): store.dispatch('POST', '/auth/google', dict(id_token='signed', verification_token='proof'))


if __name__ == '__main__': unittest.main()
