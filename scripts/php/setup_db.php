<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Allow running from command line or web browser
if (php_sapi_name() !== 'cli') {
    echo '<!DOCTYPE html><html><head><title>GrepMny DB Setup</title></head><body style="font-family: sans-serif; padding: 20px;">';
}

try {
    $conn = db();
    
    // 1. Alter login table to add role
    $result = $conn->query("SHOW COLUMNS FROM `login` LIKE 'role'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE `login` ADD COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'student'");
        echo "Added 'role' column to 'login' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    } else {
        echo "'role' column already exists in 'login' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }

    // 1b. Alter login table to add has_logged_in
    $result = $conn->query("SHOW COLUMNS FROM `login` LIKE 'has_logged_in'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE `login` ADD COLUMN `has_logged_in` TINYINT(1) NOT NULL DEFAULT 0");
        $conn->query("UPDATE `login` SET `has_logged_in` = 1"); // Mark existing users as logged in
        echo "Added 'has_logged_in' column to 'login' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    } else {
        echo "'has_logged_in' column already exists in 'login' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }

    // 2. Create course_teachers table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS `course_teachers` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `teacher_email` VARCHAR(80) NOT NULL,
        `cid` INT NOT NULL,
        `cname` VARCHAR(60) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_teacher_email` (`teacher_email`),
        KEY `idx_ct_cid` (`cid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Created or verified 'course_teachers' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");

    // 3. Seed roles
    $seedUsers = [
        ['email' => 'superadmin@GrepMny.com', 'userid' => 'superadmin', 'role' => 'superadmin', 'passwd' => password_hash('password123', PASSWORD_DEFAULT)],
        ['email' => 'admin@GrepMny.com', 'userid' => 'admin', 'role' => 'admin', 'passwd' => password_hash('password123', PASSWORD_DEFAULT)],
        ['email' => 'teacher@GrepMny.com', 'userid' => 'teacher', 'role' => 'teacher', 'passwd' => password_hash('password123', PASSWORD_DEFAULT)],
        ['email' => 'student@GrepMny.com', 'userid' => 'student', 'role' => 'student', 'passwd' => password_hash('password123', PASSWORD_DEFAULT)],
    ];

    foreach ($seedUsers as $user) {
        $stmt = $conn->prepare("SELECT email, userid FROM `login` WHERE email = ? OR userid = ? LIMIT 1");
        $stmt->bind_param("ss", $user['email'], $user['userid']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO `login` (email, passwd, userid, role) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $user['email'], $user['passwd'], $user['userid'], $user['role']);
            $insert->execute();
            echo "Seeded user: {$user['email']} ({$user['role']})" . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
        } else {
            $existing = $res->fetch_assoc();
            $update = $conn->prepare("UPDATE `login` SET role = ? WHERE email = ? OR userid = ?");
            $update->bind_param("sss", $user['role'], $existing['email'], $existing['userid']);
            $update->execute();
            echo "Updated role for existing user: {$existing['email']} to {$user['role']}" . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
        }
    }

    // New Tables for Features
    $tables = [
        "courses" => "CREATE TABLE IF NOT EXISTS `courses` (
            `cid` INT NOT NULL,
            `cname` VARCHAR(60) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`cid`),
            UNIQUE KEY `unique_course_name` (`cname`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "course_timetables" => "CREATE TABLE IF NOT EXISTS `course_timetables` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cid` INT NOT NULL,
            `batch_name` VARCHAR(80) NOT NULL DEFAULT '',
            `day_of_week` VARCHAR(20) NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `venue` VARCHAR(120) NOT NULL DEFAULT '',
            `notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tt_cid` (`cid`),
            KEY `idx_tt_batch` (`batch_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "student_gaps" => "CREATE TABLE IF NOT EXISTS `student_gaps` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `semail` VARCHAR(80) NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `reason` TEXT,
            `status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
            `reviewed_by` VARCHAR(80) DEFAULT NULL,
            `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
            `review_note` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sg_semail` (`semail`),
            KEY `idx_sg_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "course_resources" => "CREATE TABLE IF NOT EXISTS `course_resources` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cid` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `url` VARCHAR(2000) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_cr_cid` (`cid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "assignments" => "CREATE TABLE IF NOT EXISTS `assignments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cid` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `due_date` DATETIME NOT NULL,
            `type` VARCHAR(50) NOT NULL DEFAULT 'Assignment',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_asn_cid` (`cid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "assignment_questions" => "CREATE TABLE IF NOT EXISTS `assignment_questions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `assignment_id` INT UNSIGNED NOT NULL,
            `question_text` TEXT NOT NULL,
            `option_a` VARCHAR(255) NOT NULL,
            `option_b` VARCHAR(255) NOT NULL,
            `option_c` VARCHAR(255) NOT NULL,
            `option_d` VARCHAR(255) NOT NULL,
            `correct_option` CHAR(1) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_aq_asn` (`assignment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "student_assignments" => "CREATE TABLE IF NOT EXISTS `student_assignments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `assignment_id` INT UNSIGNED NOT NULL,
            `semail` VARCHAR(80) NOT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
            `score` INT DEFAULT NULL,
            `feedback` TEXT,
            `submitted_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sa_asn` (`assignment_id`),
            KEY `idx_sa_email` (`semail`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "mock_tests" => "CREATE TABLE IF NOT EXISTS `mock_tests` (
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
            KEY `idx_mt_cid` (`cid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "mock_test_questions" => "CREATE TABLE IF NOT EXISTS `mock_test_questions` (
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
            PRIMARY KEY (`id`),
            KEY `idx_mtq_test` (`test_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "mock_test_results" => "CREATE TABLE IF NOT EXISTS `mock_test_results` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `test_id` INT UNSIGNED NOT NULL,
            `semail` VARCHAR(80) NOT NULL,
            `score` DECIMAL(8,2) NOT NULL DEFAULT 0,
            `total` INT NOT NULL DEFAULT 0,
            `status` VARCHAR(30) NOT NULL DEFAULT 'Submitted',
            `feedback` TEXT,
            `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_mtr_test` (`test_id`),
            KEY `idx_mtr_email` (`semail`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "mock_test_answers" => "CREATE TABLE IF NOT EXISTS `mock_test_answers` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `result_id` INT UNSIGNED NOT NULL,
            `question_id` INT UNSIGNED NOT NULL,
            `answer_text` TEXT,
            `is_correct` TINYINT(1) DEFAULT NULL,
            `marks_awarded` DECIMAL(8,2) NOT NULL DEFAULT 0,
            `teacher_feedback` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_mta_result` (`result_id`),
            KEY `idx_mta_question` (`question_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tables as $name => $query) {
        $conn->query($query);
        echo "Created or verified '$name' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }

    $columnExists = static function (mysqli $conn, string $table, string $column): bool {
        $database = $conn->query("SELECT DATABASE() AS db_name")->fetch_assoc()['db_name'];
        $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->bind_param("sss", $database, $table, $column);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    };

    $addColumnIfMissing = static function (mysqli $conn, string $table, string $column, string $definition) use ($columnExists): void {
        if (!$columnExists($conn, $table, $column)) {
            $conn->query("ALTER TABLE `$table` ADD COLUMN $definition");
            echo "Added '$column' column to '$table' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
        }
    };

    $addColumnIfMissing($conn, 'mock_tests', 'description', '`description` TEXT AFTER `title`');
    $addColumnIfMissing($conn, 'mock_tests', 'batch_name', "`batch_name` VARCHAR(80) NOT NULL DEFAULT '' AFTER `description`");
    $addColumnIfMissing($conn, 'mock_tests', 'assigned_students', '`assigned_students` TEXT AFTER `batch_name`');
    $addColumnIfMissing($conn, 'mock_tests', 'starts_at', '`starts_at` DATETIME NULL DEFAULT NULL AFTER `assigned_students`');
    $addColumnIfMissing($conn, 'mock_tests', 'ends_at', '`ends_at` DATETIME NULL DEFAULT NULL AFTER `starts_at`');
    $addColumnIfMissing($conn, 'mock_tests', 'duration_minutes', '`duration_minutes` INT NOT NULL DEFAULT 30 AFTER `ends_at`');
    $addColumnIfMissing($conn, 'mock_tests', 'pass_percentage', '`pass_percentage` INT NOT NULL DEFAULT 40 AFTER `duration_minutes`');
    $addColumnIfMissing($conn, 'mock_test_questions', 'question_type', "`question_type` VARCHAR(30) NOT NULL DEFAULT 'mcq' AFTER `test_id`");
    $addColumnIfMissing($conn, 'mock_test_questions', 'marks', '`marks` INT NOT NULL DEFAULT 1 AFTER `correct_option`');
    $addColumnIfMissing($conn, 'mock_test_results', 'status', "`status` VARCHAR(30) NOT NULL DEFAULT 'Submitted' AFTER `total`");
    $addColumnIfMissing($conn, 'mock_test_results', 'feedback', '`feedback` TEXT AFTER `status`');
    $addColumnIfMissing($conn, 'student_gaps', 'status', "`status` VARCHAR(20) NOT NULL DEFAULT 'Pending' AFTER `reason`");
    $addColumnIfMissing($conn, 'student_gaps', 'reviewed_by', '`reviewed_by` VARCHAR(80) DEFAULT NULL AFTER `status`');
    $addColumnIfMissing($conn, 'student_gaps', 'reviewed_at', '`reviewed_at` TIMESTAMP NULL DEFAULT NULL AFTER `reviewed_by`');
    $addColumnIfMissing($conn, 'student_gaps', 'review_note', '`review_note` TEXT AFTER `reviewed_at`');
    $conn->query("ALTER TABLE `mock_test_results` MODIFY COLUMN `score` DECIMAL(8,2) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE `mock_test_results` MODIFY COLUMN `total` INT NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE `mock_test_questions` MODIFY COLUMN `option_a` VARCHAR(255) NOT NULL DEFAULT ''");
    $conn->query("ALTER TABLE `mock_test_questions` MODIFY COLUMN `option_b` VARCHAR(255) NOT NULL DEFAULT ''");
    $conn->query("ALTER TABLE `mock_test_questions` MODIFY COLUMN `option_c` VARCHAR(255) NOT NULL DEFAULT ''");
    $conn->query("ALTER TABLE `mock_test_questions` MODIFY COLUMN `option_d` VARCHAR(255) NOT NULL DEFAULT ''");
    $conn->query("ALTER TABLE `mock_test_questions` MODIFY COLUMN `correct_option` VARCHAR(255) NOT NULL DEFAULT ''");

    if (!$columnExists($conn, 'course_resources', 'type')) {
        $conn->query("ALTER TABLE `course_resources` ADD COLUMN `type` VARCHAR(50) NOT NULL DEFAULT 'Link' AFTER `title`");
        echo "Added 'type' column to 'course_resources' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }
    if (!$columnExists($conn, 'course_resources', 'url')) {
        $conn->query("ALTER TABLE `course_resources` ADD COLUMN `url` VARCHAR(2000) NOT NULL DEFAULT '' AFTER `type`");
        echo "Added 'url' column to 'course_resources' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }
    if ($columnExists($conn, 'course_resources', 'teacher_email')) {
        $conn->query("ALTER TABLE `course_resources` MODIFY COLUMN `teacher_email` VARCHAR(80) NOT NULL DEFAULT ''");
    }
    if ($columnExists($conn, 'course_resources', 'resource_type')) {
        $conn->query("ALTER TABLE `course_resources` MODIFY COLUMN `resource_type` VARCHAR(10) NOT NULL DEFAULT ''");
    }
    if ($columnExists($conn, 'course_resources', 'file_path')) {
        $conn->query("ALTER TABLE `course_resources` MODIFY COLUMN `file_path` VARCHAR(255) NOT NULL DEFAULT ''");
    }

    if (!$columnExists($conn, 'assignments', 'description')) {
        $conn->query("ALTER TABLE `assignments` ADD COLUMN `description` TEXT AFTER `title`");
        echo "Added 'description' column to 'assignments' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }
    if (!$columnExists($conn, 'assignments', 'type')) {
        $conn->query("ALTER TABLE `assignments` ADD COLUMN `type` VARCHAR(50) NOT NULL DEFAULT 'Assignment' AFTER `due_date`");
        echo "Added 'type' column to 'assignments' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }
    if ($columnExists($conn, 'assignments', 'teacher_email')) {
        $conn->query("ALTER TABLE `assignments` MODIFY COLUMN `teacher_email` VARCHAR(80) NOT NULL DEFAULT ''");
    }
    if ($columnExists($conn, 'assignments', 'week_number')) {
        $conn->query("ALTER TABLE `assignments` MODIFY COLUMN `week_number` INT NOT NULL DEFAULT 0");
    }
    $conn->query("ALTER TABLE `assignments` MODIFY COLUMN `due_date` DATETIME NOT NULL");

    if (!$columnExists($conn, 'student_assignments', 'status')) {
        $conn->query("ALTER TABLE `student_assignments` ADD COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER `semail`");
        echo "Added 'status' column to 'student_assignments' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }
    if (!$columnExists($conn, 'student_assignments', 'feedback')) {
        $conn->query("ALTER TABLE `student_assignments` ADD COLUMN `feedback` TEXT AFTER `score`");
        echo "Added 'feedback' column to 'student_assignments' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }
    if (!$columnExists($conn, 'student_assignments', 'created_at')) {
        $conn->query("ALTER TABLE `student_assignments` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added 'created_at' column to 'student_assignments' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }
    $conn->query("ALTER TABLE `student_assignments` MODIFY COLUMN `score` INT DEFAULT NULL");
    if ($columnExists($conn, 'student_assignments', 'total')) {
        $conn->query("ALTER TABLE `student_assignments` MODIFY COLUMN `total` INT DEFAULT 0");
    }
    $conn->query("ALTER TABLE `student_assignments` MODIFY COLUMN `submitted_at` TIMESTAMP NULL DEFAULT NULL");

    // Seed courses catalog from known course data
    $seedCourses = [
        ['cid' => 101, 'cname' => 'Data Analytics'],
        ['cid' => 102, 'cname' => 'Python Foundations'],
        ['cid' => 103, 'cname' => 'UX Research'],
        ['cid' => 104, 'cname' => 'Cloud Security'],
    ];
    foreach ($seedCourses as $course) {
        $stmt = $conn->prepare("INSERT INTO courses (cid, cname) VALUES (?, ?) ON DUPLICATE KEY UPDATE cname = VALUES(cname)");
        $stmt->bind_param("is", $course['cid'], $course['cname']);
        $stmt->execute();
    }
    echo "Seeded courses catalog." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");

    // Backfill courses from enrollments and teacher mappings
    $conn->query("INSERT IGNORE INTO courses (cid, cname) SELECT DISTINCT cid, cname FROM `student details`");
    $conn->query("INSERT IGNORE INTO courses (cid, cname) SELECT DISTINCT cid, cname FROM course_teachers");

    // 4. Seed mock courses and teacher assignments
    $mockTeachers = [
        ['email' => 'teacher@GrepMny.com', 'cid' => 101, 'cname' => 'Data Analytics'],
        ['email' => 'teacher@GrepMny.com', 'cid' => 102, 'cname' => 'Python Foundations'],
        ['email' => 'another_teacher@GrepMny.com', 'cid' => 103, 'cname' => 'UX Research'],
    ];

    foreach ($mockTeachers as $ct) {
        $stmt = $conn->prepare("SELECT id FROM `course_teachers` WHERE teacher_email = ? AND cid = ?");
        $stmt->bind_param("si", $ct['email'], $ct['cid']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO `course_teachers` (teacher_email, cid, cname) VALUES (?, ?, ?)");
            $insert->bind_param("sis", $ct['email'], $ct['cid'], $ct['cname']);
            $insert->execute();
            echo "Seeded course teacher mapping: {$ct['email']} for course {$ct['cid']}" . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
        }
    }

    $studentSeedEmail = 'student@GrepMny.com';
    $studentSeedStmt = $conn->prepare("SELECT email FROM `login` WHERE userid = ? OR email = ? ORDER BY email = ? DESC LIMIT 1");
    $studentSeedUserid = 'student';
    $studentSeedStmt->bind_param("sss", $studentSeedUserid, $studentSeedEmail, $studentSeedEmail);
    $studentSeedStmt->execute();
    if ($studentSeedRow = $studentSeedStmt->get_result()->fetch_assoc()) {
        $studentSeedEmail = $studentSeedRow['email'];
    }

    // 5. Seed student details
    $mockStudents = [
        ['sname' => 'Riya Sharma', 'semail' => 'riya@example.com', 'cid' => 101, 'cname' => 'Data Analytics', 'duration' => '12 weeks', 'start_date' => '2026-06-01', 'end_date' => '2026-08-24', 'fees' => 12000],
        ['sname' => 'Arjun Mehta', 'semail' => 'arjun@example.com', 'cid' => 102, 'cname' => 'Python Foundations', 'duration' => '8 weeks', 'start_date' => '2026-06-15', 'end_date' => '2026-08-10', 'fees' => 9000],
        ['sname' => 'Maya Rao', 'semail' => 'maya@example.com', 'cid' => 103, 'cname' => 'UX Research', 'duration' => '6 weeks', 'start_date' => '2026-07-01', 'end_date' => '2026-08-12', 'fees' => 7500],
        ['sname' => 'Dev Patel', 'semail' => 'dev@example.com', 'cid' => 104, 'cname' => 'Cloud Security', 'duration' => '10 weeks', 'start_date' => '2026-06-01', 'end_date' => '2026-08-10', 'fees' => 14500],
        ['sname' => 'Sara Khan', 'semail' => 'sara@example.com', 'cid' => 101, 'cname' => 'Data Analytics', 'duration' => '12 weeks', 'start_date' => '2026-06-01', 'end_date' => '2026-08-24', 'fees' => 12000],
        ['sname' => 'Rahul Kumar', 'semail' => $studentSeedEmail, 'cid' => 101, 'cname' => 'Data Analytics', 'duration' => '12 weeks', 'start_date' => '2026-06-01', 'end_date' => '2026-08-24', 'fees' => 12000],
        ['sname' => 'Rahul Kumar', 'semail' => $studentSeedEmail, 'cid' => 102, 'cname' => 'Python Foundations', 'duration' => '8 weeks', 'start_date' => '2026-06-15', 'end_date' => '2026-08-10', 'fees' => 9000],
    ];

    foreach ($mockStudents as $student) {
        $stmt = $conn->prepare("SELECT id FROM `student details` WHERE semail = ? AND cid = ?");
        $stmt->bind_param("si", $student['semail'], $student['cid']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO `student details` (sname, semail, cid, cname, duration, start_date, end_date, fees) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("ssissssi", $student['sname'], $student['semail'], $student['cid'], $student['cname'], $student['duration'], $student['start_date'], $student['end_date'], $student['fees']);
            $insert->execute();
            echo "Seeded student enrollment: {$student['sname']} for course {$student['cname']}" . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
        }
    }

    echo (php_sapi_name() === 'cli') 
        ? "\nDatabase setup completed successfully!\n" 
        : "<h3>Database setup completed successfully!</h3>";

} catch (Exception $e) {
    echo (php_sapi_name() === 'cli')
        ? "\nDatabase Migration Failed:\n" . $e->getMessage() . "\n"
        : "<h3>Database Migration Failed</h3><p>Error details: " . htmlspecialchars($e->getMessage()) . "</p>";
}

if (php_sapi_name() !== 'cli') {
    echo '</body></html>';
}
