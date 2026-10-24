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
  <style>
    .role-pills {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
      background: var(--muted);
      padding: 0.35rem;
      border-radius: var(--radius-md);
    }
    .role-pill {
      flex: 1;
      text-align: center;
      padding: 0.55rem 0.75rem;
      border-radius: var(--radius-sm);
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--muted-foreground);
      text-decoration: none;
      transition: all 0.15s ease;
    }
    .role-pill.active {
      background: var(--surface-raised);
      color: var(--foreground);
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }
    .password-field-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .password-field-wrapper input {
      padding-right: 4rem;
    }
    .pw-toggle-btn {
      position: absolute;
      right: 0.75rem;
      background: none;
      border: none;
      color: var(--brand);
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      padding: 0.25rem 0.5rem;
    }
    .pw-toggle-btn:hover {
      opacity: 0.85;
    }
  </style>
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
        <!-- Role Switching Pills -->
        <div class="role-pills">
          <a href="?role=lecturer" class="role-pill <?= $roleParam !== 'admin' ? 'active' : '' ?>">
            👨‍🏫 Lecturer
          </a>
          <a href="?role=admin" class="role-pill <?= $roleParam === 'admin' ? 'active' : '' ?>">
            ⚡ Super Admin
          </a>
        </div>

        <span class="eyebrow"><?= htmlspecialchars(strtoupper($roleParam)) ?> ACCESS</span>
        <h2>Welcome back.</h2>
        <p class="login-card-intro">
          <?= $roleParam === 'admin' 
              ? 'Enter administrator credentials to manage platform hardware, lecturers, and system audits.' 
              : 'Sign in to access your assigned ESP32 device, courses, and attendance reports.' ?>
        </p>

        <div id="error-box" class="form-error" style="display: none; margin-bottom: 1rem;"></div>

        <form class="form-stack" id="login-form">
          <div class="field">
            <label for="email">Work Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required placeholder="you@institution.edu">
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="password-field-wrapper">
              <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password">
              <button type="button" class="pw-toggle-btn" id="pw-toggle" onclick="togglePasswordVisibility()">Show</button>
            </div>
          </div>

          <button type="submit" id="submit-btn" class="button button-primary" style="width: 100%;">
            Sign In →
          </button>
        </form>

        <div style="margin-top: 1.5rem; padding: 0.85rem 1rem; background: var(--surface); border: 1px dashed var(--border); border-radius: var(--radius-md); font-size: 0.76rem; color: var(--muted-foreground);">
          <strong>💡 System Credentials:</strong><br>
          Super Admin: <code>admin@test.com</code> / <code>password123</code><br>
          <span style="opacity: 0.85; font-size: 0.72rem;">Lecturer accounts: Created and managed in Super Admin portal.</span>
        </div>

        <p class="login-footer">
          Need an account? Contact your institution's MyAttenda administrator. 
          <br><br>
          <a href="/" class="button-quiet">← Return to Home</a>
        </p>
      </div>
    </section>
  </main>

  <script>
    function togglePasswordVisibility() {
      const pwInput = document.getElementById('password');
      const toggleBtn = document.getElementById('pw-toggle');
      if (pwInput.type === 'password') {
        pwInput.type = 'text';
        toggleBtn.innerText = 'Hide';
      } else {
        pwInput.type = 'password';
        toggleBtn.innerText = 'Show';
      }
    }

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
        errorBox.innerText = 'Unable to connect to the backend server. Please verify your connection.';
        errorBox.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.innerText = 'Sign In →';
      }
    });
  </script>
</body>
</html>