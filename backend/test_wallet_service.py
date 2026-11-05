import unittest

from server import Problem
from wallet_service import sale_split


class SplitTests(unittest.TestCase):
    def test_product_payment_excludes_shipping(self):
        self.assertEqual(sale_split(100000), dict(seller_minor=80000, admin_minor=10000, owner_minor=10000))

    def test_rounding_conserves_every_centavo(self):
        for subtotal in range(10001):
            split = sale_split(subtotal)
            self.assertEqual(sum(split.values()), subtotal)
            self.assertTrue(all(v >= 0 for v in split.values()))
            self.assertLessEqual(abs(split['admin_minor']-split['owner_minor']), 1)

    def test_rejects_invalid_money(self):
        for value in (-1, 1.1, True, '100'):
            with self.assertRaises(Problem):
                sale_split(value)


if __name__ == '__main__':
    unittest.main()
