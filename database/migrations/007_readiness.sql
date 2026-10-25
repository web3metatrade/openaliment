CREATE TABLE IF NOT EXISTS producer_sessions (
 id CHAR(32) PRIMARY KEY,
 session_hash CHAR(64) NOT NULL UNIQUE,
 user_id CHAR(32) NOT NULL,
 device_name VARCHAR(120) NOT NULL,
 authenticated_at DATETIME NOT NULL,
 verified_at DATETIME NULL,
 last_seen_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX (user_id,revoked_at),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS account_challenges (
 token_hash CHAR(64) PRIMARY KEY,
 purpose VARCHAR(40) NOT NULL,
 user_id CHAR(32) NULL,
 home_account_id CHAR(32) NULL,
 session_hash CHAR(64) NULL,
 payload_json TEXT NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX (expires_at),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (home_account_id) REFERENCES home_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS passkey_credentials (
 id CHAR(32) PRIMARY KEY,
 credential_sha256 CHAR(64) NOT NULL UNIQUE,
 credential_id TEXT NOT NULL,
 user_id CHAR(32) NOT NULL,
 name VARCHAR(120) NOT NULL,
 public_key TEXT NOT NULL,
 signature_counter BIGINT UNSIGNED NOT NULL DEFAULT 0,
 backup_eligible TINYINT NOT NULL DEFAULT 0,
 backed_up TINYINT NOT NULL DEFAULT 0,
 last_used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS account_recovery_codes (
 user_id CHAR(32) NOT NULL,
 code_hash CHAR(64) NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY (user_id,code_hash),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS community_vote_states (
 proposal_id CHAR(32) NOT NULL,
 user_id CHAR(32) NOT NULL,
 active TINYINT NOT NULL,
 mutation_version INT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY (proposal_id,user_id),
 FOREIGN KEY (proposal_id) REFERENCES change_proposals(id),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO community_vote_states (proposal_id,user_id,active,mutation_version,updated_at)
 SELECT proposal_id,user_id,1,1,created_at FROM votes;
CREATE TABLE IF NOT EXISTS feedback_criteria (
 feedback_id CHAR(32) NOT NULL,
 criterion VARCHAR(40) NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 PRIMARY KEY (feedback_id,criterion),
 FOREIGN KEY (feedback_id) REFERENCES feedback(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS document_blobs (
 tenant_id CHAR(32) NOT NULL,
 sha256 CHAR(64) NOT NULL,
 storage_key VARCHAR(180) NOT NULL,
 mime VARCHAR(120) NOT NULL,
 size_bytes BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY (tenant_id,sha256),
 FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS document_provenance (
 id CHAR(32) PRIMARY KEY,
 document_id CHAR(32) NOT NULL,
 source_sha256 CHAR(64) NOT NULL,
 details_json TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY (document_id,source_sha256),
 FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS document_scans (
 tenant_id CHAR(32) NOT NULL,
 sha256 CHAR(64) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'pending',
 engine VARCHAR(120) NULL,
 scanned_at DATETIME NULL,
 details_json TEXT NOT NULL,
 PRIMARY KEY (tenant_id,sha256),
 FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS operation_checks (
 check_key VARCHAR(80) PRIMARY KEY,
 state VARCHAR(24) NOT NULL,
 checked_at DATETIME NOT NULL,
 details_json TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS security_outbox (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NOT NULL,
 event_key VARCHAR(100) NOT NULL,
 subject VARCHAR(190) NOT NULL,
 message TEXT NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 available_at DATETIME NOT NULL,
 sent_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY (user_id,event_key),
 INDEX (sent_at,available_at),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS home_erasure_requests (
 id CHAR(32) PRIMARY KEY,
 subject_sha256 CHAR(64) NOT NULL,
 completed_at DATETIME NOT NULL,
 exported_at DATETIME NULL,
 details_json TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS private_file_deletions (
 id CHAR(32) PRIMARY KEY,
 storage_key VARCHAR(180) NOT NULL UNIQUE,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
