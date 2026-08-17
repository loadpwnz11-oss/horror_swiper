-- DarkDate Database Schema - Phase 2
-- Character encoding: utf8mb4 for emoji and special symbols
-- All tables prefixed with 'darkdate_' to avoid conflicts

CREATE DATABASE IF NOT EXISTS darkdate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE darkdate;

-- Users table
CREATE TABLE IF NOT EXISTS darkdate_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    fear_level INT DEFAULT 0,
    is_blocked BOOLEAN DEFAULT FALSE,
    block_until TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages
CREATE TABLE IF NOT EXISTS darkdate_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_type ENUM('user', 'bot', 'system') NOT NULL,
    sender_id VARCHAR(50),
    content TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    message_type ENUM('text', 'image', 'audio', 'glitch', 'spam') DEFAULT 'text',
    FOREIGN KEY (user_id) REFERENCES darkdate_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Story progress
CREATE TABLE IF NOT EXISTS darkdate_story_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chapter_id INT NOT NULL,
    scene_id INT NOT NULL,
    choices_made JSON,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY unique_user_chapter (user_id, chapter_id),
    FOREIGN KEY (user_id) REFERENCES darkdate_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE IF NOT EXISTS darkdate_notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    content TEXT,
    type ENUM('message', 'system', 'horror', 'fake') DEFAULT 'message',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    trigger_time TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES darkdate_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fear level log (for analytics and dynamic difficulty)
CREATE TABLE IF NOT EXISTS darkdate_fear_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    fear_level INT NOT NULL,
    trigger_event VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES darkdate_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Achievements
CREATE TABLE IF NOT EXISTS darkdate_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_key VARCHAR(50) NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_achievement (user_id, achievement_key),
    FOREIGN KEY (user_id) REFERENCES darkdate_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions (for API authentication)
CREATE TABLE IF NOT EXISTS darkdate_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES darkdate_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Spam logs and bot blocking
CREATE TABLE IF NOT EXISTS darkdate_spam_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bot_type VARCHAR(50) NOT NULL,
    blocked_until TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES darkdate_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_darkdate_messages_user ON darkdate_messages(user_id);
CREATE INDEX IF NOT EXISTS idx_darkdate_messages_timestamp ON darkdate_messages(timestamp);
CREATE INDEX IF NOT EXISTS idx_darkdate_notifications_user ON darkdate_notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_darkdate_story_progress_user ON darkdate_story_progress(user_id);
CREATE INDEX IF NOT EXISTS idx_darkdate_fear_log_user ON darkdate_fear_log(user_id);
CREATE INDEX IF NOT EXISTS idx_darkdate_spam_logs_user ON darkdate_spam_logs(user_id);
