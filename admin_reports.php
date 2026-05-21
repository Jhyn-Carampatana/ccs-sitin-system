<?php
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$admin_name = $_SESSION['admin_name'] ?? 'CCS Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// Get all students for report
$students_query = "SELECT id_number, first_name, middle_name, last_name, course, year_level, sessions, total_points FROM students ORDER BY total_points DESC";
$students_result = $conn->query($students_query);

// Get all sit-in sessions for report
$sessions_query = "SELECT s.*, 
                   CONCAT(st.first_name, ' ', st.last_name) as student_full_name,
                   st.course, st.year_level
                   FROM sit_in_sessions s
                   LEFT JOIN students st ON s.id_number = st.id_number
                   ORDER BY s.created_at DESC";
$sessions_result = $conn->query($sessions_query);

// Calculate statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_sitins = $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions")->fetch_assoc()['count'];
$total_points = $conn->query("SELECT SUM(total_points) as sum FROM students")->fetch_assoc()['sum'] ?? 0;
$active_sessions = $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions WHERE status = 'active'")->fetch_assoc()['count'];
$completed_sessions = $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions WHERE status = 'completed'")->fetch_assoc()['count'];
$completion_rate = $total_sitins > 0 ? round(($completed_sessions / $total_sitins) * 100) : 0;

// Get lab usage statistics
$lab_stats = [];
$lab_query = $conn->query("SELECT laboratory, COUNT(*) as count FROM sit_in_sessions GROUP BY laboratory");
if ($lab_query) {
    while ($row = $lab_query->fetch_assoc()) {
        $lab_stats[$row['laboratory']] = $row['count'];
    }
}

// Get weekly activity (last 4 weeks)
$weekly_data = [];
for ($i = 3; $i >= 0; $i--) {
    $week_start = date('Y-m-d', strtotime("-{$i} weeks", strtotime('monday this week')));
    $week_end = date('Y-m-d', strtotime("+6 days", strtotime($week_start)));
    $week_query = $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions WHERE DATE(created_at) BETWEEN '$week_start' AND '$week_end'");
    $weekly_data[] = $week_query ? $week_query->fetch_assoc()['count'] : 0;
}

