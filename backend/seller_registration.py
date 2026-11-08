"""Private seller verification uploads; never served by the public media route."""
import base64
import secrets
from contextlib import contextmanager
from pathlib import Path

from server import require, text


def validate_documents(documents):
    require(isinstance(documents, dict), 'Select all required seller documents.')
    result = {}
    for key, label in (('id', 'Government-issued ID'),):
        document = documents.get(key)
        require(isinstance(document, dict), label + ' is required.')
        name = text(document.get('name'), label + ' filename', 1, 255)
        data = document.get('data')
        require(isinstance(data, str) and len(data) <= 7 * 1024 * 1024,
                label + ' must be at most 5 MB.')
        try:
            raw = base64.b64decode(data, validate=True)
        except ValueError:
            require(False, label + ' contains invalid file data.')
        require(0 < len(raw) <= 5 * 1024 * 1024, label + ' must be at most 5 MB.')
        extension = ('.jpg' if raw.startswith(b'\xff\xd8\xff') else
                     '.png' if raw.startswith(b'\x89PNG\r\n\x1a\n') else
                     '.pdf' if raw.startswith(b'%PDF-') else None)
        require(extension and Path(name).suffix.lower() in
                ({'.jpg', '.jpeg'} if extension == '.jpg' else {extension}),
                label + ' must be a JPG, PNG, or PDF file matching its contents.')
        result[key] = (extension, raw)
    return result


@contextmanager
def private_documents(config):
    """Remove newly written files if registration or its DB commit fails."""
    written = []

    def save(documents, seller_id):
        root = Path(config.get('PRIVATE_UPLOAD_ROOT') or
                    Path(__file__).parent.parent / 'private_uploads').resolve()
        directory = root / 'seller-verification' / str(int(seller_id))
        directory.mkdir(parents=True, exist_ok=True)
        paths = {}
        for key, (extension, raw) in documents.items():
            path = directory / (secrets.token_hex(24) + extension)
            with path.open('xb') as upload:
                written.append(path)
                upload.write(raw)
            paths[key] = path.relative_to(root).as_posix()
        return paths

    try:
        yield save
    except BaseException:
        for path in written:
            path.unlink(missing_ok=True)
        raise
