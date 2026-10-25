CREATE TABLE IF NOT EXISTS api_token_metadata (
 token_id CHAR(32) PRIMARY KEY, prefix VARCHAR(24) NOT NULL,
 last_used_at DATETIME NULL, rotated_from CHAR(32) NULL, revocation_reason VARCHAR(500) NULL,
 FOREIGN KEY (token_id) REFERENCES api_tokens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_public_items (
 trade_item_id CHAR(32) PRIMARY KEY, product_id CHAR(32) NOT NULL, tenant_id CHAR(32) NOT NULL,
 product_info_version_id CHAR(32) NOT NULL, name VARCHAR(180) NOT NULL, category VARCHAR(80) NOT NULL,
 INDEX public_name (name,trade_item_id), INDEX public_category (category,trade_item_id),
 INDEX public_producer (tenant_id,trade_item_id), INDEX public_product (product_id,trade_item_id),
 FOREIGN KEY (tenant_id,trade_item_id) REFERENCES trade_items(tenant_id,id),
 FOREIGN KEY (tenant_id,product_info_version_id) REFERENCES product_info_versions(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_clients (
 id CHAR(32) PRIMARY KEY, owner_user_id CHAR(32) NOT NULL,
 name VARCHAR(120) NOT NULL, redirect_uris_json TEXT NOT NULL, scopes_json TEXT NOT NULL,
 active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL,
 INDEX (owner_user_id,id), FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_client_origins (
 client_id CHAR(32) NOT NULL, origin VARCHAR(255) NOT NULL,
 PRIMARY KEY (client_id,origin), INDEX (origin), FOREIGN KEY (client_id) REFERENCES api_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_oauth_requests (
 id CHAR(32) PRIMARY KEY, client_id CHAR(32) NOT NULL, realm VARCHAR(16) NOT NULL,
 redirect_uri VARCHAR(2048) NOT NULL, state_value VARCHAR(512) NOT NULL,
 challenge CHAR(43) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, scopes_json TEXT NOT NULL,
 expires_at DATETIME NOT NULL, consumed_at DATETIME NULL, created_at DATETIME NOT NULL,
 INDEX (expires_at), FOREIGN KEY (client_id) REFERENCES api_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_oauth_grants (
 id CHAR(32) PRIMARY KEY, client_id CHAR(32) NOT NULL, realm VARCHAR(16) NOT NULL,
 user_id CHAR(32) NULL, tenant_id CHAR(32) NULL, home_account_id CHAR(32) NULL,
 household_id CHAR(32) NULL, home_device_id CHAR(32) NULL,
 scopes_json TEXT NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX (user_id,id), INDEX (home_account_id,id), INDEX (tenant_id,id),
 FOREIGN KEY (client_id) REFERENCES api_clients(id), FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (tenant_id) REFERENCES organizations(id), FOREIGN KEY (home_account_id) REFERENCES home_accounts(id),
 FOREIGN KEY (household_id) REFERENCES home_households(id), FOREIGN KEY (home_device_id) REFERENCES home_devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_oauth_codes (
 code_hash CHAR(64) PRIMARY KEY, request_id CHAR(32) NOT NULL, grant_id CHAR(32) NOT NULL,
 expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL,
 INDEX (expires_at), FOREIGN KEY (request_id) REFERENCES api_oauth_requests(id), FOREIGN KEY (grant_id) REFERENCES api_oauth_grants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_oauth_tokens (
 id CHAR(32) PRIMARY KEY, grant_id CHAR(32) NOT NULL,
 access_hash CHAR(64) NOT NULL UNIQUE, refresh_hash CHAR(64) NOT NULL UNIQUE,
 access_expires_at DATETIME NOT NULL, refresh_expires_at DATETIME NOT NULL,
 refresh_used_at DATETIME NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL,
 INDEX (grant_id,id), INDEX (refresh_expires_at), FOREIGN KEY (grant_id) REFERENCES api_oauth_grants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
