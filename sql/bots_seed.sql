-- DarkDate: Initial Bot Data
-- Insert default bots with different personalities

INSERT INTO `darkdate_bots` (`bot_key`, `name`, `avatar_url`, `personality_type`, `message_frequency`, `active_hours_start`, `active_hours_end`, `response_delay_min`, `response_delay_max`, `fear_trigger_threshold`, `is_active`, `script_data`) VALUES
('alice', 'Алиса', '/assets/avatars/alice.png', 'friendly', 8, 8, 23, 2, 5, 60, 1, '{
    "intro_scene": "chapter1_intro",
    "dialogue_tree": "alice_main",
    "relationship_tracking": true
}'),
('shadow', 'Тень', '/assets/avatars/shadow.png', 'mysterious', 3, 20, 6, 5, 15, 40, 1, '{
    "trigger_keywords": ["темнота", "страх", "ночь", "они"],
    "special_events": ["night_visit", "whisper_message"]
}'),
('error_404', 'ERROR_404', '/assets/avatars/glitch.png', 'glitch', 2, 0, 23, 3, 8, 30, 1, '{
    "glitch_intensity": "medium",
    "corruption_messages": true
}'),
('spammer_x', 'SPAM_BOT_X', '/assets/avatars/spam.png', 'spammer', 15, 0, 23, 1, 3, 20, 1, '{
    "attack_triggers": ["ignore", "reject"],
    "spam_duration": 10
}'),
('viktor', 'Виктор', '/assets/avatars/viktor.png', 'aggressive', 4, 18, 2, 3, 7, 50, 1, '{
    "confrontation_style": "direct",
    "threat_level": "medium"
}');

-- Update story progress table to track bot relationships
ALTER TABLE `darkdate_story_progress` 
ADD COLUMN `bot_relationships` JSON AFTER `choices_made`;

-- Add index for bot activity scheduling
CREATE INDEX `idx_bot_active_hours` ON `darkdate_bots` (`active_hours_start`, `active_hours_end`, `is_active`);
