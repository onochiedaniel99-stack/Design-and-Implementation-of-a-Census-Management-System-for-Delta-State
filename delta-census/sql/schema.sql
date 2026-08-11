-- Create database
CREATE DATABASE IF NOT EXISTS delta_census;
USE delta_census;

-- Users table (updated)
CREATE TABLE users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    surname VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    other_name VARCHAR(50) NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    date_of_birth DATE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    passport_photo VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'enumerator') DEFAULT 'enumerator',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    created_by INT(11) NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expiry DATETIME NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Location assignments table
CREATE TABLE user_locations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    lga VARCHAR(100) NOT NULL,
    ward VARCHAR(100) NOT NULL,
    community VARCHAR(100) NULL,
    enumeration_area VARCHAR(50) NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT(11) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    UNIQUE KEY unique_user_location (user_id, lga, ward, community, enumeration_area)
);

-- Households table
CREATE TABLE households (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    household_code VARCHAR(50) UNIQUE NOT NULL,
    lga VARCHAR(100) NOT NULL,
    ward VARCHAR(100) NOT NULL,
    community VARCHAR(100) NULL,
    enumeration_area VARCHAR(50) NULL,
    head_of_household VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20) NULL,
    total_members INT(11) DEFAULT 0,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Household members table
CREATE TABLE household_members (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    household_id INT(11) NOT NULL,
    surname VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    other_name VARCHAR(50) NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    date_of_birth DATE NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    occupation VARCHAR(100) NULL,
    education_level VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE
);

-- Login attempts table
CREATE TABLE login_attempts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 0,
    INDEX idx_ip_time (ip_address, attempt_time)
);

-- Activity log
CREATE TABLE activity_log (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin (password: Admin@123)
INSERT INTO users (
    username, employee_id, surname, first_name, 
    gender, date_of_birth, phone, email, 
    password_hash, role, status
) VALUES (
    'admin', 'ADMIN001', 'System', 'Administrator',
    'Male', '1990-01-01', '08012345678', 'admin@delta.gov',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin', 'active'
);

-- Sample LGA, Ward, Community data for demonstration
INSERT INTO user_locations (user_id, lga, ward, community, enumeration_area, assigned_by) 
VALUES (1, 'Delta North', 'Ward 1', 'Community A', 'EA001', 1);