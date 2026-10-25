CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(50) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS organizations (
 id CHAR(32) PRIMARY KEY, display_name VARCHAR(180) NOT NULL, identity_status VARCHAR(24) NOT NULL DEFAULT 'unverified', created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS users (
 id CHAR(32) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, display_name VARCHAR(120) NOT NULL, password_hash VARCHAR(255) NOT NULL,
 is_admin TINYINT NOT NULL DEFAULT 0, email_verified TINYINT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS memberships (
 tenant_id CHAR(32) NOT NULL, user_id CHAR(32) NOT NULL, role VARCHAR(24) NOT NULL,
 PRIMARY KEY (tenant_id,user_id), FOREIGN KEY (tenant_id) REFERENCES organizations(id), FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_tokens (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, user_id CHAR(32) NOT NULL, name VARCHAR(120) NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
 scopes TEXT NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY (tenant_id,user_id) REFERENCES memberships(tenant_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS products (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, name VARCHAR(180) NOT NULL, category VARCHAR(80) NOT NULL, source_key VARCHAR(190) NULL,
 created_at DATETIME NOT NULL, UNIQUE KEY (tenant_id,id), UNIQUE KEY (tenant_id,source_key), FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS recipe_versions (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, product_id CHAR(32) NOT NULL, version_key VARCHAR(60) NOT NULL,
 snapshot_json LONGTEXT NOT NULL, snapshot_sha256 CHAR(64) NOT NULL, visibility VARCHAR(24) NOT NULL DEFAULT 'private', technical_status VARCHAR(50) NOT NULL DEFAULT 'experimental',
 license_id VARCHAR(80) NULL, rights_status VARCHAR(24) NOT NULL DEFAULT 'unconfirmed', created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,product_id,id), UNIQUE KEY (tenant_id,product_id,version_key), FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS trade_items (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, product_id CHAR(32) NOT NULL, name VARCHAR(180) NOT NULL,
 gtin14 CHAR(14) CHARACTER SET ascii COLLATE ascii_bin NULL, gtin_original VARCHAR(14) NULL, gtin_status VARCHAR(24) NOT NULL DEFAULT 'unverified',
 claim_evidence TEXT NULL, verified_by CHAR(32) NULL, verified_at DATETIME NULL, current_info_id CHAR(32) NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,id), UNIQUE KEY (tenant_id,product_id,id), FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id), FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS gtin_registry (
 gtin14 CHAR(14) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, tenant_id CHAR(32) NOT NULL, trade_item_id CHAR(32) NOT NULL,
 FOREIGN KEY (tenant_id,trade_item_id) REFERENCES trade_items(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS product_info_versions (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, product_id CHAR(32) NOT NULL, trade_item_id CHAR(32) NOT NULL,
 version_key VARCHAR(60) NOT NULL, snapshot_json LONGTEXT NOT NULL, snapshot_sha256 CHAR(64) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'draft', approved_by CHAR(32) NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,id), UNIQUE KEY (tenant_id,product_id,trade_item_id,id), UNIQUE KEY (tenant_id,trade_item_id,version_key),
 FOREIGN KEY (tenant_id,product_id,trade_item_id) REFERENCES trade_items(tenant_id,product_id,id), FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS batches (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, product_id CHAR(32) NOT NULL, trade_item_id CHAR(32) NOT NULL,
 product_info_version_id CHAR(32) NOT NULL, recipe_version_id CHAR(32) NULL,
 lot_code VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, snapshot_json LONGTEXT NOT NULL, snapshot_sha256 CHAR(64) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'planned', visibility VARCHAR(24) NOT NULL DEFAULT 'private', approved_by CHAR(32) NULL, approved_at DATETIME NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,id), UNIQUE KEY (tenant_id,trade_item_id,lot_code),
 FOREIGN KEY (tenant_id,product_id,trade_item_id,product_info_version_id) REFERENCES product_info_versions(tenant_id,product_id,trade_item_id,id),
 FOREIGN KEY (tenant_id,product_id,recipe_version_id) REFERENCES recipe_versions(tenant_id,product_id,id), FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS batch_events (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, batch_id CHAR(32) NOT NULL, event_type VARCHAR(40) NOT NULL,
 payload_json TEXT NOT NULL, actor_id CHAR(32) NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY (tenant_id,batch_id) REFERENCES batches(tenant_id,id), FOREIGN KEY (actor_id) REFERENCES users(id), INDEX (batch_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS alerts (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, batch_id CHAR(32) NOT NULL, message TEXT NOT NULL,
 created_by CHAR(32) NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME NULL, resolved_by CHAR(32) NULL, resolution TEXT NULL,
 FOREIGN KEY (tenant_id,batch_id) REFERENCES batches(tenant_id,id), FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (resolved_by) REFERENCES users(id), INDEX (batch_id,resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS documents (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, product_id CHAR(32) NOT NULL, title VARCHAR(180) NOT NULL,
 sha256 CHAR(64) NOT NULL, storage_key VARCHAR(190) NOT NULL, mime VARCHAR(100) NOT NULL, size_bytes INT NOT NULL,
 visibility VARCHAR(24) NOT NULL DEFAULT 'private', rights_status VARCHAR(24) NOT NULL DEFAULT 'unconfirmed', license_id VARCHAR(80) NULL,
 provenance_json TEXT NOT NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,id), FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS feedback (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, product_id CHAR(32) NOT NULL, batch_id CHAR(32) NULL, product_info_version_id CHAR(32) NOT NULL,
 kind VARCHAR(24) NOT NULL, rating TINYINT NULL, original_text TEXT NOT NULL, public_text TEXT NULL, status VARCHAR(24) NOT NULL DEFAULT 'pending',
 contact_token_hash CHAR(64) NOT NULL, request_key CHAR(64) NOT NULL, request_hash CHAR(64) NOT NULL, consent_version VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,id), UNIQUE KEY (tenant_id,request_key), FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id),
 FOREIGN KEY (tenant_id,batch_id) REFERENCES batches(tenant_id,id), FOREIGN KEY (tenant_id,product_info_version_id) REFERENCES product_info_versions(tenant_id,id), INDEX (product_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS change_proposals (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, product_id CHAR(32) NOT NULL, feedback_id CHAR(32) NULL,
 title VARCHAR(180) NOT NULL, body TEXT NOT NULL, source VARCHAR(24) NOT NULL, state VARCHAR(24) NOT NULL DEFAULT 'proposed', visibility VARCHAR(24) NOT NULL DEFAULT 'private', decision TEXT NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,id), FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id), FOREIGN KEY (tenant_id,feedback_id) REFERENCES feedback(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS votes (
 proposal_id CHAR(32) NOT NULL, user_id CHAR(32) NOT NULL, created_at DATETIME NOT NULL,
 PRIMARY KEY (proposal_id,user_id), FOREIGN KEY (proposal_id) REFERENCES change_proposals(id), FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ai_jobs (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, feedback_id CHAR(32) NOT NULL, job_type VARCHAR(40) NOT NULL DEFAULT 'feedback_triage',
 output_audience VARCHAR(24) NOT NULL DEFAULT 'public', state VARCHAR(24) NOT NULL DEFAULT 'pending', attempts INT NOT NULL DEFAULT 0, available_at DATETIME NOT NULL,
 lease_until DATETIME NULL, lease_token CHAR(32) NULL, result_json LONGTEXT NULL, source_sha256 CHAR(64) NULL, error_code VARCHAR(50) NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,feedback_id,job_type), FOREIGN KEY (tenant_id,feedback_id) REFERENCES feedback(tenant_id,id), INDEX (state,available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ai_runs (
 id CHAR(32) PRIMARY KEY, job_id CHAR(32) NOT NULL, tenant_id CHAR(32) NOT NULL, model VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY (job_id) REFERENCES ai_jobs(id), FOREIGN KEY (tenant_id) REFERENCES organizations(id), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit_events (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, actor_id CHAR(32) NULL, action VARCHAR(60) NOT NULL, object_id CHAR(32) NOT NULL,
 details_json TEXT NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY (tenant_id) REFERENCES organizations(id), INDEX (tenant_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS imports (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, source_key VARCHAR(190) NOT NULL, package_sha256 CHAR(64) NOT NULL,
 payload_json LONGTEXT NOT NULL, result_json TEXT NOT NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,source_key,package_sha256), FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS url_mappings (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, source_url VARCHAR(1000) NOT NULL, local_object_id CHAR(32) NOT NULL,
 destination_url VARCHAR(1000) NULL, state VARCHAR(24) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL,
 FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS producer_domains (
 id CHAR(32) PRIMARY KEY, tenant_id CHAR(32) NOT NULL, hostname VARCHAR(190) NOT NULL UNIQUE, challenge_hash CHAR(64) NOT NULL,
 verified_at DATETIME NULL, created_at DATETIME NOT NULL, FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS idempotency_keys (
 tenant_id CHAR(32) NOT NULL, principal_id CHAR(32) NOT NULL, request_key VARCHAR(128) NOT NULL, request_hash CHAR(64) NOT NULL,
 response_json LONGTEXT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (tenant_id,principal_id,request_key), FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS rate_limits (id CHAR(64) PRIMARY KEY,hits INT NOT NULL,expires_at DATETIME NOT NULL, INDEX(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
