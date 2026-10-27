<?php
// MyAttenda V2 — Super Admin Platform Portal (Zero Mock Data, 100% MariaDB)
require_once __DIR__ . '/../api/core/Auth.php';
Auth::startSession();

if (!Auth::isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /login.php?role=admin");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Platform | MyAttenda V2</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 100; place-items: center; padding: 1rem; }
    .modal-overlay.active { display: grid; }
    .modal-card { width: min(100%, 32rem); background: var(--surface-raised); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .modal-header h3 { margin: 0; font-size: 1.25rem; }
    .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--muted-foreground); }
    .action-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
    .search-input { padding: 0.6rem 0.9rem; border: 1px solid var(--border); border-radius: var(--radius-md); font-size: 0.85rem; width: 16rem; }
    .pin-digit-box {
      width: 2.8rem;
      height: 3.5rem;
      background: rgba(255, 255, 255, 0.05);
      border: 2px solid var(--border);
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      font-weight: 800;
      font-family: var(--font-mono);
      color: #60a5fa;
      letter-spacing: 0;
      transition: all 0.2s;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>

  <!-- Mobile Navigation Topbar -->
  <header class="mobile-topbar">
    <button class="mobile-menu-btn" onclick="toggleMobileSidebar()" aria-label="Toggle Navigation">
      ☰
    </button>
    <a href="/admin/index.php" class="brand-lockup">
      <span class="brand-mark">⚡</span>
      <span class="brand-name">MyAttenda</span>
      <span class="brand-version" style="color: #60a5fa;">ADMIN</span>
    </a>
    <button class="mobile-menu-btn" onclick="refreshCurrentTab()" title="Refresh Data" style="font-size: 1rem;">
      🔄
    </button>
  </header>

  <!-- Mobile Drawer Backdrop -->
  <div class="sidebar-backdrop" id="sidebar-backdrop" onclick="closeMobileSidebar()"></div>

  <div class="portal-layout">
    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <a href="/admin/index.php" class="brand-lockup">
          <span class="brand-mark">⚡</span>
          <span class="brand-name">MyAttenda</span>
          <span class="brand-version">ADMIN</span>
        </a>
        <button class="mobile-menu-btn" onclick="closeMobileSidebar()" style="background: transparent; color: #fff; font-size: 1.25rem; margin-left: auto;" aria-label="Close Navigation">
          ✕
        </button>
      </div>

      <div class="sidebar-section">
        <span class="sidebar-label">WORKSPACE</span>
        <a href="#overview" class="sidebar-link active" onclick="switchTab('overview')">📊 Overview</a>
        <a href="#lecturers" class="sidebar-link" onclick="switchTab('lecturers')">👥 Lecturers</a>
        <a href="#devices" class="sidebar-link" onclick="switchTab('devices')">📱 Devices & Telemetry</a>
        <a href="#assignments" class="sidebar-link" onclick="switchTab('assignments')">🔗 Device Assignment</a>
      </div>



      <div class="sidebar-section">
        <span class="sidebar-label">OPERATIONS</span>
        <a href="#audit" class="sidebar-link" onclick="switchTab('audit')">🛡️ Audit Center</a>
        <a href="#backups" class="sidebar-link" onclick="switchTab('backups')">💾 Database & Backups</a>
        <a href="/simulator.php" class="sidebar-link" style="color: #60a5fa; font-weight: 700;">🧪 Hardware Simulator</a>
        <a href="/docs/API.md" target="_blank" class="sidebar-link">📖 REST API Docs</a>
      </div>

      <div class="sidebar-footer">
        <span class="sidebar-label">AUTHENTICATION</span>
        <button id="logout-btn" class="sidebar-link" style="width: 100%; border: none; background: transparent; cursor: pointer; text-align: left;">
          🚪 Sign Out
        </button>
      </div>
    </aside>

    <!-- Main Workspace Area -->
    <main class="workspace">
      <header class="workspace-header">
        <div>
          <h1 id="page-title">Platform Overview</h1>
          <p>Super Admin Fleet Management & MariaDB Live Console</p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
          <button class="button button-secondary" onclick="openModal('modal-hardware-cheat-sheet')" style="border-color: #3b82f6; color: #60a5fa; font-weight: 600;">🔌 Hardware Pinout & Wiring</button>
          <span class="status-chip" id="platform-status-chip">MariaDB Connected</span>
          <button class="button button-secondary" onclick="refreshCurrentTab()">🔄 Refresh</button>
        </div>
      </header>

      <div class="workspace-content">

        <!-- ================= TAB 1: OVERVIEW ================= -->
        <div id="tab-overview" class="tab-content active">
          <div class="stat-grid">
            <div class="stat-card">
              <span class="stat-card-label">Total Lecturers</span>
              <span class="stat-card-value" id="stat-lecturers">—</span>
              <span class="stat-card-note">Verified Academic Staff</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Registered Devices</span>
              <span class="stat-card-value" id="stat-devices">—</span>
              <span class="stat-card-note" id="stat-online-devices">Loading status...</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Active Semester</span>
              <span class="stat-card-value" id="stat-semester" style="font-size: 1.15rem; word-break: break-word;">—</span>
              <span class="stat-card-note">Isolation Boundary</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Active Sessions</span>
              <span class="stat-card-value" id="stat-sessions">—</span>
              <span class="stat-card-note" id="stat-sync-status">Live Classrooms</span>
            </div>
          </div>

          <div class="dashboard-grid">
            <section class="panel">
              <div class="panel-header">
                <h3>Recent System & Security Alerts</h3>
                <span>Live MariaDB Audit Trail</span>
              </div>
              <div class="panel-body">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Action</th>
                      <th>Description</th>
                      <th>Timestamp</th>
                    </tr>
                  </thead>
                  <tbody id="overview-alerts-body">
                    <tr><td colspan="3" style="text-align: center; color: var(--muted-foreground);">Loading alerts...</td></tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section class="panel">
              <div class="panel-header">
                <h3>Quick Hardware Activation</h3>
                <span>ESP32 DevKit / ESP32</span>
              </div>
              <div class="panel-body" style="display: grid; gap: 1rem;">
                <p style="font-size: 0.8rem; color: var(--muted-foreground); margin: 0;">
                  Generate a secure 6-digit one-time PIN for hardware authentication:
                </p>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                  <div class="field" style="margin: 0;">
                    <label style="font-size: 0.78rem;">Select Registered Hardware Device</label>
                    <select id="quick-device-select" class="search-input" style="width: 100%;">
                      <option value="">Loading devices...</option>
                    </select>
                  </div>
                  <button class="button button-primary" onclick="handleQuickGeneratePin()" style="width: 100%; justify-content: center;">
                    🔑 Generate Activation PIN & Connect →
                  </button>
                </div>
                <hr style="border: 0; border-top: 1px solid var(--border);">
                <button class="button button-secondary" onclick="openModal('modal-hardware-cheat-sheet')" style="text-align: center; justify-content: center;">
                  🔌 View Pinout & Keypad Wiring
                </button>
              </div>
            </section>
          </div>
        </div>

        <!-- ================= TAB 2: LECTURERS ================= -->
        <div id="tab-lecturers" class="tab-content">
          <div class="action-bar">
            <input type="text" id="lecturer-search" placeholder="Search by name or email..." class="search-input" onkeyup="loadLecturers()">
            <button class="button button-primary" onclick="openModal('modal-add-lecturer')">+ Add Lecturer</button>
          </div>

          <section class="panel">
            <div class="panel-header">
              <h3>Lecturer Directory</h3>
              <span id="lecturers-count">0 recorded</span>
            </div>
            <div class="panel-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Department</th>
                    <th>Faculty</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="lecturers-table-body">
                  <tr><td colspan="6" style="text-align: center;">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- ================= TAB 3: DEVICES ================= -->
        <div id="tab-devices" class="tab-content">
          <div class="action-bar">
            <input type="text" id="device-search" placeholder="Filter devices..." class="search-input">
            <button class="button button-primary" onclick="openModal('modal-add-device')">+ Register Hardware Device</button>
          </div>

          <section class="panel">
            <div class="panel-header">
              <h3>ESP32 / ESP32 DevKit V1 Fleet Telemetry</h3>
              <span id="devices-fleet-count">0 recorded</span>
            </div>
            <div class="panel-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Device ID</th>
                    <th>Hardware Chip UID</th>
                    <th>Status</th>
                    <th>Assigned Lecturer</th>
                    <th>Battery</th>
                    <th>Signal (RSSI)</th>
                    <th>SD Card</th>
                    <th>Slots Used</th>
                    <th>Last Heartbeat</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="devices-fleet-body">
                  <tr><td colspan="10" style="text-align: center;">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- ================= TAB 4: ASSIGNMENTS ================= -->
        <div id="tab-assignments" class="tab-content">
          <div class="dashboard-grid">
            <section class="panel">
              <div class="panel-header">
                <h3>Assign Device to Lecturer</h3>
                <span>Rule: 1 Device = 1 Lecturer</span>
              </div>
              <div class="panel-body">
                <form class="form-stack" id="form-assign-device" onsubmit="handleAssignDevice(event)">
                  <div class="field">
                    <label>Select Device</label>
                    <select id="assign-select-device" required></select>
                  </div>
                  <div class="field">
                    <label>Select Lecturer</label>
                    <select id="assign-select-lecturer" required></select>
                  </div>
                  <button type="submit" class="button button-primary">Confirm Device Assignment →</button>
                </form>
              </div>
            </section>

            <section class="panel">
              <div class="panel-header">
                <h3>Active Hardware Pairings</h3>
                <span>Ownership Roster</span>
              </div>
              <div class="panel-body">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Device ID</th>
                      <th>Lecturer</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody id="assignment-matrix-body">
                    <tr><td colspan="3" style="text-align: center;">Loading pairings...</td></tr>
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        </div>



        <!-- ================= TAB 8: AUDIT ================= -->
        <div id="tab-audit" class="tab-content">
          <div class="action-bar">
            <input type="text" id="audit-search" placeholder="Search audit actions..." class="search-input" onkeyup="loadAuditLogs()">
            <button class="button button-secondary" onclick="loadAuditLogs()">Refresh Audit</button>
          </div>

          <section class="panel">
            <div class="panel-header">
              <h3>Comprehensive System Audit Trail</h3>
              <span id="audit-count">0 logged</span>
            </div>
            <div class="panel-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Actor (User)</th>
                    <th>Device</th>
                  </tr>
                </thead>
                <tbody id="audit-table-body">
                  <tr><td colspan="5" style="text-align: center;">Loading logs...</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- ================= TAB 9: DATABASE & BACKUPS ================= -->
        <div id="tab-backups" class="tab-content">
          <div class="stat-grid">
            <div class="stat-card">
              <span class="stat-card-label">Database Name</span>
              <span class="stat-card-value" id="db-stat-name" style="font-size: 1.15rem; word-break: break-all;">—</span>
              <span class="stat-card-note" id="db-stat-host">Host: Checking...</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Total Tables</span>
              <span class="stat-card-value" id="db-stat-tables">—</span>
              <span class="stat-card-note" id="db-stat-version">MariaDB/MySQL</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Total Stored Records</span>
              <span class="stat-card-value" id="db-stat-records">—</span>
              <span class="stat-card-note">Students, Attendance, Devices</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Database Size</span>
              <span class="stat-card-value" id="db-stat-size">—</span>
              <span class="stat-card-note">Data & Indexes</span>
            </div>
          </div>

          <div class="action-bar" style="margin-top: 1rem; margin-bottom: 1.5rem; justify-content: flex-start; gap: 0.75rem; flex-wrap: wrap;">
            <a href="/api/v1/admin/backup.php?action=export_sql" class="button button-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.7rem 1.25rem;">
              ⬇️ Download Full SQL Backup (.sql)
            </a>
            <a href="/api/v1/admin/backup.php?action=export_json" class="button button-secondary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.7rem 1.25rem;">
              ⬇️ Download JSON Backup (.json)
            </a>
            <button class="button button-secondary" onclick="openModal('modal-restore-backup')" style="display: inline-flex; align-items: center; gap: 0.5rem; border-color: #f59e0b; color: #fbbf24;">
              ⬆️ Restore / Import Database (.sql)
            </button>
            <button class="button button-secondary" onclick="loadBackups()">🔄 Refresh Tables</button>
          </div>

          <div class="dashboard-grid">
            <section class="panel">
              <div class="panel-header">
                <h3>Live Database Tables & Row Counts</h3>
                <span id="db-tables-count">0 tables</span>
              </div>
              <div class="panel-body">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Table Name</th>
                      <th>Row Count</th>
                      <th>Data Size (KB)</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody id="db-tables-body">
                    <tr><td colspan="4" style="text-align: center;">Loading tables...</td></tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section class="panel">
              <div class="panel-header">
                <h3>Cloud Migration & Permanent Reliability Guide</h3>
                <span style="color: #60a5fa;">Railway Free Tier Alternative</span>
              </div>
              <div class="panel-body" style="font-size: 0.85rem; line-height: 1.6; color: var(--foreground);">
                <div style="background: rgba(59, 130, 246, 0.08); border-left: 4px solid #3b82f6; padding: 0.9rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
                  <strong style="color: #60a5fa; font-size: 0.92rem;">Why download your SQL backup right now?</strong>
                  <p style="margin: 0.35rem 0 0 0; color: var(--muted-foreground);">
                    Railway free trial credits are non-renewable. Once credits run out, containers are halted and MySQL volumes risk being purged. Downloading your <code>.sql</code> backup preserves 100% of your student biometric slot assignments, attendance logs, and lecturer logins.
                  </p>
                </div>

                <h4 style="margin: 0.75rem 0 0.35rem 0; font-size: 0.9rem; color: #fff;">Recommended Permanent Hosting Options:</h4>
                <ul style="padding-left: 1.2rem; margin: 0; color: var(--muted-foreground);">
                  <li style="margin-bottom: 0.5rem;">
                    <strong style="color: #fff;">1. Aiven for MySQL (100% Free Forever)</strong>: Provides a free dedicated cloud MySQL database with automated daily backups. You can connect it directly to Render, Koyeb, or Railway.
                  </li>
                  <li style="margin-bottom: 0.5rem;">
                    <strong style="color: #fff;">2. Cheap cPanel Shared Hosting (Namecheap / Hostinger / Local Registrar)</strong>: Costs ~$1.50 - $2.50/mo. Includes persistent MySQL, phpMyAdmin, unlimited automatic backups, and free SSL with zero container sleep.
                  </li>
                  <li style="margin-bottom: 0.5rem;">
                    <strong style="color: #fff;">3. Local Department Mini-PC / Raspberry Pi (100% Free)</strong>: Run Apache & MySQL on a local PC connected to your faculty Wi-Fi router. ESP32 connects directly over LAN with 0 internet cost and instant attendance logging.
                  </li>
                </ul>
              </div>
            </section>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- ================= MODALS ================= -->

  <!-- Modal: Restore Database -->
  <div id="modal-restore-backup" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Restore Database from SQL</h3>
        <button class="close-btn" onclick="closeModal('modal-restore-backup')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleRestoreBackup(event)">
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem; color: #fca5a5;">
          ⚠️ <strong>Warning:</strong> Restoring an SQL backup will overwrite existing tables and replace records with the contents of the backup file. Ensure you have downloaded a fresh backup first.
        </div>
        <div class="field" style="margin-top: 0.75rem;">
          <label>Select SQL Backup File (.sql)</label>
          <input type="file" id="restore-file-input" accept=".sql" required style="padding: 0.5rem 0;">
        </div>
        <div id="restore-progress-msg" style="display: none; font-size: 0.82rem; color: #60a5fa; margin-top: 0.5rem;">
          ⏳ Restoring database... please do not close this window.
        </div>
        <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
          <button type="submit" id="btn-submit-restore" class="button button-primary" style="background: #dc2626; border-color: #ef4444;">Proceed with Restore</button>
          <button type="button" class="button button-secondary" onclick="closeModal('modal-restore-backup')">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Add Lecturer -->
  <div id="modal-add-lecturer" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Provision New Lecturer</h3>
        <button class="close-btn" onclick="closeModal('modal-add-lecturer')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleAddLecturer(event)">
        <div class="field">
          <label>Full Name</label>
          <input type="text" id="new-lecturer-name" required placeholder="e.g. Dr. Aminu Bello">
        </div>
        <div class="field">
          <label>Work Email</label>
          <input type="email" id="new-lecturer-email" required placeholder="lecturer@institution.edu">
        </div>
        <div class="field">
          <label>Faculty</label>
          <input type="text" id="new-lecturer-faculty" placeholder="Computing">
        </div>
        <div class="field">
          <label>Department</label>
          <input type="text" id="new-lecturer-dept" placeholder="Computer Science">
        </div>
        <div class="field">
          <label>Login Password</label>
          <input type="text" id="new-lecturer-password" placeholder="Enter password (or leave blank to auto-generate)">
          <small style="color: var(--muted-foreground); font-size: 0.75rem;">You can set the exact password the lecturer will use to log in.</small>
        </div>
        <button type="submit" class="button button-primary" style="margin-top: 0.5rem;">Save Lecturer Account</button>
      </form>
    </div>
  </div>

  <!-- Modal: Edit Lecturer -->
  <div id="modal-edit-lecturer" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Edit Lecturer Account</h3>
        <button class="close-btn" onclick="closeModal('modal-edit-lecturer')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleEditLecturer(event)">
        <input type="hidden" id="edit-lecturer-id">
        <div class="field">
          <label>Full Name</label>
          <input type="text" id="edit-lecturer-name" required>
        </div>
        <div class="field">
          <label>Work Email</label>
          <input type="email" id="edit-lecturer-email" required>
        </div>
        <div class="field">
          <label>Faculty</label>
          <input type="text" id="edit-lecturer-faculty">
        </div>
        <div class="field">
          <label>Department</label>
          <input type="text" id="edit-lecturer-dept">
        </div>
        <div class="field">
          <label>Set / Change Password</label>
          <input type="text" id="edit-lecturer-password" placeholder="Type new password (or leave blank to keep unchanged)">
          <small style="color: var(--muted-foreground); font-size: 0.75rem;">Entering a password here updates their login password immediately.</small>
        </div>
        <button type="submit" class="button button-primary" style="margin-top: 0.5rem;">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- Modal: Register Device -->
  <div id="modal-add-device" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Register ESP32 / ESP32 DevKit V1 Device</h3>
        <button class="close-btn" onclick="closeModal('modal-add-device')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleRegisterDevice(event)">
        <div class="field">
          <label>Hardware Unique Chip UID</label>
          <input type="text" id="new-device-uid" required placeholder="e.g. ESP32-A1B2C3D4 or ESP32-998811">
        </div>
        <div class="field">
          <label>Device Label / Friendly Name</label>
          <input type="text" id="new-device-name" placeholder="Hall B Mobile Scanner">
        </div>
        <button type="submit" class="button button-primary">Register Hardware Device</button>
      </form>
    </div>
  </div>



  <!-- Modal: Device Activation PIN Display -->
  <div id="modal-show-pin" class="modal-overlay">
    <div class="modal-card" style="max-width: 32rem; text-align: center;">
      <div class="modal-header">
        <h3 id="pin-modal-title">Device Activation PIN</h3>
        <button class="close-btn" onclick="closePinModal()">&times;</button>
      </div>
      <div style="padding: 1.25rem 0;">
        <p style="color: var(--muted-foreground); margin-bottom: 1.25rem; font-size: 0.85rem;">
          Enter this 6-digit one-time PIN on the physical ESP32 keypad to authenticate and bind this hardware unit.
        </p>

        <!-- Visual 6-digit PIN display -->
        <div id="pin-digits-container" style="display: flex; justify-content: center; gap: 0.6rem; margin-bottom: 1.5rem;">
          <div class="pin-digit-box">·</div>
          <div class="pin-digit-box">·</div>
          <div class="pin-digit-box">·</div>
          <div class="pin-digit-box">·</div>
          <div class="pin-digit-box">·</div>
          <div class="pin-digit-box">·</div>
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0.9rem; text-align: left; font-size: 0.82rem; margin-bottom: 1.25rem;">
          <div style="font-weight: 600; color: var(--foreground); margin-bottom: 0.4rem;">Physical ESP32 Instructions:</div>
          <ol style="margin: 0; padding-left: 1.2rem; color: var(--muted-foreground); line-height: 1.6;">
            <li>Power ON ESP32 DevKit. Wait for Wi-Fi to connect.</li>
            <li>OLED will prompt: <code style="color: #60a5fa;">ENTER PIN: ______</code>.</li>
            <li>Type the 6 digits using the 4×4 keypad.</li>
            <li>Press <code style="color: #34d399;">#</code> to submit (or <code style="color: #f87171;">*</code> to clear).</li>
          </ol>
        </div>

        <div id="pin-live-status" style="font-size: 0.85rem; padding: 0.6rem 1rem; border-radius: var(--radius-sm); background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: #93c5fd; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
          <span class="spinner" style="display:inline-block; width:0.85rem; height:0.85rem; border:2px solid #60a5fa; border-top-color:transparent; border-radius:50%; animation:spin 1s linear infinite;"></span>
          Waiting for physical device to enter PIN...
        </div>
      </div>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
        <span style="font-size: 0.75rem; color: var(--muted-foreground);" id="pin-expiry-text">Code valid for 24 hours</span>
        <button class="button button-secondary" onclick="closePinModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- Modal: Hardware Wiring Cheat Sheet -->
  <div id="modal-hardware-cheat-sheet" class="modal-overlay">
    <div class="modal-card" style="max-width: 44rem;">
      <div class="modal-header">
        <h3>🔌 ESP32 DevKit V1 Hardware Pinout & Wiring</h3>
        <button class="close-btn" onclick="closeModal('modal-hardware-cheat-sheet')">&times;</button>
      </div>
      <div style="padding: 0.5rem 0 1rem; max-height: 70vh; overflow-y: auto;">
        <p style="font-size: 0.82rem; color: var(--muted-foreground); margin-bottom: 1rem;">
          Production pin assignments configured in <code>firmware/MyAttendaESP32/Config.h</code> for the ESP32 DevKit V1.
        </p>
        
        <table class="data-table" style="font-size: 0.78rem; margin-bottom: 1.25rem;">
          <thead>
            <tr>
              <th>Module / Peripheral</th>
              <th>ESP32 DevKit Pin</th>
              <th>Bus / Function</th>
              <th>Notes / Voltage</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>OLED SSD1306 (128×64)</strong></td>
              <td><code>GPIO 21 (SDA)</code>, <code>GPIO 22 (SCL)</code></td>
              <td>I2C Bus (0x3C)</td>
              <td>3.3V or 5V VCC</td>
            </tr>
            <tr>
              <td><strong>PCF8574 I2C Expander</strong></td>
              <td><code>GPIO 21 (SDA)</code>, <code>GPIO 22 (SCL)</code></td>
              <td>I2C Bus (0x20)</td>
              <td>A0, A1, A2 tied to GND. Connects 4×4 Matrix Keypad.</td>
            </tr>
            <tr>
              <td><strong>DS3231 High-Precision RTC</strong></td>
              <td><code>GPIO 21 (SDA)</code>, <code>GPIO 22 (SCL)</code></td>
              <td>I2C Bus (0x68)</td>
              <td>Shared I2C bus. 3.3V VCC. Battery backup.</td>
            </tr>
            <tr>
              <td><strong>R558 Capacitive Sensor</strong></td>
              <td><code>GPIO 16 (RX2)</code> ← Sensor TX (Yellow/Green)<br><code>GPIO 17 (TX2)</code> → Sensor RX (White/Blue)</td>
              <td>Hardware UART2 (57600 baud)</td>
              <td><strong>3.3V VCC</strong> (ESP32 3V3 pin). Integrated RGB Ring LED (Aura feedback).</td>
            </tr>
            <tr>
              <td><strong>MicroSD SPI Module</strong></td>
              <td><code>GPIO 5 (CS)</code>, <code>GPIO 23 (MOSI)</code><br><code>GPIO 19 (MISO)</code>, <code>GPIO 18 (SCK)</code></td>
              <td>VSPI Bus</td>
              <td>Formatted as FAT16/FAT32. 5V VCC.</td>
            </tr>
            <tr>
              <td><strong>Piezo Buzzer</strong></td>
              <td><code>GPIO 4</code> (+) / <code>GND</code> (-)</td>
              <td>Active / PWM</td>
              <td>Audio tones for Scan OK, Error, and Sync.</td>
            </tr>
          </tbody>
        </table>

        <h4 style="margin: 0.75rem 0 0.4rem; font-size: 0.88rem;">4×4 Matrix Keypad Mapping (Option A - PCF8574 on 0x20)</h4>
        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0.75rem; font-family: var(--font-mono); font-size: 0.8rem; line-height: 1.8;">
          <div>PCF8574 P0–P3 → Keypad Rows 1–4 &nbsp;|&nbsp; PCF8574 P4–P7 → Keypad Cols 1–4</div>
          <div style="color: #60a5fa; margin-top: 0.3rem;">
            [ 1 ] [ 2 ] [ 3 ] [ A ] &nbsp;→ [A: Mode Toggle]<br>
            [ 4 ] [ 5 ] [ 6 ] [ B ] &nbsp;→ [B: Back / Cancel]<br>
            [ 7 ] [ 8 ] [ 9 ] [ C ] &nbsp;→ [C: Force Wi-Fi Sync]<br>
            [ * ] [ 0 ] [ # ] [ D ] &nbsp;→ [*: Clear | #: Enter | D: Staff Mode]
          </div>
        </div>
      </div>
      <div style="text-align: right;">
        <button class="button button-primary" onclick="closeModal('modal-hardware-cheat-sheet')">Got it</button>
      </div>
    </div>
  </div>

  <script>
    let currentTab = 'overview';

    function toggleMobileSidebar() {
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');
      if (sidebar && backdrop) {
        sidebar.classList.toggle('mobile-open');
        backdrop.classList.toggle('active');
      }
    }

    function closeMobileSidebar() {
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');
      if (sidebar && backdrop) {
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('active');
      }
    }

    function switchTab(tabId) {
      currentTab = tabId;
      closeMobileSidebar();
      document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));

      const target = document.getElementById(`tab-${tabId}`);
      if (target) target.classList.add('active');

      const link = document.querySelector(`.sidebar-link[onclick="switchTab('${tabId}')"]`);
      if (link) link.classList.add('active');

      const titles = {
        overview: 'Platform Overview',
        lecturers: 'Lecturer Management',
        devices: 'ESP32 Device Management',
        assignments: 'Device Assignment Console',
        courses: 'Course Curriculum Management',
        students: 'Student Directory & Enrollment',
        semesters: 'Semester Lifecycle & Resets',
        audit: 'Audit Center & Security Logs',
        backups: 'Database Management & Cloud Backups'
      };
      document.getElementById('page-title').innerText = titles[tabId] || 'Admin Console';

      refreshCurrentTab();
    }

    function refreshCurrentTab() {
      if (currentTab === 'overview') loadOverview();
      else if (currentTab === 'lecturers') loadLecturers();
      else if (currentTab === 'devices') loadDevices();
      else if (currentTab === 'assignments') loadAssignments();
      else if (currentTab === 'courses') loadCourses();
      else if (currentTab === 'students') loadStudents();
      else if (currentTab === 'semesters') loadSemesters();
      else if (currentTab === 'audit') loadAuditLogs();
      else if (currentTab === 'backups') loadBackups();
    }

    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    // --- OVERVIEW TAB ---
    async function loadOverview() {
      try {
        const res = await fetch('/api/v1/admin/dashboard.php');
        const json = await res.json();
        if (json.success) {
          const d = json.data;
          document.getElementById('stat-lecturers').innerText = d.total_lecturers;
          document.getElementById('stat-devices').innerText = d.total_devices;
          document.getElementById('stat-online-devices').innerText = `${d.online_devices} online · ${d.offline_devices} offline`;
          document.getElementById('stat-semester').innerText = d.active_semester;
          document.getElementById('stat-sessions').innerText = d.active_attendance_sessions;
          document.getElementById('stat-sync-status').innerText = `${d.pending_synchronization} pending sync · ${d.failed_synchronization} errors`;

          const body = document.getElementById('overview-alerts-body');
          if (d.recent_alerts && d.recent_alerts.length > 0) {
            body.innerHTML = d.recent_alerts.map(a => `
              <tr>
                <td><span class="badge badge-neutral">${a.action}</span></td>
                <td>${a.description}</td>
                <td style="font-family: var(--font-mono); font-size: 0.72rem; color: var(--muted-foreground);">${a.created_at}</td>
              </tr>
            `).join('');
          } else {
            body.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--muted-foreground);">No alerts recorded.</td></tr>';
          }
          populateQuickDeviceSelect();
        }
      } catch (e) { console.error(e); }
    }

    // --- LECTURERS TAB ---
    async function loadLecturers() {
      const search = document.getElementById('lecturer-search').value;
      try {
        const res = await fetch(`/api/v1/admin/lecturers.php?search=${encodeURIComponent(search)}`);
        const json = await res.json();
        if (json.success) {
          document.getElementById('lecturers-count').innerText = `${json.meta.total} registered`;
          const body = document.getElementById('lecturers-table-body');
          if (json.data.length > 0) {
            body.innerHTML = json.data.map(l => `
              <tr>
                <td><strong>${l.full_name}</strong></td>
                <td>${l.email}</td>
                <td>${l.department || '—'}</td>
                <td>${l.faculty || '—'}</td>
                <td><span class="badge ${l.status === 'active' ? 'badge-success' : 'badge-danger'}">${l.status}</span></td>
                <td>
                  <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.72rem;" onclick="openEditLecturerModal(${JSON.stringify(l).replace(/"/g, '&quot;')})">
                    ✏️ Edit
                  </button>
                  <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.72rem; margin-left: 0.25rem;" onclick="toggleLecturerStatus(${l.id}, '${l.status === 'active' ? 'disabled' : 'active'}')">
                    ${l.status === 'active' ? 'Disable' : 'Enable'}
                  </button>
                  <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.72rem; margin-left: 0.25rem; color: #f87171; border-color: rgba(248, 113, 113, 0.4);" onclick="deleteLecturer(${l.id}, '${l.full_name.replace(/'/g, "\\'")}')">
                    Delete
                  </button>
                </td>
              </tr>
            `).join('');
          } else {
            body.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--muted-foreground);">No lecturers found. Click "+ Add Lecturer" to create one.</td></tr>';
          }
        }
      } catch (e) { console.error(e); }
    }

    async function handleAddLecturer(e) {
      e.preventDefault();
      const payload = {
        full_name: document.getElementById('new-lecturer-name').value,
        email: document.getElementById('new-lecturer-email').value,
        faculty: document.getElementById('new-lecturer-faculty').value,
        department: document.getElementById('new-lecturer-dept').value,
        password: document.getElementById('new-lecturer-password').value
      };
      const res = await fetch('/api/v1/admin/lecturers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        alert(`Lecturer created successfully!\nLogin Email: ${data.data.email}\nLogin Password: ${data.data.temporary_password}`);
        closeModal('modal-add-lecturer');
        document.getElementById('new-lecturer-password').value = '';
        loadLecturers();
      } else {
        alert(data.error || 'Failed');
      }
    }

    function openEditLecturerModal(l) {
      document.getElementById('edit-lecturer-id').value = l.id;
      document.getElementById('edit-lecturer-name').value = l.full_name;
      document.getElementById('edit-lecturer-email').value = l.email;
      document.getElementById('edit-lecturer-faculty').value = l.faculty || '';
      document.getElementById('edit-lecturer-dept').value = l.department || '';
      document.getElementById('edit-lecturer-password').value = '';
      openModal('modal-edit-lecturer');
    }

    async function handleEditLecturer(e) {
      e.preventDefault();
      const id = document.getElementById('edit-lecturer-id').value;
      const payload = {
        id: id,
        full_name: document.getElementById('edit-lecturer-name').value,
        email: document.getElementById('edit-lecturer-email').value,
        faculty: document.getElementById('edit-lecturer-faculty').value,
        department: document.getElementById('edit-lecturer-dept').value
      };
      const pass = document.getElementById('edit-lecturer-password').value;
      if (pass && pass.trim() !== '') {
        payload.password = pass.trim();
      }

      const res = await fetch('/api/v1/admin/lecturers.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        alert('Lecturer account updated successfully!');
        closeModal('modal-edit-lecturer');
        loadLecturers();
      } else {
        alert(data.error || 'Failed to update lecturer');
      }
    }

    async function toggleLecturerStatus(id, newStatus) {
      await fetch('/api/v1/admin/lecturers.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status: newStatus })
      });
      loadLecturers();
    }
    
    async function resetLecturerPassword(id, name) {
      if (!confirm(`Are you sure you want to reset the password for ${name}?`)) return;
      try {
        const res = await fetch('/api/v1/admin/lecturers.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id, action: 'reset_password' })
        });
        const data = await res.json();
        if (data.success) {
          alert(`Password reset successful for ${name}.\nNew Temporary Password: ${data.temporary_password}\n\nPlease copy and share this with the lecturer.`);
        } else {
          alert(data.error || 'Failed to reset password');
        }
      } catch (e) { console.error(e); alert('Error resetting password'); }
    }

    async function deleteLecturer(id, name) {
      if (!confirm(`WARNING: Are you sure you want to permanently delete lecturer ${name}? This will also delete all their assigned courses and student enrollments!`)) return;
      try {
        const res = await fetch('/api/v1/admin/lecturers.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
          alert(`Lecturer ${name} deleted successfully.`);
          loadLecturers();
        } else {
          alert(data.error || 'Failed to delete lecturer');
        }
      } catch (e) { console.error(e); alert('Error deleting lecturer'); }
    }

    // --- DEVICES TAB ---
    async function loadDevices() {
      try {
        const res = await fetch('/api/v1/admin/devices.php');
        const json = await res.json();
        if (json.success) {
          document.getElementById('devices-fleet-count').innerText = `${json.meta.total} units`;
          const body = document.getElementById('devices-fleet-body');
          if (json.data.length > 0) {
            body.innerHTML = json.data.map(d => `
              <tr>
                <td><strong>${d.device_id}</strong></td>
                <td style="font-family: var(--font-mono); font-size: 0.78rem;">${d.hardware_uid}</td>
                <td><span class="badge ${d.status === 'active' ? 'badge-success' : (d.status === 'pending' ? 'badge-warning' : 'badge-danger')}">${d.status}</span></td>
                <td>${d.lecturer_name || '<em style="color:var(--muted-foreground);">Unassigned</em>'}</td>
                <td>${d.battery_voltage ? d.battery_voltage + ' V' : '—'}</td>
                <td>${d.wifi_rssi ? d.wifi_rssi + ' dBm' : '—'}</td>
                <td>${d.sd_status || 'ok'}</td>
                <td>${d.fingerprint_count || 0}</td>
                <td style="font-family: var(--font-mono); font-size: 0.72rem;">${d.last_seen || 'Never'}</td>
                <td>
                  <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.72rem;" onclick="promptPinForDevice(${d.id}, '${d.device_id}', '${d.hardware_uid}')">
                    🔑 PIN
                  </button>
                </td>
              </tr>
            `).join('');
          } else {
            body.innerHTML = '<tr><td colspan="10" style="text-align: center; color: var(--muted-foreground);">No devices registered yet. Click "+ Register Hardware Device".</td></tr>';
          }
        }
      } catch (e) { console.error(e); }
    }

    async function handleRegisterDevice(e) {
      e.preventDefault();
      const payload = {
        hardware_uid: document.getElementById('new-device-uid').value,
        device_name: document.getElementById('new-device-name').value
      };
      const res = await fetch('/api/v1/admin/devices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        alert(`Device registered! Human ID: ${data.data.device_id}`);
        closeModal('modal-add-device');
        loadDevices();
      } else {
        alert(data.error || 'Failed to register');
      }
    }

    let pinPollInterval = null;

    function stopPinPolling() {
      if (pinPollInterval) {
        clearInterval(pinPollInterval);
        pinPollInterval = null;
      }
    }

    function closePinModal() {
      stopPinPolling();
      closeModal('modal-show-pin');
    }

    async function populateQuickDeviceSelect() {
      try {
        const res = await fetch('/api/v1/admin/devices.php');
        const json = await res.json();
        const sel = document.getElementById('quick-device-select');
        if (!sel) return;
        if (json.success && json.data && json.data.length > 0) {
          sel.innerHTML = json.data.map(d => `
            <option value="${d.id}" data-code="${d.device_id}" data-hwuid="${d.hardware_uid}">
              ${d.device_id} (${d.hardware_uid}) — [${d.status.toUpperCase()}]
            </option>
          `).join('');
        } else {
          sel.innerHTML = '<option value="">No registered devices found</option>';
        }
      } catch (e) { console.error(e); }
    }

    function handleQuickGeneratePin() {
      const select = document.getElementById('quick-device-select');
      const devId = select.value;
      if (!devId) {
        alert('Please select a device from the list.');
        return;
      }
      const opt = select.options[select.selectedIndex];
      const devCode = opt ? (opt.dataset.code || `Device #${devId}`) : `Device #${devId}`;
      const hwUid = opt ? (opt.dataset.hwuid || '') : '';
      promptPinForDevice(devId, devCode, hwUid);
    }

    async function promptPinForDevice(id, deviceIdStr, hwUid) {
      stopPinPolling();
      const title = document.getElementById('pin-modal-title');
      const boxes = document.getElementById('pin-digits-container');
      const statusBox = document.getElementById('pin-live-status');

      title.innerText = `Activation PIN — ${deviceIdStr || 'Device #' + id}`;
      boxes.innerHTML = `
        <div class="pin-digit-box">·</div>
        <div class="pin-digit-box">·</div>
        <div class="pin-digit-box">·</div>
        <div class="pin-digit-box">·</div>
        <div class="pin-digit-box">·</div>
        <div class="pin-digit-box">·</div>
      `;
      statusBox.innerHTML = `
        <span class="spinner" style="display:inline-block; width:0.85rem; height:0.85rem; border:2px solid #60a5fa; border-top-color:transparent; border-radius:50%; animation:spin 1s linear infinite;"></span>
        Generating secure one-time PIN...
      `;
      statusBox.style.background = 'rgba(59, 130, 246, 0.1)';
      statusBox.style.borderColor = 'rgba(59, 130, 246, 0.2)';
      statusBox.style.color = '#93c5fd';

      openModal('modal-show-pin');

      try {
        const res = await fetch('/api/v1/admin/activation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ device_id: id })
        });
        const data = await res.json();
        if (data.success && data.data && data.data.activation_code) {
          const pin = String(data.data.activation_code);
          boxes.innerHTML = pin.split('').map(d => `<div class="pin-digit-box">${d}</div>`).join('');
          statusBox.innerHTML = `
            <span class="spinner" style="display:inline-block; width:0.85rem; height:0.85rem; border:2px solid #60a5fa; border-top-color:transparent; border-radius:50%; animation:spin 1s linear infinite;"></span>
            PIN active! Waiting for physical device to connect and enter PIN...
          `;

          // Start polling device status every 3 seconds
          pinPollInterval = setInterval(async () => {
            try {
              const devRes = await fetch('/api/v1/admin/devices.php');
              const devJson = await devRes.json();
              if (devJson.success && devJson.data) {
                const dev = devJson.data.find(d => d.id == id);
                if (dev && dev.status === 'active') {
                  stopPinPolling();
                  statusBox.style.background = 'rgba(16, 185, 129, 0.15)';
                  statusBox.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                  statusBox.style.color = '#34d399';
                  statusBox.innerHTML = `✅ <strong>Device Successfully Activated & Online!</strong> Hardware UID: <code>${dev.hardware_uid}</code>`;
                  loadDevices();
                }
              }
            } catch (err) { console.error('Pin polling err:', err); }
          }, 3000);

        } else {
          statusBox.style.background = 'rgba(239, 68, 68, 0.15)';
          statusBox.style.borderColor = 'rgba(239, 68, 68, 0.3)';
          statusBox.style.color = '#f87171';
          statusBox.innerHTML = `❌ Error: ${data.error || 'Failed to generate PIN'}`;
        }
      } catch (err) {
        statusBox.style.background = 'rgba(239, 68, 68, 0.15)';
        statusBox.style.borderColor = 'rgba(239, 68, 68, 0.3)';
        statusBox.style.color = '#f87171';
        statusBox.innerHTML = `❌ Network error while generating PIN`;
      }
    }

    // --- ASSIGNMENTS TAB ---
    async function loadAssignments() {
      const [devRes, lectRes] = await Promise.all([
        fetch('/api/v1/admin/devices.php'),
        fetch('/api/v1/admin/lecturers.php')
      ]);
      const [devs, lects] = await Promise.all([devRes.json(), lectRes.json()]);

      const devSelect = document.getElementById('assign-select-device');
      devSelect.innerHTML = devs.data.map(d => `<option value="${d.id}">${d.device_id} (${d.hardware_uid})</option>`).join('');

      const lectSelect = document.getElementById('assign-select-lecturer');
      lectSelect.innerHTML = lects.data.map(l => `<option value="${l.id}">${l.full_name} (${l.email})</option>`).join('');

      const matrix = document.getElementById('assignment-matrix-body');
      matrix.innerHTML = devs.data.map(d => `
        <tr>
          <td><strong>${d.device_id}</strong></td>
          <td>${d.lecturer_name || '<em style="color:var(--muted-foreground);">Unassigned</em>'}</td>
          <td><span class="badge ${d.lecturer_name ? 'badge-success' : 'badge-neutral'}">${d.lecturer_name ? 'Active Pairing' : 'Available'}</span></td>
        </tr>
      `).join('');
    }

    async function handleAssignDevice(e) {
      e.preventDefault();
      const payload = {
        device_id: document.getElementById('assign-select-device').value,
        lecturer_id: document.getElementById('assign-select-lecturer').value
      };
      const res = await fetch('/api/v1/admin/device-assignment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      alert(data.message || (data.success ? 'Device assigned successfully!' : data.error));
      loadAssignments();
    }




    // --- AUDIT TAB ---
    async function loadAuditLogs() {
      const search = document.getElementById('audit-search').value;
      const res = await fetch(`/api/v1/admin/audit.php?search=${encodeURIComponent(search)}`);
      const json = await res.json();
      if (json.success) {
        document.getElementById('audit-count').innerText = `${json.meta.total} records`;
        const body = document.getElementById('audit-table-body');
        if (json.data.length > 0) {
          body.innerHTML = json.data.map(a => `
            <tr>
              <td style="font-family: var(--font-mono); font-size: 0.72rem; color: var(--muted-foreground);">${a.created_at}</td>
              <td><span class="badge badge-neutral">${a.action}</span></td>
              <td>${a.description}</td>
              <td>${a.user_email || '<em style="color:var(--muted-foreground);">System</em>'}</td>
              <td>${a.device_code || '—'}</td>
            </tr>
          `).join('');
        } else {
          body.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--muted-foreground);">No audit logs match your query.</td></tr>';
        }
      }
    }

    // --- DATABASE & BACKUPS TAB ---
    async function loadBackups() {
      try {
        const res = await fetch('/api/v1/admin/backup.php?action=status');
        const json = await res.json();
        if (json.success) {
          const d = json.data;
          document.getElementById('db-stat-name').innerText = d.database_name;
          document.getElementById('db-stat-host').innerText = `MySQL ${d.mysql_version}`;
          document.getElementById('db-stat-tables').innerText = d.total_tables;
          document.getElementById('db-stat-version').innerText = `Server Time: ${d.server_time}`;
          document.getElementById('db-stat-records').innerText = Number(d.total_records).toLocaleString();
          document.getElementById('db-stat-size').innerText = `${d.total_size_mb} MB`;
          document.getElementById('db-tables-count').innerText = `${d.total_tables} total tables`;

          const body = document.getElementById('db-tables-body');
          if (d.tables && d.tables.length > 0) {
            body.innerHTML = d.tables.map(t => `
              <tr>
                <td style="font-family: var(--font-mono); font-weight: 600; color: #60a5fa;">${t.name}</td>
                <td><strong>${Number(t.rows).toLocaleString()}</strong> rows</td>
                <td>${t.size_kb.toFixed(2)} KB</td>
                <td><span class="badge badge-success">Active</span></td>
              </tr>
            `).join('');
          } else {
            body.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--muted-foreground);">No tables found in database.</td></tr>';
          }
        } else {
          alert('Failed to load database status: ' + (json.error || 'Unknown error'));
        }
      } catch (err) {
        console.error('Error fetching database status:', err);
      }
    }

    async function handleRestoreBackup(event) {
      event.preventDefault();
      const fileInput = document.getElementById('restore-file-input');
      if (!fileInput.files || fileInput.files.length === 0) {
        alert('Please choose an .sql backup file to restore.');
        return;
      }

      if (!confirm('Are you ABSOLUTELY SURE you want to restore this backup? Existing tables will be overwritten.')) {
        return;
      }

      const file = fileInput.files[0];
      const formData = new FormData();
      formData.append('backup_file', file);

      const submitBtn = document.getElementById('btn-submit-restore');
      const progressMsg = document.getElementById('restore-progress-msg');
      submitBtn.disabled = true;
      progressMsg.style.display = 'block';

      try {
        const res = await fetch('/api/v1/admin/backup.php?action=restore_sql', {
          method: 'POST',
          body: formData
        });
        const json = await res.json();
        if (json.success) {
          alert('Database restored successfully!');
          closeModal('modal-restore-backup');
          loadBackups();
        } else {
          alert('Restore failed: ' + (json.details || json.error || 'Server error'));
        }
      } catch (err) {
        alert('Failed to execute restore: ' + err.message);
      } finally {
        submitBtn.disabled = false;
        progressMsg.style.display = 'none';
      }
    }

    document.getElementById('logout-btn').addEventListener('click', async () => {
      await fetch('/api/v1/auth/logout.php', { method: 'POST' });
      window.location.href = '/login.php';
    });

    // Run initial tab load
    loadOverview();
  </script>
</body>
</html>
