"""Apply only the additive buyer refund tables to the configured local database."""
from pathlib import Path
from mysql_store import MySQLStore

if __name__ == '__main__':
    store = MySQLStore()
    assert store.config.get('MYSQL_HOST') in ('localhost', '127.0.0.1'), 'Local database only'
    assert store.config.get('MYSQL_DATABASE') == 'lumear_ecommerce', 'Unexpected database'
    with store.transaction() as db:
        sql = Path(__file__).with_name('migrations').joinpath('20261107_buyer_wallet.sql').read_text()
        for statement in sql.split(';'):
            if statement.strip():
                db.execute(statement)
    print('Applied buyer wallet tables. Existing orders and refunds unchanged.')
