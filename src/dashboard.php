<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../scripts/php/config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../index.html');
    exit;
}

$username = $_SESSION['username'];
$userid = $_SESSION['userid'];
$role = $_SESSION['role'] ?? 'student';

// Connect to DB
try {
    $conn = db();
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// ----------------------------------------------------
// Data queries depending on role
// ----------------------------------------------------

$total_students = 0;
$total_courses = 0;
$total_revenue = 0;
$average_fees = 0.0;
$course_stats = [];
$recent_registrations = [];
$all_users = [];
$all_teacher_mappings = [];

$teacher_courses = [];
$total_teacher_students = 0;
$total_teacher_revenue = 0;
$teacher_roster = [];

$student_enrollments = [];
$total_student_fees = 0;
$active_student_courses = 0;

if ($role === 'superadmin' || $role === 'admin') {
    // Global stats
    $tot_students_q = $conn->query("SELECT COUNT(DISTINCT semail) as count FROM `student details`")->fetch_assoc();
    $total_students = (int) ($tot_students_q['count'] ?? 0);

    $tot_courses_q = $conn->query("SELECT COUNT(DISTINCT cid) as count FROM `student details`")->fetch_assoc();
    $total_courses = (int) ($tot_courses_q['count'] ?? 0);

    $tot_rev_q = $conn->query("SELECT SUM(fees) as total FROM `student details`")->fetch_assoc();
    $total_revenue = (float) ($tot_rev_q['total'] ?? 0);

    $avg_fees_q = $conn->query("SELECT AVG(fees) as avg FROM `student details`")->fetch_assoc();
    $average_fees = (float) ($avg_fees_q['avg'] ?? 0);

    // Course distribution for charts
    $cs_query = $conn->query("SELECT cname, COUNT(*) as student_count, SUM(fees) as revenue FROM `student details` GROUP BY cid, cname");
    while ($row = $cs_query->fetch_assoc()) {
        $course_stats[] = $row;
    }

    // Recent registrations
    $rr_query = $conn->query("SELECT * FROM `student details` ORDER BY id DESC LIMIT 10");
    while ($row = $rr_query->fetch_assoc()) {
        $recent_registrations[] = $row;
    }

    // Teacher course mappings
    $mappings_query = $conn->query("SELECT * FROM course_teachers ORDER BY cid ASC");
    while ($row = $mappings_query->fetch_assoc()) {
        $all_teacher_mappings[] = $row;
    }

    // Superadmin only: Users list
    if ($role === 'superadmin') {
        $users_query = $conn->query("SELECT email, userid, role FROM login ORDER BY role DESC, email ASC");
        while ($row = $users_query->fetch_assoc()) {
            $all_users[] = $row;
        }
    }
} elseif ($role === 'teacher') {
    // Assigned courses
    $tc_stmt = $conn->prepare("SELECT * FROM course_teachers WHERE teacher_email = ? ORDER BY cid ASC");
    $tc_stmt->bind_param("s", $username);
    $tc_stmt->execute();
    $tc_res = $tc_stmt->get_result();
    while ($row = $tc_res->fetch_assoc()) {
        $teacher_courses[] = $row;
    }

    $teacher_cids = array_column($teacher_courses, 'cid');
    if (!empty($teacher_cids)) {
        $in_clause = implode(',', array_fill(0, count($teacher_cids), '?'));
        
        // Total Students in teacher's courses
        $ts_stmt = $conn->prepare("SELECT COUNT(*) as count FROM `student details` WHERE cid IN ($in_clause)");
        $types = str_repeat('i', count($teacher_cids));
        $ts_stmt->bind_param($types, ...$teacher_cids);
        $ts_stmt->execute();
        $total_teacher_students = (int) ($ts_stmt->get_result()->fetch_assoc()['count'] ?? 0);

        // Total Revenue in teacher's courses
        $tr_stmt = $conn->prepare("SELECT SUM(fees) as total FROM `student details` WHERE cid IN ($in_clause)");
        $tr_stmt->bind_param($types, ...$teacher_cids);
        $tr_stmt->execute();
        $total_teacher_revenue = (float) ($tr_stmt->get_result()->fetch_assoc()['total'] ?? 0);

        // Roster of students in teacher's courses
        $roster_stmt = $conn->prepare("SELECT * FROM `student details` WHERE cid IN ($in_clause) ORDER BY start_date DESC");
        $roster_stmt->bind_param($types, ...$teacher_cids);
        $roster_stmt->execute();
        $roster_res = $roster_stmt->get_result();
        while ($row = $roster_res->fetch_assoc()) {
            $teacher_roster[] = $row;
        }
    }
} elseif ($role === 'student') {
    // Student enrollments matching semail
    $se_stmt = $conn->prepare("SELECT * FROM `student details` WHERE semail = ? ORDER BY start_date DESC");
    $se_stmt->bind_param("s", $username);
    $se_stmt->execute();
    $se_res = $se_stmt->get_result();
    while ($row = $se_res->fetch_assoc()) {
        $student_enrollments[] = $row;
    }

    foreach ($student_enrollments as $enrollment) {
        $total_student_fees += (int) $enrollment['fees'];
        $today = date('Y-m-d');
        if ($today >= $enrollment['start_date'] && $today <= $enrollment['end_date']) {
            $active_student_courses++;
        }
    }
}

// NEW FEATURES DATA FETCHING
$all_resources = [];
$all_tests = [];
$test_summary = ['total' => 0, 'attempts' => 0, 'avg_score' => 0.0, 'pending_review' => 0];
$all_gaps = [];
$student_names = [];
$student_display_name = $userid ?: $username;

$student_names_query = $conn->query("SELECT semail, MAX(sname) as sname FROM `student details` GROUP BY semail");
while ($row = $student_names_query->fetch_assoc()) {
    if (!empty($row['semail']) && !empty($row['sname'])) {
        $student_names[$row['semail']] = $row['sname'];
    }
}
$student_display_name = $student_names[$username] ?? $student_display_name;


if ($role === 'superadmin' || $role === 'admin') {
    $res_query = $conn->query("SELECT * FROM course_resources ORDER BY created_at DESC");
    while ($r = $res_query->fetch_assoc()) $all_resources[] = $r;

    $test_query = $conn->query("SELECT mt.*, COUNT(DISTINCT r.id) AS attempts, AVG(CASE WHEN r.total > 0 THEN (r.score / r.total) * 100 END) AS avg_pct, SUM(CASE WHEN r.status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review FROM mock_tests mt LEFT JOIN mock_test_results r ON r.test_id = mt.id GROUP BY mt.id ORDER BY mt.created_at DESC");
    while ($r = $test_query->fetch_assoc()) $all_tests[] = $r;
    $summary_query = $conn->query("SELECT COUNT(DISTINCT mt.id) AS total, COUNT(r.id) AS attempts, AVG(CASE WHEN r.total > 0 THEN (r.score / r.total) * 100 END) AS avg_score, SUM(CASE WHEN r.status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review FROM mock_tests mt LEFT JOIN mock_test_results r ON r.test_id = mt.id");
    $test_summary = array_merge($test_summary, $summary_query->fetch_assoc() ?: []);

    $gap_query = $conn->query("SELECT * FROM student_gaps ORDER BY start_date DESC");
    while ($r = $gap_query->fetch_assoc()) $all_gaps[] = $r;


} elseif ($role === 'teacher') {
    // Teachers can see all uploaded resources
    $res_query = $conn->query("SELECT * FROM course_resources ORDER BY created_at DESC");
    while ($r = $res_query->fetch_assoc()) $all_resources[] = $r;

    $test_stmt = $conn->prepare("SELECT mt.*, COUNT(DISTINCT r.id) AS attempts, AVG(CASE WHEN r.total > 0 THEN (r.score / r.total) * 100 END) AS avg_pct, SUM(CASE WHEN r.status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review FROM mock_tests mt LEFT JOIN mock_test_results r ON r.test_id = mt.id WHERE mt.teacher_email = ? GROUP BY mt.id ORDER BY mt.created_at DESC");
    $test_stmt->bind_param("s", $username);
    $test_stmt->execute();
    $test_res = $test_stmt->get_result();
    while ($r = $test_res->fetch_assoc()) $all_tests[] = $r;
    $summary_stmt = $conn->prepare("SELECT COUNT(DISTINCT mt.id) AS total, COUNT(r.id) AS attempts, AVG(CASE WHEN r.total > 0 THEN (r.score / r.total) * 100 END) AS avg_score, SUM(CASE WHEN r.status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review FROM mock_tests mt LEFT JOIN mock_test_results r ON r.test_id = mt.id WHERE mt.teacher_email = ?");
    $summary_stmt->bind_param("s", $username);
    $summary_stmt->execute();
    $test_summary = array_merge($test_summary, $summary_stmt->get_result()->fetch_assoc() ?: []);
    
    // Gaps (all gaps visible to everyone)
    $gap_query = $conn->query("SELECT * FROM student_gaps ORDER BY start_date DESC");
    while ($r = $gap_query->fetch_assoc()) $all_gaps[] = $r;
} elseif ($role === 'student') {
    $student_cids = array_unique(array_column($student_enrollments, 'cid'));
    if (!empty($student_cids)) {
        $in_clause = implode(',', array_fill(0, count($student_cids), '?'));
        $types = str_repeat('i', count($student_cids));
        
        $res_stmt = $conn->prepare("SELECT * FROM course_resources WHERE cid IN ($in_clause) ORDER BY created_at DESC");
        $res_stmt->bind_param($types, ...$student_cids);
        $res_stmt->execute();
        $res = $res_stmt->get_result();
        while ($r = $res->fetch_assoc()) $all_resources[] = $r;

        $test_stmt = $conn->prepare("SELECT mt.*, mtr.score, mtr.total, mtr.status AS result_status, mtr.submitted_at,
            (SELECT COUNT(*) + 1 FROM mock_test_results r2 WHERE r2.test_id = mt.id AND r2.score > COALESCE(mtr.score, -1)) AS student_rank,
            (SELECT COUNT(*) FROM mock_test_results r3 WHERE r3.test_id = mt.id) AS attempts
            FROM mock_tests mt
            LEFT JOIN mock_test_results mtr ON mt.id = mtr.test_id AND mtr.semail = ?
            WHERE mt.cid IN ($in_clause)
            AND (mt.assigned_students IS NULL OR mt.assigned_students = '' OR FIND_IN_SET(?, REPLACE(mt.assigned_students, ' ', '')) > 0)
            ORDER BY COALESCE(mt.starts_at, mt.created_at) DESC");
        $test_bind_params = array_merge([$username], $student_cids, [$username]);
        $test_bind_types = 's' . $types . 's';
        $test_stmt->bind_param($test_bind_types, ...$test_bind_params);
        $test_stmt->execute();
        $test_res = $test_stmt->get_result();
        while ($r = $test_res->fetch_assoc()) $all_tests[] = $r;
    }

    // Gaps (all gaps visible to everyone)
    $gap_query = $conn->query("SELECT * FROM student_gaps ORDER BY start_date DESC");
    while ($r = $gap_query->fetch_assoc()) $all_gaps[] = $r;
}

$student_available_tests = [];
$student_test_summary = ['total' => 0, 'available' => 0, 'completed' => 0];
if ($role === 'student') {
    $nowTs = time();
    foreach ($all_tests as $test_item) {
        $student_test_summary['total']++;
        $isSubmitted = isset($test_item['score']) && $test_item['score'] !== null;
        if ($isSubmitted) {
            $student_test_summary['completed']++;
            continue;
        }
        $notOpen = !empty($test_item['starts_at']) && $nowTs < strtotime((string)$test_item['starts_at']);
        $closed = !empty($test_item['ends_at']) && $nowTs > strtotime((string)$test_item['ends_at']);
        if (!$notOpen && !$closed) {
            $student_available_tests[] = $test_item;
            $student_test_summary['available']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-page="dashboard">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="GrepMny Role-Based Dashboard and Analytics Workspace.">
  <meta name="theme-color" content="#f7f4ed">
  <title>GrepMny | Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
  <style>
    /* Dashboard specific layout and wow elements */
    .dashboard-grid {
      display: grid;
      grid-template-columns: 240px 1fr;
      gap: 2rem;
      width: min(1180px, calc(100% - 2rem));
      margin: 2rem auto;
    }

    @media (max-width: 768px) {
      .dashboard-grid {
        grid-template-columns: 1fr;
      }
    }

    .sidebar {
      background: var(--surface-strong);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 1.5rem;
      height: fit-content;
      box-shadow: var(--shadow);
      position: sticky;
      top: 6rem;
    }

    .sidebar-user {
      text-align: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--line);
    }

    .sidebar-user h4 {
      margin: 0.5rem 0 0.25rem;
      font-size: 1rem;
      font-weight: 700;
      word-break: break-all;
    }

    .sidebar-user p {
      margin: 0;
      font-size: 0.8rem;
      color: var(--muted);
    }

    .sidebar-nav {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      border-radius: var(--radius);
      color: var(--muted);
      text-decoration: none;
      font-weight: 700;
      cursor: pointer;
      background: transparent;
      border: none;
      text-align: left;
      width: 100%;
      font-family: inherit;
      transition: background 0.15s, color 0.15s;
    }

    .sidebar-link:hover, 
    .sidebar-link.is-active {
      background: var(--accent-soft);
      color: var(--text);
    }

    .main-content {
      display: flex;
      flex-direction: column;
      gap: 2rem;
    }

    .dashboard-card {
      background: var(--surface-strong);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .role-badge {
      display: inline-block;
      padding: 0.2rem 0.5rem;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .role-superadmin { background: #fee2e2; color: #991b1b; }
    .role-admin { background: #e0f2fe; color: #075985; }
    .role-teacher { background: #fef3c7; color: #92400e; }
    .role-student { background: #dcfce7; color: #166534; }

    /* SVG and Chart styles */
    .chart-container {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin: 1rem 0;
    }

    @media (max-width: 900px) {
      .chart-container {
        grid-template-columns: 1fr;
      }
    }

    .chart-box {
      background: var(--surface-strong);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 1.25rem;
      box-shadow: var(--shadow);
    }

    .chart-box h3 {
      margin: 0 0 1rem 0;
      font-size: 1.1rem;
      font-weight: 700;
    }

    .svg-bar {
      transition: opacity 0.2s, transform 0.2s;
      transform-origin: bottom;
      cursor: pointer;
    }

    .svg-bar:hover {
      opacity: 0.85;
      transform: scaleY(1.02);
    }

    /* Table styling */
    .data-table-wrapper {
      overflow-x: auto;
      margin-top: 0.5rem;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    .data-table th, 
    .data-table td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid var(--line);
    }

    .data-table th {
      font-weight: 800;
      color: var(--muted);
      background: color-mix(in srgb, var(--surface) 60%, transparent);
    }

    .data-table tr:hover td {
      background: color-mix(in srgb, var(--surface) 30%, transparent);
    }

    /* Progress bar layout */
    .progress-bar-container {
      background: var(--line);
      height: 10px;
      border-radius: 5px;
      overflow: hidden;
      margin-top: 0.5rem;
      position: relative;
    }

    .progress-bar-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--primary), var(--accent));
      border-radius: 5px;
      transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Helper styling */
    .select-field {
      height: 2.25rem;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      background: var(--surface-strong);
      color: var(--text);
      padding: 0 0.5rem;
      font-size: 0.85rem;
      font-family: inherit;
    }

    .form-row-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
      gap: 1rem;
      align-items: end;
    }

    .form-row-grid .field {
      min-width: 0;
      margin-bottom: 0;
    }

    .form-row-grid .field input,
    .form-row-grid .field select,
    .form-row-grid .field textarea {
      box-sizing: border-box;
      width: 100%;
      min-width: 0;
    }

    .form-row-grid input[type="date"],
    .form-row-grid input[type="datetime-local"] {
      appearance: auto;
      font-size: 0.95rem;
      line-height: 1.2;
      padding-right: 0.75rem;
    }

    .assignment-form {
      grid-template-columns:
        minmax(7rem, 0.7fr)
        minmax(12rem, 1.2fr)
        max-content;
    }

    @media (max-width: 980px) {
      .assignment-form {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .assignment-form .btn {
        width: 100%;
      }
    }

    @media (max-width: 560px) {
      .assignment-form {
        grid-template-columns: 1fr;
      }
    }

    .tab-pane {
      display: none;
      animation: fadeIn 0.3s ease;
    }

    .tab-pane.is-active {
      display: flex;
      flex-direction: column;
      gap: 2rem;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .site-header .nav-wrap {
      min-height: 4.5rem;
    }
  </style>
  <script>
    // Theme initializer block
    const root = document.documentElement;
    const storedTheme = localStorage.getItem("GrepMny-theme");
    const preferredDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    const initialTheme = storedTheme || (preferredDark ? "dark" : "light");
    root.dataset.theme = initialTheme;
  </script>
</head>
<body>
  <a class="skip-link" href="#main-dashboard-content">Skip to content</a>
  <header class="site-header compact-header" data-header>
    <nav class="nav-wrap" aria-label="Primary navigation">
      <a class="brand-mark" href="./dashboard.php" aria-label="GrepMny home">
        <span>GM</span>
        <strong>GrepMny</strong>
      </a>
      <div class="site-menu always-visible">
        <a href="./grepMny.html">About Workspace</a>
        <a href="./profile.php">View Profile</a>
        <a href="../scripts/php/logout.php" style="color:var(--danger)">Sign Out</a>
      </div>
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle></button>
    </nav>
  </header>

  <main id="main-dashboard-content" class="dashboard-grid">
    <aside class="sidebar">
      <div class="sidebar-user">
        <span class="role-badge role-<?php echo $role; ?>"><?php echo $role; ?></span>
        <h4><?php echo htmlspecialchars($userid); ?></h4>
        <p><?php echo htmlspecialchars($username); ?></p>
      </div>
      <nav class="sidebar-nav" role="tablist">
        <button class="sidebar-link is-active" data-tab-trigger="overview" role="tab" aria-selected="true">
          Overview
        </button>
        <?php if ($role === 'superadmin'): ?>
          <button class="sidebar-link" data-tab-trigger="roles" role="tab" aria-selected="false">
            User Roles
          </button>
        <?php endif; ?>
        <?php if ($role === 'superadmin' || $role === 'admin'): ?>
          <button class="sidebar-link" data-tab-trigger="mappings" role="tab" aria-selected="false">
            Course Teachers
          </button>
          <button class="sidebar-link" data-tab-trigger="registry" role="tab" aria-selected="false">
            Student Registry
          </button>
        <?php elseif ($role === 'teacher'): ?>
          <button class="sidebar-link" data-tab-trigger="roster" role="tab" aria-selected="false">
            My Student Roster
          </button>
        <?php elseif ($role === 'student'): ?>
          <button class="sidebar-link" data-tab-trigger="progress" role="tab" aria-selected="false">
            My Courses & Progress
          </button>
        <?php endif; ?>
        <button class="sidebar-link" data-tab-trigger="resources" role="tab" aria-selected="false">Course Resources</button>
        <button class="sidebar-link" data-tab-trigger="tests" role="tab" aria-selected="false">Tests</button>
        <button class="sidebar-link" data-tab-trigger="gaps" role="tab" aria-selected="false">Gap Tracking</button>

      </nav>
    </aside>

    <section class="main-content">
      <!-- Alerts & Notices -->
      <div class="alert" role="status" aria-live="polite" data-alert></div>

      <!-- ---------------------------------------------------- -->
      <!-- OVERVIEW TAB -->
      <!-- ---------------------------------------------------- -->
      <div id="tab-overview" class="tab-pane is-active" role="tabpanel">
        <div class="dashboard-card">
          <div>
            <p class="eyebrow"><?php echo ucfirst($role); ?> Workspace</p>
            <h1 style="margin:0; letter-spacing:-0.04em;">Performance Analytics</h1>
          </div>
        </div>

        <?php if ($role === 'superadmin' || $role === 'admin'): ?>
          <!-- Global Metrics Card Grid -->
          <div class="metric-grid" aria-label="Global metrics">
            <div>
              <strong><?php echo $total_students; ?></strong>
              <span>Registered Students</span>
            </div>
            <div>
              <strong><?php echo $total_courses; ?></strong>
              <span>Unique Courses</span>
            </div>
            <div>
              <strong>₹<?php echo number_format($total_revenue); ?></strong>
              <span>Total Course Revenue</span>
            </div>
          </div>

          <!-- Charts -->
          <div class="chart-container">
            <!-- Course Enrollment distribution Bar Chart -->
            <div class="chart-box">
              <h3>Students per Course</h3>
              <?php if (empty($course_stats)): ?>
                <p style="color:var(--muted); font-size:0.9rem;">No data registered yet.</p>
              <?php else: ?>
                <?php
                $max_students = 1;
                foreach ($course_stats as $stat) {
                    if ((int)$stat['student_count'] > $max_students) {
                        $max_students = (int)$stat['student_count'];
                    }
                }
                ?>
                <svg viewBox="0 0 500 240" style="width:100%; height:auto;">
                  <line x1="40" y1="40" x2="480" y2="40" stroke="var(--line)" stroke-dasharray="4" />
                  <line x1="40" y1="110" x2="480" y2="110" stroke="var(--line)" stroke-dasharray="4" />
                  <line x1="40" y1="180" x2="480" y2="180" stroke="var(--line)" />
                  
                  <?php 
                  $bar_width = 36;
                  $spacing = 65;
                  $start_x = 60;
                  foreach ($course_stats as $index => $stat): 
                      if ($index >= 6) break; // Limit to first 6 courses for visual space
                      $x = $start_x + ($index * $spacing);
                      $height = ((int)$stat['student_count'] / $max_students) * 120;
                      $y = 180 - $height;
                  ?>
                      <rect x="<?php echo $x; ?>" y="<?php echo $y; ?>" width="<?php echo $bar_width; ?>" height="<?php echo $height; ?>" rx="4" fill="url(#barGrad)" class="svg-bar" />
                      <text x="<?php echo $x + ($bar_width / 2); ?>" y="<?php echo $y - 6; ?>" text-anchor="middle" font-size="10" font-weight="bold" fill="var(--text)"><?php echo $stat['student_count']; ?></text>
                      <text x="<?php echo $x + ($bar_width / 2); ?>" y="200" text-anchor="middle" font-size="9" font-weight="700" fill="var(--muted)" transform="rotate(-12, <?php echo $x + ($bar_width / 2); ?>, 200)"><?php echo htmlspecialchars(substr($stat['cname'], 0, 8)); ?>..</text>
                  <?php endforeach; ?>
                  <defs>
                    <linearGradient id="barGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                      <stop offset="0%" stop-color="var(--primary)" />
                      <stop offset="100%" stop-color="var(--accent)" />
                    </linearGradient>
                  </defs>
                </svg>
              <?php endif; ?>
            </div>

            <!-- Revenue Breakdown Progress Chart -->
            <div class="chart-box">
              <h3>Revenue by Course</h3>
              <?php if (empty($course_stats)): ?>
                <p style="color:var(--muted); font-size:0.9rem;">No data registered yet.</p>
              <?php else: ?>
                <?php
                $max_revenue = 1.0;
                foreach ($course_stats as $stat) {
                    if ((float)$stat['revenue'] > $max_revenue) {
                        $max_revenue = (float)$stat['revenue'];
                    }
                }
                ?>
                <div style="display:flex; flex-direction:column; gap: 0.85rem; margin-top: 0.5rem;">
                  <?php foreach ($course_stats as $index => $stat): 
                      if ($index >= 5) break;
                      $pct = ($stat['revenue'] / $max_revenue) * 100;
                  ?>
                    <div>
                      <div style="display:flex; justify-content:space-between; font-size:0.8rem; font-weight:700; margin-bottom:0.2rem;">
                        <span><?php echo htmlspecialchars($stat['cname']); ?></span>
                        <span>₹<?php echo number_format((float)$stat['revenue']); ?></span>
                      </div>
                      <div style="background:var(--line); height:8px; border-radius:4px; overflow:hidden;">
                        <div style="background:linear-gradient(90deg, var(--primary), var(--success)); width:<?php echo $pct; ?>%; height:100%; border-radius:4px;"></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Recent Registrations -->
          <div class="dashboard-card">
            <h3>Recent Student Registrations</h3>
            <div class="data-table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Duration</th>
                    <th>Fees</th>
                    <th>Start Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recent_registrations)): ?>
                    <tr>
                      <td colspan="6" style="text-align:center; color:var(--muted);">No student records found. Add some from the Student Registry page.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($recent_registrations as $r): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($r['sname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['semail']); ?></td>
                        <td><?php echo htmlspecialchars($r['cname']); ?> <small style="color:var(--muted)">(ID: <?php echo $r['cid']; ?>)</small></td>
                        <td><?php echo htmlspecialchars($r['duration']); ?></td>
                        <td>₹<?php echo number_format((float)$r['fees']); ?></td>
                        <td><?php echo htmlspecialchars($r['start_date']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        <?php elseif ($role === 'teacher'): ?>
          <!-- Teacher Stats Grid -->
          <div class="metric-grid" aria-label="Teacher metrics">
            <div>
              <strong><?php echo count($teacher_courses); ?></strong>
              <span>Assigned Courses</span>
            </div>
            <div>
              <strong><?php echo $total_teacher_students; ?></strong>
              <span>Enrolled Students</span>
            </div>
            <div>
              <strong>₹<?php echo number_format($total_teacher_revenue); ?></strong>
              <span>Tuition Revenues</span>
            </div>
          </div>

          <!-- Courses Taught List -->
          <div class="dashboard-card">
            <h3>My Assigned Courses</h3>
            <div class="data-table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Course ID</th>
                    <th>Course Name</th>
                    <th>Enrolled Students</th>
                    <th>Course Revenue</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($teacher_courses)): ?>
                    <tr>
                      <td colspan="4" style="text-align:center; color:var(--muted);">No courses currently assigned to you. Contact your administrator.</td>
                    </tr>
                  <?php else: ?>
                    <?php 
                    foreach ($teacher_courses as $tc): 
                        // Query student stats for this course
                        $stat_stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(fees) as rev FROM `student details` WHERE cid = ?");
                        $stat_stmt->bind_param("i", $tc['cid']);
                        $stat_stmt->execute();
                        $c_stats = $stat_stmt->get_result()->fetch_assoc();
                        $c_students = $c_stats['count'] ?? 0;
                        $c_revenue = $c_stats['rev'] ?? 0;
                    ?>
                      <tr>
                        <td><code><?php echo $tc['cid']; ?></code></td>
                        <td><strong><?php echo htmlspecialchars($tc['cname']); ?></strong></td>
                        <td><?php echo $c_students; ?> students</td>
                        <td>₹<?php echo number_format((float)$c_revenue); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        <?php elseif ($role === 'student'): ?>
          <!-- Student Stats Grid -->
          <div class="metric-grid" aria-label="Student metrics">
            <div>
              <strong><?php echo count($student_enrollments); ?></strong>
              <span>Enrolled Courses</span>
            </div>
            <div>
              <strong><?php echo $active_student_courses; ?></strong>
              <span>Active Courses</span>
            </div>
            <div>
              <strong><?php echo $student_test_summary['available']; ?></strong>
              <span>Tests Available</span>
            </div>
            <div>
              <strong>₹<?php echo number_format($total_student_fees); ?></strong>
              <span>Total Fees Paid</span>
            </div>
          </div>

          <!-- Available Tests -->
          <div class="dashboard-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
              <div>
                <h3 style="margin:0 0 0.35rem;">My Tests</h3>
                <p class="description" style="margin:0;">Take assigned assessments for your enrolled courses.</p>
              </div>
              <button type="button" class="btn btn-secondary" data-tab-trigger="tests">View All Tests</button>
            </div>

            <?php if (empty($all_tests)): ?>
              <p style="color:var(--muted); margin:0;">No tests have been assigned to your courses yet.</p>
            <?php elseif (empty($student_available_tests)): ?>
              <p style="color:var(--muted); margin:0;">No tests are open right now. Completed or upcoming tests are listed under the Tests tab.</p>
            <?php else: ?>
              <div style="display:flex; flex-direction:column; gap:0.85rem;">
                <?php foreach ($student_available_tests as $test_item): ?>
                  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; padding:1rem 1.15rem; border:1px solid var(--line); border-radius:var(--radius); background:color-mix(in srgb, var(--surface) 30%, transparent);">
                    <div>
                      <strong><?php echo htmlspecialchars($test_item['title']); ?></strong>
                      <small style="display:block; color:var(--muted); margin-top:0.3rem;">
                        Course <?php echo (int)$test_item['cid']; ?>
                        · <?php echo (int)($test_item['duration_minutes'] ?? 30); ?> min
                        · Pass <?php echo (int)($test_item['pass_percentage'] ?? 40); ?>%
                        <?php if (!empty($test_item['ends_at'])): ?>
                          · Ends <?php echo htmlspecialchars((string)$test_item['ends_at']); ?>
                        <?php endif; ?>
                      </small>
                    </div>
                    <a href="take_mcq.php?test_id=<?php echo (int)$test_item['id']; ?>" class="btn btn-primary">Take Test</a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Student Enrolled Courses -->
          <div class="dashboard-card">
            <h3>My Registrations</h3>
            <div class="data-table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Course Name</th>
                    <th>Duration</th>
                    <th>Fees Paid</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Course Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($student_enrollments)): ?>
                    <tr>
                      <td colspan="6" style="text-align:center; color:var(--muted);">You are not registered in any courses yet.</td>
                    </tr>
                  <?php else: ?>
                    <?php 
                    foreach ($student_enrollments as $se): 
                        $today = date('Y-m-d');
                        if ($today < $se['start_date']) {
                            $status = 'Upcoming';
                            $statusClass = 'role-teacher';
                        } elseif ($today > $se['end_date']) {
                            $status = 'Completed';
                            $statusClass = 'role-student';
                        } else {
                            $status = 'Active';
                            $statusClass = 'role-admin';
                        }
                    ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($se['cname']); ?></strong> <small style="color:var(--muted)">(ID: <?php echo $se['cid']; ?>)</small></td>
                        <td><?php echo htmlspecialchars($se['duration']); ?></td>
                        <td>₹<?php echo number_format((float)$se['fees']); ?></td>
                        <td><?php echo htmlspecialchars($se['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($se['end_date']); ?></td>
                        <td><span class="role-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- ---------------------------------------------------- -->
      <!-- USER ROLES TAB (Superadmin Only) -->
      <!-- ---------------------------------------------------- -->
      <?php if ($role === 'superadmin'): ?>
        <div id="tab-roles" class="tab-pane" role="tabpanel">
          <div class="dashboard-card">
            <h3>User Role Manager</h3>
            <p class="description">Review registered users and upgrade/downgrade their system access roles.</p>
            
            <label class="field compact" style="margin-bottom:1rem;">
              <span>Filter Users</span>
              <input type="text" placeholder="Search by email or user ID..." id="user-role-search">
            </label>

            <div class="data-table-wrapper">
              <table class="data-table" id="users-role-table">
                <thead>
                  <tr>
                    <th>User ID</th>
                    <th>Email Address</th>
                    <th>Access Role</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($all_users as $u): ?>
                    <tr class="user-row" data-search-text="<?php echo htmlspecialchars(strtolower($u['userid'] . ' ' . $u['email'])); ?>">
                      <td><strong><?php echo htmlspecialchars($u['userid']); ?></strong></td>
                      <td><?php echo htmlspecialchars($u['email']); ?></td>
                      <td>
                        <form action="../scripts/php/manage_roles.php" method="post" style="display:inline-flex; gap:0.5rem; align-items:center;">
                          <input type="hidden" name="action" value="update_role">
                          <input type="hidden" name="email" value="<?php echo htmlspecialchars($u['email']); ?>">
                          <select name="role" class="select-field">
                            <option value="student" <?php echo $u['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo $u['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="superadmin" <?php echo $u['role'] === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
                          </select>
                          <button type="submit" class="btn btn-secondary" style="min-height:auto; padding:0.35rem 0.75rem; font-size:0.8rem; font-weight:800;">Update</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ---------------------------------------------------- -->
      <!-- COURSE TEACHERS TAB (Superadmin & Admin) -->
      <!-- ---------------------------------------------------- -->
      <?php if ($role === 'superadmin' || $role === 'admin'): ?>
        <div id="tab-mappings" class="tab-pane" role="tabpanel">
          <div class="dashboard-card">
            <h3>Assign Course Teacher</h3>
            <form action="../scripts/php/manage_roles.php" method="post" class="form-row-grid">
              <input type="hidden" name="action" value="assign_teacher">
              
              <label class="field compact">
                <span>Teacher Email</span>
                <input type="email" name="teacher_email" placeholder="teacher@example.com" required>
              </label>

              <label class="field compact">
                <span>Course ID</span>
                <input type="number" name="cid" min="1" placeholder="101" required>
              </label>

              <label class="field compact">
                <span>Course Name</span>
                <input type="text" name="cname" placeholder="Full Stack Web Dev" required>
              </label>

              <button class="btn btn-primary" type="submit" style="min-height:3rem;">Map Course</button>
            </form>
          </div>

          <div class="dashboard-card">
            <h3>Course-Teacher Assignments</h3>
            <div class="data-table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Course ID</th>
                    <th>Course Name</th>
                    <th>Teacher Email</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($all_teacher_mappings)): ?>
                    <tr>
                      <td colspan="4" style="text-align:center; color:var(--muted);">No teacher assignments found. Map a course above to start.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($all_teacher_mappings as $mapping): ?>
                      <tr>
                        <td><code><?php echo $mapping['cid']; ?></code></td>
                        <td><strong><?php echo htmlspecialchars($mapping['cname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($mapping['teacher_email']); ?></td>
                        <td>
                          <form action="../scripts/php/manage_roles.php" method="post" style="display:inline;" onsubmit="return confirm('Remove this course-teacher mapping?');">
                            <input type="hidden" name="action" value="remove_teacher">
                            <input type="hidden" name="id" value="<?php echo $mapping['id']; ?>">
                            <button type="submit" class="btn btn-secondary" style="min-height:auto; padding:0.35rem 0.75rem; font-size:0.8rem; font-weight:800; border-color:var(--danger); color:var(--danger);">Remove</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ---------------------------------------------------- -->
        <!-- STUDENT REGISTRY TAB (Superadmin & Admin) -->
        <!-- ---------------------------------------------------- -->
        <div id="tab-registry" class="tab-pane" role="tabpanel">
          <div class="dashboard-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
              <div>
                <h3>Student Course Registry Database</h3>
                <p class="description">View, audit, and clean course registration documents directly.</p>
              </div>
              <a href="./data.html" class="btn btn-primary" style="min-height:auto; padding:0.6rem 1.25rem;">Register New Course</a>
            </div>

            <div class="data-table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Student Name</th>
                    <th>Email Address</th>
                    <th>Course Details</th>
                    <th>Fees</th>
                    <th>Start / End Date</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  // Query all student records
                  $all_students_q = $conn->query("SELECT * FROM `student details` ORDER BY start_date DESC");
                  if ($all_students_q->num_rows === 0): 
                  ?>
                    <tr>
                      <td colspan="6" style="text-align:center; color:var(--muted);">No student records found.</td>
                    </tr>
                  <?php else: ?>
                    <?php while ($s = $all_students_q->fetch_assoc()): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($s['sname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['semail']); ?></td>
                        <td>
                          <strong><?php echo htmlspecialchars($s['cname']); ?></strong><br>
                          <small style="color:var(--muted);">Course ID: <?php echo $s['cid']; ?> | Duration: <?php echo htmlspecialchars($s['duration']); ?></small>
                        </td>
                        <td>₹<?php echo number_format((float)$s['fees']); ?></td>
                        <td>
                          <span style="font-size:0.8rem;"><?php echo htmlspecialchars($s['start_date']); ?> to <?php echo htmlspecialchars($s['end_date']); ?></span>
                        </td>
                        <td>
                          <form action="../scripts/php/manage_roles.php" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this student record?');">
                            <input type="hidden" name="action" value="delete_student">
                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                            <button type="submit" class="btn btn-secondary" style="min-height:auto; padding:0.35rem 0.75rem; font-size:0.8rem; font-weight:800; border-color:var(--danger); color:var(--danger);">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ---------------------------------------------------- -->
      <!-- TEACHER ROSTER TAB (Teacher Only) -->
      <!-- ---------------------------------------------------- -->
      <?php if ($role === 'teacher'): ?>
        <div id="tab-roster" class="tab-pane" role="tabpanel">
          <div class="dashboard-card">
            <h3>My Course Enrollments</h3>
            <p class="description">The student roster for courses assigned to you.</p>

            <div class="data-table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Student Name</th>
                    <th>Email Address</th>
                    <th>Course Taught</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Duration</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($teacher_roster)): ?>
                    <tr>
                      <td colspan="6" style="text-align:center; color:var(--muted);">No students are currently registered in your courses.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($teacher_roster as $student): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($student['sname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($student['semail']); ?></td>
                        <td><strong><?php echo htmlspecialchars($student['cname']); ?></strong> <small style="color:var(--muted)">(ID: <?php echo $student['cid']; ?>)</small></td>
                        <td><?php echo htmlspecialchars($student['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($student['end_date']); ?></td>
                        <td><?php echo htmlspecialchars($student['duration']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ---------------------------------------------------- -->
      <!-- STUDENT PROGRESS TAB (Student Only) -->
      <!-- ---------------------------------------------------- -->
      <?php if ($role === 'student'): ?>
        <div id="tab-progress" class="tab-pane" role="tabpanel">
          <div class="dashboard-card">
            <h3>My Course Progress Meters</h3>
            <p class="description">Visual tracking of time elapsed vs total duration for active courses.</p>

            <?php if (empty($student_enrollments)): ?>
              <p style="color:var(--muted);">You have no enrolled courses.</p>
            <?php else: ?>
              <div style="display:flex; flex-direction:column; gap:1.5rem; margin-top:1rem;">
                <?php 
                foreach ($student_enrollments as $enrollment): 
                    $start_t = strtotime($enrollment['start_date']);
                    $end_t = strtotime($enrollment['end_date']);
                    $now_t = time();
                    
                    $total_days = ($end_t - $start_t) / 86400;
                    if ($total_days <= 0) $total_days = 1;

                    $elapsed_days = ($now_t - $start_t) / 86400;
                    if ($now_t < $start_t) {
                        $pct = 0;
                        $days_text = "Starts in " . ceil(abs($elapsed_days)) . " days";
                    } elseif ($now_t > $end_t) {
                        $pct = 100;
                        $days_text = "Completed";
                    } else {
                        $pct = round(($elapsed_days / $total_days) * 100);
                        $days_left = ceil(($end_t - $now_t) / 86400);
                        $days_text = "Active · " . $days_left . " days remaining (" . $pct . "% elapsed)";
                    }
                ?>
                  <div class="dashboard-card" style="padding:1.25rem; gap:0.5rem; background:color-mix(in srgb, var(--surface) 30%, transparent);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                      <div>
                        <h4 style="margin:0; font-size:1.1rem;"><?php echo htmlspecialchars($enrollment['cname']); ?></h4>
                        <span style="font-size:0.8rem; color:var(--muted);">Duration: <?php echo htmlspecialchars($enrollment['duration']); ?> | Dates: <?php echo htmlspecialchars($enrollment['start_date']); ?> to <?php echo htmlspecialchars($enrollment['end_date']); ?></span>
                      </div>
                      <span class="role-badge <?php echo $pct >= 100 ? 'role-student' : ($pct > 0 ? 'role-admin' : 'role-teacher'); ?>"><?php echo $days_text; ?></span>
                    </div>
                    <div class="progress-bar-container">
                      <div class="progress-bar-fill" style="width: <?php echo $pct; ?>%;"></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
      <!-- ---------------------------------------------------- -->
      <!-- COURSE RESOURCES TAB -->
      <!-- ---------------------------------------------------- -->
      <div id="tab-resources" class="tab-pane" role="tabpanel">
        <div class="dashboard-card">
          <h3>Course Resources</h3>
          <p class="description">Learning materials, video lectures, and notes.</p>
          
          <?php if ($role === 'teacher'): ?>
            <form action="../scripts/php/manage_resources.php" method="post" enctype="multipart/form-data" class="form-row-grid" style="margin-bottom:1.5rem; border-bottom:1px solid var(--line); padding-bottom:1.5rem;">
              <input type="hidden" name="action" value="add_resource">
              <label class="field compact">
                <span>Course ID</span>
                <input type="number" name="cid" required>
              </label>
              <label class="field compact">
                <span>Resource Title</span>
                <input type="text" name="title" required>
              </label>
              <label class="field compact">
                <span>Type (e.g. PDF, Video)</span>
                <input type="text" name="type" required>
              </label>
              <label class="field compact">
                <span>URL / Link (or leave blank)</span>
                <input type="url" name="url">
              </label>
              <label class="field compact">
                <span>Upload PDF/Video</span>
                <input type="file" name="resource_file" accept=".pdf,video/*">
              </label>
              <button class="btn btn-primary" type="submit">Add Resource</button>
            </form>
          <?php endif; ?>

          <div class="data-table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Course ID</th>
                  <th>Title</th>
                  <th>Type</th>
                  <th>Link</th>
                  <?php if ($role === 'teacher'): ?><th>Actions</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($all_resources)): ?>
                  <tr><td colspan="5" style="text-align:center; color:var(--muted);">No resources found.</td></tr>
                <?php else: foreach ($all_resources as $r): ?>
                  <tr>
                    <td><code><?php echo $r['cid']; ?></code></td>
                    <td><strong><?php echo htmlspecialchars($r['title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['type']); ?></td>
                    <td><a href="<?php echo htmlspecialchars($r['url']); ?>" target="_blank" style="color:var(--primary)">View Material</a></td>
                    <?php if ($role === 'teacher'): ?>
                      <td>
                        <form action="../scripts/php/manage_resources.php" method="post" onsubmit="return confirm('Delete this resource?');">
                          <input type="hidden" name="action" value="delete_resource">
                          <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                          <button type="submit" class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.8rem; border-color:var(--danger); color:var(--danger);">Delete</button>
                        </form>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ---------------------------------------------------- -->
      <!-- TESTS TAB -->
      <!-- ---------------------------------------------------- -->
      <div id="tab-tests" class="tab-pane" role="tabpanel">
        <div class="dashboard-card">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:0.5rem;">
            <div>
              <h3 style="margin:0 0 0.35rem;">Tests</h3>
              <p class="description" style="margin:0;">Schedule tests, attempt assigned assessments, and review performance history.</p>
            </div>
            <?php if ($role === 'student' && !empty($student_available_tests)): ?>
              <span class="role-badge role-admin"><?php echo count($student_available_tests); ?> open now</span>
            <?php endif; ?>
          </div>

          <?php if ($role === 'student'): ?>
            <?php if (!empty($student_available_tests)): ?>
              <div style="display:flex; flex-direction:column; gap:0.75rem; margin:1.25rem 0 1.5rem; padding:1rem; border:1px solid var(--line); border-radius:var(--radius); background:color-mix(in srgb, var(--primary) 6%, transparent);">
                <strong style="font-size:0.95rem;">Ready to attempt</strong>
                <div style="display:flex; flex-wrap:wrap; gap:0.65rem;">
                  <?php foreach ($student_available_tests as $test_item): ?>
                    <a href="take_mcq.php?test_id=<?php echo (int)$test_item['id']; ?>" class="btn btn-primary" style="font-size:0.85rem;">
                      Take: <?php echo htmlspecialchars($test_item['title']); ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          <?php elseif ($role !== 'student'): ?>
            <div class="metric-grid" style="margin-bottom:1.5rem;">
              <div class="metric-card"><strong><?php echo (int)($test_summary['total'] ?? 0); ?></strong><span>Total Tests</span></div>
              <div class="metric-card"><strong><?php echo (int)($test_summary['attempts'] ?? 0); ?></strong><span>Student Attempts</span></div>
              <div class="metric-card"><strong><?php echo round((float)($test_summary['avg_score'] ?? 0), 1); ?>%</strong><span>Average Score</span></div>
              <div class="metric-card"><strong><?php echo (int)($test_summary['pending_review'] ?? 0); ?></strong><span>Pending Review</span></div>
            </div>
          <?php endif; ?>

          <?php if ($role === 'teacher'): ?>
            <form action="../scripts/php/manage_mcq.php" method="post" class="form-row-grid assignment-form" style="margin-bottom:1.5rem; border-bottom:1px solid var(--line); padding-bottom:1.5rem;">
              <input type="hidden" name="action" value="create_test">
              <label class="field compact">
                <span>Course ID</span>
                <input type="number" name="cid" required>
              </label>
              <label class="field compact">
                <span>Title</span>
                <input type="text" name="title" required>
              </label>

              <label class="field compact">
                <span>Starts At</span>
                <input type="datetime-local" name="starts_at">
              </label>
              <label class="field compact">
                <span>Ends At</span>
                <input type="datetime-local" name="ends_at">
              </label>
              <label class="field compact">
                <span>Duration</span>
                <input type="number" name="duration_minutes" min="1" max="240" value="30" required>
              </label>
              <label class="field compact">
                <span>Pass %</span>
                <input type="number" name="pass_percentage" min="0" max="100" value="40" required>
              </label>
              <label class="field compact" style="grid-column:1 / -1;">
                <span>Specific Student Emails</span>
                <input type="text" name="assigned_students" placeholder="Comma-separated; leave blank for all students in course/batch">
              </label>
              <label class="field compact" style="grid-column:1 / -1;">
                <span>Description</span>
                <input type="text" name="description" placeholder="Instructions or syllabus focus">
              </label>
              <button class="btn btn-primary" type="submit">Create Test</button>
            </form>
          <?php endif; ?>

          <div class="data-table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Course ID</th>
                  <th>Test</th>
                  <th>Schedule</th>
                  <?php if ($role === 'student'): ?>
                    <th>Result</th>
                    <th>Action</th>
                  <?php else: ?>
                    <th>Analytics</th>
                    <th><?php echo $role === 'teacher' ? 'Manage' : 'Owner'; ?></th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($all_tests)): ?>
                  <tr><td colspan="5" style="text-align:center; color:var(--muted);">No tests.</td></tr>
                <?php else: foreach ($all_tests as $test_item): ?>
                  <tr>
                    <td><code><?php echo $test_item['cid']; ?></code></td>
                    <td>
                      <strong><?php echo htmlspecialchars($test_item['title']); ?></strong>

                    </td>
                    <td>
                      <small>
                        <?php echo htmlspecialchars((string)($test_item['starts_at'] ?: 'Open now')); ?><br>
                        Ends: <?php echo htmlspecialchars((string)($test_item['ends_at'] ?: 'No deadline')); ?><br>
                        <?php echo (int)($test_item['duration_minutes'] ?? 30); ?> min
                      </small>
                    </td>
                    <?php if ($role === 'student'): ?>
                      <td>
                        <?php
                          $isSubmitted = isset($test_item['score']) && $test_item['score'] !== null;
                          $pct = $isSubmitted && (int)$test_item['total'] > 0 ? round(((float)$test_item['score'] / (int)$test_item['total']) * 100, 1) : 0;
                          $passed = $isSubmitted && $pct >= (int)($test_item['pass_percentage'] ?? 40);
                        ?>
                        <?php if ($isSubmitted): ?>
                          <strong><?php echo htmlspecialchars((string)$test_item['score']); ?>/<?php echo htmlspecialchars((string)$test_item['total']); ?> (<?php echo $pct; ?>%)</strong><br>
                          <span class="role-badge <?php echo $passed ? 'role-student' : 'role-superadmin'; ?>"><?php echo $passed ? 'Pass' : 'Fail'; ?></span>
                          <small style="color:var(--muted);">Rank #<?php echo (int)($test_item['student_rank'] ?? 1); ?> · <?php echo htmlspecialchars((string)($test_item['result_status'] ?? 'Evaluated')); ?></small>
                        <?php else: ?>
                          <span class="role-badge role-admin">Available</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php
                          $nowTs = time();
                          $notOpen = !empty($test_item['starts_at']) && $nowTs < strtotime($test_item['starts_at']);
                          $closed = !empty($test_item['ends_at']) && $nowTs > strtotime($test_item['ends_at']);
                        ?>
                        <?php if (!$isSubmitted && !$notOpen && !$closed): ?>
                          <a href="take_mcq.php?test_id=<?php echo $test_item['id']; ?>" class="btn btn-primary" style="padding:0.25rem 0.5rem; font-size:0.8rem;">Take Test</a>
                        <?php elseif ($notOpen): ?>
                          <span style="color:var(--muted); font-size:0.85rem; font-weight:700;">Scheduled</span>
                        <?php elseif ($closed && !$isSubmitted): ?>
                          <span style="color:var(--danger); font-size:0.85rem; font-weight:700;">Closed</span>
                        <?php else: ?>
                          <span style="color:var(--muted); font-size:0.85rem; font-weight:700;">Completed</span>
                        <?php endif; ?>
                      </td>
                    <?php else: ?>
                      <td>
                        <?php echo (int)($test_item['attempts'] ?? 0); ?> attempts<br>
                        <small style="color:var(--muted);">Avg: <?php echo round((float)($test_item['avg_pct'] ?? 0), 1); ?>% · Review: <?php echo (int)($test_item['pending_review'] ?? 0); ?></small>
                      </td>
                      <td>
                        <?php if ($role === 'teacher'): ?>
                          <a href="manage_mcq_ui.php?test_id=<?php echo $test_item['id']; ?>" class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.8rem;">Manage</a>
                        <?php else: ?>
                          <?php echo htmlspecialchars($test_item['teacher_email'] ?? '-'); ?>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ---------------------------------------------------- -->
      <!-- GAP TRACKING TAB -->
      <!-- ---------------------------------------------------- -->
      <div id="tab-gaps" class="tab-pane" role="tabpanel">
        <div class="dashboard-card">
          <h3>Gap Tracking</h3>
          <p class="description">Academic breaks taken by students.</p>

          <?php if ($role === 'student'): ?>
          <form action="../scripts/php/manage_gaps.php" method="post" class="form-row-grid" style="margin-bottom:1.5rem; border-bottom:1px solid var(--line); padding-bottom:1.5rem;">
            <input type="hidden" name="action" value="add_gap">
            <input type="hidden" name="semail" value="<?php echo htmlspecialchars($username); ?>">
            <label class="field compact">
              <span>Student Name</span>
              <input type="text" value="<?php echo htmlspecialchars($student_display_name); ?>" readonly>
            </label>
            <label class="field compact">
              <span>Start Date</span>
              <input type="date" name="start_date" required>
            </label>
            <label class="field compact">
              <span>End Date</span>
              <input type="date" name="end_date" required>
            </label>
            <label class="field compact">
              <span>Reason</span>
              <input type="text" name="reason" required>
            </label>
            <button class="btn btn-primary" type="submit">Record Gap</button>
          </form>
          <?php endif; ?>

          <div class="data-table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Student Name</th>
                  <th>Dates</th>
                  <th>Reason</th>
                  <?php if ($role !== 'student'): ?><th>Actions</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($all_gaps)): ?>
                  <tr><td colspan="4" style="text-align:center; color:var(--muted);">No gaps recorded.</td></tr>
                <?php else: foreach ($all_gaps as $g): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($student_names[$g['semail']] ?? $g['semail']); ?></strong></td>
                    <td><?php echo htmlspecialchars($g['start_date']); ?> to <?php echo htmlspecialchars($g['end_date']); ?></td>
                    <td><?php echo htmlspecialchars($g['reason']); ?></td>
                    <?php if ($role !== 'student'): ?>
                      <td>
                        <form action="../scripts/php/manage_gaps.php" method="post" onsubmit="return confirm('Delete this gap record?');">
                          <input type="hidden" name="action" value="delete_gap">
                          <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                          <button type="submit" class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.8rem; border-color:var(--danger); color:var(--danger);">Delete</button>
                        </form>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>


    </section>
  </main>

  <footer class="site-footer">
    <div style="width: min(1180px, calc(100% - 2rem)); margin: 0 auto; display:flex; justify-content:space-between; flex-wrap:wrap; gap:1.5rem;">
      <div>
        <a class="brand-mark" href="./dashboard.php"><span>GM</span><strong>GrepMny</strong></a>
        <p>Dynamic Analytics & Role-Based Dashboard Portal.</p>
      </div>
      <div class="footer-links">
        <a href="./grepMny.html">Home</a>
        <a href="../scripts/php/logout.php">Sign Out</a>
      </div>
    </div>
  </footer>

  <script src="../assets/js/app.js" defer></script>
  <script>
    // Sidebar Tabs switching script
    document.addEventListener("DOMContentLoaded", () => {
      const triggers = document.querySelectorAll("[data-tab-trigger]");
      const panes = document.querySelectorAll(".tab-pane");

      triggers.forEach(trigger => {
        trigger.addEventListener("click", () => {
          const tabName = trigger.getAttribute("data-tab-trigger");
          
          // Set active link
          triggers.forEach(t => {
            t.classList.remove("is-active");
            t.setAttribute("aria-selected", "false");
          });
          trigger.classList.add("is-active");
          trigger.setAttribute("aria-selected", "true");

          // Set active pane
          panes.forEach(pane => {
            pane.classList.remove("is-active");
          });
          const activePane = document.getElementById("tab-" + tabName);
          if (activePane) {
            activePane.classList.add("is-active");
          }
        });
      });

      // User role filter script (Superadmin only)
      const roleSearch = document.getElementById("user-role-search");
      if (roleSearch) {
        roleSearch.addEventListener("input", () => {
          const query = roleSearch.value.trim().toLowerCase();
          const rows = document.querySelectorAll("#users-role-table .user-row");
          rows.forEach(row => {
            const searchText = row.getAttribute("data-search-text");
            row.style.display = (query === "" || searchText.includes(query)) ? "" : "none";
          });
        });
      }
    });
  </script>
</body>
</html>
