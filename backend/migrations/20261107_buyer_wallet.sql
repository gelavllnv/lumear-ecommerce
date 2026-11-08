-- Buyer refund destination and append-only credits. Existing refunds are untouched.
CREATE TABLE IF NOT EXISTS mobile_buyer_wallet_returns (
  return_request_id BIGINT UNSIGNED PRIMARY KEY,
  buyer_id BIGINT UNSIGNED NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (return_request_id) REFERENCES return_requests(id),
  FOREIGN KEY (buyer_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_buyer_wallet_credits (
  return_request_id BIGINT UNSIGNED PRIMARY KEY,
  buyer_id BIGINT UNSIGNED NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  seller_order_id BIGINT UNSIGNED NOT NULL,
  seller_minor BIGINT UNSIGNED NOT NULL,
  admin_minor BIGINT UNSIGNED NOT NULL,
  owner_minor BIGINT UNSIGNED NOT NULL,
  allocation_status ENUM('pending','released') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX buyer_history (buyer_id, created_at),
  INDEX order_refunds (seller_order_id),
  FOREIGN KEY (return_request_id) REFERENCES mobile_buyer_wallet_returns(return_request_id),
  FOREIGN KEY (buyer_id) REFERENCES users(id)
) ENGINE=InnoDB;
