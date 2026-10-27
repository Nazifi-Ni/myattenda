<?php
// MyAttenda V2 — Lecturer Scoped Workspace (Zero Mock Data, 100% MariaDB)
require_once __DIR__ . '/../api/core/Auth.php';
Auth::startSession();

if (!Auth::isLoggedIn() || ($_SESSION['role'] ?? '') !== 'lecturer') {
    header("Location: /login.php?role=lecturer");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lecturer Workspace | MyAttenda V2</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .action-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
    .filter-bar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.25rem; }
    .search-input { padding: 0.55rem 0.85rem; border: 1px solid var(--border); border-radius: var(--radius-md); font-size: 0.85rem; background: rgba(255,255,255,0.03); color: var(--foreground); }
    .search-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 100; place-items: center; padding: 1rem; }
    .modal-overlay.active { display: grid; }
    .modal-card { width: min(100%, 34rem); background: var(--surface-raised); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .modal-header h3 { margin: 0; font-size: 1.25rem; }
    .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--muted-foreground); }
    .close-btn:hover { color: var(--foreground); }

    /* Refined Status Chips */
    .connection-chip { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.8rem; border-radius: 9999px; font-size: 0.78rem; font-weight: 600; border: 1px solid transparent; }
    .connection-chip.chip-online { background: rgba(16, 185, 129, 0.12); color: #34d399; border-color: rgba(16, 185, 129, 0.3); }
    .connection-chip.chip-offline { background: rgba(239, 68, 68, 0.12); color: #f87171; border-color: rgba(239, 68, 68, 0.3); }
    .connection-chip.chip-pending { background: rgba(245, 158, 11, 0.12); color: #fbbf24; border-color: rgba(245, 158, 11, 0.3); }
    .status-dot { width: 0.5rem; height: 0.5rem; border-radius: 50%; display: inline-block; }
    .status-dot.online { background: #10b981; box-shadow: 0 0 6px #10b981; }
    .status-dot.offline { background: #ef4444; }
    .status-dot.pending { background: #f59e0b; }

    /* Progress bar */
    .progress-bar-container { background: rgba(255,255,255,0.08); border-radius: 9999px; height: 0.45rem; overflow: hidden; width: 100%; margin-top: 0.4rem; }
    .progress-bar-fill { height: 100%; border-radius: 9999px; transition: width 0.3s ease; }
    .progress-bar-fill.good { background: #10b981; }
    .progress-bar-fill.warn { background: #f59e0b; }
    .progress-bar-fill.danger { background: #ef4444; }

    /* Telemetry cards */
    .telemetry-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(13.5rem, 1fr)); gap: 1.25rem; }
    .signal-bar-box { display: flex; align-items: flex-end; gap: 3px; height: 1.2rem; }
    .signal-bar { width: 4px; background: rgba(255,255,255,0.2); border-radius: 1px; }
    .signal-bar.active { background: #10b981; }
  </style>
</head>
<body>

  <!-- Mobile Topbar for responsive screens -->
  <header class="mobile-topbar">
    <button class="mobile-menu-btn" onclick="toggleMobileSidebar()" aria-label="Toggle Navigation">
      ☰
    </button>
    <a href="/dashboard/index.php" class="brand-lockup">
      <span class="brand-mark">⚡</span>
      <span class="brand-name">MyAttenda</span>
      <span class="brand-version" style="color: #93c5fd;">LECTURER</span>
    </a>
    <button class="mobile-menu-btn" onclick="refreshCurrentTab()" title="Refresh Data" style="font-size: 1rem;">
      🔄
    </button>
  </header>

  <!-- Mobile Drawer Backdrop -->
  <div class="sidebar-backdrop" id="sidebar-backdrop" onclick="closeMobileSidebar()"></div>

  <div class="portal-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <a href="/dashboard/index.php" class="brand-lockup">
          <span class="brand-mark">⚡</span>
          <span class="brand-name">MyAttenda</span>
          <span class="brand-version">LECTURER</span>
        </a>
        <button class="mobile-menu-btn" onclick="closeMobileSidebar()" style="background: transparent; color: #fff; font-size: 1.25rem; margin-left: auto;" id="sidebar-close-btn" aria-label="Close Navigation">
          ✕
        </button>
      </div>

      <div class="sidebar-section">
        <span class="sidebar-label">WORKSPACE</span>
        <a href="#overview" class="sidebar-link active" onclick="switchTab('overview')">📊 Overview</a>
        <a href="#sessions" class="sidebar-link" onclick="switchTab('sessions')">⏱️ Attendance Sessions</a>
        <a href="#reports" class="sidebar-link" onclick="switchTab('reports')">📥 Reports & Exports</a>
      </div>

      <div class="sidebar-section">
        <span class="sidebar-label">ACADEMIC</span>
        <a href="#courses" class="sidebar-link" onclick="switchTab('courses')">📚 My Courses</a>
        <a href="#students" class="sidebar-link" onclick="switchTab('students')">🎓 Enrolled Students</a>
        <a href="#semesters" class="sidebar-link" onclick="switchTab('semesters')">🔄 Semesters</a>
      </div>

      <div class="sidebar-footer">
        <span class="sidebar-label">AUTHENTICATION</span>
        <button id="logout-btn" class="sidebar-link" style="width: 100%; border: none; background: transparent; cursor: pointer; text-align: left;">
          🚪 Sign Out
        </button>
      </div>
    </aside>

    <!-- Workspace Main -->
    <main class="workspace">
      <header class="workspace-header">
        <div>
          <h1 id="page-title">Workspace Overview</h1>
          <p id="page-subtitle">Class Attendance & Student Records Management</p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
          <span class="connection-chip" id="device-chip"><span class="status-dot offline"></span> Checking Device...</span>
          <button class="button button-secondary" onclick="refreshCurrentTab()">🔄 Refresh</button>
        </div>
      </header>

      <div class="workspace-content">

        <!-- ================= TAB 1: OVERVIEW ================= -->
        <div id="tab-overview" class="tab-content active">
          <div class="stat-grid">
            <div class="stat-card">
              <span class="stat-card-label">Assigned Courses</span>
              <span class="stat-card-value" id="stat-courses">—</span>
              <span class="stat-card-note" id="stat-semester-name">Active Semester</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Total Students</span>
              <span class="stat-card-value" id="stat-students">—</span>
              <span class="stat-card-note">Enrolled across your classes</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Device Status</span>
              <span class="stat-card-value" id="stat-device-status"><span class="status-dot offline"></span> —</span>
              <span class="stat-card-note" id="stat-last-sync">Last sync: —</span>
            </div>
            <div class="stat-card">
              <span class="stat-card-label">Student Fingerprints</span>
              <span class="stat-card-value" id="stat-capacity">0 Registered</span>
              <div class="progress-bar-container">
                <div class="progress-bar-fill good" id="stat-capacity-fill" style="width: 0%;"></div>
              </div>
              <span class="stat-card-note" id="stat-capacity-note" style="margin-top: 0.35rem;">0 of 100 students registered</span>
            </div>
          </div>

          <!-- Active Attendance Controller -->
          <section class="panel">
            <div class="panel-header">
              <h3>Take Class Attendance</h3>
              <span id="session-status-text">Ready for Attendance</span>
            </div>
            <div class="panel-body">
              <div id="no-active-session">
                <p style="font-size: 0.85rem; color: var(--muted-foreground); margin: 0 0 1rem;">
                  Select a course to start taking attendance on your device:
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
                  <select id="start-session-course-select" class="search-input" style="min-width: 18rem;"></select>
                  <button class="button button-primary" onclick="startAttendanceSession()">▶️ Start Attendance Session</button>
                </div>
                <div id="device-offline-notice" style="display: none; margin-top: 1rem; padding: 0.75rem 1rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-md); font-size: 0.8rem; color: #f87171;">
                  ⚠️ <strong>Notice:</strong> Your attendance device is currently offline. Turn on your device and ensure it is connected to Wi-Fi to start taking attendance.
                </div>
              </div>

              <div id="active-session-box" style="display: none; padding: 1.5rem; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: var(--radius-md);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                  <div>
                    <span class="badge badge-success" style="font-size: 0.75rem; letter-spacing: 0.05em;">● LIVE ATTENDANCE IN PROGRESS</span>
                    <h3 id="active-session-title" style="margin: 0.6rem 0 0.25rem; font-size: 1.35rem;">Course</h3>
                    <p id="active-session-meta" style="margin: 0 0 0.75rem; font-size: 0.85rem; color: var(--muted-foreground);">Started at: —</p>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem;">
                      <span id="active-session-count" style="font-size: 1.1rem; font-weight: 700; color: #34d399;">Present: 0 / 0</span>
                      <span id="active-session-timer" style="font-family: var(--font-mono); font-size: 0.85rem; background: rgba(255,255,255,0.06); padding: 0.2rem 0.5rem; border-radius: 4px;">00:00:00</span>
                    </div>
                    <div class="progress-bar-container" style="max-width: 24rem; margin-top: 0.6rem;">
                      <div class="progress-bar-fill good" id="active-session-progress-fill" style="width: 0%;"></div>
                    </div>
                  </div>
                  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button class="button button-secondary" onclick="switchTab('sessions')">👁️ View Live Roster</button>
                    <button class="button button-secondary" style="color: var(--danger); border-color: var(--danger);" onclick="stopAttendanceSession()">⏹️ Stop Session</button>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- ================= TAB 2: SESSIONS ================= -->
        <div id="tab-sessions" class="tab-content">
          <div class="action-bar">
            <div><strong>Attendance Sessions & History</strong></div>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
              <button class="button button-secondary" onclick="loadSessions()">🔄 Refresh Sessions</button>
            </div>
          </div>

          <div class="filter-bar">
            <input type="text" id="sessions-search-input" class="search-input" placeholder="Search by course code or title..." oninput="filterSessionsTable()">
            <select id="sessions-course-filter" class="search-input" onchange="filterSessionsTable()">
              <option value="">All Courses</option>
            </select>
          </div>

          <section class="panel">
            <div class="panel-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Session ID</th>
                    <th>Course</th>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                    <th>Attended / Enrolled</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="sessions-table-body">
                  <tr><td colspan="8" style="text-align: center;">Loading sessions...</td></tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Session Detail Modal / Container -->
          <section class="panel" id="session-roster-panel" style="display: none; margin-top: 1.5rem;">
            <div class="panel-header">
              <h3 id="session-roster-title">Session Roster</h3>
              <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <button class="button button-danger" id="roster-stop-session-btn" style="display: none; min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="stopAttendanceSession(viewingRosterSessionId)">⏹️ End Session</button>
                <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="exportCurrentSessionCsv()">📥 Export CSV</button>
                <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="closeRosterPanel()">✕ Close Roster</button>
              </div>
            </div>
            <div class="panel-body">
              <div id="session-summary-box" style="margin-bottom: 1rem; font-size: 0.85rem; font-weight: 600;"></div>
              <div class="filter-bar">
                <input type="text" id="roster-search-input" class="search-input" placeholder="Filter student name or matric number..." oninput="filterRosterTable()">
              </div>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Temp ID</th>
                    <th>Matric / Student ID</th>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Attended Timestamp</th>
                    <th>Manual Override</th>
                  </tr>
                </thead>
                <tbody id="session-roster-body">
                  <tr><td colspan="7" style="text-align: center;">Loading roster...</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- ================= TAB 3: COURSES ================= -->
        <div id="tab-courses" class="tab-content">
          <div class="action-bar">
            <div><strong>Curriculum Catalog</strong></div>
            <button class="button button-primary" onclick="openModal('modal-add-course')">+ Add Course</button>
          </div>
          <div class="filter-bar">
            <input type="text" id="courses-search-input" class="search-input" placeholder="Search courses by code or title..." oninput="filterCoursesTable()">
          </div>
          <section class="panel">
            <div class="panel-header">
              <h3>My Assigned Teaching Curriculum</h3>
              <span>Active Semester Courses</span>
            </div>
            <div class="panel-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Department</th>
                    <th>Credit Units</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="lecturer-courses-body">
                  <tr><td colspan="6" style="text-align: center;">Loading courses...</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- ================= TAB 4: STUDENTS ================= -->
        <div id="tab-students" class="tab-content">
          <div class="action-bar">
            <div><strong>Enrolled Students Management</strong></div>
            <div style="display: flex; gap: 0.5rem; margin-left: auto;">
              <button class="button button-primary" onclick="openModal('modal-add-student')">+ Register Student</button>
              <button class="button button-secondary" onclick="openModal('modal-enroll-student')">🔗 Enroll into Course</button>
            </div>
          </div>
          <div class="filter-bar">
            <input type="text" id="students-search-input" class="search-input" placeholder="Search by student name or ID..." oninput="filterStudentsTable()">
            <select id="students-course-filter" class="search-input" onchange="filterStudentsTable()">
              <option value="">All Courses</option>
            </select>
          </div>
          <section class="panel">
            <div class="panel-header">
              <h3>Student Class List</h3>
              <span>Registered Students in Your Courses</span>
            </div>
            <div class="panel-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Passcode (Last 4)</th>
                    <th>Matric / Student ID</th>
                    <th>Full Name</th>
                    <th>Course Code</th>
                    <th>Fingerprint Status</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="lecturer-students-body">
                  <tr><td colspan="6" style="text-align: center;">Loading enrolled students...</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- ================= TAB 5: SEMESTERS ================= -->
        <div id="tab-semesters" class="tab-content">
          <div class="action-bar">
            <div><strong>Academic Semesters & Lifecycle</strong></div>
            <button class="button button-primary" onclick="openModal('modal-add-semester')">+ Create New Semester</button>
          </div>

          <section class="panel">
            <div class="panel-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Semester Name</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="semesters-table-body">
                  <tr><td colspan="5" style="text-align: center;">Loading semesters...</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- ================= TAB 7: REPORTS ================= -->
        <div id="tab-reports" class="tab-content">
          <div class="action-bar">
            <div><strong>Attendance Intelligence & Reports</strong></div>
            <a id="download-reports-csv-btn" href="/api/v1/reports/attendance.php?format=csv" class="button button-primary" download>
              📥 Download Complete CSV Report
            </a>
          </div>

          <!-- Summary Cards for Reports -->
          <div class="stat-grid" style="margin-bottom: 1.25rem;">
            <div class="stat-card" style="min-height: 5.5rem;">
              <span class="stat-card-label">Sessions Held</span>
              <span class="stat-card-value" id="rep-stat-sessions">0</span>
              <span class="stat-card-note">In scope</span>
            </div>
            <div class="stat-card" style="min-height: 5.5rem;">
              <span class="stat-card-label">Average Attendance</span>
              <span class="stat-card-value" id="rep-stat-avg">0%</span>
              <span class="stat-card-note">Across all courses</span>
            </div>
            <div class="stat-card" style="min-height: 5.5rem;">
              <span class="stat-card-label" id="rep-stat-defaulter-label">Defaulters (&lt;75%)</span>
              <span class="stat-card-value" id="rep-stat-defaulters" style="color: #f87171;">0</span>
              <span class="stat-card-note">Exam Ineligible</span>
            </div>
          </div>

          <div class="filter-bar" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <select id="reports-course-filter" class="search-input" onchange="filterReportsTable()">
              <option value="">All Courses</option>
            </select>
            <input type="text" id="reports-search-input" class="search-input" placeholder="Search student name or ID..." oninput="filterReportsTable()">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-left: auto; background: rgba(255, 255, 255, 0.03); padding: 0.35rem 0.75rem; border: 1px solid var(--border); border-radius: var(--radius-md);">
              <label for="defaulter-threshold-input" style="font-size: 0.8rem; font-weight: 600; white-space: nowrap;">Defaulter Cutoff:</label>
              <input type="number" id="defaulter-threshold-input" min="1" max="100" value="75" style="width: 4rem; padding: 0.25rem 0.4rem; border: 1px solid var(--border); border-radius: 4px; text-align: center; font-weight: 700; color: #ef4444;" oninput="updateDefaulterThreshold(this.value)">
              <span style="font-size: 0.85rem; font-weight: 600;">%</span>
            </div>
          </div>

          <section class="panel">
            <div class="panel-header">
              <h3>Course Attendance Summary</h3>
              <span>Official Recorded Attendance</span>
            </div>
            <div class="panel-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Passcode (Last 4)</th>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Course</th>
                    <th>Attended / Total</th>
                    <th>Attendance Rate</th>
                    <th>Exam Eligibility</th>
                  </tr>
                </thead>
                <tbody id="reports-table-body">
                  <tr><td colspan="7" style="text-align: center;">Loading reports...</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

      </div>
    </main>
  </div>
  <!-- Modal: Add Course -->
  <div id="modal-add-course" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Add New Academic Course</h3>
        <button class="close-btn" onclick="closeModal('modal-add-course')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleAddCourse(event)">
        <div class="field">
          <label>Course Code</label>
          <input type="text" id="new-course-code" required placeholder="CSC401">
        </div>
        <div class="field">
          <label>Course Title</label>
          <input type="text" id="new-course-title" required placeholder="Embedded Systems & IoT Architecture">
        </div>
        <div class="field">
          <label>Department</label>
          <input type="text" id="new-course-dept" placeholder="Computer Science">
        </div>
        <div class="field">
          <label>Credit Unit</label>
          <input type="number" id="new-course-credits" value="3" min="1" max="6">
        </div>
        <button type="submit" class="button button-primary">Save Course</button>
      </form>
    </div>
  </div>

  <!-- Modal: Edit Course -->
  <div id="modal-edit-course" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Edit Course Details</h3>
        <button class="close-btn" onclick="closeModal('modal-edit-course')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleEditCourse(event)">
        <input type="hidden" id="edit-course-id">
        <div class="field">
          <label>Course Code</label>
          <input type="text" id="edit-course-code" required>
        </div>
        <div class="field">
          <label>Course Title</label>
          <input type="text" id="edit-course-title" required>
        </div>
        <div class="field">
          <label>Department</label>
          <input type="text" id="edit-course-dept">
        </div>
        <div class="field">
          <label>Credit Unit</label>
          <input type="number" id="edit-course-credits" min="1" max="6">
        </div>
        <div class="field">
          <label>Status</label>
          <select id="edit-course-status" class="search-input" style="width: 100%;">
            <option value="active">Active</option>
            <option value="archived">Archived</option>
          </select>
        </div>
        <button type="submit" class="button button-primary">Update Course</button>
      </form>
    </div>
  </div>

  <!-- Modal: Add Student -->
  <div id="modal-add-student" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Register Student</h3>
        <button class="close-btn" onclick="closeModal('modal-add-student')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleAddStudent(event)">
        <div class="field">
          <label>Matriculation / Student ID</label>
          <input type="text" id="new-student-id" required placeholder="U19/CS/1042">
        </div>
        <div class="field">
          <label>Full Legal Name</label>
          <input type="text" id="new-student-name" required placeholder="Fatima Zahra">
        </div>
        <div class="field">
          <label>Department</label>
          <input type="text" id="new-student-dept" placeholder="Computer Science">
        </div>
        <div class="field">
          <label>Level</label>
          <input type="text" id="new-student-level" placeholder="300L">
        </div>
        <div class="field" style="margin-top: 0.5rem; padding: 0.75rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-md);">
          <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer;">
            <input type="checkbox" id="auto-enroll-checkbox" onchange="toggleAutoEnrollCourse()">
            <span>Also enroll student into a course now (1-Step Registration)</span>
          </label>
          <div id="auto-enroll-select-box" style="display: none; margin-top: 0.75rem;">
            <label style="font-size: 0.8rem; color: var(--muted-foreground);">Select Course for Enrollment:</label>
            <select id="auto-enroll-course-select" class="search-input" style="width: 100%; margin-top: 0.25rem;"></select>
          </div>
        </div>
        <button type="submit" class="button button-primary" style="margin-top: 1rem;">Register Student</button>
      </form>
    </div>
  </div>

  <!-- Modal: Edit Student -->
  <div id="modal-edit-student" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Edit Student Record</h3>
        <button class="close-btn" onclick="closeModal('modal-edit-student')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleEditStudent(event)">
        <input type="hidden" id="edit-student-db-id">
        <div class="field">
          <label>Matriculation / Student ID</label>
          <input type="text" id="edit-student-id" required>
        </div>
        <div class="field">
          <label>Full Legal Name</label>
          <input type="text" id="edit-student-name" required>
        </div>
        <div class="field">
          <label>Department</label>
          <input type="text" id="edit-student-dept">
        </div>
        <div class="field">
          <label>Level</label>
          <input type="text" id="edit-student-level">
        </div>
        <p style="font-size: 0.75rem; color: var(--muted-foreground);">
          Note: This updates the global student profile.
        </p>
        <button type="submit" class="button button-primary" style="margin-top: 1rem;">Update Student</button>
      </form>
    </div>
  </div>
  <!-- Modal: Enroll Student -->
  <div id="modal-enroll-student" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Enroll Student into Course (Active Semester)</h3>
        <button class="close-btn" onclick="closeModal('modal-enroll-student')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleEnrollStudent(event)">
        <div class="field">
          <label>Student</label>
          <select id="enroll-student-select" required></select>
        </div>
        <div class="field">
          <label>Course</label>
          <select id="enroll-course-select" required></select>
        </div>
        <p style="font-size: 0.75rem; color: var(--muted-foreground);">
          The student will enter the last 4 digits of their registration number on the keypad when registering their fingerprint on the attendance machine.
        </p>
        <button type="submit" class="button button-primary">Enroll Student into Course</button>
      </form>
    </div>
  </div>

  <!-- Modal: Add Semester -->
  <div id="modal-add-semester" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Create Academic Semester</h3>
        <button class="close-btn" onclick="closeModal('modal-add-semester')">&times;</button>
      </div>
      <form class="form-stack" onsubmit="handleAddSemester(event)">
        <div class="field">
          <label>Semester Name</label>
          <input type="text" id="new-sem-name" required placeholder="2026/2027 Second Semester">
        </div>
        <div class="field">
          <label>Start Date</label>
          <input type="date" id="new-sem-start">
        </div>
        <div class="field">
          <label>End Date</label>
          <input type="date" id="new-sem-end">
        </div>
        <button type="submit" class="button button-primary">Create Draft Semester</button>
      </form>
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
    // --- STATE MANAGEMENT ---
    let currentTab = 'overview';
    let currentSessionId = null;
    let sessionStartTime = null;
    let sessionTimerInterval = null;
    let viewingRosterSessionId = null;
    let livePollInterval = null;

    // In-memory data caches for instantaneous search & filtering
    let allSessions = [];
    let currentRosterRecords = [];
    let allCourses = [];
    let allStudents = [];
    let allReports = [];

    // --- MODAL CONTROLS ---
    function openModal(id) {
      const modal = document.getElementById(id);
      if (modal) modal.classList.add('active');
    }
    function closeModal(id) {
      const modal = document.getElementById(id);
      if (modal) modal.classList.remove('active');
    }

    function toggleAutoEnrollCourse() {
      const cb = document.getElementById('auto-enroll-checkbox');
      const box = document.getElementById('auto-enroll-select-box');
      if (box && cb) {
        box.style.display = cb.checked ? 'block' : 'none';
      }
    }

    function closeRosterPanel() {
      viewingRosterSessionId = null;
      document.getElementById('session-roster-panel').style.display = 'none';
    }

    // --- LIVE POLLING ---
    function startLivePolling() {
      if (livePollInterval) return;
      livePollInterval = setInterval(async () => {
        if (currentTab === 'overview' && currentSessionId) {
          checkActiveSession(true);
        } else if (currentTab === 'sessions' && viewingRosterSessionId) {
          viewSessionRoster(viewingRosterSessionId, true);
        }
      }, 3500);
    }

    function stopLivePolling() {
      if (livePollInterval) {
        clearInterval(livePollInterval);
        livePollInterval = null;
      }
    }

    // --- TAB NAVIGATION & HASH ROUTING ---
    function getHashTab() {
      const hash = window.location.hash.replace('#', '');
      const valid = ['overview', 'sessions', 'courses', 'students', 'semesters', 'reports'];
      return valid.includes(hash) ? hash : 'overview';
    }

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

    function switchTab(tabId, updateHash = true) {
      if (tabId === 'device') tabId = 'overview';
      currentTab = tabId;
      if (updateHash) {
        window.location.hash = '#' + tabId;
      }
      closeMobileSidebar();

      document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));

      const target = document.getElementById(`tab-${tabId}`);
      if (target) target.classList.add('active');

      const link = document.querySelector(`.sidebar-link[href="#${tabId}"]`) ||
                   document.querySelector(`.sidebar-link[onclick="switchTab('${tabId}')"]`);
      if (link) link.classList.add('active');

      const titles = {
        overview: 'Class Overview',
        sessions: 'Attendance Sessions',
        courses: 'My Courses',
        students: 'Enrolled Students',
        semesters: 'Academic Semesters',
        reports: 'Attendance Reports & Defaulters'
      };
      const subtitles = {
        overview: 'Welcome to your attendance and course management dashboard',
        sessions: 'View past and ongoing classroom attendance records',
        courses: 'Manage your teaching subjects and registered classes',
        students: 'View students enrolled across your courses',
        semesters: 'View academic calendar and semester periods',
        reports: 'Analyze student attendance percentages and export spreadsheets'
      };
      const titleEl = document.getElementById('page-title');
      if (titleEl) titleEl.innerText = titles[tabId] || 'Lecturer Portal';
      const subTitleEl = document.getElementById('page-subtitle');
      if (subTitleEl) subTitleEl.innerText = subtitles[tabId] || '';

      refreshCurrentTab();
    }

    window.addEventListener('hashchange', () => {
      const tab = getHashTab();
      if (tab !== currentTab) switchTab(tab, false);
    });

    function refreshCurrentTab() {
      if (currentTab === 'overview') loadOverview();
      else if (currentTab === 'sessions') loadSessions();
      else if (currentTab === 'courses') loadCourses();
      else if (currentTab === 'students') loadStudents();
      else if (currentTab === 'semesters') loadSemesters();
      else if (currentTab === 'reports') loadReports();
    }

    // --- HELPER: FORMAT DURATION ---
    function formatDuration(seconds) {
      const h = Math.floor(seconds / 3600);
      const m = Math.floor((seconds % 3600) / 60);
      const s = Math.floor(seconds % 60);
      return [
        h.toString().padStart(2, '0'),
        m.toString().padStart(2, '0'),
        s.toString().padStart(2, '0')
      ].join(':');
    }

    function updateSessionTimer() {
      if (!sessionStartTime) return;
      const elapsed = Math.max(0, Math.floor((Date.now() - sessionStartTime) / 1000));
      const timerEl = document.getElementById('active-session-timer');
      if (timerEl) timerEl.innerText = formatDuration(elapsed);
    }

    // =========================================================================
    // TAB 1: OVERVIEW
    // =========================================================================
    async function loadOverview() {
      try {
        const res = await fetch('/api/v1/lecturer/dashboard.php');
        const json = await res.json();
        if (json.success) {
          const d = json.data;
          document.getElementById('stat-courses').innerText = d.assigned_courses ?? 0;
          document.getElementById('stat-students').innerText = d.total_students ?? 0;

          // Semester name
          const semEl = document.getElementById('stat-semester-name');
          if (semEl) {
            semEl.innerText = d.current_semester ? d.current_semester : 'No Active Semester';
          }

          // Device status
          const isOnline = d.device_status === 'Online';
          const statDev = document.getElementById('stat-device-status');
          statDev.innerHTML = `<span class="status-dot ${isOnline ? 'online' : 'offline'}"></span> ${d.device_status || 'Offline'}`;
          document.getElementById('stat-last-sync').innerText = 'Last sync: ' + (d.last_sync_relative || d.last_synchronization || 'Never');

          // Student fingerprint count calculation
          const count = d.device ? (d.device.fingerprint_count || 0) : 0;
          document.getElementById('stat-capacity').innerText = `${count} Registered`;
          const capNote = document.getElementById('stat-capacity-note');
          if (capNote) {
            capNote.innerText = `${count} of 100 students registered`;
          }
          const capFill = document.getElementById('stat-capacity-fill');
          if (capFill) {
            const capPercent = Math.min(100, Math.round((count / 100) * 100));
            capFill.style.width = capPercent + '%';
            capFill.className = 'progress-bar-fill ' + (capPercent > 90 ? 'danger' : (capPercent > 70 ? 'warn' : 'good'));
          }

          // Topbar Device Chip
          const chip = document.getElementById('device-chip');
          if (chip) {
            if (d.device) {
              chip.className = `connection-chip ${isOnline ? 'chip-online' : 'chip-offline'}`;
              chip.innerHTML = `<span class="status-dot ${isOnline ? 'online' : 'offline'}"></span> Attendance Device: ${isOnline ? 'Ready' : 'Offline'}`;
            } else {
              chip.className = 'connection-chip chip-offline';
              chip.innerHTML = '<span class="status-dot offline"></span> No Device Assigned';
            }
          }

          // Offline notice in session start box
          const notice = document.getElementById('device-offline-notice');
          if (notice) {
            notice.style.display = isOnline ? 'none' : 'block';
          }

          // Check active session & populate courses
          checkActiveSession();
          populateCourseSelects();
        }
      } catch (e) {
        console.error('Overview error:', e);
      }
    }

    async function populateCourseSelects() {
      try {
        const res = await fetch('/api/v1/lecturer/courses.php');
        const json = await res.json();
        if (json.success && json.data.length > 0) {
          allCourses = json.data;
          const optionsHtml = allCourses.map(c => `<option value="${c.id}">${c.course_code}: ${c.course_title}</option>`).join('');
          
          const selStart = document.getElementById('start-session-course-select');
          if (selStart) selStart.innerHTML = optionsHtml;

          const selAuto = document.getElementById('auto-enroll-course-select');
          if (selAuto) selAuto.innerHTML = optionsHtml;

          const selEnroll = document.getElementById('enroll-course-select');
          if (selEnroll) selEnroll.innerHTML = optionsHtml;

          // Filter dropdowns
          const filterSessions = document.getElementById('sessions-course-filter');
          if (filterSessions) {
            filterSessions.innerHTML = '<option value="">All Courses</option>' + optionsHtml;
          }
          const filterStudents = document.getElementById('students-course-filter');
          if (filterStudents) {
            filterStudents.innerHTML = '<option value="">All Courses</option>' + optionsHtml;
          }
          const filterReports = document.getElementById('reports-course-filter');
          if (filterReports) {
            filterReports.innerHTML = '<option value="">All Courses</option>' + optionsHtml;
          }
        }
      } catch (e) {
        console.error('Course select populate error:', e);
      }
    }

    async function checkActiveSession(silent = false) {
      try {
        const res = await fetch('/api/v1/lecturer/sessions.php?status=active');
        const json = await res.json();
        if (json.success && json.data.length > 0) {
          const sess = json.data[0];
          currentSessionId = sess.id;

          const noSessBox = document.getElementById('no-active-session');
          const actSessBox = document.getElementById('active-session-box');
          if (noSessBox) noSessBox.style.display = 'none';
          if (actSessBox) actSessBox.style.display = 'block';

          document.getElementById('session-status-text').innerHTML = '<span style="color: #34d399; font-weight: 700;">● Live Session Running</span>';
          document.getElementById('active-session-title').innerText = `${sess.course_code}: ${sess.course_title}`;
          document.getElementById('active-session-meta').innerHTML = `
            Class Session #${sess.id} · Started: <code>${sess.start_time}</code>
          `;

          const enrolled = sess.enrolled_count || 0;
          const attended = sess.attendance_count || 0;
          const pct = enrolled > 0 ? Math.round((attended / enrolled) * 100) : 0;

          document.getElementById('active-session-count').innerText = `Present: ${attended} / ${enrolled} (${pct}%)`;
          const progFill = document.getElementById('active-session-progress-fill');
          if (progFill) {
            progFill.style.width = pct + '%';
            progFill.className = 'progress-bar-fill ' + (pct >= 75 ? 'good' : (pct >= 40 ? 'warn' : 'danger'));
          }

          // Setup running timer
          if (sess.start_time) {
            const today = new Date().toISOString().split('T')[0];
            const startDateTime = new Date(`${sess.session_date || today}T${sess.start_time}`);
            if (!isNaN(startDateTime.getTime())) {
              sessionStartTime = startDateTime.getTime();
            } else if (!sessionStartTime) {
              sessionStartTime = Date.now();
            }
          } else if (!sessionStartTime) {
            sessionStartTime = Date.now();
          }

          if (!sessionTimerInterval) {
            updateSessionTimer();
            sessionTimerInterval = setInterval(updateSessionTimer, 1000);
          }

          startLivePolling();
        } else {
          currentSessionId = null;
          sessionStartTime = null;
          if (sessionTimerInterval) {
            clearInterval(sessionTimerInterval);
            sessionTimerInterval = null;
          }
          const noSessBox = document.getElementById('no-active-session');
          const actSessBox = document.getElementById('active-session-box');
          if (noSessBox) noSessBox.style.display = 'block';
          if (actSessBox) actSessBox.style.display = 'none';
          document.getElementById('session-status-text').innerText = 'Ready for Attendance';
        }
      } catch (e) {
        console.error('Check active session error:', e);
      }
    }

    async function startAttendanceSession() {
      const courseId = document.getElementById('start-session-course-select').value;
      if (!courseId) { alert('Please select a course to start attendance.'); return; }
      
      const res = await fetch('/api/v1/lecturer/sessions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start', course_id: courseId })
      });
      const data = await res.json();
      if (data.success) {
        alert('Attendance session started! Your attendance device is now ready to scan student fingerprints.');
        loadOverview();
      } else {
        alert(data.error || 'Failed to start session');
      }
    }

    async function stopAttendanceSession(targetId = null) {
      const sessId = targetId || currentSessionId || 0;
      if (!confirm('Are you sure you want to end this attendance session?')) return;
      
      try {
        const res = await fetch('/api/v1/lecturer/sessions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'stop', session_id: sessId })
        });
        const data = await res.json();
        if (data.success) {
          alert('Attendance session ended successfully.');
          currentSessionId = null;
          sessionStartTime = null;
          if (sessionTimerInterval) {
            clearInterval(sessionTimerInterval);
            sessionTimerInterval = null;
          }
          stopLivePolling();
          loadOverview();
          loadSessions();
          if (viewingRosterSessionId) {
            viewSessionRoster(viewingRosterSessionId, true);
          }
        } else {
          alert(data.error || 'Failed to stop session');
        }
      } catch (err) {
        alert('Network error while terminating session: ' + err.message);
      }
    }

    // =========================================================================
    // TAB 2: SESSIONS & ROSTER
    // =========================================================================
    async function loadSessions() {
      try {
        const res = await fetch('/api/v1/lecturer/sessions.php');
        const json = await res.json();
        if (json.success) {
          allSessions = json.data || [];
          populateCourseSelects();
          filterSessionsTable();
        }
      } catch (e) {
        console.error('Load sessions error:', e);
      }
    }

    function filterSessionsTable() {
      const search = (document.getElementById('sessions-search-input')?.value || '').toLowerCase().trim();
      const courseFilter = document.getElementById('sessions-course-filter')?.value || '';

      const filtered = allSessions.filter(s => {
        const matchSearch = !search ||
          (s.course_code && s.course_code.toLowerCase().includes(search)) ||
          (s.course_title && s.course_title.toLowerCase().includes(search)) ||
          s.id.toString().includes(search);
        const matchCourse = !courseFilter || s.course_id == courseFilter;
        return matchSearch && matchCourse;
      });

      renderSessionsTable(filtered);
    }

    function renderSessionsTable(sessions) {
      const body = document.getElementById('sessions-table-body');
      if (!body) return;

      if (sessions.length > 0) {
        body.innerHTML = sessions.map(s => `
          <tr style="${s.status === 'active' ? 'background: rgba(16, 185, 129, 0.05);' : ''}">
            <td><strong>#${s.id}</strong></td>
            <td><strong>${s.course_code}</strong>: ${s.course_title}</td>
            <td>${s.session_date}</td>
            <td style="font-family: var(--font-mono); font-size: 0.75rem;">${s.start_time}</td>
            <td style="font-family: var(--font-mono); font-size: 0.75rem;">${s.end_time || '—'}</td>
            <td><span class="badge ${s.status === 'active' ? 'badge-success' : 'badge-neutral'}">${s.status.toUpperCase()}</span></td>
            <td><strong>${s.attendance_count}</strong> / ${s.enrolled_count}</td>
            <td>
              <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                ${s.status === 'active' ? `
                  <button class="button button-danger" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="stopAttendanceSession(${s.id})">
                    ⏹️ End Session
                  </button>
                ` : ''}
                <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="viewSessionRoster(${s.id})">
                  👁️ View Roster
                </button>
                <button class="button button-danger" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="deleteSession(${s.id})">
                  🗑️ Delete
                </button>
              </div>
            </td>
          </tr>
        `).join('');
      } else {
        body.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--muted-foreground); padding: 1.5rem;">No matching attendance sessions found.</td></tr>';
      }
    }

    async function deleteSession(sessionId) {
      if (!confirm('Are you absolutely sure you want to delete Session #' + sessionId + '? This will also delete all attendance records for this session permanently.')) {
        return;
      }
      try {
        const res = await fetch(`/api/v1/lecturer/sessions.php?session_id=${sessionId}`, {
          method: 'DELETE'
        });
        const json = await res.json();
        if (json.success) {
          alert('Session deleted successfully.');
          if (viewingRosterSessionId === sessionId) {
            closeSessionRoster();
          }
          loadOverview();
          loadSessions();
        } else {
          alert(json.error || 'Failed to delete session');
        }
      } catch (e) {
        console.error('Delete session error:', e);
        alert('A network error occurred.');
      }
    }

    async function viewSessionRoster(sessionId, silent = false) {
      viewingRosterSessionId = sessionId;
      try {
        const res = await fetch(`/api/v1/lecturer/attendance.php?session_id=${sessionId}`);
        const json = await res.json();
        if (json.success) {
          const panel = document.getElementById('session-roster-panel');
          panel.style.display = 'block';

          const stopBtn = document.getElementById('roster-stop-session-btn');
          if (stopBtn) {
            stopBtn.style.display = json.session.status === 'active' ? 'inline-flex' : 'none';
          }

          document.getElementById('session-roster-title').innerHTML = `
            Session #${sessionId} — ${json.session.course_code}: ${json.session.course_title}
            ${json.session.status === 'active' ? '<span class="badge badge-success" style="margin-left: 0.5rem; font-size: 0.72rem;">● LIVE CLASS</span>' : ''}
          `;

          document.getElementById('session-summary-box').innerHTML = `
            Enrolled: <strong>${json.summary.total_enrolled}</strong> |
            <span style="color:#34d399; font-weight:700;">Present: ${json.summary.present}</span> |
            <span style="color:#fbbf24; font-weight:600;">Late: ${json.summary.late}</span> |
            <span style="color:#f87171; font-weight:600;">Absent: ${json.summary.absent}</span> |
            Attendance Rate: <strong>${json.summary.percentage}%</strong>
          `;

          currentRosterRecords = json.records || [];
          filterRosterTable();

          if (!silent) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }

          if (json.session.status === 'active') {
            startLivePolling();
          }
        }
      } catch (e) {
        console.error('View roster error:', e);
      }
    }

    function filterRosterTable() {
      const search = (document.getElementById('roster-search-input')?.value || '').toLowerCase().trim();
      const filtered = currentRosterRecords.filter(r => {
        return !search ||
          (r.student_id && r.student_id.toLowerCase().includes(search)) ||
          (r.full_name && r.full_name.toLowerCase().includes(search)) ||
          (r.temporary_id && r.temporary_id.toLowerCase().includes(search));
      });
      renderRosterTable(filtered);
    }

    function renderRosterTable(records) {
      const body = document.getElementById('session-roster-body');
      if (!body) return;

      if (records.length > 0) {
        body.innerHTML = records.map(r => {
          let badgeClass = 'badge-neutral';
          if (r.attendance_status === 'present') badgeClass = 'badge-success';
          else if (r.attendance_status === 'late') badgeClass = 'badge-warning';
          else if (r.attendance_status === 'absent') badgeClass = 'badge-danger';
          else if (r.attendance_status === 'excused') badgeClass = 'badge-neutral';

          return `
            <tr style="${r.attendance_status === 'present' ? 'background: rgba(16, 185, 129, 0.05);' : ''}">
              <td><code>${r.temporary_id}</code></td>
              <td><strong>${r.student_id}</strong></td>
              <td>${r.full_name}</td>
              <td>${r.department || '—'}</td>
              <td><span class="badge ${badgeClass}">${r.attendance_status.toUpperCase()}</span></td>
              <td style="font-family: var(--font-mono); font-size: 0.75rem;">${r.attended_at || '—'}</td>
              <td>
                <select class="search-input" style="padding: 0.2rem 0.4rem; font-size: 0.75rem; border-radius: 4px;"
                        onchange="overrideAttendance(${viewingRosterSessionId}, ${r.enrollment_id}, this.value)">
                  <option value="present" ${r.attendance_status === 'present' ? 'selected' : ''}>Present</option>
                  <option value="late" ${r.attendance_status === 'late' ? 'selected' : ''}>Late</option>
                  <option value="excused" ${r.attendance_status === 'excused' ? 'selected' : ''}>Excused</option>
                  <option value="absent" ${r.attendance_status === 'absent' ? 'selected' : ''}>Absent</option>
                </select>
              </td>
            </tr>
          `;
        }).join('');
      } else {
        body.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--muted-foreground); padding: 1.5rem;">No students found for this session roster.</td></tr>';
      }
    }

    async function overrideAttendance(sessionId, enrollmentId, status) {
      try {
        const res = await fetch('/api/v1/lecturer/attendance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ session_id: sessionId, enrollment_id: enrollmentId, status: status })
        });
        const json = await res.json();
        if (json.success) {
          viewSessionRoster(sessionId, true);
        } else {
          alert(json.error || 'Failed to update attendance');
        }
      } catch (e) {
        alert('Network error while updating attendance');
      }
    }

    function exportCurrentSessionCsv() {
      if (!currentRosterRecords || currentRosterRecords.length === 0) {
        alert('No roster records to export.');
        return;
      }
      const headers = ['Temp ID', 'Matric Number', 'Full Name', 'Department', 'Status', 'Attended Timestamp'];
      const rows = currentRosterRecords.map(r => [
        `"${r.temporary_id}"`,
        `"${r.student_id}"`,
        `"${r.full_name}"`,
        `"${r.department || ''}"`,
        `"${r.attendance_status.toUpperCase()}"`,
        `"${r.attended_at || ''}"`
      ]);
      const csvContent = [headers.join(','), ...rows.map(r => r.join(','))].join('\r\n');
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `session_${viewingRosterSessionId}_roster.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // =========================================================================
    // TAB 3: COURSES
    // =========================================================================
    async function loadCourses() {
      try {
        const res = await fetch('/api/v1/lecturer/courses.php');
        const json = await res.json();
        if (json.success) {
          allCourses = json.data || [];
          populateCourseSelects();
          filterCoursesTable();
        }
      } catch (e) {
        console.error('Load courses error:', e);
      }
    }

    function filterCoursesTable() {
      const search = (document.getElementById('courses-search-input')?.value || '').toLowerCase().trim();
      const filtered = allCourses.filter(c => {
        return !search ||
          (c.course_code && c.course_code.toLowerCase().includes(search)) ||
          (c.course_title && c.course_title.toLowerCase().includes(search)) ||
          (c.department && c.department.toLowerCase().includes(search));
      });
      renderCoursesTable(filtered);
    }

    function renderCoursesTable(courses) {
      const body = document.getElementById('lecturer-courses-body');
      if (!body) return;

      if (courses.length > 0) {
        body.innerHTML = courses.map(c => `
          <tr>
            <td><strong>${c.course_code}</strong></td>
            <td>${c.course_title}</td>
            <td>${c.department || '—'}</td>
            <td>${c.credit_unit || 3}</td>
            <td><span class="badge ${c.status === 'active' ? 'badge-success' : 'badge-neutral'}">${c.status}</span></td>
            <td>
              <div style="display: flex; gap: 0.5rem;">
                <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="copyInviteLink(${c.id})">
                  🔗 Invite Link
                </button>
                <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="openEditCourseModal(${c.id})">
                  ✏️ Edit
                </button>
                <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem; color: #f87171; border-color: rgba(248, 113, 113, 0.4);" onclick="deleteCourse(${c.id}, '${c.course_code.replace(/'/g, "\\'")}')">
                  🗑️ Delete
                </button>
              </div>
            </td>
          </tr>
        `).join('');
      } else {
        body.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--muted-foreground); padding: 1.5rem;">No courses found.</td></tr>';
      }
    }

    async function deleteCourse(courseId, courseCode) {
      if (!confirm(`Are you sure you want to permanently delete course "${courseCode}"?\n\nWARNING: This will delete all attendance sessions, events, and student enrollments linked to this course!`)) return;
      try {
        const res = await fetch('/api/v1/lecturer/courses.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: courseId })
        });
        const data = await res.json();
        if (data.success) {
          alert(data.message || 'Course deleted successfully.');
          loadCourses();
          loadOverview();
        } else {
          alert(data.error || 'Failed to delete course');
        }
      } catch (e) {
        console.error(e);
        alert('Error deleting course');
      }
    }

    function copyInviteLink(courseId) {
      const link = window.location.origin + "/enroll.php?course=" + courseId;
      navigator.clipboard.writeText(link).then(() => {
        alert("Public enrollment link copied to clipboard!\n\n" + link);
      }).catch(err => {
        alert("Failed to copy link. Here is the link:\n\n" + link);
      });
    }

    function openEditCourseModal(courseId) {
      const course = allCourses.find(c => c.id == courseId);
      if (!course) return;
      document.getElementById('edit-course-id').value = course.id;
      document.getElementById('edit-course-code').value = course.course_code;
      document.getElementById('edit-course-title').value = course.course_title;
      document.getElementById('edit-course-dept').value = course.department || '';
      document.getElementById('edit-course-credits').value = course.credit_unit || 3;
      document.getElementById('edit-course-status').value = course.status || 'active';
      openModal('modal-edit-course');
    }

    async function handleEditCourse(e) {
      e.preventDefault();
      const payload = {
        id: document.getElementById('edit-course-id').value,
        course_code: document.getElementById('edit-course-code').value,
        course_title: document.getElementById('edit-course-title').value,
        department: document.getElementById('edit-course-dept').value,
        credit_unit: document.getElementById('edit-course-credits').value,
        status: document.getElementById('edit-course-status').value
      };
      const res = await fetch('/api/v1/lecturer/courses.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        alert('Course details updated successfully!');
        closeModal('modal-edit-course');
        loadCourses();
      } else {
        alert(data.error || 'Failed to update course');
      }
    }

    async function handleAddCourse(e) {
      e.preventDefault();
      const payload = {
        course_code: document.getElementById('new-course-code').value,
        course_title: document.getElementById('new-course-title').value,
        department: document.getElementById('new-course-dept').value,
        credit_unit: document.getElementById('new-course-credits').value
      };
      const res = await fetch('/api/v1/lecturer/courses.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        alert('Course created successfully!');
        closeModal('modal-add-course');
        loadCourses();
      } else {
        alert(data.error || 'Failed to create course');
      }
    }

    // =========================================================================
    // TAB 4: STUDENTS & ENROLLMENT
    // =========================================================================
    async function loadStudents() {
      try {
        const res = await fetch('/api/v1/lecturer/enrollments.php');
        const json = await res.json();
        if (json.success) {
          allStudents = json.data || [];
          populateCourseSelects();
          filterStudentsTable();
        }

        // Populate student dropdown in the standalone Enroll modal
        const sRes = await fetch('/api/v1/lecturer/students.php');
        const sJson = await sRes.json();
        if (sJson.success) {
          const selStudent = document.getElementById('enroll-student-select');
          if (selStudent) {
            selStudent.innerHTML = sJson.data.map(s => `
              <option value="${s.id}">${s.student_id} - ${s.full_name}</option>
            `).join('');
          }
        }
      } catch (e) {
        console.error('Load students error:', e);
      }
    }

    function filterStudentsTable() {
      const search = (document.getElementById('students-search-input')?.value || '').toLowerCase().trim();
      const courseFilter = document.getElementById('students-course-filter')?.value || '';

      const filtered = allStudents.filter(s => {
        const matchSearch = !search ||
          (s.student_id && s.student_id.toLowerCase().includes(search)) ||
          (s.full_name && s.full_name.toLowerCase().includes(search)) ||
          (s.temporary_id && s.temporary_id.toLowerCase().includes(search));
        const matchCourse = !courseFilter || s.course_id == courseFilter;
        return matchSearch && matchCourse;
      });

      renderStudentsTable(filtered);
    }

    function renderStudentsTable(students) {
      const body = document.getElementById('lecturer-students-body');
      if (!body) return;

      if (students.length > 0) {
        body.innerHTML = students.map(e => `
          <tr>
            <td><span class="badge badge-neutral"><code>${e.temporary_id}</code></span></td>
            <td><strong>${e.student_id}</strong></td>
            <td>${e.full_name}</td>
            <td><strong>${e.course_code}</strong></td>
            <td>
              ${e.slot_number ?
                `<span class="badge badge-success">Fingerprint Registered</span>` :
                `<span class="badge badge-warning">Pending Fingerprint</span>`}
            </td>
            <td><span class="badge badge-success">${e.status}</span></td>
            <td>
              <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.5rem; font-size: 0.75rem;" onclick='openEditStudentModal(${JSON.stringify(e).replace(/'/g, "&apos;")})'>✏️ Edit</button>
              <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.5rem; font-size: 0.75rem; color: var(--danger); border-color: var(--danger);" onclick="deleteEnrollment(${e.id})">🗑️ Drop</button>
            </td>
          </tr>
        `).join('');
      } else {
        body.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--muted-foreground); padding: 1.5rem;">No students found.</td></tr>';
      }
    }

    async function handleAddStudent(e) {
      e.preventDefault();
      const autoEnroll = document.getElementById('auto-enroll-checkbox')?.checked;
      const selectedCourseId = document.getElementById('auto-enroll-course-select')?.value;

      const payload = {
        student_id: document.getElementById('new-student-id').value,
        full_name: document.getElementById('new-student-name').value,
        department: document.getElementById('new-student-dept').value,
        level: document.getElementById('new-student-level').value
      };

      const res = await fetch('/api/v1/lecturer/students.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.success) {
        const newStudentId = data.data.id;
        if (autoEnroll && selectedCourseId && newStudentId) {
          // Perform auto-enrollment
          const enrollRes = await fetch('/api/v1/lecturer/enrollments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ student_id: newStudentId, course_id: selectedCourseId })
          });
          const enrollData = await enrollRes.json();
          if (enrollData.success) {
            alert(`Student registered and enrolled into course! Assigned Temp ID: ${enrollData.data.temporary_id}`);
          } else {
            alert(`Student registered, but enrollment failed: ${enrollData.error}`);
          }
        } else {
          alert('Student registered successfully!');
        }
        closeModal('modal-add-student');
        loadStudents();
      } else {
        alert(data.error || 'Failed to register student');
      }
    }

    function openEditStudentModal(student) {
      document.getElementById('edit-student-db-id').value = student.student_db_id || student.id;
      document.getElementById('edit-student-id').value = student.student_id;
      document.getElementById('edit-student-name').value = student.full_name;
      document.getElementById('edit-student-dept').value = student.department || '';
      document.getElementById('edit-student-level').value = student.level || '';
      openModal('modal-edit-student');
    }

    async function handleEditStudent(e) {
      e.preventDefault();
      const payload = {
        id: document.getElementById('edit-student-db-id').value,
        student_id: document.getElementById('edit-student-id').value,
        full_name: document.getElementById('edit-student-name').value,
        department: document.getElementById('edit-student-dept').value,
        level: document.getElementById('edit-student-level').value
      };

      const res = await fetch('/api/v1/lecturer/students.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      
      if (data.success) {
        closeModal('modal-edit-student');
        loadStudents();
      } else {
        alert(data.error || 'Failed to update student');
      }
    }

    async function deleteEnrollment(enrollmentId) {
      if (!confirm('Are you sure you want to drop this student from the course? This will remove their biometric data from this course and delete their attendance records.')) return;
      
      const res = await fetch('/api/v1/lecturer/enrollments.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ enrollment_id: enrollmentId })
      });
      const data = await res.json();
      
      if (data.success) {
        loadStudents();
      } else {
        alert(data.error || 'Failed to drop student');
      }
    }

    async function handleEnrollStudent(e) {
      e.preventDefault();
      const payload = {
        student_id: document.getElementById('enroll-student-select').value,
        course_id: document.getElementById('enroll-course-select').value
      };
      const res = await fetch('/api/v1/lecturer/enrollments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        alert(`Student enrolled! Assigned Temporary ID: ${data.data.temporary_id}`);
        closeModal('modal-enroll-student');
        loadStudents();
      } else {
        alert(data.error || 'Enrollment failed');
      }
    }

    // =========================================================================
    // TAB 5: DEVICE TELEMETRY & HARDWARE HEALTH
    // =========================================================================
    async function loadDevice(silent = false) {
      try {
        const res = await fetch('/api/v1/lecturer/dashboard.php');
        const json = await res.json();
        const container = document.getElementById('device-tab-telemetry-body');
        if (!container) return;

        if (json.success && json.data.device) {
          const d = json.data.device;
          const isOnline = json.data.device_status === 'Online';
          const rssi = d.wifi_rssi ? parseInt(d.wifi_rssi) : null;

          // Wi-Fi Signal Bars calculation
          let signalBars = 0;
          let signalQuality = 'No Signal';
          if (rssi !== null) {
            if (rssi >= -60) { signalBars = 4; signalQuality = 'Excellent'; }
            else if (rssi >= -70) { signalBars = 3; signalQuality = 'Good'; }
            else if (rssi >= -80) { signalBars = 2; signalQuality = 'Fair'; }
            else { signalBars = 1; signalQuality = 'Weak'; }
          }

          // Battery calculations
          const batt = d.battery_voltage ? parseFloat(d.battery_voltage) : null;
          let battDesc = 'Unknown';
          if (batt !== null) {
            if (batt >= 4.5) battDesc = '⚡ USB 5V Powered';
            else if (batt >= 4.0) battDesc = '🔋 Fully Charged';
            else if (batt >= 3.7) battDesc = '🔋 Normal';
            else battDesc = '⚠️ Low Battery';
          }

          // Slot percentage
          const count = d.fingerprint_count || 0;
          const slotPct = Math.round((count / 1000) * 100);

          container.innerHTML = `
            <div class="telemetry-grid">
              <div class="stat-card" style="min-height: 7rem;">
                <span class="stat-card-label">Hardware Device ID</span>
                <span class="stat-card-value" style="font-size: 1.35rem;">${d.device_id}</span>
                <span class="stat-card-note">Assigned ESP32 Biometric Unit</span>
              </div>

              <div class="stat-card" style="min-height: 7rem;">
                <span class="stat-card-label">Hardware UID (MAC)</span>
                <span class="stat-card-value" style="font-size: 1.1rem; font-family: var(--font-mono);">${d.hardware_uid || '—'}</span>
                <span class="stat-card-note">Verified ESP-IDF Hardware Chip</span>
              </div>

              <div class="stat-card" style="min-height: 7rem;">
                <span class="stat-card-label">Liveness & Synchronization</span>
                <span class="stat-card-value" style="font-size: 1.25rem;">
                  <span class="badge ${isOnline ? 'badge-success' : 'badge-danger'}">${json.data.device_status}</span>
                </span>
                <span class="stat-card-note">Last heartbeat: ${json.data.last_sync_relative || 'Never'}</span>
              </div>

              <div class="stat-card" style="min-height: 7rem;">
                <span class="stat-card-label">Wi-Fi Signal Strength</span>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.4rem;">
                  <span class="stat-card-value" style="font-size: 1.35rem;">${rssi !== null ? rssi + ' dBm' : '—'}</span>
                  <div class="signal-bar-box" title="${signalQuality}">
                    <div class="signal-bar ${signalBars >= 1 ? 'active' : ''}" style="height: 25%;"></div>
                    <div class="signal-bar ${signalBars >= 2 ? 'active' : ''}" style="height: 50%;"></div>
                    <div class="signal-bar ${signalBars >= 3 ? 'active' : ''}" style="height: 75%;"></div>
                    <div class="signal-bar ${signalBars >= 4 ? 'active' : ''}" style="height: 100%;"></div>
                  </div>
                </div>
                <span class="stat-card-note">${signalQuality} Connection</span>
              </div>

              <div class="stat-card" style="min-height: 7rem;">
                <span class="stat-card-label">Power & Battery</span>
                <span class="stat-card-value" style="font-size: 1.35rem;">${batt !== null ? batt.toFixed(2) + ' V' : '5.00 V'}</span>
                <span class="stat-card-note">${battDesc}</span>
              </div>

              <div class="stat-card" style="min-height: 7rem;">
                <span class="stat-card-label">R558 Capacitive Sensor Slots</span>
                <span class="stat-card-value" style="font-size: 1.35rem;">${count} / 1000</span>
                <div class="progress-bar-container">
                  <div class="progress-bar-fill ${slotPct > 90 ? 'danger' : 'good'}" style="width: ${slotPct}%;"></div>
                </div>
                <span class="stat-card-note" style="margin-top: 0.3rem;">${1000 - count} slots available</span>
              </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
              <div style="font-size: 0.82rem; color: var(--muted-foreground);">
                Firmware: <code>MyAttendaESP32 v2.0</code> · I2C Bus: <code>0x3C (OLED), 0x20 (Keypad), 0x68 (RTC)</code>
              </div>
              <div style="display: flex; gap: 0.75rem;">
                <button class="button button-secondary" onclick="openModal('modal-hardware-cheat-sheet')">🔌 View Wiring Pinout</button>
                <a href="/simulator.php" class="button button-primary">🧪 Test in Circuit Simulator →</a>
              </div>
            </div>
          `;
        } else {
          container.innerHTML = '<p style="color: var(--muted-foreground); padding: 1.5rem;">No physical device currently assigned to your account. Please contact Super Admin.</p>';
        }
      } catch (e) {
        console.error('Load device error:', e);
      }
    }

    // =========================================================================
    // TAB 6: SEMESTERS
    // =========================================================================
    async function loadSemesters() {
      try {
        const res = await fetch('/api/v1/lecturer/semesters.php');
        const json = await res.json();
        const body = document.getElementById('semesters-table-body');
        if (!body) return;

        if (json.success && json.data.length > 0) {
          body.innerHTML = json.data.map(s => `
            <tr>
              <td><strong>${s.name}</strong></td>
              <td><span class="badge ${s.status === 'active' ? 'badge-success' : (s.status === 'draft' ? 'badge-warning' : 'badge-neutral')}">${s.status.toUpperCase()}</span></td>
              <td>${s.start_date || '—'}</td>
              <td>${s.end_date || '—'}</td>
              <td>
                <div style="display: flex; gap: 0.4rem; align-items: center;">
                  ${s.status !== 'active' ? `
                    <button class="button button-primary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="activateSemester(${s.id})">
                      Activate
                    </button>
                  ` : `
                    <button class="button button-secondary" style="color:var(--danger); min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem;" onclick="closeAndResetSemester(${s.id})">
                      Close & Wipe Devices
                    </button>
                  `}
                  <button class="button button-secondary" style="min-height: 1.8rem; padding: 0.2rem 0.6rem; font-size: 0.75rem; color: #f87171; border-color: rgba(248, 113, 113, 0.4);" onclick="deleteSemester(${s.id}, '${s.name.replace(/'/g, "\\'")}')">
                    🗑️ Delete
                  </button>
                </div>
              </td>
            </tr>
          `).join('');
        } else {
          body.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--muted-foreground); padding: 1.5rem;">No academic semesters found.</td></tr>';
        }
      } catch (e) {
        console.error('Load semesters error:', e);
      }
    }

    async function handleAddSemester(e) {
      e.preventDefault();
      const payload = {
        name: document.getElementById('new-sem-name').value,
        start_date: document.getElementById('new-sem-start').value,
        end_date: document.getElementById('new-sem-end').value
      };
      const res = await fetch('/api/v1/lecturer/semesters.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        alert('Semester created in DRAFT state.');
        closeModal('modal-add-semester');
        loadSemesters();
      } else {
        alert(data.error || 'Failed to create semester');
      }
    }

    async function activateSemester(id) {
      if (!confirm('Activate this semester? Only ONE semester can be active. All other semesters will be marked closed.')) return;
      await fetch('/api/v1/lecturer/semesters.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status: 'active' })
      });
      loadSemesters();
      loadOverview();
    }

    async function closeAndResetSemester(id) {
      if (!confirm('CRITICAL ACTION: Close this semester? This will halt active sessions, revoke fingerprint mappings, and queue device sensor wipes. Historical attendance records will be preserved.')) return;
      const res = await fetch('/api/v1/lecturer/semester-reset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ semester_id: id })
      });
      const data = await res.json();
      alert(data.message || 'Semester reset executed.');
      loadSemesters();
      loadOverview();
    }

    async function deleteSemester(id, name) {
      if (!confirm(`Are you sure you want to permanently delete semester "${name}"?\n\nWARNING: This will delete all attendance sessions, events, and student enrollments linked to this semester!`)) return;
      try {
        const res = await fetch('/api/v1/lecturer/semesters.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
          alert(data.message || 'Semester deleted successfully.');
          loadSemesters();
          loadOverview();
        } else {
          alert(data.error || 'Failed to delete semester');
        }
      } catch (e) {
        console.error(e);
        alert('Error deleting semester');
      }
    }

    // =========================================================================
    // TAB 7: REPORTS & EXPORTS
    // =========================================================================
    let defaulterThreshold = parseInt(localStorage.getItem('myattenda_defaulter_threshold')) || 75;

    async function loadReports() {
      try {
        const thresholdInput = document.getElementById('defaulter-threshold-input');
        if (thresholdInput) thresholdInput.value = defaulterThreshold;

        const res = await fetch('/api/v1/reports/attendance.php');
        const json = await res.json();
        if (json.success) {
          allReports = json.records || [];
          populateCourseSelects();
          filterReportsTable();
        }
      } catch (e) {
        console.error('Load reports error:', e);
      }
    }

    function updateDefaulterThreshold(val) {
      let parsed = parseInt(val);
      if (isNaN(parsed) || parsed < 1) parsed = 1;
      if (parsed > 100) parsed = 100;
      defaulterThreshold = parsed;
      localStorage.setItem('myattenda_defaulter_threshold', defaulterThreshold);
      filterReportsTable();
    }

    function filterReportsTable() {
      const search = (document.getElementById('reports-search-input')?.value || '').toLowerCase().trim();
      const courseFilter = document.getElementById('reports-course-filter')?.value || '';

      const filtered = allReports.filter(r => {
        const matchSearch = !search ||
          (r.student_id && r.student_id.toLowerCase().includes(search)) ||
          (r.full_name && r.full_name.toLowerCase().includes(search)) ||
          (r.temporary_id && r.temporary_id.toLowerCase().includes(search));
        const matchCourse = !courseFilter || r.course_id == courseFilter;
        return matchSearch && matchCourse;
      });

      // Update Summary Cards
      const totalSessions = filtered.length > 0 ? Math.max(...filtered.map(r => r.total_sessions_held || 0)) : 0;
      const avgAttendance = filtered.length > 0
        ? Math.round(filtered.reduce((acc, r) => acc + (r.attendance_percentage || 0), 0) / filtered.length)
        : 0;
      const defaulters = filtered.filter(r => (r.attendance_percentage || 0) < defaulterThreshold).length;

      document.getElementById('rep-stat-sessions').innerText = totalSessions;
      document.getElementById('rep-stat-avg').innerText = avgAttendance + '%';
      
      const defLabel = document.getElementById('rep-stat-defaulter-label');
      if (defLabel) defLabel.innerHTML = `Defaulters (&lt;${defaulterThreshold}%)`;
      document.getElementById('rep-stat-defaulters').innerText = defaulters;

      // Update Download CSV link with custom threshold
      const csvBtn = document.getElementById('download-reports-csv-btn');
      if (csvBtn) {
        let csvUrl = `/api/v1/reports/attendance.php?format=csv&threshold=${defaulterThreshold}`;
        if (courseFilter) csvUrl += `&course_id=${courseFilter}`;
        csvBtn.setAttribute('href', csvUrl);
      }

      renderReportsTable(filtered);
    }

    function renderReportsTable(records) {
      const body = document.getElementById('reports-table-body');
      if (!body) return;

      if (records.length > 0) {
        body.innerHTML = records.map(r => {
          const eligible = (r.attendance_percentage || 0) >= defaulterThreshold;
          return `
            <tr>
              <td><code>${r.temporary_id}</code></td>
              <td><strong>${r.student_id}</strong></td>
              <td>${r.full_name}</td>
              <td><strong>${r.course_code}</strong></td>
              <td><strong>${r.sessions_attended}</strong> / ${r.total_sessions_held}</td>
              <td><strong>${r.attendance_percentage}%</strong></td>
              <td>
                <span class="badge ${eligible ? 'badge-success' : 'badge-danger'}">
                  ${eligible ? `ELIGIBLE (≥${defaulterThreshold}%)` : `DEFAULTER (<${defaulterThreshold}%)`}
                </span>
              </td>
            </tr>
          `;
        }).join('');
      } else {
        body.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--muted-foreground); padding: 1.5rem;">No cumulative records found.</td></tr>';
      }
    }

    // --- AUTHENTICATION ---
    document.getElementById('logout-btn').addEventListener('click', async () => {
      await fetch('/api/v1/auth/logout.php', { method: 'POST' });
      window.location.href = '/login.php';
    });

    // --- UNIVERSAL BUTTON LOADING FEEDBACK ---
    function setButtonLoading(btn, isLoading) {
      if (!btn) return;
      if (isLoading) {
        btn.classList.add('is-loading');
        btn.setAttribute('disabled', 'true');
      } else {
        btn.classList.remove('is-loading');
        btn.removeAttribute('disabled');
      }
    }

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('button, .button');
      if (!btn) return;
      if (btn.classList.contains('is-loading') || btn.classList.contains('close-btn') || btn.id === 'sidebar-close-btn') return;

      btn.classList.add('is-loading');
      setTimeout(() => {
        btn.classList.remove('is-loading');
      }, 2500);
    });

    document.addEventListener('submit', (e) => {
      const submitBtn = e.target.querySelector('button[type="submit"], input[type="submit"]');
      if (submitBtn) {
        submitBtn.classList.add('is-loading');
        setTimeout(() => {
          submitBtn.classList.remove('is-loading');
        }, 4000);
      }
    });

    // --- INITIALIZE ---
    const initialTab = getHashTab();
    switchTab(initialTab, false);
  </script>
</body>
</html>
