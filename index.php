<?php
// MyAttenda V2 — Public Landing Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MyAttenda V2 | Portable IoT Fingerprint Attendance System</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

  <!-- Site Header -->
  <header class="site-header">
    <div class="site-header-inner">
      <a href="/" class="brand-lockup">
        <span class="brand-mark">⚡</span>
        <span class="brand-name">MyAttenda</span>
        <span class="brand-version">V2</span>
      </a>
      <nav class="site-nav">
        <a href="#platform">Platform</a>
        <a href="#workflow">How it Works</a>
        <a href="#security">Security</a>
        <a href="/docs/API.md" target="_blank">API Docs</a>
      </nav>
      <div class="header-actions">
        <a href="/login.php" class="button button-secondary">Lecturer Login</a>
        <a href="/login.php?role=admin" class="button button-primary">Admin Portal →</a>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <main>
    <section class="hero">
      <div class="hero-inner">
        <div>
          <span class="eyebrow">Connected Attendance Intelligence</span>
          <h1>Attendance that works <em>where learning happens.</em></h1>
          <p class="hero-copy">
            MyAttenda V2 connects portable ESP32 DevKit V1 ESP32 fingerprint devices to a secure, semester-isolated workspace built for the academic realities of higher education institutions.
          </p>
          <div class="hero-actions">
            <a href="/login.php" class="button button-primary">Open Lecturer Workspace →</a>
            <a href="/login.php?role=admin" class="button button-secondary">Super Admin Access</a>
          </div>
          <div class="trust-line">
            <span class="trust-dot"></span>
            Offline-first with MicroSD & DS3231 RTC · R307 Biometric Privacy Assured
          </div>
        </div>

        <!-- Interactive IoT Device Simulation Frame -->
        <div class="device-visual" aria-label="MyAttenda hardware device display">
          <div class="device-frame">
            <div class="device-top">
              <span>MYA / ESP32</span>
              <div class="device-lights">
                <i class="device-light"></i>
                <i class="device-light" style="background:#3b82f6;"></i>
              </div>
            </div>
            <div class="device-screen">
              <div class="fingerprint-icon">🔘</div>
              <strong>READY FOR ATTENDANCE</strong>
              <small id="device-clock">RTC TIME IN SYNC</small>
            </div>
            <div class="device-controls">
              <span class="control-key">1</span><span class="control-key">2</span><span class="control-key">3</span>
              <span class="control-key">4</span><span class="control-key">5</span><span class="control-key">6</span>
              <span class="control-key">7</span><span class="control-key">8</span><span class="control-key">9</span>
            </div>
          </div>

          <div class="signal-card">
            <span class="signal-icon">📡</span>
            <div>
              <strong>Device Telemetry</strong>
              <span id="api-status-badge">Checking API state...</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Platform Highlights -->
    <section class="section" id="platform">
      <div class="section-header">
        <div>
          <span class="eyebrow">Strict Hardware Isolation</span>
          <h2>A dependable record of every class.</h2>
        </div>
        <p class="section-intro">
          From the biometric device in the lecture hall to the consolidated audit report, every layer remains auditable, attributable, and resilient.
        </p>
      </div>

      <div class="feature-grid">
        <article class="feature-item">
          <span class="feature-index">01 / BIOMETRICS</span>
          <h3>Biometric by Default</h3>
          <p>Students identify themselves at the device. Raw fingerprint templates stay locked within the optical R307 sensor—never stored in the web database.</p>
        </article>
        <article class="feature-item">
          <span class="feature-index">02 / RESILIENCE</span>
          <h3>Resilient Offline Operation</h3>
          <p>Lectures continue even when campus Wi-Fi drops. The ESP32 timestamps events locally using DS3231 and synchronizes idempotently upon reconnection.</p>
        </article>
        <article class="feature-item">
          <span class="feature-index">03 / OWNERSHIP</span>
          <h3>One Device = One Lecturer</h3>
          <p>Strict boundaries ensure a lecturer sees only their assigned students, courses, device telemetry, and attendance history.</p>
        </article>
      </div>
    </section>

    <!-- Workflow Section -->
    <section class="workflow" id="workflow">
      <div class="section">
        <div class="section-header">
          <div>
            <span class="eyebrow">The Attendance Loop</span>
            <h2>From fingerprint to verified record.</h2>
          </div>
          <p class="section-intro">Effortless at the point of scanning. Normalized everywhere else.</p>
        </div>

        <div class="workflow-grid">
          <div class="workflow-step">
            <span>01</span>
            <h3>Prepare</h3>
            <p>Lecturer selects an active course and initiates an attendance session from their dashboard.</p>
          </div>
          <div class="workflow-step">
            <span>02</span>
            <h3>Identify</h3>
            <p>Student places their finger on the R307 sensor. The ESP32 matches the slot against its cached MicroSD roster.</p>
          </div>
          <div class="workflow-step">
            <span>03</span>
            <h3>Record</h3>
            <p>The DS3231 hardware clock generates a tamper-proof timestamp and logs the unique event ID.</p>
          </div>
          <div class="workflow-step">
            <span>04</span>
            <h3>Reconcile</h3>
            <p>The server validates batch records via idempotent sync, instantly refreshing the dashboard.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Security & Privacy Band -->
    <section class="section" id="security">
      <div class="privacy-band">
        <div>
          <span class="eyebrow">Built for Institutional Trust</span>
          <h2>Clear data boundaries and semester safety.</h2>
          <p class="section-intro">
            MyAttenda isolates academic sessions, ensuring closing a semester archives historical reports while wiping sensor memory for the upcoming cohort.
          </p>
        </div>
        <div class="privacy-list">
          <div class="privacy-item">
            <span>🛡️</span>
            <div>
              <strong>Historical Data Preserved</strong>
              <p>Semester reset cleans device slots and roster caches without deleting historical attendance logs.</p>
            </div>
          </div>
          <div class="privacy-item">
            <span>🔑</span>
            <div>
              <strong>Two Independent Auth Domains</strong>
              <p>Lecturers use secure web sessions. ESP32 devices use dedicated cryptographically hashed bearer keys.</p>
            </div>
          </div>
          <div class="privacy-item">
            <span>📊</span>
            <div>
              <strong>Live MariaDB Statistics</strong>
              <p>No invented figures. Telemetry, sync states, and active sessions are queried directly from real database records.</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Site Footer -->
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="brand-lockup">
        <span class="brand-mark">⚡</span>
        <span class="brand-name">MyAttenda</span>
        <span class="brand-version">V2</span>
      </div>
      <span>IoT-based Biometric Attendance Management System · Academic Edition</span>
      <span>Connected to MariaDB 10.4+ / PHP 8+</span>
    </div>
  </footer>

  <script>
    // Live health-check indicator against our PHP backend
    fetch('/api/v1/semester/current.php')
      .then(res => res.json())
      .then(data => {
        const badge = document.getElementById('api-status-badge');
        if (data.success) {
          badge.innerHTML = '<span style="color:var(--success);">● MariaDB API Live</span>';
        } else {
          badge.innerText = 'Database connecting...';
        }
      })
      .catch(() => {
        document.getElementById('api-status-badge').innerText = 'API Offline';
      });

    // Device clock simulation
    setInterval(() => {
      const now = new Date();
      document.getElementById('device-clock').innerText = now.toLocaleTimeString();
    }, 1000);
  </script>
</body>
</html>
