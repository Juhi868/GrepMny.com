<?php
declare(strict_types=1);
session_start();

// If user is already logged in, skip the landing page and go straight to dashboard
if (isset($_SESSION['username'])) {
    header('Location: ./src/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-page="login">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in to GrepMny, a focused workspace for searching, organizing, and registering course data.">
  <meta name="theme-color" content="#f7f4ed">
  <title>GrepMny | Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="./assets/css/app.css" rel="stylesheet">
</head>
<body>
  <a class="skip-link" href="#login-form">Skip to login</a>
  <main class="auth-shell">
    <section class="auth-visual" aria-labelledby="brand-title">
      <a class="brand-mark" href="./src/grepMny.php" aria-label="GrepMny home">
        <span>GM</span>
        <strong>GrepMny</strong>
      </a>
      <div class="auth-copy">
        <p class="eyebrow">Search smarter</p>
        <h1 id="brand-title">A modern workspace for finding, storing, and managing useful records.</h1>
        <p>Keep student course data organized, validate submissions before they reach the database, and move through the workflow with a clean, responsive interface.</p>
      </div>
    </section>

    <section class="auth-panel" aria-labelledby="login-title">
      <div class="mode-row">
        <span>Welcome back</span>
        <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle></button>
      </div>
      <form id="login-form" class="form-card" action="./scripts/php/login.php" method="post" novalidate data-validate>
        <div class="form-heading">
          <p class="eyebrow">Secure access</p>
          <h2 id="login-title">Log in to GrepMny</h2>
          <p>Use your registered email and password to continue.</p>
        </div>

        <div class="alert" role="status" aria-live="polite" data-alert></div>

        <label class="field">
          <span>Email address</span>
          <input type="email" name="email" autocomplete="email" placeholder="you@example.com" required>
          <small></small>
        </label>

        <label class="field">
          <span>Password</span>
          <input type="password" name="password" autocomplete="current-password" minlength="6" placeholder="Enter password" required>
          <small></small>
        </label>

        <div class="split-row">
          <label class="check-field">
            <input type="checkbox" name="remember">
            <span>Remember me</span>
          </label>
          <div style="display:flex; gap:0.85rem;">
            <a href="./src/forgot.html">Forgot Password?</a>
            <a href="./src/signup.html">Create account</a>
          </div>
        </div>

        <button class="btn btn-primary" type="submit">Login</button>
      </form>
    </section>
  </main>
  <script src="./assets/js/app.js" defer></script>
</body>
</html>
