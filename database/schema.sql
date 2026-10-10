-- MyAttenda V2 Database Schema
-- Compatible with MariaDB 10.4.x

SET FOREIGN_KEY_CHECKS=0;

-- --------------------------------------------------------
-- 1. Users (Admins & Lecturers)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'lecturer') NOT NULL DEFAULT 'lecturer',
  `full_name` VARCHAR(255) NOT NULL,
  `faculty` VARCHAR(255) DEFAULT NULL,
  `department` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  `force_password_change` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 2. Devices
-- --------------------------------------------------------
CREATE TABLE `devices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` VARCHAR(50) NOT NULL, -- e.g. MYA-ESP32-XXXXXX
  `hardware_uid` VARCHAR(100) NOT NULL, -- ESP32 chip ID
  `device_name` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
  `api_key_hash` VARCHAR(255) DEFAULT NULL,
  
  -- Telemetry Data
  `firmware_version` VARCHAR(50) DEFAULT NULL,
  `last_seen` TIMESTAMP NULL DEFAULT NULL,
  `battery_voltage` DECIMAL(4,2) DEFAULT NULL,
  `wifi_rssi` INT DEFAULT NULL,
  `sd_status` ENUM('ok', 'error', 'missing') DEFAULT NULL,
  `fingerprint_count` INT DEFAULT 0,
  `sync_status` ENUM('synced', 'pending_events', 'error') DEFAULT 'synced',
  
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_devices_device_id` (`device_id`),
  UNIQUE KEY `idx_devices_hardware_uid` (`hardware_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 3. Device Assignments
-- --------------------------------------------------------
CREATE TABLE `device_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` BIGINT UNSIGNED NOT NULL,
  `lecturer_id` BIGINT UNSIGNED NOT NULL,
  `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unassigned_at` TIMESTAMP NULL DEFAULT NULL,
  `status` ENUM('active', 'historical') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 4. Semesters
-- --------------------------------------------------------
CREATE TABLE `semesters` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL, -- e.g. 2026/2027 First Semester
  `status` ENUM('draft', 'active', 'closed', 'archived') NOT NULL DEFAULT 'draft',
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 5. Courses
-- --------------------------------------------------------
CREATE TABLE `courses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_code` VARCHAR(20) NOT NULL,
  `course_title` VARCHAR(255) NOT NULL,
  `department` VARCHAR(255) DEFAULT NULL,
  `credit_unit` INT DEFAULT NULL,
  `lecturer_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `semester_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 6. Students
-- --------------------------------------------------------
CREATE TABLE `students` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(50) NOT NULL, -- e.g. Registration/Matric number
  `full_name` VARCHAR(255) NOT NULL,
  `department` VARCHAR(255) DEFAULT NULL,
  `level` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_students_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 7. Enrollments (Semester & Course Scoped)
-- --------------------------------------------------------
CREATE TABLE `enrollments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` BIGINT UNSIGNED NOT NULL,
  `course_id` BIGINT UNSIGNED NOT NULL,
  `semester_id` BIGINT UNSIGNED NOT NULL,
  `temporary_id` VARCHAR(20) NOT NULL, -- e.g. T-001
  `status` ENUM('active', 'dropped') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_enrollment` (`course_id`, `semester_id`, `temporary_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 8. Fingerprints
-- --------------------------------------------------------
CREATE TABLE `fingerprints` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` BIGINT UNSIGNED NOT NULL,
  `device_id` BIGINT UNSIGNED NOT NULL,
  `slot_number` INT NOT NULL, -- R307 slot
  `status` ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_device_slot` (`device_id`, `slot_number`),
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 9. Attendance Sessions
-- --------------------------------------------------------
CREATE TABLE `attendance_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` BIGINT UNSIGNED NOT NULL,
  `semester_id` BIGINT UNSIGNED NOT NULL,
  `device_id` BIGINT UNSIGNED NOT NULL,
  `lecturer_id` BIGINT UNSIGNED NOT NULL,
  `session_date` DATE NOT NULL,
  `start_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` TIMESTAMP NULL DEFAULT NULL,
  `status` ENUM('active', 'completed') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 10. Attendance Records
-- --------------------------------------------------------
CREATE TABLE `attendance_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `enrollment_id` BIGINT UNSIGNED NOT NULL,
  `event_uid` VARCHAR(100) NOT NULL, -- UUID generated by device for idempotency
  `status` ENUM('present', 'late', 'excused') NOT NULL DEFAULT 'present',
  `timestamp` TIMESTAMP NOT NULL, -- from DS3231 RTC
  `synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_event` (`event_uid`),
  UNIQUE KEY `idx_unique_attendance` (`session_id`, `enrollment_id`),
  FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 11. Audit Logs
-- --------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL, -- Admin or Lecturer performing action
  `device_id` BIGINT UNSIGNED NULL, -- If action originates from device
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
