-- N0thy Casino - Structure de la base de données
-- Exécuter ce script dans phpMyAdmin ou via MySQL CLI

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS n0thy_casino CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE n0thy_casino;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    twitch_username VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_twitch (twitch_username),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Insertion du compte administrateur par défaut
-- Mot de passe: admin123 (à changer après première connexion!)
INSERT INTO users (email, password_hash, twitch_username, status, role) VALUES 
('admin@n0thy.tv', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'N0thy_Admin', 'approved', 'admin')
ON DUPLICATE KEY UPDATE id=id;
