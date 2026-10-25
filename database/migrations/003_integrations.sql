CREATE TABLE IF NOT EXISTS integration_connectors (
 id CHAR(32) PRIMARY KEY,
 tenant_id CHAR(32) NOT NULL,
 name VARCHAR(180) NOT NULL,
 kind VARCHAR(24) NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,id),
 FOREIGN KEY (tenant_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS integration_mappings (
 id CHAR(32) PRIMARY KEY,
 tenant_id CHAR(32) NOT NULL,
 connector_id CHAR(32) NOT NULL,
 version_key VARCHAR(60) NOT NULL,
 mapping_json MEDIUMTEXT NOT NULL,
 sha256 CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY (tenant_id,id),
 UNIQUE KEY (connector_id,version_key),
 FOREIGN KEY (tenant_id,connector_id) REFERENCES integration_connectors(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS integration_bindings (
 tenant_id CHAR(32) NOT NULL,
 connector_id CHAR(32) NOT NULL,
 external_id VARCHAR(120) COLLATE utf8mb4_bin NOT NULL,
 product_id CHAR(32) NOT NULL,
 trade_item_id CHAR(32) NOT NULL,
 PRIMARY KEY (tenant_id,connector_id,external_id),
 FOREIGN KEY (tenant_id,connector_id) REFERENCES integration_connectors(tenant_id,id),
 FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id),
 FOREIGN KEY (tenant_id,trade_item_id) REFERENCES trade_items(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS integration_events (
 id CHAR(32) PRIMARY KEY,
 tenant_id CHAR(32) NOT NULL,
 connector_id CHAR(32) NOT NULL,
 mapping_id CHAR(32) NOT NULL,
 event_key VARCHAR(128) COLLATE utf8mb4_bin NOT NULL,
 request_sha256 CHAR(64) NOT NULL,
 result_json MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY (connector_id,event_key),
 FOREIGN KEY (tenant_id,connector_id) REFERENCES integration_connectors(tenant_id,id),
 FOREIGN KEY (tenant_id,mapping_id) REFERENCES integration_mappings(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS consumer_receipts (
 id CHAR(32) PRIMARY KEY,
 tenant_id CHAR(32) NOT NULL,
 connector_id CHAR(32) NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 receipt_json MEDIUMTEXT NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX (expires_at),
 FOREIGN KEY (tenant_id,connector_id) REFERENCES integration_connectors(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
