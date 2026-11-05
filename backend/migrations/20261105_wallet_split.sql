-- Additive ledger for NEW mobile purchases. Never backfill historical orders.
CREATE TABLE IF NOT EXISTS mobile_sale_allocations (
    seller_order_id BIGINT UNSIGNED PRIMARY KEY,
    seller_minor BIGINT UNSIGNED NOT NULL,
    admin_minor BIGINT UNSIGNED NOT NULL,
    owner_minor BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','released','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    FOREIGN KEY (seller_order_id) REFERENCES seller_orders(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mobile_platform_wallets (
    beneficiary ENUM('admin','owner') PRIMARY KEY,
    available_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO mobile_platform_wallets(beneficiary) VALUES ('admin'),('owner');
