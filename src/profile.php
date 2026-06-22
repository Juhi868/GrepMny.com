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

try {
    $conn = db();
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Fetch Profile Photo
$photo_path = '../media/profiles/' . $userid . '.jpg';
$photo_url = file_exists(__DIR__ . '/' . $photo_path) ? $photo_path . '?v=' . time() : 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>';

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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
  <style>
    .profile-header {
      display: flex;
      gap: 2rem;
      align-items: center;
      padding: 2rem;
      background: var(--surface);
      border-radius: var(--radius);
      box-shadow: 0 4px 6px rgba(0,0,0,0.05);
      margin-bottom: 2rem;
    }
    .profile-photo {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      background: var(--line);
      border: 3px solid var(--primary);
    }
    .profile-info h1 {
      margin: 0 0 0.5rem 0;
      font-size: 1.75rem;
    }
    .profile-info p {
      margin: 0 0 0.25rem 0;
      color: var(--muted);
    }
    .photo-upload-form {
      margin-top: 1rem;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }
    .section-title {
      border-bottom: 2px solid var(--line);
      padding-bottom: 0.5rem;
      margin-bottom: 1.5rem;
    }
    .btn-danger {
      background: #e11d48;
      color: white;
      border: none;
    }
    .btn-danger:hover {
      background: #be123c;
    }
  </style>
</head>
<body>
  <header class="site-header">
    <nav class="nav-wrap">
      <a class="brand-mark" href="./dashboard.php">
        <span>GM</span>
        <strong>GrepMny</strong>
      </a>
      <div class="site-menu always-visible">
        <a href="./dashboard.php">Dashboard</a>
        <a href="../scripts/php/logout.php" style="color:var(--danger)">Sign Out</a>
      </div>
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle></button>
    </nav>
  </header>

  <main style="max-width: 900px; margin: 3rem auto; padding: 0 1rem;">
    <div class="profile-header">
      <div>
        <img src="<?php echo $photo_url; ?>" alt="Profile Photo" class="profile-photo">
        <form action="../scripts/php/upload_photo.php" method="post" enctype="multipart/form-data" class="photo-upload-form">
          <input type="file" name="profile_photo" accept="image/*" required style="font-size: 0.8rem; width: 180px;">
          <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Update Photo</button>
        </form>
      </div>
      <div class="profile-info">
        <h1><?php echo htmlspecialchars($username); ?></h1>
        <p><strong>User ID:</strong> <?php echo htmlspecialchars($userid); ?></p>
        <p><strong>Role:</strong> <span style="text-transform: capitalize;"><?php echo htmlspecialchars($role); ?></span></p>
      </div>
    </div>

    <?php if ($role === 'student'): ?>
    <div style="margin-bottom: 3rem;">
      <h2 class="section-title">My Courses</h2>
      <?php if (empty($my_courses)): ?>
        <p style="color:var(--muted)">You are not enrolled in any courses.</p>
      <?php else: ?>
        <div style="display:grid; gap:1rem; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));">
          <?php foreach ($my_courses as $c): ?>
            <div style="padding:1rem; border:1px solid var(--line); border-radius:var(--radius); background:var(--surface);">
              <h3 style="margin:0 0 0.5rem 0; font-size:1.1rem;"><?php echo htmlspecialchars($c['cname']); ?> (<?php echo $c['cid']; ?>)</h3>
              <p style="margin:0; font-size:0.9rem; color:var(--muted);">Started: <?php echo htmlspecialchars($c['start_date']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div style="margin-bottom: 3rem;">
      <h2 class="section-title">Progress Report</h2>
      <?php if (empty($my_assignments)): ?>
        <p style="color:var(--muted)">No progress data available.</p>
      <?php else: ?>
        <table style="width:100%; border-collapse:collapse; text-align:left;">
          <thead>
            <tr style="border-bottom:2px solid var(--line);">
              <th style="padding:0.5rem;">Assignment</th>
              <th style="padding:0.5rem;">Due Date</th>
              <th style="padding:0.5rem;">Status</th>
              <th style="padding:0.5rem;">Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($my_assignments as $a): ?>
            <tr style="border-bottom:1px solid var(--line);">
              <td style="padding:0.5rem;"><strong><?php echo htmlspecialchars($a['title']); ?></strong></td>
              <td style="padding:0.5rem;"><?php echo htmlspecialchars($a['due_date']); ?></td>
              <td style="padding:0.5rem;"><?php echo htmlspecialchars($a['status'] ?? 'Pending'); ?></td>
              <td style="padding:0.5rem; font-weight:bold;"><?php echo $a['score'] !== null ? htmlspecialchars((string)$a['score']) . '/100' : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="margin-bottom: 3rem; padding: 2rem; border: 1px solid #fca5a5; border-radius: var(--radius); background: #fef2f2;">
      <h2 style="margin:0 0 0.5rem 0; color:#b91c1c;">Danger Zone</h2>
      <p style="margin:0 0 1.5rem 0; color:#991b1b;">Permanently delete your account and all associated data. This action cannot be undone.</p>
      <form action="../scripts/php/delete_account.php" method="post" onsubmit="return confirm('Are you completely sure you want to delete your account? This is irreversible.');">
        <button type="submit" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 1rem; cursor: pointer; border-radius: var(--radius);">Delete Account</button>
      </form>
    </div>
  </main>
  <script src="../assets/js/app.js" defer></script>
</body>
</html>
