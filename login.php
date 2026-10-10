<?php
// MyAttenda V2 — Unified Login Portal
$roleParam = $_GET['role'] ?? 'lecturer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In | MyAttenda V2</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <main class="login-page">
    <!-- Aside Branding -->
    <section class="login-aside">
      <a href="/" class="brand-lockup">
        <span class="brand-mark">⚡</span>
        <span class="brand-name">MyAttenda</span>
        <span class="brand-version">V2</span>
      </a>

      <div class="login-aside-content">
        <span class="eyebrow">Academic Attendance Intelligence</span>
        <h1>Your institution, in sync.</h1>
        <p>
          Sign in to your scoped workspace. Lecturers see strictly their assigned ESP32 device, active semester courses, students, and attendance reports.
        </p>
      </div>

      <span class="login-meta">MYATTENDA V2 / ROLE-BASED ACCESS CONTROL</span>
    </section>

    <!-- Main Login Card -->
    <section class="login-main">
      <div class="login-card">
        <span class="eyebrow"><?= htmlspecialchars(strtoupper($roleParam)) ?> PORTAL</span>
        <h2>Welcome back.</h2>
        <p class="login-card-intro">
          Use the credentials issued by your administrator. New accounts are provisioned via Super Admin.
        </p>

        <div id="error-box" class="form-error" style="display: none; margin-bottom: 1rem;"></div>

        <form class="form-stack" id="login-form">
          <div class="field">
            <label for="email">Work Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required placeholder="you@institution.edu">
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password">
          </div>

          <button type="submit" id="submit-btn" class="button button-primary">
            Sign In →
          </button>
        </form>

        <div style="margin-top: 1.5rem; padding: 0.9rem; background: var(--surface); border: 1px dashed var(--border); border-radius: var(--radius-md); font-size: 0.76rem; color: var(--muted-foreground);">
          <strong>💡 Development Quick Login:</strong><br>
          Admin: <code>admin@test.com</code> / <code>password123</code><br>
          <small>(Ensure this user exists in your MariaDB <code>users</code> table)</small>
        </div>

        <p class="login-footer">
          Need access? Contact your institution's MyAttenda administrator. 
          <br><br>
          <a href="/" class="button-quiet">← Return to Home</a>
        </p>
      </div>
    </section>
  </main>

  <script>
    const form = document.getElementById('login-form');
    const errorBox = document.getElementById('error-box');
    const submitBtn = document.getElementById('submit-btn');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorBox.style.display = 'none';
      submitBtn.disabled = true;
      submitBtn.innerText = 'Authenticating...';

      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;

      try {
        const response = await fetch('/api/v1/auth/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (data.success) {
          submitBtn.innerText = 'Success! Redirecting...';
          if (data.user.role === 'admin') {
            window.location.href = '/admin/index.php';
          } else {
            window.location.href = '/dashboard/index.php';
          }
        } else {
          errorBox.innerText = data.message || data.error || 'Invalid credentials.';
          errorBox.style.display = 'block';
          submitBtn.disabled = false;
          submitBtn.innerText = 'Sign In →';
        }
      } catch (err) {
        errorBox.innerText = 'Unable to connect to the backend server. Please make sure the PHP server is active on port 8000.';
        errorBox.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.innerText = 'Sign In →';
      }
    });
  </script>
</body>
</html>
