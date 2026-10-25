CREATE TABLE IF NOT EXISTS home_accounts (
 id CHAR(32) PRIMARY KEY,
 email VARCHAR(190) NOT NULL UNIQUE,
 verified_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_households (
 id CHAR(32) PRIMARY KEY,
 owner_account_id CHAR(32) NOT NULL,
 name VARCHAR(120) NOT NULL,
 change_seq BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 FOREIGN KEY (owner_account_id) REFERENCES home_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_members (
 household_id CHAR(32) NOT NULL,
 account_id CHAR(32) NOT NULL,
 role VARCHAR(20) NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY (household_id,account_id),
 FOREIGN KEY (household_id) REFERENCES home_households(id),
 FOREIGN KEY (account_id) REFERENCES home_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_devices (
 id CHAR(32) PRIMARY KEY,
 account_id CHAR(32) NOT NULL,
 household_id CHAR(32) NOT NULL,
 name VARCHAR(120) NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 last_seen_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX (account_id,revoked_at),
 FOREIGN KEY (account_id) REFERENCES home_accounts(id),
 FOREIGN KEY (household_id) REFERENCES home_households(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_login_tokens (
 token_hash CHAR(64) PRIMARY KEY,
 email VARCHAR(190) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_items (
 household_id CHAR(32) NOT NULL,
 id CHAR(32) NOT NULL,
 server_revision INT UNSIGNED NOT NULL,
 last_seq BIGINT UNSIGNED NOT NULL,
 deleted TINYINT NOT NULL DEFAULT 0,
 snapshot_json MEDIUMTEXT NULL,
 PRIMARY KEY (household_id,id),
 INDEX home_delta (household_id,last_seq),
 FOREIGN KEY (household_id) REFERENCES home_households(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_changes (
 household_id CHAR(32) NOT NULL,
 seq BIGINT UNSIGNED NOT NULL,
 event_id CHAR(32) NOT NULL,
 request_sha256 CHAR(64) NOT NULL,
 item_id CHAR(32) NOT NULL,
 server_revision INT UNSIGNED NOT NULL,
 before_json MEDIUMTEXT NULL,
 after_json MEDIUMTEXT NULL,
 account_id CHAR(32) NOT NULL,
 device_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY (household_id,seq),
 UNIQUE KEY (household_id,event_id),
 FOREIGN KEY (household_id) REFERENCES home_households(id),
 FOREIGN KEY (account_id) REFERENCES home_accounts(id),
 FOREIGN KEY (device_id) REFERENCES home_devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_photos (
 household_id CHAR(32) NOT NULL,
 id CHAR(32) NOT NULL,
 sha256 CHAR(64) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 storage_key VARCHAR(180) NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY (household_id,id),
 FOREIGN KEY (household_id) REFERENCES home_households(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_history (
 household_id CHAR(32) NOT NULL,
 id CHAR(32) NOT NULL,
 seq BIGINT UNSIGNED NOT NULL,
 sha256 CHAR(64) NOT NULL,
 event_json MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY (household_id,id),
 UNIQUE KEY (household_id,seq),
 FOREIGN KEY (household_id) REFERENCES home_households(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_invites (
 token_hash CHAR(64) PRIMARY KEY,
 household_id CHAR(32) NOT NULL,
 created_by CHAR(32) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_by CHAR(32) NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY (household_id) REFERENCES home_households(id),
 FOREIGN KEY (created_by) REFERENCES home_accounts(id),
 FOREIGN KEY (used_by) REFERENCES home_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_access_events (
 id CHAR(32) PRIMARY KEY,
 account_id CHAR(32) NOT NULL,
 household_id CHAR(32) NOT NULL,
 action VARCHAR(60) NOT NULL,
 object_id CHAR(32) NOT NULL,
 details_json TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX (household_id,created_at),
 FOREIGN KEY (account_id) REFERENCES home_accounts(id),
 FOREIGN KEY (household_id) REFERENCES home_households(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
