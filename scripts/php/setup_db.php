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
        $stmt = $conn->prepare("SELECT email FROM `login` WHERE email = ?");
        $stmt->bind_param("s", $user['email']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO `login` (email, passwd, userid, role) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $user['email'], $user['passwd'], $user['userid'], $user['role']);
            $insert->execute();
            echo "Seeded user: {$user['email']} ({$user['role']})" . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
        } else {
            // Update the role to make sure it matches
            $update = $conn->prepare("UPDATE `login` SET role = ? WHERE email = ?");
            $update->bind_param("ss", $user['role'], $user['email']);
            $update->execute();
            echo "Updated role for existing user: {$user['email']} to {$user['role']}" . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
        }
    }

    // New Tables for Features
    $tables = [
        "course_resources" => "CREATE TABLE IF NOT EXISTS `course_resources` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cid` INT NOT NULL,
            `teacher_email` VARCHAR(80) NOT NULL,
            `resource_type` VARCHAR(10) NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `title` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_cr_cid` (`cid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "assignments" => "CREATE TABLE IF NOT EXISTS `assignments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cid` INT NOT NULL,
            `teacher_email` VARCHAR(80) NOT NULL,
            `title` VARCHAR(100) NOT NULL,
            `week_number` INT NOT NULL,
            `due_date` DATE NOT NULL,
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
            `score` INT NOT NULL,
            `total` INT NOT NULL,
            `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sa_asn` (`assignment_id`),
            KEY `idx_sa_email` (`semail`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "mock_tests" => "CREATE TABLE IF NOT EXISTS `mock_tests` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cid` INT NOT NULL,
            `teacher_email` VARCHAR(80) NOT NULL,
            `title` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_mt_cid` (`cid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "mock_test_questions" => "CREATE TABLE IF NOT EXISTS `mock_test_questions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `test_id` INT UNSIGNED NOT NULL,
            `question_text` TEXT NOT NULL,
            `option_a` VARCHAR(255) NOT NULL,
            `option_b` VARCHAR(255) NOT NULL,
            `option_c` VARCHAR(255) NOT NULL,
            `option_d` VARCHAR(255) NOT NULL,
            `correct_option` CHAR(1) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_mtq_test` (`test_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "mock_test_results" => "CREATE TABLE IF NOT EXISTS `mock_test_results` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `test_id` INT UNSIGNED NOT NULL,
            `semail` VARCHAR(80) NOT NULL,
            `score` INT NOT NULL,
            `total` INT NOT NULL,
            `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_mtr_test` (`test_id`),
            KEY `idx_mtr_email` (`semail`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tables as $name => $query) {
        $conn->query($query);
        echo "Created or verified '$name' table." . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    }

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

    // 5. Seed student details
    $mockStudents = [
        ['sname' => 'Riya Sharma', 'semail' => 'riya@example.com', 'cid' => 101, 'cname' => 'Data Analytics', 'duration' => '12 weeks', 'start_date' => '2026-06-01', 'end_date' => '2026-08-24', 'fees' => 12000],
        ['sname' => 'Arjun Mehta', 'semail' => 'arjun@example.com', 'cid' => 102, 'cname' => 'Python Foundations', 'duration' => '8 weeks', 'start_date' => '2026-06-15', 'end_date' => '2026-08-10', 'fees' => 9000],
        ['sname' => 'Maya Rao', 'semail' => 'maya@example.com', 'cid' => 103, 'cname' => 'UX Research', 'duration' => '6 weeks', 'start_date' => '2026-07-01', 'end_date' => '2026-08-12', 'fees' => 7500],
        ['sname' => 'Dev Patel', 'semail' => 'dev@example.com', 'cid' => 104, 'cname' => 'Cloud Security', 'duration' => '10 weeks', 'start_date' => '2026-06-01', 'end_date' => '2026-08-10', 'fees' => 14500],
        ['sname' => 'Sara Khan', 'semail' => 'sara@example.com', 'cid' => 101, 'cname' => 'Data Analytics', 'duration' => '12 weeks', 'start_date' => '2026-06-01', 'end_date' => '2026-08-24', 'fees' => 12000],
        ['sname' => 'Rahul Kumar', 'semail' => 'student@GrepMny.com', 'cid' => 101, 'cname' => 'Data Analytics', 'duration' => '12 weeks', 'start_date' => '2026-06-01', 'end_date' => '2026-08-24', 'fees' => 12000],
        ['sname' => 'Rahul Kumar', 'semail' => 'student@GrepMny.com', 'cid' => 102, 'cname' => 'Python Foundations', 'duration' => '8 weeks', 'start_date' => '2026-06-15', 'end_date' => '2026-08-10', 'fees' => 9000],
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
