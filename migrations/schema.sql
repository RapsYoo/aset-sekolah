-- Hapus database jika sudah ada (opsional)
DROP DATABASE IF EXISTS aset_sekolah;
CREATE DATABASE aset_sekolah;
USE aset_sekolah;

-- Tabel roles
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel units (Sekolah/Unit)
CREATE TABLE units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Tabel assets_monthly (Aset per bulan per KIB)
CREATE TABLE assets_monthly (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kib_type ENUM('A', 'B', 'C', 'D', 'E', 'F') NOT NULL,
    year INT NOT NULL,
    month INT NOT NULL,
    total INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_kib_month (kib_type, year, month),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_kib_year (kib_type, year),
    INDEX idx_month (month)
);

-- Insert default roles
INSERT INTO roles (name) VALUES ('admin'), ('pegawai');

-- Insert default units
INSERT INTO units (code, name) VALUES 
('SKL001', 'Sekolah Dasar Negeri 1'),
('SKL002', 'Sekolah Dasar Negeri 2'),
('SKL003', 'Sekolah Menengah Pertama 1');

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password_hash, role_id) 
VALUES ('Admin Sekolah', 'admin@sekolah.com', '$2y$10$4MfBmkqB.JlUcLw8UG7/oeP5DqQGPXK1gCm4F2O8R7Hgz3pjL2HJG', 1);

-- Insert sample pegawai user (password: pegawai123)
INSERT INTO users (name, email, password_hash, role_id) 
VALUES ('Pegawai Sekolah', 'pegawai@sekolah.com', '$2y$10$L9Y5ZKz1C5p2.V3RqI2dJ.KJJxB8C3M9L2Z7Y8P1Q4R5S6T7U8V9', 2);

-- Insert sample data aset bulanan
INSERT INTO assets_monthly (kib_type, year, month, total, created_by) VALUES
('A', 2025, 1, 150, 1),
('A', 2025, 2, 155, 1),
('A', 2025, 3, 160, 1),
('A', 2025, 4, 165, 1),
('A', 2025, 5, 170, 1),
('A', 2025, 6, 175, 1),
('B', 2025, 1, 100, 1),
('B', 2025, 2, 105, 1),
('B', 2025, 3, 110, 1),
('C', 2025, 1, 200, 1),
('C', 2025, 2, 205, 1),
('C', 2025, 3, 210, 1);

