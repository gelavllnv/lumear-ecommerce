import threading
import unittest
from types import SimpleNamespace
from unittest.mock import Mock, patch
from support_service import assistant
from server import Problem

class AssistantTests(unittest.TestCase):
    def store(self, key=''):
        return SimpleNamespace(config=dict(OPENAI_API_KEY=key,OPENAI_MODEL='gpt-4.1-mini'),
            lock=threading.RLock(),assistant_attempts={})

    def question(self, text='How do I track my order?'):
        return dict(messages=[dict(role='user',content=text)])

    @patch('support_service.requests.post')
    def test_no_key_never_calls_provider(self, post):
        result=assistant(self.store(),'1',self.question())
        self.assertEqual(result['mode'],'guide')
        self.assertIn('Orders',result['reply'])
        post.assert_not_called()

    @patch('support_service.requests.post')
    def test_ai_uses_server_key_bounded_context_and_no_storage(self, post):
        post.return_value=Mock(status_code=200)
        post.return_value.json.return_value={'output':[{'type':'message','content':[{'type':'output_text','text':'Open Orders.'}]}]}
        result=assistant(self.store('test-only-key'),'1',self.question())
        self.assertEqual(result,dict(reply='Open Orders.',mode='ai'))
        args=post.call_args.kwargs
        self.assertFalse(args['json']['store'])
        self.assertEqual(args['json']['input'][-1]['role'],'user')
        self.assertEqual(args['headers']['Authorization'],'Bearer test-only-key')
        self.assertLess(args['timeout'],20)

    @patch('support_service.requests.post')
    def test_provider_failure_is_explicit_and_does_not_expose_secret(self, post):
        post.return_value=Mock(status_code=401)
        with self.assertRaises(Problem) as caught: assistant(self.store('test-only-key'),'1',self.question())
        self.assertNotIn('test-only-key',str(caught.exception))

    @patch('support_service.requests.post')
    def test_rate_limit_and_user_isolation(self, post):
        post.return_value=Mock(status_code=500)
        store=self.store('test-only-key')
        for _ in range(6):
            with self.assertRaises(Problem): assistant(store,'1',self.question())
        with self.assertRaises(Problem) as caught: assistant(store,'1',self.question())
        self.assertEqual(caught.exception.status,429)
        self.assertNotIn('2',store.assistant_attempts)
        self.assertEqual(post.call_count,6)
