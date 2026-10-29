CREATE TABLE IF NOT EXISTS learning_courses (
 id CHAR(32) PRIMARY KEY,
 slug VARCHAR(100) NOT NULL UNIQUE,
 title VARCHAR(190) NOT NULL,
 draft_json MEDIUMTEXT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 current_release_id CHAR(32) NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'draft',
 created_by CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_releases (
 id CHAR(32) PRIMARY KEY,
 course_id CHAR(32) NOT NULL,
 version_key VARCHAR(40) NOT NULL,
 manifest_json MEDIUMTEXT NOT NULL,
 sha256 CHAR(64) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'published',
 published_by CHAR(32) NOT NULL,
 published_at DATETIME NOT NULL,
 UNIQUE KEY learning_edition (course_id,version_key),
 FOREIGN KEY (course_id) REFERENCES learning_courses(id),
 FOREIGN KEY (published_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_enrollments (
 id CHAR(32) PRIMARY KEY,
 user_id CHAR(32) NOT NULL,
 release_id CHAR(32) NOT NULL,
 specialization VARCHAR(40) NOT NULL,
 state VARCHAR(24) NOT NULL DEFAULT 'active',
 policy_version VARCHAR(40) NOT NULL,
 assessment_seconds INT UNSIGNED NOT NULL DEFAULT 2700,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 UNIQUE KEY learning_enrollment (user_id,release_id,specialization),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (release_id) REFERENCES learning_releases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_progress (
 id CHAR(32) PRIMARY KEY,
 enrollment_id CHAR(32) NOT NULL,
 lesson_id CHAR(32) NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 completed TINYINT NOT NULL DEFAULT 0,
 practice_passed TINYINT NOT NULL DEFAULT 0,
 position_key VARCHAR(100) NOT NULL DEFAULT '',
 updated_at DATETIME NOT NULL,
 UNIQUE KEY learning_lesson_progress (enrollment_id,lesson_id),
 FOREIGN KEY (enrollment_id) REFERENCES learning_enrollments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
