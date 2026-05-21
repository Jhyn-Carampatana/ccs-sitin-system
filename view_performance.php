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

$admin_name = $_SESSION['admin_name'] ?? 'CCS Administrator';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// Get all students with performance metrics
$performance_query = $conn->query("SELECT id_number, first_name, middle_name, last_name, course, year_level, sessions, total_points, email 
                                    FROM students 
                                    ORDER BY total_points DESC");

// Calculate performance levels
$performance_stats = [
    'excellent' => 0,  // 100+ points
    'good' => 0,       // 50-99 points
    'average' => 0,    // 10-49 points
    'needs_improvement' => 0, // 0-9 points
    'total_points' => 0,
    'average_points' => 0
];

$students_data = [];
if ($performance_query && $performance_query->num_rows > 0) {
    $total_points_sum = 0;
    $student_count = 0;
    while ($row = $performance_query->fetch_assoc()) {
        $points = $row['total_points'] ?? 0;
        if ($points >= 100) $performance_stats['excellent']++;
        elseif ($points >= 50) $performance_stats['good']++;
        elseif ($points >= 10) $performance_stats['average']++;
        else $performance_stats['needs_improvement']++;
        
        $total_points_sum += $points;
        $student_count++;
        $students_data[] = $row;
    }
    $performance_stats['total_points'] = $total_points_sum;
    $performance_stats['average_points'] = $student_count > 0 ? round($total_points_sum / $student_count) : 0;
}

// Get top performers
$top_performers = array_slice($students_data, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Admin - View Performance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FB; font-family: 'Inter', sans-serif; color: #1E293B; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; left: 0; top: 0; bottom: 0; padding: 28px 20px; display: flex; flex-direction: column; }
        .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; padding-left: 8px; }
        .logo-image { width: 38px; height: 38px; object-fit: contain; border-radius: 10px; }
        .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: 700; display: none; }
        .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
        .logo-text span { color: #3B82F6; }
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; text-decoration: none; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .nav-item:hover { background: #F1F5F9; color: #1E293B; }
        .nav-item.active { background: #EFF6FF; color: #3B82F6; }
        .nav-item i { width: 22px; }
        .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 26px; font-weight: 700; color: #0F172A; }
        .page-header p { color: #6C7A91; margin-top: 4px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .stat-card h4 { font-size: 12px; font-weight: 600; color: #6C7A91; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .number { font-size: 32px; font-weight: 800; color: #0F172A; }
        .stat-card .trend { font-size: 12px; margin-top: 8px; color: #6C7A91; }
        
        /* Cards */
        .card { background: white; border-radius: 24px; border: 1px solid #EFF3F8; margin-bottom: 32px; overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #F0F2F5; background: #FAFBFF; }
        .card-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: #3B82F6; }
        
        /* Tables */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 20px; background: #F8FAFE; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6C7A91; border-bottom: 1px solid #EDF2F7; }
        td { padding: 14px 20px; border-bottom: 1px solid #F1F5F9; font-size: 13px; }
        tr:hover td { background: #F8FAFE; }
        
        /* Performance Badges */
        .performance-badge { padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
        .excellent { background: #DCFCE7; color: #15803D; }
        .good { background: #DBEAFE; color: #1D4ED8; }
        .average { background: #FEF3C7; color: #D97706; }
        .needs { background: #FEE2E2; color: #DC2626; }
        
        /* Charts Row */
        .charts-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 32px; }
        .chart-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; }
        .chart-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        
        /* Top Performers */
        .top-performer { display: flex; align-items: center; gap: 16px; padding: 12px 0; border-bottom: 1px solid #F1F5F9; }
        .top-performer:last-child { border-bottom: none; }
        .top-rank { font-size: 24px; font-weight: 800; width: 40px; }
        .top-rank.rank-1 { color: #F59E0B; }
        .top-rank.rank-2 { color: #94A3B8; }
        .top-rank.rank-3 { color: #CD7B3E; }
        .top-avatar { width: 48px; height: 48px; background: linear-gradient(135deg, #EFF6FF, #DBEAFE); border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-weight: 700; font-size: 16px; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 48px; color: #8A99B0; }
        .empty-icon { font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5; }
        
        /* Responsive */
        @media (max-width: 900px) { 
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .stats-grid { grid-template-columns: repeat(2,1fr); } 
            .charts-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR - Consistent with Admin Dashboard -->
<div class="sidebar">
    <div class="logo-area">
        <img src="ccslogo2.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('adminFallbackLogo').style.display='flex';">
        <div id="adminFallbackLogo" class="logo-icon" style="display: none;">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="logo-text">CCS <span>Admin</span></div>
    </div>
    <div class="nav-menu">
        <a href="admin_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="Search_Student.php" class="nav-item"><i class="fas fa-search"></i> Search Student</a>
        <a href="Student_Information.php" class="nav-item"><i class="fas fa-users"></i> Students</a>
        <a href="sit_in_management.php" class="nav-item"><i class="fas fa-chair"></i> Sit-in</a>
        <a href="reservation_management.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
        <a href="announcement_management.php" class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</a>
        <a href="reports.php" class="nav-item"><i class="fas fa-chart-pie"></i> Reports</a>
        <a href="leaderboard.php" class="nav-item"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="add_points.php" class="nav-item"><i class="fas fa-plus-circle"></i> Add Perusal/Point</a>
        <a href="view_performance.php" class="nav-item active"><i class="fas fa-chart-simple"></i> View Performance</a>
    </div>
    <div class="bottom-user">
        <div class="user-avatar"><?php echo $admin_initial; ?></div>
        <div>
            <h4><?php echo htmlspecialchars($admin_name); ?></h4>
            <p>Administrator</p>
        </div>
        <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="page-header">
        <h1>View Performance</h1>
        <p>Student performance metrics and analytics</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4><i class="fas fa-users"></i> Total Students</h4>
            <div class="number"><?php echo count($students_data); ?></div>
            <div class="trend">Active Records</div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-star"></i> Total Points</h4>
            <div class="number">⭐ <?php echo number_format($performance_stats['total_points']); ?></div>
            <div class="trend">All Time</div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-chart-line"></i> Average Points</h4>
            <div class="number">⭐ <?php echo $performance_stats['average_points']; ?></div>
            <div class="trend">Per Student</div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-trophy"></i> Top Performer</h4>
            <div class="number">⭐ <?php echo isset($top_performers[0]['total_points']) ? $top_performers[0]['total_points'] : 0; ?></div>
            <div class="trend">Highest Score</div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="charts-row">
        <div class="chart-card">
            <h3><i class="fas fa-chart-pie"></i> Performance Distribution</h3>
            <canvas id="performanceChart" height="200"></canvas>
        </div>
        <div class="chart-card">
            <h3><i class="fas fa-trophy"></i> Top 5 Performers</h3>
            <?php if (!empty($top_performers)): ?>
                <?php foreach ($top_performers as $index => $performer): ?>
                    <div class="top-performer">
                        <div class="top-rank <?php echo $index == 0 ? 'rank-1' : ($index == 1 ? 'rank-2' : ($index == 2 ? 'rank-3' : '')); ?>">#<?php echo $index + 1; ?></div>
                        <div class="top-avatar"><?php echo strtoupper(substr($performer['first_name'], 0, 1) . substr($performer['last_name'], 0, 1)); ?></div>
                        <div style="flex:1;">
                            <div style="font-weight:600;"><?php echo htmlspecialchars($performer['first_name'] . ' ' . $performer['last_name']); ?></div>
                            <div style="font-size:11px; color:#6C7A91;"><?php echo htmlspecialchars($performer['id_number']); ?> | <?php echo htmlspecialchars($performer['course']); ?></div>
                        </div>
                        <div style="font-weight:700; color:#F59E0B;">⭐ <?php echo $performer['total_points']; ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line empty-icon"></i>
                    No performance data available
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Performance Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-table-list"></i> Student Performance Details</h3>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Year Level</th>
                        <th>Sessions Completed</th>
                        <th>Points Earned</th>
                        <th>Performance Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students_data)): ?>
                        <?php foreach ($students_data as $student): ?>
                            <?php 
                            $points = $student['total_points'] ?? 0;
                            if ($points >= 100) {
                                $level = 'Excellent';
                                $levelClass = 'excellent';
                            } elseif ($points >= 50) {
                                $level = 'Good';
                                $levelClass = 'good';
                            } elseif ($points >= 10) {
                                $level = 'Average';
                                $levelClass = 'average';
                            } else {
                                $level = 'Needs Improvement';
                                $levelClass = 'needs';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['course']); ?></td>
                                <td><?php echo htmlspecialchars($student['year_level']); ?></td>
                                <td><?php echo $student['sessions']; ?> sessions</td>
                                <td class="points-badge">⭐ <?php echo $points; ?></td>
                                <td><span class="performance-badge <?php echo $levelClass; ?>"><?php echo $level; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="7" class="empty-state">
                                <i class="fas fa-user-graduate empty-icon"></i>
                                No student records found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Performance Distribution Chart
const chartData = {
    excellent: <?php echo $performance_stats['excellent']; ?>,
    good: <?php echo $performance_stats['good']; ?>,
    average: <?php echo $performance_stats['average']; ?>,
    needs: <?php echo $performance_stats['needs_improvement']; ?>
};

if (chartData.excellent > 0 || chartData.good > 0 || chartData.average > 0 || chartData.needs > 0) {
    new Chart(document.getElementById('performanceChart'), {
        type: 'doughnut',
        data: {
            labels: ['Excellent (100+)', 'Good (50-99)', 'Average (10-49)', 'Needs Improvement (0-9)'],
            datasets: [{
                data: [chartData.excellent, chartData.good, chartData.average, chartData.needs],
                backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'],
                borderWidth: 0,
                borderRadius: 8
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: true, 
            plugins: { 
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: function(context) { return context.label + ': ' + context.raw + ' students'; } } }
            } 
        }
    });
}
</script>
</body>
</html>