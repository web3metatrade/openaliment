CREATE TABLE IF NOT EXISTS resolver_links (
 id CHAR(32) PRIMARY KEY,
 tenant_id CHAR(32) NOT NULL,
 trade_item_id CHAR(32) NOT NULL,
 link_key CHAR(32) NOT NULL,
 version_no INT UNSIGNED NOT NULL,
 scope_sha256 CHAR(64) NOT NULL,
 qualifiers_json TEXT NOT NULL,
 target_json TEXT NOT NULL,
 is_default TINYINT NOT NULL DEFAULT 0,
 state VARCHAR(24) NOT NULL DEFAULT 'draft',
 is_current TINYINT NOT NULL DEFAULT 0,
 created_by CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL,
 approved_by CHAR(32) NULL,
 approved_at DATETIME NULL,
 decision TEXT NULL,
 UNIQUE KEY (tenant_id,id),
 UNIQUE KEY (tenant_id,link_key,version_no),
 INDEX resolver_public (trade_item_id,is_current,state),
 INDEX resolver_history (tenant_id,trade_item_id,link_key,version_no),
 FOREIGN KEY (tenant_id,trade_item_id) REFERENCES trade_items(tenant_id,id),
 FOREIGN KEY (created_by) REFERENCES users(id),
 FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS resolver_transport (
 id TINYINT PRIMARY KEY,
 verified TINYINT NOT NULL DEFAULT 0,
 verified_at DATETIME NULL,
 verified_by CHAR(32) NULL,
 evidence_json TEXT NOT NULL,
 FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