// Get course distribution
$course_stats = [];
$course_query = $conn->query("SELECT course, COUNT(*) as count FROM students GROUP BY course");
if ($course_query) {
    while ($row = $course_query->fetch_assoc()) {
        $course_stats[$row['course']] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Admin - Reports</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #F5F7FB; font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; }
  
  /* Sidebar */
  .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; height: 100vh; padding: 28px 20px; display: flex; flex-direction: column; }
  .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
  .logo-image { width: 38px; height: 38px; object-fit: contain; border-radius: 10px; }
  .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; }
  .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
  .logo-text span { color: #3B82F6; }
  .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; font-weight: 500; font-size: 14px; text-decoration: none; transition: all 0.2s; }
  .nav-item:hover { background: #F1F5F9; color: #1E293B; }
  .nav-item.active { background: #EFF6FF; color: #3B82F6; }
  .nav-item i { width: 20px; }
  .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
  .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
  .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
  
  /* Main Content */
  .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
  .reports-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
  .btn-group { display: flex; gap: 12px; }
  .btn { padding: 10px 20px; border-radius: 40px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: all 0.2s; }
  .btn-pdf { background: #DC2626; color: white; }
  .btn-pdf:hover { background: #B91C1C; transform: translateY(-1px); }
  .btn-excel { background: #16A34A; color: white; }
  .btn-excel:hover { background: #15803D; transform: translateY(-1px); }
  .btn-print { background: #2563EB; color: white; }
  .btn-print:hover { background: #1D4ED8; transform: translateY(-1px); }
  
  /* Stats Cards */
  .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; }
  .stat-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; transition: all 0.2s; }
  .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
  .stat-card h4 { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6C7A91; margin-bottom: 8px; }
  .stat-card .number { font-size: 32px; font-weight: 800; color: #0F172A; }
  .stat-card .trend { font-size: 12px; margin-top: 8px; }
  .trend-up { color: #10B981; }
  
  /* Charts Row */
  .charts-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 32px; }
  .chart-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; }
  .chart-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
  
  /* Tables */
  .table-card { background: white; border-radius: 20px; border: 1px solid #EFF3F8; overflow: hidden; margin-bottom: 32px; }
  .table-header { padding: 16px 20px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
  .table-header h3 { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
  .table-wrapper { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #6C7A91; background: #F8FAFC; border-bottom: 1px solid #EDF2F7; }
  td { padding: 12px 16px; font-size: 13px; color: #1E293B; border-bottom: 1px solid #F1F5F9; }
  tr:hover td { background: #F8FAFE; }
  .badge-completed { background: #DCFCE7; color: #15803D; padding: 4px 10px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
  .badge-active { background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
  
  /* Tabs */
  .report-tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #EDF2F7; }
  .tab-btn { padding: 12px 24px; border: none; background: none; font-weight: 600; color: #6C7A91; cursor: pointer; transition: all 0.2s; }
  .tab-btn.active { color: #3B82F6; border-bottom: 2px solid #3B82F6; }
  .tab-content { display: none; }
  .tab-content.active { display: block; }
  
  @media (max-width: 1000px) { .main-content { margin-left: 0; padding: 20px; } .stats-grid { grid-template-columns: repeat(2,1fr); } .charts-row { grid-template-columns: 1fr; } }
  @media print { .sidebar, .btn-group, .report-tabs { display: none; } .main-content { margin-left: 0; padding: 0; } }
</style>
</head>
<body>

<!-- UNIFIED SIDEBAR -->
<div class="sidebar">
  <div class="logo-area">
    <img src="ccslogo.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('adminFallbackLogo').style.display='flex';">
    <div id="adminFallbackLogo" class="logo-icon" style="display: none;"><i class="fas fa-graduation-cap"></i></div>
    <div class="logo-text">CCS <span>Admin</span></div>
  </div>
  <div class="nav-menu">
    <a href="admin_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
    <a href="Search_Student.php" class="nav-item"><i class="fas fa-search"></i> Search Student</a>
    <a href="Student_Information.php" class="nav-item"><i class="fas fa-users"></i> Students</a>
    <a href="sit_in_management.php" class="nav-item"><i class="fas fa-chair"></i> Sit-in</a>
    <a href="reservation_management.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
    <a href="announcement_management.php" class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="admin_reports.php" class="nav-item active"><i class="fas fa-chart-pie"></i> Reports</a>
    <a href="leaderboard.php" class="nav-item"><i class="fas fa-trophy"></i> Leaderboard</a>
  </div>
  <div class="bottom-user">
    <div class="user-avatar"><?php echo $admin_initial; ?></div>
    <div><h4><?php echo htmlspecialchars($admin_name); ?></h4><p>Administrator</p></div>
    <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<div class="main-content" id="reportContent">
  <div class="reports-header">
    <h1>Reports & Analytics</h1>
    <div class="btn-group">
      <button class="btn btn-pdf" onclick="exportPDF()"><i class="fas fa-file-pdf"></i> Export PDF</button>
      <button class="btn btn-excel" onclick="exportExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
      <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="stats-grid">
    <div class="stat-card"><h4>Total Students</h4><div class="number"><?php echo $total_students; ?></div><div class="trend trend-up"><i class="fas fa-arrow-up"></i> Registered</div></div>
    <div class="stat-card"><h4>Total Sit-ins</h4><div class="number"><?php echo $total_sitins; ?></div><div class="trend trend-up"><i class="fas fa-calendar"></i> All time</div></div>
    <div class="stat-card"><h4>Total Points</h4><div class="number">⭐ <?php echo number_format($total_points); ?></div><div class="trend trend-up"><i class="fas fa-star"></i> Earned</div></div>
    <div class="stat-card"><h4>Completion Rate</h4><div class="number"><?php echo $completion_rate; ?>%</div><div class="trend <?php echo $completion_rate > 50 ? 'trend-up' : ''; ?>"><i class="fas fa-chart-line"></i> Success rate</div></div>
  </div>

  <!-- Tabs -->
  <div class="report-tabs">
    <button class="tab-btn active" onclick="showTab('students')">📋 Students Report</button>
    <button class="tab-btn" onclick="showTab('sessions')">🪑 Sit-in Report</button>
    <button class="tab-btn" onclick="showTab('charts')">📊 Charts & Analytics</button>
  </div>

  <!-- Students Report Tab -->
  <div id="tab-students" class="tab-content active">
    <div class="table-card">
      <div class="table-header"><h3><i class="fas fa-users"></i> Student Records</h3></div>
      <div class="table-wrapper">
        <table id="studentsTable">
          <thead><tr><th>ID Number</th><th>Full Name</th><th>Course</th><th>Year Level</th><th>Sessions Left</th><th>Points</th></tr></thead>
          <tbody>
            <?php if ($students_result && $students_result->num_rows > 0): ?>
              <?php while ($row = $students_result->fetch_assoc()): ?>
                <?php $full_name = $row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']; ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                  <td><?php echo htmlspecialchars($full_name); ?></td>
                  <td><?php echo htmlspecialchars($row['course']); ?></td>
                  <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                  <td><?php echo $row['sessions']; ?></td>
                  <td>⭐ <?php echo $row['total_points'] ?? 0; ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center; padding:40px;">No student records found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Sit-in Report Tab -->
  <div id="tab-sessions" class="tab-content">
    <div class="table-card">
      <div class="table-header"><h3><i class="fas fa-chair"></i> Sit-in Session Records</h3></div>
      <div class="table-wrapper">
        <table id="sessionsTable">
          <thead><tr><th>Date</th><th>Student Name</th><th>ID Number</th><th>Course</th><th>Lab</th><th>Purpose</th><th>Time In</th><th>Time Out</th><th>Points</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($sessions_result && $sessions_result->num_rows > 0): ?>
              <?php while ($row = $sessions_result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                  <td><?php echo htmlspecialchars($row['student_full_name'] ?? $row['student_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                  <td><?php echo htmlspecialchars($row['course'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($row['laboratory']); ?></td>
                  <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                  <td><?php echo date('g:i A', strtotime($row['time_in'])); ?></td>
                  <td><?php echo $row['time_out'] ? date('g:i A', strtotime($row['time_out'])) : '-'; ?></td>
                  <td><?php echo $row['points_earned'] ?? 0; ?></td>
                  <td><span class="<?php echo $row['status'] == 'completed' ? 'badge-completed' : 'badge-active'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="10" style="text-align:center; padding:40px;">No sit-in records found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Charts Tab -->
  <div id="tab-charts" class="tab-content">
    <div class="charts-row">
      <div class="chart-card"><h3><i class="fas fa-chart-bar"></i> Lab Utilization</h3><canvas id="labChart" height="200"></canvas></div>
      <div class="chart-card"><h3><i class="fas fa-chart-pie"></i> Course Distribution</h3><canvas id="courseChart" height="200"></canvas></div>
    </div>
    <div class="charts-row">
      <div class="chart-card"><h3><i class="fas fa-chart-line"></i> Weekly Activity (Last 4 Weeks)</h3><canvas id="weeklyChart" height="200"></canvas></div>
      <div class="chart-card"><h3><i class="fas fa-chart-doughnut"></i> Session Status</h3><canvas id="statusChart" height="200"></canvas></div>
    </div>
  </div>
</div>

<script>
// Tab switching
function showTab(tab) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.getElementById(`tab-${tab}`).classList.add('active');
  event.target.classList.add('active');
}

// Charts
new Chart(document.getElementById('labChart'), {
  type: 'bar',
  data: { labels: <?php echo json_encode(array_keys($lab_stats)); ?>, datasets: [{ label: 'Number of Sit-ins', data: <?php echo json_encode(array_values($lab_stats)); ?>, backgroundColor: '#3B82F6', borderRadius: 8 }] },
  options: { responsive: true, maintainAspectRatio: true }
});

new Chart(document.getElementById('courseChart'), {
  type: 'pie',
  data: { labels: <?php echo json_encode(array_keys($course_stats)); ?>, datasets: [{ data: <?php echo json_encode(array_values($course_stats)); ?>, backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'] }] },
  options: { responsive: true, maintainAspectRatio: true }
});

new Chart(document.getElementById('weeklyChart'), {
  type: 'line',
  data: { labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'], datasets: [{ label: 'Sit-ins', data: <?php echo json_encode($weekly_data); ?>, borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,0.1)', tension: 0.4, fill: true }] },
  options: { responsive: true, maintainAspectRatio: true }
});

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: { labels: ['Active', 'Completed'], datasets: [{ data: [<?php echo $active_sessions; ?>, <?php echo $completed_sessions; ?>], backgroundColor: ['#F59E0B', '#10B981'] }] },
  options: { responsive: true, maintainAspectRatio: true }
});

// Export functions
function exportPDF() {
  html2pdf().from(document.getElementById('reportContent')).set({ margin: 0.5, filename: 'CCS_Report.pdf' }).save();
}

function exportExcel() {
  let wsData = [['ID Number', 'Full Name', 'Course', 'Year Level', 'Sessions Left', 'Points']];
  <?php 
  $students_result->data_seek(0);
  while ($row = $students_result->fetch_assoc()): 
    $full_name = $row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name'];
  ?>
  wsData.push(['<?php echo addslashes($row['id_number']); ?>', '<?php echo addslashes($full_name); ?>', '<?php echo addslashes($row['course']); ?>', '<?php echo addslashes($row['year_level']); ?>', <?php echo $row['sessions']; ?>, <?php echo $row['total_points'] ?? 0; ?>]);
  <?php endwhile; ?>
  const ws = XLSX.utils.aoa_to_sheet(wsData);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Student Records');
  XLSX.writeFile(wb, `CCS_Report_<?php echo date('Y-m-d'); ?>.xlsx`);
}
</script>
</body>
</html>