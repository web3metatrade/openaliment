CREATE TABLE IF NOT EXISTS partner_applications (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NOT NULL,
 tenant_id CHAR(32) NOT NULL,
 legal_version_id CHAR(32) NOT NULL,
 profile_id CHAR(32) NULL,
 previous_profile_id CHAR(32) NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 draft_json MEDIUMTEXT NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'draft',
 identity_verified TINYINT NOT NULL DEFAULT 0,
 reviewed_by CHAR(32) NULL,
 decision_json TEXT NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (tenant_id) REFERENCES organizations(id),
 FOREIGN KEY (legal_version_id) REFERENCES organization_legal_profile_versions(id),
 FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_profiles (
 id CHAR(32) PRIMARY KEY,
 public_name VARCHAR(190) NULL,
 tenant_id CHAR(32) NOT NULL,
 country_code CHAR(2) NOT NULL DEFAULT 'RO',
 cui VARCHAR(10) NOT NULL,
 slug VARCHAR(100) NOT NULL UNIQUE,
 current_version_id CHAR(32) NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'pending',
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 observation_id CHAR(32) NOT NULL,
 fiscal_checked_at DATETIME NOT NULL,
 fiscal_state VARCHAR(24) NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY partner_company (country_code,cui),
 KEY partner_name (public_name,id),
 FOREIGN KEY (tenant_id) REFERENCES organizations(id),
 FOREIGN KEY (observation_id) REFERENCES anaf_observations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_profile_versions (
 id CHAR(32) PRIMARY KEY,
 profile_id CHAR(32) NOT NULL,
 revision INT UNSIGNED NOT NULL,
 legal_version_id CHAR(32) NOT NULL,
 snapshot_json MEDIUMTEXT NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'draft',
 reviewed_by CHAR(32) NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY partner_public_version (profile_id,revision),
 FOREIGN KEY (profile_id) REFERENCES partner_profiles(id),
 FOREIGN KEY (legal_version_id) REFERENCES organization_legal_profile_versions(id),
 FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_affiliations (
 id CHAR(32) PRIMARY KEY,
 profile_id CHAR(32) NOT NULL,
 user_id CHAR(32) NOT NULL,
 role VARCHAR(24) NOT NULL DEFAULT 'member',
 state VARCHAR(24) NOT NULL DEFAULT 'active',
 verified_by CHAR(32) NOT NULL,
 started_at DATETIME NOT NULL,
 ended_at DATETIME NULL,
 KEY partner_person (user_id,profile_id,state),
 FOREIGN KEY (profile_id) REFERENCES partner_profiles(id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_contacts (
 id CHAR(32) PRIMARY KEY,
 application_id CHAR(32) NOT NULL,
 kind VARCHAR(20) NOT NULL,
 value_text VARCHAR(254) NOT NULL,
 source_kind VARCHAR(24) NOT NULL DEFAULT 'company',
 verified_at DATETIME NULL,
 expires_at DATETIME NULL,
 public_consent TINYINT NOT NULL DEFAULT 0,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 withdrawn_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY (application_id) REFERENCES partner_applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_authorizations (
 id CHAR(32) PRIMARY KEY,
 public_id CHAR(32) NOT NULL UNIQUE,
 profile_id CHAR(32) NOT NULL,
 specialization VARCHAR(40) NOT NULL,
 credential_id CHAR(32) NOT NULL,
 policy_version VARCHAR(40) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'active',
 issued_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 reviewed_by CHAR(32) NOT NULL,
 reason TEXT NULL,
 KEY partner_authorization_current (profile_id,state,expires_at),
 UNIQUE KEY partner_qualification (profile_id,specialization,credential_id),
 FOREIGN KEY (profile_id) REFERENCES partner_profiles(id),
 FOREIGN KEY (credential_id) REFERENCES learning_credentials(id),
 FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
