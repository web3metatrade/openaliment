CREATE TABLE IF NOT EXISTS external_providers (
 id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 api_version VARCHAR(20) NOT NULL,
 mapping_version VARCHAR(60) NOT NULL,
 source_environment VARCHAR(24) NOT NULL DEFAULT 'production',
 dataset_license_json TEXT NOT NULL,
 read_state VARCHAR(24) NOT NULL DEFAULT 'disabled',
 outbound_state VARCHAR(24) NOT NULL DEFAULT 'disabled',
 policy_version VARCHAR(60) NULL,
 policy_reviewed_at DATETIME NULL,
 data_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
 storage_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_products (
 id CHAR(32) PRIMARY KEY,
 provider_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 gtin14 CHAR(14) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 source_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 availability VARCHAR(24) NOT NULL DEFAULT 'unknown',
 current_revision_id CHAR(32) NULL,
 last_fetched_at DATETIME NULL,
 last_attempt_at DATETIME NULL,
 next_refresh_at DATETIME NULL,
 failure_count INT UNSIGNED NOT NULL DEFAULT 0,
 negative_until DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY external_identity (provider_id,gtin14),
 KEY external_refresh (provider_id,availability,next_refresh_at),
 FOREIGN KEY (provider_id) REFERENCES external_providers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_provider_events (
 id CHAR(32) PRIMARY KEY,
 provider_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id CHAR(32) NOT NULL,
 action VARCHAR(60) NOT NULL,
 object_id VARCHAR(40) NOT NULL,
 details_json TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 KEY external_provider_event_age (provider_id,created_at),
 FOREIGN KEY (provider_id) REFERENCES external_providers(id),
 FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_product_revisions (
 id CHAR(32) PRIMARY KEY,
 external_product_id CHAR(32) NOT NULL,
 remote_revision VARCHAR(60) NULL,
 remote_schema_version INT NULL,
 source_modified_at DATETIME NULL,
 source_updated_at DATETIME NULL,
 fetched_at DATETIME NOT NULL,
 mapping_version VARCHAR(60) NOT NULL,
 content_sha256 CHAR(64) NOT NULL,
    normalized_json MEDIUMTEXT NULL,
    normalized_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provenance_json TEXT NOT NULL,
 visibility VARCHAR(24) NOT NULL DEFAULT 'public',
 raw_json MEDIUMTEXT NULL,
 raw_archive_key VARCHAR(180) NULL,
 normalized_archive_key VARCHAR(180) NULL,
 UNIQUE KEY external_revision_identity (external_product_id,id),
 UNIQUE KEY external_revision_content (external_product_id,content_sha256),
 KEY external_revision_age (external_product_id,fetched_at),
 FOREIGN KEY (external_product_id) REFERENCES external_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_assets (
 id CHAR(32) PRIMARY KEY,
 external_product_id CHAR(32) NOT NULL,
 source_asset_key CHAR(64) NOT NULL,
 source_url VARCHAR(2048) NOT NULL,
 remote_revision VARCHAR(60) NULL,
 license_json TEXT NOT NULL,
 credit_json TEXT NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'pending',
 sha256 CHAR(64) NULL,
 mime VARCHAR(80) NULL,
 width INT UNSIGNED NULL,
 height INT UNSIGNED NULL,
 size_bytes INT UNSIGNED NULL,
 storage_key VARCHAR(180) NULL,
 fetched_at DATETIME NULL,
 last_error_code VARCHAR(60) NULL,
 UNIQUE KEY external_asset_identity (external_product_id,source_asset_key),
 KEY external_asset_state (state,fetched_at),
 FOREIGN KEY (external_product_id) REFERENCES external_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_jobs (
 id CHAR(32) PRIMARY KEY,
 provider_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kind VARCHAR(30) NOT NULL,
 gtin14 CHAR(14) CHARACTER SET ascii COLLATE ascii_bin NULL,
 external_product_id CHAR(32) NULL,
 active_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'queued',
 priority INT NOT NULL DEFAULT 0,
 available_at DATETIME NOT NULL,
 lease_token CHAR(32) NULL,
 lease_until DATETIME NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 deadline_at DATETIME NOT NULL,
 result_code VARCHAR(60) NULL,
 result_json TEXT NULL,
 created_at DATETIME NOT NULL,
 finished_at DATETIME NULL,
 UNIQUE KEY external_job_active (provider_id,active_key),
 KEY external_job_due (state,available_at,priority),
 KEY external_job_lease (state,lease_until),
 FOREIGN KEY (provider_id) REFERENCES external_providers(id),
 FOREIGN KEY (external_product_id) REFERENCES external_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_provider_limits (
 provider_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 bucket VARCHAR(30) NOT NULL,
 next_slot_at DATETIME(6) NULL,
 cooldown_until DATETIME NULL,
 consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
 breaker_state VARCHAR(24) NOT NULL DEFAULT 'closed',
 updated_at DATETIME NOT NULL,
 PRIMARY KEY (provider_id,bucket),
 FOREIGN KEY (provider_id) REFERENCES external_providers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_dataset_runs (
 id CHAR(32) PRIMARY KEY,
 provider_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kind VARCHAR(30) NOT NULL,
 state VARCHAR(24) NOT NULL,
 manifest_json MEDIUMTEXT NOT NULL,
 manifest_sha256 CHAR(64) NOT NULL,
 artifact_key VARCHAR(180) NULL,
 byte_offset BIGINT UNSIGNED NOT NULL DEFAULT 0,
 record_count INT UNSIGNED NOT NULL DEFAULT 0,
 accepted_count INT UNSIGNED NOT NULL DEFAULT 0,
 rejected_count INT UNSIGNED NOT NULL DEFAULT 0,
 started_at DATETIME NOT NULL,
 finished_at DATETIME NULL,
 result_json TEXT NULL,
 KEY external_dataset_state (provider_id,kind,state),
 FOREIGN KEY (provider_id) REFERENCES external_providers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_product_bindings (
 id CHAR(32) PRIMARY KEY,
 tenant_id CHAR(32) NOT NULL,
 trade_item_id CHAR(32) NOT NULL,
 external_product_id CHAR(32) NOT NULL,
 last_seen_revision_id CHAR(32) NULL,
 last_import_revision_id CHAR(32) NULL,
 last_import_label_id CHAR(32) NULL,
 created_by CHAR(32) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 UNIQUE KEY external_binding_identity (tenant_id,trade_item_id,external_product_id),
 UNIQUE KEY external_binding_tenant (tenant_id,id,external_product_id,trade_item_id),
 FOREIGN KEY (tenant_id,trade_item_id) REFERENCES trade_items(tenant_id,id),
 FOREIGN KEY (external_product_id) REFERENCES external_products(id),
 FOREIGN KEY (external_product_id,last_seen_revision_id) REFERENCES external_product_revisions(external_product_id,id),
 FOREIGN KEY (external_product_id,last_import_revision_id) REFERENCES external_product_revisions(external_product_id,id),
 FOREIGN KEY (tenant_id,last_import_label_id) REFERENCES product_info_versions(tenant_id,id),
 FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_conflicts (
 id CHAR(32) PRIMARY KEY,
 tenant_id CHAR(32) NOT NULL,
 binding_id CHAR(32) NOT NULL,
 external_product_id CHAR(32) NOT NULL,
 trade_item_id CHAR(32) NOT NULL,
 external_revision_id CHAR(32) NOT NULL,
 local_label_id CHAR(32) NOT NULL,
 direction VARCHAR(24) NOT NULL DEFAULT 'inbound',
 severity VARCHAR(24) NOT NULL,
 fingerprint CHAR(64) NOT NULL,
 diff_json MEDIUMTEXT NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'open',
 decision TEXT NULL,
 decided_by CHAR(32) NULL,
 decided_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY external_conflict_identity (tenant_id,binding_id,fingerprint),
 KEY external_conflict_queue (tenant_id,state,severity,created_at),
 FOREIGN KEY (tenant_id,binding_id,external_product_id,trade_item_id) REFERENCES external_product_bindings(tenant_id,id,external_product_id,trade_item_id),
 FOREIGN KEY (external_product_id,external_revision_id) REFERENCES external_product_revisions(external_product_id,id),
 FOREIGN KEY (tenant_id,local_label_id) REFERENCES product_info_versions(tenant_id,id),
 FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
