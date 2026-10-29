CREATE TABLE IF NOT EXISTS program_staff_grants (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NOT NULL,
 role VARCHAR(40) NOT NULL,
 granted_by CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 KEY program_staff_user (user_id,role,revoked_at),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (granted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS program_events (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NOT NULL,
 object_kind VARCHAR(40) NOT NULL,
 object_id CHAR(32) NOT NULL,
 action VARCHAR(80) NOT NULL,
 details_json MEDIUMTEXT NOT NULL,
 receipt_key CHAR(64) NULL UNIQUE,
 request_hash CHAR(64) NULL,
 result_json MEDIUMTEXT NULL,
 created_at DATETIME NOT NULL,
 KEY program_event_object (object_kind,object_id,created_at),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS program_assets (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NOT NULL,
 purpose VARCHAR(30) NOT NULL,
 file_key VARCHAR(100) NOT NULL,
 mime VARCHAR(60) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 width INT UNSIGNED NULL,
 height INT UNSIGNED NULL,
 sha256 CHAR(64) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'private',
 rights_confirmed TINYINT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 withdrawn_at DATETIME NULL,
 KEY program_asset_owner (user_id,state),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS program_jobs (
 id CHAR(32) PRIMARY KEY,
 job_key VARCHAR(190) NOT NULL UNIQUE,
 kind VARCHAR(40) NOT NULL,
 payload_json TEXT NOT NULL,
 state VARCHAR(20) NOT NULL DEFAULT 'pending',
 attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 available_at DATETIME NOT NULL,
 lease_until DATETIME NULL,
 last_error VARCHAR(60) NULL,
 created_at DATETIME NOT NULL,
 KEY program_job_queue (state,available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contributor_profiles (
 id CHAR(32) PRIMARY KEY,
 public_name VARCHAR(120) NULL,
 user_id CHAR(32) NULL,
 slug VARCHAR(100) NOT NULL UNIQUE,
 draft_json MEDIUMTEXT NOT NULL,
 approved_json MEDIUMTEXT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 submitted_revision INT UNSIGNED NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'draft',
 consent_at DATETIME NULL,
 consent_version INT UNSIGNED NULL,
 approved_by CHAR(32) NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 KEY contributor_name (public_name,id),
 KEY contributor_public (state,id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS program_verifications (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NULL,
 purpose VARCHAR(40) NOT NULL,
 object_id CHAR(32) NULL,
 value_text VARCHAR(254) NULL,
 token_hash CHAR(64) NULL UNIQUE,
 observation_id CHAR(32) NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'pending',
 evidence_json TEXT NULL,
 verified_by CHAR(32) NULL,
 expires_at DATETIME NULL,
 consumed_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 KEY program_verification_object (object_id,purpose,state),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (observation_id) REFERENCES anaf_observations(id),
 FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
