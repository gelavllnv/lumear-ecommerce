"""HTTP transport and validation helpers for the shared MySQL API."""
import json
import sys
from http.server import BaseHTTPRequestHandler
from urllib.parse import urlparse


class Problem(Exception):
    def __init__(self, message, status=422):
        super().__init__(message)
        self.message, self.status = message, status


def require(condition, message, status=422):
    if not condition:
        raise Problem(message, status)


def text(value, label, minimum=1, maximum=500):
    require(isinstance(value,str), f'{label} is required.')
    value=value.strip()
    require(minimum<=len(value)<=maximum, f'{label} must contain {minimum} to {maximum} characters.')
    return value


def integer(value, label, minimum=1, maximum=99999):
    require(type(value) is int and minimum<=value<=maximum, f'{label} must be a whole number from {minimum} to {maximum}.')
    return value


class Handler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass  # Do not write tokens, URLs or request data to logs.

    def handle_request(self):
        try:
            length=int(self.headers.get('Content-Length',0))
            require(0<=length<=64*1024*1024,'Request is too large.',413)
            body=json.loads(self.rfile.read(length)) if length else {}
            result=self.server.store.dispatch(self.command,self.path,body,
                self.headers.get('Authorization','').removeprefix('Bearer '))
            self.respond(200,result)
        except Problem as error:
            self.respond(error.status,dict(message=error.message))
        except (json.JSONDecodeError,ValueError,TypeError):
            self.respond(400,dict(message='Invalid request. Check the fields and try again.'))
        except Exception as error:
            code=error.args[0] if error.args and isinstance(error.args[0],int) else None
            print(f'API error: {type(error).__name__}, code {code or "unknown"}',file=sys.stderr,flush=True)
            self.respond(409 if code==1062 else 503 if code in (1045,2003,2006,2013) else 500,
                dict(message='The request could not be saved. Refresh and try again.' if code==1062 else 'The shared service could not complete this request.'))

    def respond(self,status,result):
        raw=json.dumps(result).encode()
        self.send_response(status)
        self.send_header('Content-Type','application/json; charset=utf-8')
        self.send_header('Content-Length',str(len(raw)))
        self.send_header('Cache-Control','no-store')
        self.send_header('X-Content-Type-Options','nosniff')
        origin=self.headers.get('Origin','')
        if urlparse(origin).hostname in ('localhost','127.0.0.1'):
            self.send_header('Access-Control-Allow-Origin',origin)
            self.send_header('Vary','Origin')
        self.end_headers()
        self.wfile.write(raw)

    def do_OPTIONS(self):
        origin=self.headers.get('Origin','')
        if urlparse(origin).hostname not in ('localhost','127.0.0.1'):
            self.respond(403,dict(message='Origin not allowed.')); return
        self.send_response(204)
        self.send_header('Access-Control-Allow-Origin',origin)
        self.send_header('Access-Control-Allow-Methods','GET,POST,PATCH,DELETE,OPTIONS')
        self.send_header('Access-Control-Allow-Headers','Authorization,Content-Type')
        self.send_header('Vary','Origin')
        self.end_headers()

    do_GET=do_POST=do_PATCH=do_DELETE=handle_request
