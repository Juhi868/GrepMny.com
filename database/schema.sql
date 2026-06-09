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

