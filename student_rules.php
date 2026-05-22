<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: Login.php');
    exit;
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Fetch student data
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$student_name = htmlspecialchars(trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name']));
$student_course = htmlspecialchars($user['course'] ?? 'N/A');
$student_id = htmlspecialchars($user['id_number'] ?? 'N/A');
$initials = strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1));

// Get lab statistics
$lab_stats = [];
$lab_query = $conn->query("SELECT laboratory, COUNT(*) as count FROM sit_in_sessions GROUP BY laboratory");
while ($row = $lab_query->fetch_assoc()) {
    $lab_stats[$row['laboratory']] = $row['count'];
}

// Get most used lab
$most_used_lab = !empty($lab_stats) ? array_keys($lab_stats, max($lab_stats))[0] : 'N/A';
$total_visits = array_sum($lab_stats);
$total_labs = count($lab_stats);

// Get current lab occupancy
$lab_occupancy = [];
$occupancy_query = $conn->query("SELECT laboratory, COUNT(*) as count FROM sit_in_sessions WHERE status = 'active' GROUP BY laboratory");
while ($row = $occupancy_query->fetch_assoc()) {
    $lab_occupancy[$row['laboratory']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Rules & Regulations - CCS Student</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FB; font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 280px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; height: 100vh; padding: 28px 20px; display: flex; flex-direction: column; }
        .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; padding-left: 8px; }
        .logo-image { width: 42px; height: 42px; object-fit: contain; border-radius: 12px; }
        .logo-icon { background: #3B82F6; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 700; display: none; }
        .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
        .logo-text span { color: #3B82F6; }
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; font-weight: 500; font-size: 14px; text-decoration: none; transition: all 0.2s; }
        .nav-item i { width: 22px; color: #7E8BA0; }
        .nav-item:hover { background: #F1F5F9; color: #1E293B; }
        .nav-item.active { background: #EFF6FF; color: #3B82F6; }
        .nav-item.active i { color: #3B82F6; }
        .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 44px; height: 44px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
        .logout-icon { margin-left: auto; color: #EF4444; background: none; border: none; cursor: pointer; }
        
        /* Main Content */
        .main-content { margin-left: 280px; flex: 1; padding: 28px 36px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .page-breadcrumb h1 { font-size: 26px; font-weight: 700; color: #0F172A; }
        .breadcrumb-links { display: flex; align-items: center; gap: 8px; margin-top: 6px; font-size: 13px; color: #64748B; }
        .student-chip { background: white; border-radius: 40px; padding: 6px 18px; display: flex; align-items: center; gap: 10px; border: 1px solid #E9EEF3; font-size: 13px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; display: flex; justify-content: space-between; align-items: center; }
        .stat-card h4 { font-size: 12px; font-weight: 600; color: #6C7A91; text-transform: uppercase; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: 800; color: #0F172A; }
        .stat-card .icon { width: 48px; height: 48px; background: #EFF6FF; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-size: 24px; }
        
        /* Main Container */
        .rules-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 28px; }
        .rule-card { background: white; border-radius: 20px; border: 1px solid #EFF3F8; overflow: hidden; }
        .rule-header { padding: 20px; background: linear-gradient(135deg, #EFF6FF, #DBEAFE); border-bottom: 1px solid #E2E8F0; }
        .rule-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
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
        .lab-table { width: 100%; border-collapse: collapse; }
        .lab-table th, .lab-table td { padding: 14px 20px; text-align: left; border-bottom: 1px solid #F1F5F9; }
        .lab-table th { background: #F8FAFE; font-size: 12px; font-weight: 600; color: #6C7A91; text-transform: uppercase; }
        .progress-bar { height: 8px; background: #E2E8F0; border-radius: 4px; overflow: hidden; width: 150px; }
        .progress-fill { height: 100%; background: #3B82F6; border-radius: 4px; }
        .badge-available { background: #DCFCE7; color: #15803D; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-occupied { background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        
        /* Penalties Section */
        .penalties-card { background: white; border-radius: 20px; border: 1px solid #EFF3F8; overflow: hidden; }
        
        @media (max-width: 1000px) { .main-content { margin-left: 0; padding: 20px; } .rules-container { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: repeat(2,1fr); } }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo-area">
        <img src="ccslogo2.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('studentFallbackLogo').style.display='flex';">
        <div id="studentFallbackLogo" class="logo-icon" style="display: none;"><i class="fas fa-user-graduate"></i></div>
        <div class="logo-text">CCS <span>Student</span></div>
    </div>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="editProfile.php" class="nav-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
        <a href="history.php" class="nav-item"><i class="fas fa-history"></i> History</a>
        <a href="reservation.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
        <a href="student_rules.php" class="nav-item active"><i class="fas fa-gavel"></i> Lab Rules</a>
        <a href="student_rewards.php" class="nav-item"><i class="fas fa-gift"></i> Rewards/Points</a>
    </div>
    <div class="bottom-user">
        <div class="user-avatar"><?php echo $initials; ?></div>
        <div><h4><?php echo $student_name; ?></h4><p><?php echo $student_course; ?></p></div>
        <form method="POST" action="logout.php" style="display:inline;"><button type="submit" class="logout-icon"><i class="fas fa-sign-out-alt"></i></button></form>
    </div>
</div>

<div class="main-content">
    <div class="top-header">
        <div class="page-breadcrumb">
            <h1><i class="fas fa-gavel"></i> Lab Rules & Regulations</h1>
            <div class="breadcrumb-links"><span>Home</span> <i class="fas fa-chevron-right"></i> <span>Lab Rules</span></div>
        </div>
        <div class="student-chip"><i class="fas fa-user"></i> CCS Student · <strong><?php echo $student_course; ?></strong></div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card"><div><h4>Total Labs</h4><div class="number"><?php echo $total_labs; ?></div></div><div class="icon"><i class="fas fa-flask"></i></div></div>
        <div class="stat-card"><div><h4>Most Used Lab</h4><div class="number"><?php echo $most_used_lab; ?></div></div><div class="icon"><i class="fas fa-chart-line"></i></div></div>
        <div class="stat-card"><div><h4>Total Lab Visits</h4><div class="number"><?php echo $total_visits; ?></div></div><div class="icon"><i class="fas fa-users"></i></div></div>
        <div class="stat-card"><div><h4>Lab Capacity</h4><div class="number">30</div></div><div class="icon"><i class="fas fa-chair"></i></div></div>
    </div>

    <!-- Rules Container -->
    <div class="rules-container">
        <!-- General Rules -->
        <div class="rule-card">
            <div class="rule-header"><h3><i class="fas fa-clipboard-list"></i> General Laboratory Rules</h3></div>
            <div class="rule-list">
                <div class="rule-item"><div class="rule-icon"><i class="fas fa-id-card"></i></div><div class="rule-text"><div class="rule-title">Valid ID Required</div><div class="rule-desc">Present your student ID before entering the laboratory</div></div></div>
                <div class="rule-item"><div class="rule-icon"><i class="fas fa-clock"></i></div><div class="rule-text"><div class="rule-title">On-Time Entry</div><div class="rule-desc">Sessions start on time; late entries may be denied</div></div></div>
                <div class="rule-item"><div class="rule-icon"><i class="fas fa-shoe-prints"></i></div><div class="rule-text"><div class="rule-title">No Footwear Inside</div><div class="rule-desc">Remove shoes before entering the laboratory area</div></div></div>
                <div class="rule-item"><div class="rule-icon"><i class="fas fa-volume-up"></i></div><div class="rule-text"><div class="rule-title">Maintain Silence</div><div class="rule-desc">Keep noise levels low to avoid disturbing others</div></div></div>
            </div>
        </div>

        <!-- Computer Usage Rules -->
        <div class="rule-card">
            <div class="rule-header"><h3><i class="fas fa-laptop-code"></i> Computer Usage Guidelines</h3></div>
            <div class="rule-list">
                <div class="rule-item"><div class="rule-icon"><i class="fas fa-utensils"></i></div><div class="rule-text"><div class="rule-title">No Food or Drinks</div><div class="rule-desc">Eating and drinking are strictly prohibited near computers</div></div></div>
                <div class="rule-item"><div class="rule-icon"><i class="fas fa-download"></i></div><div class="rule-text"><div class="rule-title">No Unauthorized Software</div><div class="rule-desc">Do not install or download unauthorized programs</div></div></div>
                <div class="rule-item"><div class="rule-icon"><i class="fas fa-shield-alt"></i></div><div class="rule-text"><div class="rule-title">Report Issues Immediately</div><div class="rule-desc">Notify lab personnel of any technical problems</div></div></div>
                <div class="rule-item"><div class="rule-icon"><i class="fas fa-hourglass-end"></i></div><div class="rule-text"><div class="rule-title">Log Out After Use</div><div class="rule-desc">Always log out and shut down properly when finished</div></div></div>
            </div>
        </div>
    </div>

    <!-- Lab Statistics Table -->
    <div class="lab-stats-card">
        <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Laboratory Usage Statistics</h3></div>
        <table class="lab-table">
            <thead><tr><th>Laboratory</th><th>Total Sessions</th><th>Usage Rate</th><th>Current Occupancy</th><th>Status</th></tr></thead>
            <tbody>
                <?php 
                $max_visits = max($lab_stats) ?: 1;
                $labs = ['Lab 544', 'Lab 524', 'Lab 526', 'Lab 528', 'Lab 530'];
                foreach ($labs as $lab): 
                    $count = $lab_stats[$lab] ?? 0;
                    $percentage = ($count / $max_visits) * 100;
                    $occupancy = $lab_occupancy[$lab] ?? 0;
                    $status = $occupancy >= 30 ? 'Full' : ($occupancy > 0 ? 'Occupied' : 'Available');
                    $statusClass = $status == 'Available' ? 'badge-available' : 'badge-occupied';
                ?>
                <tr>
                    <td><strong><?php echo $lab; ?></strong></td>
                    <td><?php echo $count; ?> sessions</d>
                    <td><div class="progress-bar"><div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div></div></td>
                    <td><?php echo $occupancy; ?>/30</d>
                    <td><span class="<?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Penalties Section -->
    <div class="penalties-card">
        <div class="rule-header"><h3><i class="fas fa-exclamation-triangle"></i> Violations & Penalties</h3></div>
        <div class="rule-list">
            <div class="rule-item"><div class="rule-icon danger"><i class="fas fa-comment"></i></div><div class="rule-text"><div class="rule-title">First Offense</div><div class="rule-desc">Verbal warning and reminder of laboratory policies</div></div></div>
            <div class="rule-item"><div class="rule-icon danger"><i class="fas fa-file-signature"></i></div><div class="rule-text"><div class="rule-title">Second Offense</div><div class="rule-desc">Written warning and 1-week suspension from lab access</div></div></div>
            <div class="rule-item"><div class="rule-icon danger"><i class="fas fa-times-circle"></i></div><div class="rule-text"><div class="rule-title">Third Offense</div><div class="rule-desc">Referral to the Dean and possible loss of lab privileges</div></div></div>
        </div>
    </div>
</div>

</body>
</html>