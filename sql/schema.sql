-- DarkDate Database Schema - Phase 2
-- Character encoding: utf8mb4 for emoji and special symbols

CREATE DATABASE IF NOT EXISTS darkdate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE darkdate;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    fear_level INT DEFAULT 0,
    is_blocked BOOLEAN DEFAULT FALSE,
    block_until TIMESTAMP NULL
);

-- Chat messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    sender_type ENUM('user', 'bot', 'system') NOT NULL,
    sender_id VARCHAR(50),
    content TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    message_type ENUM('text', 'image', 'audio', 'glitch', 'spam') DEFAULT 'text',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Story progress
CREATE TABLE story_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    chapter_id INT NOT NULL,
    scene_id INT NOT NULL,
    choices_made JSON,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_chapter (user_id, chapter_id)
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(100) NOT NULL,
    content TEXT,
    type ENUM('message', 'system', 'horror', 'fake') DEFAULT 'message',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    trigger_time TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Fear level log (for analytics and dynamic difficulty)
CREATE TABLE fear_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    fear_level INT NOT NULL,
    trigger_event VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Achievements
CREATE TABLE achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    achievement_key VARCHAR(50) NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_achievement (user_id, achievement_key)
);

-- Sessions (for API authentication)
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_messages_user ON messages(user_id);
CREATE INDEX idx_messages_timestamp ON messages(timestamp);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_story_progress_user ON story_progress(user_id);
CREATE INDEX idx_fear_log_user ON fear_log(user_id);
