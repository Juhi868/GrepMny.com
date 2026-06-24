CREATE DATABASE IF NOT EXISTS `grepMny`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `grepMny`;

CREATE TABLE IF NOT EXISTS `login` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(80) NOT NULL,
  `passwd` VARCHAR(255) NOT NULL,
  `userid` VARCHAR(32) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'student',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_login_email` (`email`),
  UNIQUE KEY `unique_login_userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sname` VARCHAR(40) NOT NULL,
  `semail` VARCHAR(80) NOT NULL,
  `cid` INT NOT NULL,
  `cname` VARCHAR(60) NOT NULL,
  `duration` VARCHAR(20) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `fees` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_email` (`semail`),
  KEY `idx_course_id` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `course_teachers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_email` VARCHAR(80) NOT NULL,
  `cid` INT NOT NULL,
  `cname` VARCHAR(60) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_email` (`teacher_email`),
  KEY `idx_ct_cid` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `course_resources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cid` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `url` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_cid` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_gaps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `semail` VARCHAR(80) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `reason` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sg_semail` (`semail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cid` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `due_date` DATETIME NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'Assignment',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assign_cid` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `semail` VARCHAR(80) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `score` INT DEFAULT NULL,
  `feedback` TEXT,
  `submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sa_assignment` (`assignment_id`),
  KEY `idx_sa_semail` (`semail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `semail` VARCHAR(80) NOT NULL,
  `bio` TEXT,
  `attendance_pct` INT DEFAULT 100,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_profile_semail` (`semail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mcq_questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `question_text` TEXT NOT NULL,
  `option_a` VARCHAR(255) NOT NULL,
  `option_b` VARCHAR(255) NOT NULL,
  `option_c` VARCHAR(255) NOT NULL,
  `option_d` VARCHAR(255) NOT NULL,
  `correct_option` CHAR(1) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mcq_assignment` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_mcq_answers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_assignment_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `selected_option` CHAR(1) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sma_sa` (`student_assignment_id`),
  KEY `idx_sma_q` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mock_tests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cid` INT NOT NULL,
  `teacher_email` VARCHAR(80) NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `batch_name` VARCHAR(80) NOT NULL DEFAULT '',
  `assigned_students` TEXT,
  `starts_at` DATETIME NULL DEFAULT NULL,
  `ends_at` DATETIME NULL DEFAULT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 30,
  `pass_percentage` INT NOT NULL DEFAULT 40,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mt_cid` (`cid`),
  KEY `idx_mt_teacher` (`teacher_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mock_test_questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` INT UNSIGNED NOT NULL,
  `question_type` VARCHAR(30) NOT NULL DEFAULT 'mcq',
  `question_text` TEXT NOT NULL,
  `option_a` VARCHAR(255) NOT NULL DEFAULT '',
  `option_b` VARCHAR(255) NOT NULL DEFAULT '',
  `option_c` VARCHAR(255) NOT NULL DEFAULT '',
  `option_d` VARCHAR(255) NOT NULL DEFAULT '',
  `correct_option` VARCHAR(255) NOT NULL DEFAULT '',
  `marks` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mtq_test` (`test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mock_test_results` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` INT UNSIGNED NOT NULL,
  `semail` VARCHAR(80) NOT NULL,
  `score` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `total` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(30) NOT NULL DEFAULT 'Submitted',
  `feedback` TEXT,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_test_student` (`test_id`, `semail`),
  KEY `idx_mtr_test` (`test_id`),
  KEY `idx_mtr_email` (`semail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mock_test_answers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `result_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `answer_text` TEXT,
  `is_correct` TINYINT(1) DEFAULT NULL,
  `marks_awarded` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `teacher_feedback` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mta_result` (`result_id`),
  KEY `idx_mta_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
