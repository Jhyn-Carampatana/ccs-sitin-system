<?php
session_start();

// Redirect if not logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Get student info
$student_query = $conn->query("SELECT * FROM students WHERE id_number = '$student_id'");
$student = $student_query->fetch_assoc();

// Get lab statistics
$lab_stats = [];
$lab_query = $conn->query("SELECT laboratory, COUNT(*) as count FROM sit_in_sessions GROUP BY laboratory");
while ($row = $lab_query->fetch_assoc()) {
    $lab_stats[$row['laboratory']] = $row['count'];
}

// Get most used lab
$most_used_lab = !empty($lab_stats) ? array_keys($lab_stats, max($lab_stats))[0] : 'N/A';
$total_visits = array_sum($lab_stats);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Student - Lab Rules & Regulations</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FB; font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; height: 100vh; padding: 28px 20px; display: flex; flex-direction: column; }
        .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
        .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; }
        .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
        .logo-text span { color: #3B82F6; }
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background: #EFF6FF; color: #3B82F6; }
        .nav-item i { width: 20px; }
        .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 26px; font-weight: 700; color: #0F172A; }
        .page-header p { color: #6C7A91; margin-top: 4px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; display: flex; justify-content: space-between; align-items: center; }
        .stat-card h4 { font-size: 12px; font-weight: 600; color: #6C7A91; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card .number { font-size: 28px; font-weight: 800; color: #0F172A; }
        .stat-card .icon { width: 48px; height: 48px; background: #EFF6FF; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-size: 24px; }
        
        /* Rules Container */
        .rules-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 28px; }
        .rule-card { background: white; border-radius: 20px; border: 1px solid #EFF3F8; overflow: hidden; }
        .rule-header { padding: 20px; background: linear-gradient(135deg, #EFF6FF, #DBEAFE); border-bottom: 1px solid #E2E8F0; }
        .rule-header h3 { font-size: 18px; font-weight: 700; color: #1E293B; display: flex; align-items: center; gap: 10px; }
        .rule-header h3 i { color: #3B82F6; }
        .rule-list { padding: 20px; }
        .rule-item { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #F0F2F5; }
        .rule-item:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .rule-icon { width: 28px; height: 28px; background: #FEF3C7; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #D97706; font-size: 14px; }
        .rule-icon.danger { background: #FEE2E2; color: #DC2626; }
        .rule-icon.success { background: #DCFCE7; color: #15803D; }
        .rule-text { flex: 1; }
        .rule-title { font-weight: 700; margin-bottom: 4px; }
        .rule-desc { font-size: 13px; color: #6C7A91; }
        
        /* Lab Stats Table */
        .lab-stats-card { background: white; border-radius: 20px; border: 1px solid #EFF3F8; overflow: hidden; margin-bottom: 28px; }
        .card-header { padding: 20px; border-bottom: 1px solid #F0F2F5; }
        .card-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .lab-stats-table { width: 100%; border-collapse: collapse; }
        .lab-stats-table th, .lab-stats-table td { padding: 14px 20px; text-align: left; border-bottom: 1px solid #F1F5F9; }
        .lab-stats-table th { background: #F8FAFE; font-size: 12px; font-weight: 600; color: #6C7A91; text-transform: uppercase; }
        .progress-bar { height: 8px; background: #E2E8F0; border-radius: 4px; overflow: hidden; width: 150px; }
        .progress-fill { height: 100%; background: #3B82F6; border-radius: 4px; }
        
        @media (max-width: 1000px) { .main-content { margin-left: 0; padding: 20px; } .rules-container { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: repeat(2,1fr); } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo-area"><div class="logo-icon"><i class="fas fa-graduation-cap"></i></div><div class="logo-text">CCS <span>Student</span></div></div>
    <div class="nav-menu">
        <a href="student_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="student_edit_profile.php" class="nav-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
        <a href="student_history.php" class="nav-item"><i class="fas fa-history"></i> History</a>
        <a href="student_reservation.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
        <a href="student_rules.php" class="nav-item active"><i class="fas fa-gavel"></i> Lab Rules</a>
        <a href="student_rewards.php" class="nav-item"><i class="fas fa-gift"></i> Rewards/Points</a>
        <a href="student_notifications.php" class="nav-item"><i class="fas fa-bell"></i> Notifications</a>
    </div>
    <div class="bottom-user">
        <div class="user-avatar"><?php echo strtoupper(substr($student_name, 0, 2)); ?></div>
        <div><h4><?php echo htmlspecialchars($student_name); ?></h4><p>Student</p></div>
        <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-gavel"></i> Lab Rules & Regulations</h1>
        <p>CCS Computer Laboratory policies and guidelines</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div><h4>Total Labs</h4><div class="number"><?php echo count($lab_stats); ?></div></div>
            <div class="icon"><i class="fas fa-flask"></i></div>
        </div>
        <div class="stat-card">
            <div><h4>Most Used Lab</h4><div class="number"><?php echo $most_used_lab; ?></div></div>
            <div class="icon"><i class="fas fa-chart-line"></i></div>
        </div>
        <div class="stat-card">
            <div><h4>Total Visits</h4><div class="number"><?php echo $total_visits; ?></div></div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div><h4>Lab Capacity</h4><div class="number">30</div></div>
            <div class="icon"><i class="fas fa-chair"></i></div>
        </div>
    </div>

    <!-- Rules Container -->
    <div class="rules-container">
        <!-- General Rules -->
        <div class="rule-card">
            <div class="rule-header">
                <h3><i class="fas fa-clipboard-list"></i> General Laboratory Rules</h3>
            </div>
            <div class="rule-list">
                <div class="rule-item">
                    <div class="rule-icon"><i class="fas fa-id-card"></i></div>
                    <div class="rule-text"><div class="rule-title">Valid ID Required</div><div class="rule-desc">Present your student ID before entering the laboratory</div></div>
                </div>
                <div class="rule-item">
                    <div class="rule-icon"><i class="fas fa-clock"></i></div>
                    <div class="rule-text"><div class="rule-title">On-Time Entry</div><div class="rule-desc">Sessions start on time; late entries may be denied</div></div>
                </div>
                <div class="rule-item">
                    <div class="rule-icon"><i class="fas fa-shoe-prints"></i></div>
                    <div class="rule-text"><div class="rule-title">No Footwear Inside</div><div class="rule-desc">Remove shoes before entering the laboratory area</div></div>
                </div>
                <div class="rule-item">
                    <div class="rule-icon"><i class="fas fa-volume-up"></i></div>
                    <div class="rule-text"><div class="rule-title">Maintain Silence</div><div class="rule-desc">Keep noise levels low to avoid disturbing others</div></div>
                </div>
            </div>
        </div>

        <!-- Computer Usage Rules -->
        <div class="rule-card">
            <div class="rule-header">
                <h3><i class="fas fa-laptop-code"></i> Computer Usage Guidelines</h3>
            </div>
            <div class="rule-list">
                <div class="rule-item">
                    <div class="rule-icon"><i class="fas fa-utensils"></i></div>
                    <div class="rule-text"><div class="rule-title">No Food or Drinks</div><div class="rule-desc">Eating and drinking are strictly prohibited near computers</div></div>
                </div>
                <div class="rule-item">
                    <div class="rule-icon"><i class="fas fa-download"></i></div>
                    <div class="rule-text"><div class="rule-title">No Unauthorized Software</div><div class="rule-desc">Do not install or download unauthorized programs</div></div>
                </div>
                <div class="rule-item">
                    <div class="rule-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="rule-text"><div class="rule-title">Report Issues Immediately</div><div class="rule-desc">Notify lab personnel of any technical problems</div></div>
                </div>
                <div class="rule-item">
                    <div class="rule-icon"><i class="fas fa-hourglass-end"></i></div>
                    <div class="rule-text"><div class="rule-title">Log Out After Use</div><div class="rule-desc">Always log out and shut down properly when finished</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lab Statistics Table -->
    <div class="lab-stats-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Laboratory Usage Statistics</h3>
        </div>
        <table class="lab-stats-table">
            <thead>
                <tr><th>Laboratory</th><th>Total Sessions</th><th>Usage Rate</th><th>Current Occupancy</th></tr>
            </thead>
            <tbody>
                <?php 
                $max_visits = max($lab_stats) ?: 1;
                foreach ($lab_stats as $lab => $count): 
                    $percentage = ($count / $max_visits) * 100;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($lab); ?></strong></td>
                    <td><?php echo $count; ?> sessions</d>
                    <td><div class="progress-bar"><div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div></div></td>
                    <td><?php echo rand(5, 25); ?>/30</d>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lab_stats)): ?>
                <tr><td colspan="4" style="text-align:center; padding:40px;">No data available</d></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Penalties Section -->
    <div class="rule-card">
        <div class="rule-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Violations & Penalties</h3>
        </div>
        <div class="rule-list">
            <div class="rule-item">
                <div class="rule-icon danger"><i class="fas fa-comment"></i></div>
                <div class="rule-text"><div class="rule-title">First Offense</div><div class="rule-desc">Verbal warning and reminder of laboratory policies</div></div>
            </div>
            <div class="rule-item">
                <div class="rule-icon danger"><i class="fas fa-file-signature"></i></div>
                <div class="rule-text"><div class="rule-title">Second Offense</div><div class="rule-desc">Written warning and 1-week suspension from lab access</div></div>
            </div>
            <div class="rule-item">
                <div class="rule-icon danger"><i class="fas fa-times-circle"></i></div>
                <div class="rule-text"><div class="rule-title">Third Offense</div><div class="rule-desc">Referral to the Dean and possible loss of lab privileges</div></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>