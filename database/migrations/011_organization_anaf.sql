CREATE TABLE IF NOT EXISTS anaf_observations (
 id CHAR(32) PRIMARY KEY,
 cui VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 queried_for DATE NOT NULL,
 api_version VARCHAR(12) NOT NULL,
 mapping_version VARCHAR(40) NOT NULL,
 result_state VARCHAR(16) NOT NULL,
 response_json MEDIUMTEXT NOT NULL,
 response_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fetched_at DATETIME NOT NULL,
 reusable_until DATETIME NOT NULL,
 KEY anaf_cache (cui,queried_for,reusable_until),
 KEY anaf_observation_age (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organization_registration_checks (
 id CHAR(32) PRIMARY KEY,
 owner_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 purpose VARCHAR(20) NOT NULL,
 tenant_id CHAR(32) NULL,
 user_id CHAR(32) NULL,
 cui VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 observation_id CHAR(32) NULL,
 expected_version_id CHAR(32) NULL,
 state VARCHAR(20) NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 consumed_at DATETIME NULL,
 KEY anaf_check_owner (owner_hash,purpose,state),
 KEY anaf_check_expiry (expires_at),
 FOREIGN KEY (observation_id) REFERENCES anaf_observations(id),
 FOREIGN KEY (tenant_id) REFERENCES organizations(id),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organization_legal_profiles (
 tenant_id CHAR(32) PRIMARY KEY,
 country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'RO',
 cui VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 current_version_id CHAR(32) NULL,
 created_at DATETIME NOT NULL,
 KEY legal_profile_cui (country_code,cui),
 FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organization_legal_profile_versions (
 id CHAR(32) PRIMARY KEY,
 tenant_id CHAR(32) NOT NULL,
 observation_id CHAR(32) NOT NULL,
 legal_name TEXT NOT NULL,
 record_json MEDIUMTEXT NOT NULL,
 record_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 origin VARCHAR(24) NOT NULL DEFAULT 'anaf',
 confirmed_by CHAR(32) NOT NULL,
 confirmed_at DATETIME NOT NULL,
 UNIQUE KEY legal_profile_version_tenant (tenant_id,id),
 KEY legal_profile_history (tenant_id,confirmed_at),
 FOREIGN KEY (tenant_id) REFERENCES organization_legal_profiles(tenant_id),
 FOREIGN KEY (observation_id) REFERENCES anaf_observations(id),
 FOREIGN KEY (confirmed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
