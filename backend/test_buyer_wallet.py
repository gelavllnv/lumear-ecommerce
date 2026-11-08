import unittest
from unittest.mock import Mock

from buyer_wallet import allocate_refund, credit_refund, refund_amount, request_return, wallet
from server import Problem


class BuyerWalletTests(unittest.TestCase):
    def test_partial_refunds_conserve_all_shares_including_small_centavos(self):
        from wallet_service import sale_split
        for subtotal in range(1,501):
            remaining=sale_split(subtotal)
            refunded=0
            while refunded<subtotal:
                amount=min(7,subtotal-refunded)
                split=allocate_refund(amount,remaining)
                self.assertEqual(sum(split.values()),amount)
                for key in remaining:
                    self.assertGreaterEqual(split[key],0)
                    self.assertLessEqual(split[key],remaining[key])
                    remaining[key]-=split[key]
                refunded+=amount
            self.assertEqual(sum(remaining.values()),0)

    def test_refund_excludes_item_discount(self):
        self.assertEqual(refund_amount(dict(unit_price_minor=12345, quantity=2, promotion_discount_minor=137)), 24553)
        with self.assertRaises(Problem): refund_amount(dict(unit_price_minor=100, quantity=1, promotion_discount_minor=101))

    def test_foreign_order_cannot_be_returned(self):
        store, db = Mock(), Mock()
        store.one.return_value = None
        with self.assertRaises(Problem): request_return(store, db, 'buyer', dict(order_item_id='1', reason='Broken', description='Arrived damaged'))
        self.assertEqual(store.one.call_args.args[2], ('1', 'buyer'))
        db.execute.assert_not_called()

    def test_unpaid_or_undelivered_orders_cannot_be_returned(self):
        for status, payment in [('delivered', 'pending'), ('shipped', 'paid')]:
            store, db = Mock(), Mock()
            store.one.return_value = dict(status=status, payment_status=payment)
            with self.assertRaises(Problem): request_return(store, db, 2, dict(order_item_id='1', reason='Broken', description='Arrived damaged'))
            db.execute.assert_not_called()

    def test_existing_return_is_not_duplicated(self):
        store, db = Mock(), Mock()
        store.one.side_effect = [dict(status='delivered', payment_status='paid'), dict(id=1)]
        with self.assertRaises(Problem): request_return(store, db, 2, dict(order_item_id='1', reason='Broken', description='Arrived damaged'))
        db.execute.assert_not_called()

    def test_only_confirmed_matching_refunds_can_credit(self):
        for row in [None, dict(status='requested', buyer_id=1, return_buyer=1), dict(status='refunded', buyer_id=1, return_buyer=2)]:
            store, db = Mock(), Mock()
            store.one.return_value = row
            with self.assertRaises(Problem): credit_refund(store, db, 1)
            db.execute.assert_not_called()

    def test_unconfirmed_returns_do_not_inflate_balance(self):
        store = Mock()
        store.rows.side_effect = [[], [dict(id=1, status='approved', reason='Broken', product_name='Item', amount_minor=10000, reference='ORDER')]]
        result = wallet(store, Mock(), 2)
        self.assertEqual(result['balance_minor'], 0)
        self.assertEqual(result['returns'][0]['amount_minor'], 10000)
        self.assertEqual(result['transactions'], [])


if __name__ == '__main__': unittest.main()
