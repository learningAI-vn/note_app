-- ============================================================================
-- 503073 - Web Programming & Applications (Final Project)
-- Database Schema: Note Management Application
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `note_app`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `note_app`;

DROP TABLE IF EXISTS `note_shares`;
DROP TABLE IF EXISTS `note_attachments`;
DROP TABLE IF EXISTS `note_labels`;
DROP TABLE IF EXISTS `labels`;
DROP TABLE IF EXISTS `notes`;
DROP TABLE IF EXISTS `users`;

-- 1. Users table (Account management & User preferences)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `is_activated` TINYINT(1) NOT NULL DEFAULT 0,
    `activation_token` VARCHAR(64) DEFAULT NULL,
    `reset_token` VARCHAR(64) DEFAULT NULL,
    `reset_expires_at` DATETIME DEFAULT NULL,
    `pref_font_size` VARCHAR(20) NOT NULL DEFAULT 'medium',
    `pref_note_color` VARCHAR(20) NOT NULL DEFAULT '#ffffff',
    `pref_theme` VARCHAR(10) NOT NULL DEFAULT 'light',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Notes table (Core notes, pinning, per-note password protection)
CREATE TABLE `notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL DEFAULT '',
    `content` LONGTEXT NOT NULL,
    `color` VARCHAR(20) NOT NULL DEFAULT '#ffffff',
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `pinned_at` DATETIME DEFAULT NULL,
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Labels table (User-defined labels)
CREATE TABLE `labels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_label` (`user_id`, `name`),
    CONSTRAINT `fk_labels_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Note-Labels junction table (Deleting a label cascades only here, keeping notes intact)
CREATE TABLE `note_labels` (
    `note_id` INT NOT NULL,
    `label_id` INT NOT NULL,
    PRIMARY KEY (`note_id`, `label_id`),
    CONSTRAINT `fk_nl_note` FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_nl_label` FOREIGN KEY (`label_id`) REFERENCES `labels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Note Attachments table (Images, videos, and files)
CREATE TABLE `note_attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_type` ENUM('image', 'video', 'file') NOT NULL DEFAULT 'file',
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_att_note` FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Note Shares table (Sharing notes with read/edit permissions)
CREATE TABLE `note_shares` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `owner_id` INT NOT NULL,
    `recipient_id` INT NOT NULL,
    `permission` ENUM('read', 'edit') NOT NULL DEFAULT 'read',
    `is_notified` TINYINT(1) NOT NULL DEFAULT 0,
    `shared_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_note_recipient` (`note_id`, `recipient_id`),
    CONSTRAINT `fk_share_note` FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_share_owner` FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_share_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Pre-loaded Seed Data for Instructor Assessment
-- All accounts below use password: Password123!
-- Locked note (#3) uses note password: SecretNote123
-- ============================================================================

INSERT INTO `users` (`id`, `email`, `display_name`, `password_hash`, `avatar`, `is_activated`, `activation_token`, `pref_font_size`, `pref_note_color`, `pref_theme`) VALUES
(1, 'alice@tdtu.edu.vn', 'Alice Nguyễn', '$2y$12$34yZBTDtw5NG5RFw8kP.hu9i0sjdCXbsMsSUu3SLmsRA9spz/w3N.', NULL, 1, NULL, 'medium', '#eef4ff', 'light'),
(2, 'bob@tdtu.edu.vn', 'Bob Trần', '$2y$12$34yZBTDtw5NG5RFw8kP.hu9i0sjdCXbsMsSUu3SLmsRA9spz/w3N.', NULL, 1, NULL, 'medium', '#ffffff', 'light'),
(3, 'unverified@tdtu.edu.vn', 'Sinh Viên Chưa Xác Thực', '$2y$12$34yZBTDtw5NG5RFw8kP.hu9i0sjdCXbsMsSUu3SLmsRA9spz/w3N.', NULL, 0, 'sample_activation_token_503073', 'medium', '#ffffff', 'light');

INSERT INTO `labels` (`id`, `user_id`, `name`) VALUES
(1, 1, 'Đồ án Web 503073'),
(2, 1, 'Quan trọng'),
(3, 1, 'Cá nhân'),
(4, 2, 'Học tập');

INSERT INTO `notes` (`id`, `user_id`, `title`, `content`, `color`, `is_pinned`, `pinned_at`, `password_hash`, `created_at`, `updated_at`) VALUES
(1, 1, 'Kế hoạch hoàn thành Đồ án Lập trình Web', '1. Kiểm tra đủ 28/32 tiêu chí trong Rubric.\n2. Giao diện lấy cảm hứng từ Craft trên Mobbin.\n3. Tự động lưu ngay khi gõ, không dùng nút Save.', '#eef4ff', 1, '2026-10-31 09:00:00', NULL, '2026-10-31 08:30:00', '2026-10-31 09:00:00'),
(2, 1, 'Tài liệu nhóm chia sẻ với Bob', 'Ghi chú này được Alice chia sẻ cho Bob với quyền Chỉnh sửa (Edit). Bob có thể cập nhật nội dung trực tiếp.', '#ecfdf5', 1, '2026-10-31 10:00:00', NULL, '2026-10-31 09:30:00', '2026-10-31 10:00:00'),
(3, 1, 'Ghi chú bảo mật có khóa mật khẩu', 'Đây là nội dung bí mật chỉ hiện ra sau khi nhập đúng mật khẩu của ghi chú: SecretNote123.', '#fef9ec', 0, NULL, '$2y$12$1wyV55/8RUlOnVtGb0ZWOOdXA0cMGl/zIcYFaPNkYsBWyYnQofAY6', '2026-10-31 11:00:00', '2026-10-31 11:00:00'),
(4, 1, 'Biên bản họp nhóm (Chỉ đọc cho Bob)', 'Ghi chú này chia sẻ cho Bob với quyền Chỉ đọc (Read-only).', '#f5eeff', 0, NULL, NULL, '2026-10-31 12:00:00', '2026-10-31 12:00:00'),
(5, 2, 'Ghi chú riêng của Bob', 'Nội dung ôn tập PHP & MySQL PDO Prepared Statements.', '#ffffff', 0, NULL, NULL, '2026-10-31 13:00:00', '2026-10-31 13:00:00');

INSERT INTO `note_labels` (`note_id`, `label_id`) VALUES
(1, 1),
(1, 2),
(2, 1),
(3, 3),
(5, 4);

INSERT INTO `note_shares` (`id`, `note_id`, `owner_id`, `recipient_id`, `permission`, `is_notified`, `shared_at`) VALUES
(1, 2, 1, 2, 'edit', 1, '2026-10-31 10:15:00'),
(2, 4, 1, 2, 'read', 1, '2026-10-31 12:15:00');

SET FOREIGN_KEY_CHECKS = 1;
