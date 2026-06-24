<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../scripts/php/config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../index.html');
    exit;
}

$username = $_SESSION['username'];
$userid = $_SESSION['userid'] ?? '';
$role = $_SESSION['role'] ?? 'student';
$displayName = $userid ?: $username;
$nameParts = preg_split('/[\s._@-]+/', $displayName, -1, PREG_SPLIT_NO_EMPTY);
$initials = strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . substr($nameParts[1] ?? ($nameParts[0] ?? 'U'), 0, 1));

try {
    $conn = db();
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Data fetching
$my_courses = [];
$my_assignments = [];

if ($role === 'student') {
    // Fetch Enrolled Courses
    $enr_stmt = $conn->prepare("SELECT * FROM `student details` WHERE semail = ? ORDER BY start_date DESC");
    $enr_stmt->bind_param("s", $username);
    $enr_stmt->execute();
    $res = $enr_stmt->get_result();
    while ($r = $res->fetch_assoc()) $my_courses[] = $r;
    
    // Fetch Assignments Progress
    $cids = array_unique(array_column($my_courses, 'cid'));
    if (!empty($cids)) {
        $in_clause = implode(',', array_fill(0, count($cids), '?'));
        $types = str_repeat('i', count($cids));
        
        $asn_stmt = $conn->prepare("SELECT a.title, a.due_date, sa.status, sa.score, sa.feedback FROM assignments a LEFT JOIN student_assignments sa ON a.id = sa.assignment_id AND sa.semail = ? WHERE a.cid IN ($in_clause) ORDER BY a.due_date DESC");
        $bind_params = array_merge([$username], $cids);
        $bind_types = 's' . $types;
        $asn_stmt->bind_param($bind_types, ...$bind_params);
        $asn_stmt->execute();
        $res2 = $asn_stmt->get_result();
        while ($r = $res2->fetch_assoc()) $my_assignments[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-page="profile">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GrepMny | Profile</title>
  <script>
    const root = document.documentElement;
    const storedTheme = localStorage.getItem("GrepMny-theme");
    const preferredDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    root.dataset.theme = storedTheme || (preferredDark ? "dark" : "light");
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
  <style>
    html[data-page="profile"] {
      --card: var(--surface-strong);
      --card-subtle: var(--surface);
      --line-strong: color-mix(in srgb, var(--line) 72%, var(--text));
      --green-bg: #d7fbe1;
      --green-text: #05603a;
      --yellow-bg: var(--accent-soft);
      --yellow-text: var(--accent);
      --red-bg: color-mix(in srgb, var(--danger) 14%, var(--surface-strong));
      --red-text: var(--danger);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text);
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    .profile-nav {
      background: var(--card);
      border-bottom: 0.5px solid var(--line);
    }

    .nav-inner,
    .profile-shell {
      width: min(1080px, calc(100% - 2rem));
      margin: 0 auto;
    }

    .nav-inner {
      min-height: 72px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.5rem;
    }

    .brand {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      font-weight: 800;
    }

    .brand-mark {
      width: 36px;
      height: 36px;
      border: 0.5px solid var(--line);
      border-radius: var(--radius);
      display: grid;
      place-items: center;
      background: var(--card-subtle);
      font-size: 0.8rem;
    }

    .nav-actions {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .profile-shell {
      display: grid;
      gap: 2rem;
      padding: 2rem 0;
    }

    .card {
      background: var(--card);
      border: 0.5px solid var(--line);
      border-radius: var(--radius);
      padding: 1.5rem;
    }

    .section-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .section-head h2,
    .profile-copy h1 {
      margin: 0;
      letter-spacing: 0;
    }

    .section-head h2 {
      font-size: 1.1rem;
    }

    .profile-card {
      display: flex;
      align-items: center;
      gap: 1.25rem;
    }

    .avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      border: 0.5px solid var(--line-strong);
      display: grid;
      place-items: center;
      background: var(--card-subtle);
      color: var(--text);
      font-size: 1.35rem;
      font-weight: 900;
      flex: 0 0 auto;
    }

    .profile-copy {
      min-width: 0;
    }

    .profile-copy h1 {
      font-size: 1.45rem;
      line-height: 1.2;
      overflow-wrap: anywhere;
    }

    .profile-copy p,
    .muted {
      margin: 0.35rem 0 0;
      color: var(--muted);
      font-size: 0.92rem;
    }

    .meta-row {
      display: flex;
      gap: 0.6rem;
      flex-wrap: wrap;
      margin-top: 0.75rem;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      min-height: 1.6rem;
      padding: 0.2rem 0.55rem;
      border-radius: 999px;
      font-size: 0.76rem;
      font-weight: 800;
      text-transform: capitalize;
    }

    .badge-green { background: var(--green-bg); color: var(--green-text); }
    .badge-yellow { background: var(--yellow-bg); color: var(--yellow-text); }
    .badge-red { background: var(--red-bg); color: var(--red-text); }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      min-height: 2.4rem;
      padding: 0.55rem 0.85rem;
      border: 0.5px solid var(--line-strong);
      border-radius: var(--radius);
      background: var(--card-subtle);
      color: var(--text);
      font: inherit;
      font-size: 0.9rem;
      font-weight: 800;
      cursor: pointer;
    }

    .btn-danger {
      border-color: rgba(153, 27, 27, 0.35);
      color: var(--red-text);
      background: var(--card-subtle);
    }

    .icon {
      width: 18px;
      height: 18px;
      stroke: currentColor;
      stroke-width: 2;
      fill: none;
      stroke-linecap: round;
      stroke-linejoin: round;
      flex: 0 0 auto;
    }

    .icon-sm {
      width: 14px;
      height: 14px;
    }

    .course-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
      gap: 1rem;
    }

    .course-card {
      border: 0.5px solid var(--line);
      border-radius: var(--radius);
      padding: 1rem;
      background: var(--card-subtle);
    }

    .course-card h3 {
      margin: 0;
      font-size: 1rem;
      line-height: 1.35;
    }

    .course-meta {
      display: grid;
      gap: 0.45rem;
      margin-top: 0.9rem;
      color: var(--muted);
      font-size: 0.85rem;
    }

    .table-wrap {
      overflow-x: auto;
      border: 0.5px solid var(--line);
      border-radius: var(--radius);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 640px;
      background: var(--card);
      text-align: left;
    }

    th,
    td {
      padding: 0.85rem 1rem;
      border-bottom: 0.5px solid var(--line);
      font-size: 0.9rem;
      vertical-align: middle;
    }

    th {
      color: var(--muted);
      font-size: 0.78rem;
      text-transform: uppercase;
      font-weight: 900;
    }

    tbody tr:last-child td {
      border-bottom: 0;
    }

    .empty {
      margin: 0;
      color: var(--muted);
      border: 0.5px dashed var(--line-strong);
      border-radius: var(--radius);
      padding: 1rem;
      background: var(--card-subtle);
    }

    .danger-card {
      border-color: rgba(153, 27, 27, 0.3);
    }

    .danger-card p {
      margin: 0;
      color: var(--red-text);
    }

    .danger-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }

    @media (max-width: 640px) {
      .nav-inner,
      .profile-card,
      .danger-actions {
        align-items: flex-start;
        flex-direction: column;
      }

      .nav-actions {
        justify-content: flex-start;
      }
    }
  </style>
</head>
<body>
  <?php
    function tabler_icon(string $name, string $class = 'icon'): string
    {
        $icons = [
            'dashboard' => '<path d="M4 4h6v8h-6z"/><path d="M14 4h6v4h-6z"/><path d="M14 12h6v8h-6z"/><path d="M4 16h6v4h-6z"/>',
            'logout' => '<path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/><path d="M9 12h12l-3 -3"/><path d="M18 15l3 -3"/>',
            'book' => '<path d="M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0"/><path d="M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0"/><path d="M3 6v13"/><path d="M12 6v13"/><path d="M21 6v13"/>',
            'chart' => '<path d="M3 3v18h18"/><path d="M7 16l4 -4l4 3l5 -7"/>',
            'trash' => '<path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/><path d="M9 7v-3h6v3"/>',
            'calendar' => '<path d="M4 7h16"/><path d="M10 3v4"/><path d="M14 3v4"/><path d="M5 5h14v16h-14z"/>',
            'shield' => '<path d="M12 3l8 4v5c0 5 -3.5 8 -8 9c-4.5 -1 -8 -4 -8 -9v-5z"/>',
        ];

        return '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
    }
  ?>

  <header class="profile-nav">
    <nav class="nav-inner" aria-label="Profile navigation">
      <a class="brand" href="./dashboard.php">
        <span class="brand-mark">GM</span>
        <span>GrepMny</span>
      </a>
      <div class="nav-actions">
        <a class="btn" href="./dashboard.php"><?php echo tabler_icon('dashboard', 'icon icon-sm'); ?>Dashboard</a>
        <a class="btn" href="../scripts/php/logout.php"><?php echo tabler_icon('logout', 'icon icon-sm'); ?>Sign Out</a>
        <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle></button>
      </div>
    </nav>
  </header>

  <main class="profile-shell">
    <section class="card profile-card" aria-labelledby="profile-title">
      <div class="avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
      <div class="profile-copy">
        <h1 id="profile-title"><?php echo htmlspecialchars($displayName); ?></h1>
        <p><?php echo htmlspecialchars($username); ?></p>
        <div class="meta-row">
          <span class="badge badge-green"><?php echo htmlspecialchars($role); ?></span>
          <span class="badge badge-yellow"><?php echo tabler_icon('shield', 'icon icon-sm'); ?>ID: <?php echo htmlspecialchars($userid); ?></span>
        </div>
      </div>
    </section>

    <?php if ($role === 'student'): ?>
    <section class="card" aria-labelledby="courses-title">
      <div class="section-head">
        <h2 id="courses-title"><?php echo tabler_icon('book'); ?> Courses</h2>
      </div>
      <?php if (empty($my_courses)): ?>
        <p class="empty">You are not enrolled in any courses.</p>
      <?php else: ?>
        <div class="course-grid">
          <?php foreach ($my_courses as $c): ?>
            <?php
              $today = date('Y-m-d');
              if ($today < $c['start_date']) {
                  $courseStatus = 'Upcoming';
                  $courseBadge = 'badge-yellow';
              } elseif ($today > $c['end_date']) {
                  $courseStatus = 'Completed';
                  $courseBadge = 'badge-green';
              } else {
                  $courseStatus = 'Active';
                  $courseBadge = 'badge-green';
              }
            ?>
            <article class="course-card">
              <h3><?php echo htmlspecialchars($c['cname']); ?></h3>
              <div class="meta-row">
                <span class="badge <?php echo $courseBadge; ?>"><?php echo $courseStatus; ?></span>
                <span class="badge badge-yellow">CID <?php echo htmlspecialchars((string) $c['cid']); ?></span>
              </div>
              <div class="course-meta">
                <span><?php echo tabler_icon('calendar', 'icon icon-sm'); ?> <?php echo htmlspecialchars($c['start_date']); ?> to <?php echo htmlspecialchars($c['end_date']); ?></span>
                <span><?php echo htmlspecialchars($c['duration']); ?> · ₹<?php echo number_format((float) $c['fees']); ?></span>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="progress-title">
      <div class="section-head">
        <h2 id="progress-title"><?php echo tabler_icon('chart'); ?> Progress</h2>
      </div>
      <?php if (empty($my_assignments)): ?>
        <p class="empty">No progress data available.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Assignment</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Score</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($my_assignments as $a): ?>
              <?php
                $status = $a['status'] ?? 'Pending';
                $statusClass = $status === 'Graded' ? 'badge-green' : ($status === 'Submitted' ? 'badge-yellow' : 'badge-red');
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($a['title']); ?></strong></td>
                <td><?php echo htmlspecialchars($a['due_date']); ?></td>
                <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                <td><strong><?php echo $a['score'] !== null ? htmlspecialchars((string) $a['score']) . '/100' : '-'; ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card danger-card" aria-labelledby="delete-title">
      <div class="danger-actions">
        <div>
          <div class="section-head" style="margin-bottom:0.4rem;">
            <h2 id="delete-title"><?php echo tabler_icon('trash'); ?> Delete</h2>
          </div>
          <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
        </div>
      <form action="../scripts/php/delete_account.php" method="post" onsubmit="return confirm('Are you completely sure you want to delete your account? This is irreversible.');">
          <button type="submit" class="btn btn-danger"><?php echo tabler_icon('trash', 'icon icon-sm'); ?>Delete Account</button>
      </form>
      </div>
    </section>
  </main>
  <script src="../assets/js/app.js" defer></script>
</body>
</html>
