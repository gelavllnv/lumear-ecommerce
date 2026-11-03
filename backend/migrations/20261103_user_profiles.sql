-- Additive shared profile fields. Does not replace users or existing data.
CREATE TABLE IF NOT EXISTS user_profiles (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  display_name VARCHAR(100) NOT NULL,
  username VARCHAR(30) DEFAULT NULL,
  age TINYINT UNSIGNED DEFAULT NULL,
  age_recorded_at DATE DEFAULT NULL,
  profile_image_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY user_profiles_username_unique (username),
  CONSTRAINT user_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT user_profiles_age_check CHECK (age IS NULL OR age BETWEEN 18 AND 120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
