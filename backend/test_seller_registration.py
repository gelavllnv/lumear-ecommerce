import base64
import tempfile
import unittest
from pathlib import Path

from seller_registration import private_documents, validate_documents
from server import Problem


def documents():
    return {key: dict(name='verification.pdf', data=base64.b64encode(b'%PDF-1.4 test').decode())
            for key in ('id',)}


class SellerDocumentsTests(unittest.TestCase):
    def test_each_document_is_required_and_checked(self):
        for key in ('id',):
            for invalid in (None, dict(name='fake.pdf', data='invalid base64'),
                            dict(name='fake.jpg', data=base64.b64encode(b'%PDF-test').decode()),
                            dict(name='empty.pdf', data=''),
                            dict(name='large.pdf', data=base64.b64encode(b'%PDF-' + b'x' * (5*1024*1024)).decode())):
                body = documents()
                body[key] = invalid
                with self.subTest(key=key, invalid_type=type(invalid)), self.assertRaises(Problem):
                    validate_documents(body)

    def test_private_random_paths_and_failed_registration_cleanup(self):
        with tempfile.TemporaryDirectory(dir=Path(__file__).parent.parent / 'tmp') as directory:
            config = dict(PRIVATE_UPLOAD_ROOT=directory)
            with private_documents(config) as save:
                paths = save(validate_documents(documents()), 123)
            self.assertEqual(len(set(paths.values())), 1)
            for path in paths.values():
                self.assertTrue(path.startswith('seller-verification/123/'))
                self.assertEqual((Path(directory)/path).read_bytes(), b'%PDF-1.4 test')
            before = set(Path(directory).rglob('*.pdf'))
            with self.assertRaises(RuntimeError):
                with private_documents(config) as save:
                    save(validate_documents(documents()), 123)
                    raise RuntimeError('Database commit failed')
            self.assertEqual(set(Path(directory).rglob('*.pdf')), before)


if __name__ == '__main__':
    unittest.main()
