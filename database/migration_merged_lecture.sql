-- Merged lectures: one schedule row + schedule_merge_member for extra cohorts.
-- (Also applied automatically via merged_lecture_ensure_schema() on first API use.)

ALTER TABLE schedule
    ADD COLUMN IF NOT EXISTS is_merged_lecture TINYINT(1) NOT NULL DEFAULT 0 AFTER section;

CREATE TABLE IF NOT EXISTS schedule_merge_member (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    section VARCHAR(32) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_merge_member (schedule_id, semester, section),
    INDEX idx_merge_schedule (schedule_id),
    FOREIGN KEY (schedule_id) REFERENCES schedule(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
