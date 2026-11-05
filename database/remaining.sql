USE parceltrack;
CREATE TABLE IF NOT EXISTS rate_limits (
 limit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 scope ENUM('login','tracking') NOT NULL,
 identifier VARCHAR(64) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_rate_window(scope, identifier, attempted_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_logs (
 log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 event_type ENUM('LOGIN_SUCCESS','LOGIN_FAILED','LOGIN_LOCKED','LOGOUT','PASSWORD_CHANGE','USER_CREATED','USER_DISABLED','PARCEL_REGISTERED','TRACKING_RATE_LIMITED','ACCESS_DENIED','DATA_ANONYMISED','PARCEL_ASSIGNED','STATUS_CHANGE','UPLOAD_ACCEPTED','UPLOAD_REJECTED') NOT NULL,
 description VARCHAR(255) NULL,
 parcel_id INT UNSIGNED NULL,
 ip_address VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS delivery_proofs (
 proof_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 parcel_id INT UNSIGNED NOT NULL UNIQUE,
 stored_filename CHAR(36) NOT NULL,
 mime_type ENUM('image/jpeg','image/png') NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 uploaded_by INT UNSIGNED NOT NULL,
 uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(parcel_id) REFERENCES parcels(parcel_id),
 FOREIGN KEY(uploaded_by) REFERENCES users(user_id),
 CHECK(file_size > 0 AND file_size <= 5242880)
) ENGINE=InnoDB;
CREATE OR REPLACE VIEW v_public_tracking AS
 SELECT p.tracking_no, h.status, h.changed_at
 FROM parcels p JOIN parcel_status_history h ON h.parcel_id=p.parcel_id;
CREATE OR REPLACE VIEW v_dashboard_counts AS
 SELECT current_status AS status, COUNT(*) AS total FROM parcels GROUP BY current_status;
SHOW FULL TABLES;
