CREATE TABLE IF NOT EXISTS marketplace_apps (
    id CHAR(32) NOT NULL PRIMARY KEY,
    owner_user_id CHAR(32) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    state VARCHAR(20) NOT NULL DEFAULT 'draft',
    revision INT NOT NULL DEFAULT 1,
    submitted_revision INT NULL,
    approved_revision INT NULL,
    draft_json LONGTEXT NOT NULL,
    approved_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX marketplace_owner (owner_user_id,id),
    INDEX marketplace_public (state,id),
    FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_releases (
    id CHAR(32) NOT NULL PRIMARY KEY,
    app_id CHAR(32) NOT NULL,
    version VARCHAR(40) NOT NULL,
    manifest_json LONGTEXT NOT NULL,
    manifest_sha256 CHAR(64) NOT NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'submitted',
    review_json TEXT NULL,
    reviewed_by CHAR(32) NULL,
    created_at DATETIME NOT NULL,
    reviewed_at DATETIME NULL,
    UNIQUE KEY marketplace_version (app_id,version),
    FOREIGN KEY (app_id) REFERENCES marketplace_apps(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_events (
    id CHAR(32) NOT NULL PRIMARY KEY,
    app_id CHAR(32) NOT NULL,
    actor_user_id CHAR(32) NOT NULL,
    action VARCHAR(40) NOT NULL,
    details_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX marketplace_event_app (app_id,created_at),
    FOREIGN KEY (app_id) REFERENCES marketplace_apps(id),
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_selections (
    id CHAR(32) NOT NULL PRIMARY KEY,
    tenant_id CHAR(32) NOT NULL,
    app_id CHAR(32) NOT NULL,
    user_id CHAR(32) NOT NULL,
    active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY marketplace_selection (tenant_id,app_id),
    FOREIGN KEY (tenant_id) REFERENCES organizations(id),
    FOREIGN KEY (app_id) REFERENCES marketplace_apps(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_devices (
    id CHAR(32) NOT NULL PRIMARY KEY,
    tenant_id CHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    format VARCHAR(20) NOT NULL,
    credential_hash CHAR(64) NOT NULL UNIQUE,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    UNIQUE KEY print_device_owner (tenant_id,id),
    FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_serial_counters (
    gtin14 CHAR(14) NOT NULL PRIMARY KEY,
    next_value BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_usage (
    tenant_id CHAR(32) NOT NULL PRIMARY KEY,
    units_reserved BIGINT UNSIGNED NOT NULL DEFAULT 0,
    jobs_created BIGINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_units (
    id CHAR(32) NOT NULL PRIMARY KEY,
    tenant_id CHAR(32) NOT NULL,
    trade_item_id CHAR(32) NOT NULL,
    batch_id CHAR(32) NOT NULL,
    gtin14 CHAR(14) NOT NULL,
    serial VARBINARY(20) NOT NULL,
    digital_link VARCHAR(2048) NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY print_unit_identity (gtin14,serial),
    UNIQUE KEY print_unit_owner (tenant_id,id),
    INDEX print_unit_batch (tenant_id,batch_id),
    FOREIGN KEY (tenant_id) REFERENCES organizations(id),
    FOREIGN KEY (tenant_id,trade_item_id) REFERENCES trade_items(tenant_id,id),
    FOREIGN KEY (tenant_id,batch_id) REFERENCES batches(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_jobs (
    id CHAR(32) NOT NULL PRIMARY KEY,
    tenant_id CHAR(32) NOT NULL,
    device_id CHAR(32) NOT NULL,
    batch_id CHAR(32) NOT NULL,
    created_by CHAR(32) NOT NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'queued',
    quantity INT NOT NULL,
    format VARCHAR(20) NOT NULL,
    reprint_of CHAR(32) NULL,
    reason VARCHAR(1000) NULL,
    claim_hash CHAR(64) NULL,
    claimed_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX print_job_queue (device_id,state,created_at),
    UNIQUE KEY print_job_owner (tenant_id,id),
    FOREIGN KEY (tenant_id) REFERENCES organizations(id),
    FOREIGN KEY (tenant_id,device_id) REFERENCES print_devices(tenant_id,id),
    FOREIGN KEY (tenant_id,batch_id) REFERENCES batches(tenant_id,id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (tenant_id,reprint_of) REFERENCES print_jobs(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_job_units (
    tenant_id CHAR(32) NOT NULL,
    job_id CHAR(32) NOT NULL,
    unit_id CHAR(32) NOT NULL,
    position INT NOT NULL,
    PRIMARY KEY (job_id,position),
    UNIQUE KEY print_job_unit (job_id,unit_id),
    FOREIGN KEY (tenant_id,job_id) REFERENCES print_jobs(tenant_id,id),
    FOREIGN KEY (tenant_id,unit_id) REFERENCES print_units(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_job_events (
    id CHAR(32) NOT NULL PRIMARY KEY,
    tenant_id CHAR(32) NOT NULL,
    job_id CHAR(32) NOT NULL,
    state VARCHAR(20) NOT NULL,
    evidence VARCHAR(1500) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (tenant_id,job_id) REFERENCES print_jobs(tenant_id,id),
    INDEX print_event_job (job_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
