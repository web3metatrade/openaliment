CREATE TABLE IF NOT EXISTS assessment_banks (
 id CHAR(32) PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 draft_json MEDIUMTEXT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 current_release_id CHAR(32) NULL,
 created_by CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_releases (
 id CHAR(32) PRIMARY KEY,
 publication_order BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
 bank_id CHAR(32) NOT NULL,
 course_release_id CHAR(32) NOT NULL,
 version_key VARCHAR(40) NOT NULL,
 private_json MEDIUMTEXT NOT NULL,
 sha256 CHAR(64) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'published',
 published_by CHAR(32) NOT NULL,
 published_at DATETIME NOT NULL,
 UNIQUE KEY assessment_edition (bank_id,version_key),
 FOREIGN KEY (bank_id) REFERENCES assessment_banks(id),
 FOREIGN KEY (course_release_id) REFERENCES learning_releases(id),
 FOREIGN KEY (published_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_attempts (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NOT NULL,
 enrollment_id CHAR(32) NOT NULL,
 release_id CHAR(32) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'in_progress',
 started_at DATETIME NOT NULL,
 due_at DATETIME NOT NULL,
 submitted_at DATETIME NULL,
 result_id CHAR(32) NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 KEY assessment_person (user_id,started_at),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (enrollment_id) REFERENCES learning_enrollments(id),
 FOREIGN KEY (release_id) REFERENCES assessment_releases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_items (
 id CHAR(32) PRIMARY KEY,
 attempt_id CHAR(32) NOT NULL,
 question_key VARCHAR(100) NOT NULL,
 domain_key VARCHAR(40) NOT NULL,
 critical TINYINT NOT NULL DEFAULT 0,
 position SMALLINT UNSIGNED NOT NULL,
 served_json TEXT NOT NULL,
 response_json TEXT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 0,
 saved_at DATETIME NULL,
 UNIQUE KEY assessment_draw (attempt_id,question_key),
 FOREIGN KEY (attempt_id) REFERENCES assessment_attempts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_results (
 id CHAR(32) PRIMARY KEY,
 attempt_id CHAR(32) NOT NULL,
 version INT UNSIGNED NOT NULL,
 score_basis_points INT UNSIGNED NOT NULL,
 passed TINYINT NOT NULL,
 critical_passed TINYINT NOT NULL,
 details_json MEDIUMTEXT NOT NULL,
 reviewer_id CHAR(32) NULL,
 reason TEXT NULL,
 appeal_json TEXT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY assessment_result_version (attempt_id,version),
 FOREIGN KEY (attempt_id) REFERENCES assessment_attempts(id),
 FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_lab_runs (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NOT NULL,
 enrollment_id CHAR(32) NOT NULL,
 scenario VARCHAR(40) NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 state VARCHAR(24) NOT NULL DEFAULT 'draft',
 payload_json MEDIUMTEXT NOT NULL,
 checks_json MEDIUMTEXT NULL,
 evidence_asset_id CHAR(32) NULL,
 reviewer_id CHAR(32) NULL,
 review_json TEXT NULL,
 updated_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 KEY assessment_lab_retention (updated_at,state,id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (enrollment_id) REFERENCES learning_enrollments(id),
 FOREIGN KEY (evidence_asset_id) REFERENCES program_assets(id),
 FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_credentials (
 id CHAR(32) PRIMARY KEY,
 public_id CHAR(32) NOT NULL UNIQUE,
 user_id CHAR(32) NOT NULL,
 enrollment_id CHAR(32) NOT NULL,
 result_id CHAR(32) NOT NULL,
 specialization VARCHAR(40) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'active',
 issued_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 reason TEXT NULL,
 public_name TINYINT NOT NULL DEFAULT 0,
 UNIQUE KEY learning_credential_result (result_id,specialization),
 KEY learning_credential_person (user_id,state,expires_at),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (enrollment_id) REFERENCES learning_enrollments(id),
 FOREIGN KEY (result_id) REFERENCES assessment_results(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
