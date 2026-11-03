"""Run only the shared MySQL adapter; never start a sample SQLite database."""
import argparse
import json
import mimetypes
from http.server import ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlparse

from server import Handler, Problem
from shared_store import SharedStore


class SharedHandler(Handler):
    def handle_request(self):
        if self.command == 'GET' and self.path.startswith('/media/'):
            key = unquote(urlparse(self.path).path.removeprefix('/media/'))
            root = self.server.store.media_root
            path = (root / key).resolve()
            if not path.is_relative_to(root) or not key.startswith('mobile/') or not path.is_file():
                self.respond(404, {'message': 'Media file unavailable.'}); return
            raw = path.read_bytes()
            self.send_response(200)
            self.send_header('Content-Type', mimetypes.guess_type(path.name)[0] or 'application/octet-stream')
            self.send_header('X-Content-Type-Options', 'nosniff')
            self.send_header('Content-Length', str(len(raw)))
            self.end_headers()
            self.wfile.write(raw)
            return
        return super().handle_request()

    do_GET = do_POST = do_PATCH = do_DELETE = handle_request


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--host', default='127.0.0.1')
    parser.add_argument('--port', type=int, default=8000)
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    store = SharedStore()
    try:
        result = store.check()
    except Exception as error:
        code = error.args[0] if error.args and isinstance(error.args[0],int) else None
        message = error.message if isinstance(error,Problem) else f'Connection failed (MySQL code {code or "configuration"}). Check backend/.env.mysql.'
        raise SystemExit(message)
    if args.check:
        print(json.dumps(result)); raise SystemExit(0)
    server = ThreadingHTTPServer((args.host,args.port),SharedHandler)
    server.store = store
    print(f'Lumear shared MySQL API: http://{args.host}:{args.port}/api',flush=True)
    server.serve_forever()
