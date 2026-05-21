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

// Fetch fresh data from database
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate sessions remaining
$sessions_total = 30;
$sessions_used = $user['sessions_used'] ?? 0;
$sessions_remaining = $sessions_total - $sessions_used;

// Update session variables
$_SESSION['name'] = $user['first_name'];
$_SESSION['last_name'] = $user['last_name'];
$_SESSION['course'] = $user['course'];
$_SESSION['course_level'] = $user['course_level'];
$_SESSION['email'] = $user['email'];
$_SESSION['address'] = $user['address'];
$_SESSION['id_number'] = $user['id_number'];
$_SESSION['sessions_used'] = $sessions_used;
$_SESSION['sessions'] = $sessions_remaining;
$_SESSION['profile_pic'] = $user['profile_pic'];

// Handle reservation submission
$reservation_success = false;
$reservation_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    $purpose = trim($_POST['purpose']);
    $laboratory = trim($_POST['laboratory']);
    $time_in = trim($_POST['time_in']);
    $reservation_date = trim($_POST['reservation_date']);
    
    // Validate inputs
    if (empty($purpose) || empty($laboratory) || empty($time_in) || empty($reservation_date)) {
        $reservation_error = "Please fill in all fields.";
    } elseif ($sessions_remaining <= 0) {
        $reservation_error = "No remaining sessions available.";
    } else {
        // Check if student already has a pending reservation for the same date
        $check_stmt = $conn->prepare("SELECT id FROM reservations WHERE student_id = ? AND reservation_date = ? AND status = 'pending'");
        $check_stmt->bind_param("is", $user_id, $reservation_date);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $reservation_error = "You already have a pending reservation for this date.";
        } else {
            // Insert reservation into database
            $full_name = $user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name'];
            
            $insert_stmt = $conn->prepare("INSERT INTO reservations (student_id, id_number, student_name, course, year_level, purpose, laboratory, reservation_date, time_in, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $insert_stmt->bind_param("issssssss", $user_id, $user['id_number'], $full_name, $user['course'], $user['course_level'], $purpose, $laboratory, $reservation_date, $time_in);
            
            if ($insert_stmt->execute()) {
                // Update sessions_used in students table
                $new_sessions_used = $sessions_used + 1;
                $update_stmt = $conn->prepare("UPDATE students SET sessions_used = ? WHERE id = ?");
                $update_stmt->bind_param("ii", $new_sessions_used, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Update session variables
                $_SESSION['sessions_used'] = $new_sessions_used;
                $_SESSION['sessions'] = 30 - $new_sessions_used;
                
                // Add notification
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'reservation')");
                $notif_title = "Reservation Submitted";
                $notif_message = "Your reservation for $laboratory on " . date('F j, Y', strtotime($reservation_date)) . " at $time_in has been submitted for approval.";
                $notif_stmt->bind_param("iss", $user_id, $notif_title, $notif_message);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $reservation_success = true;
                
                // Refresh user data
                $refresh_stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
                $refresh_stmt->bind_param("i", $user_id);
                $refresh_stmt->execute();
                $user = $refresh_stmt->get_result()->fetch_assoc();
                $refresh_stmt->close();
                
                $sessions_used = $user['sessions_used'] ?? 0;
                $sessions_remaining = 30 - $sessions_used;
            } else {
                $reservation_error = "Error creating reservation: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

$student_name = htmlspecialchars(trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name']));
$student_course = htmlspecialchars($user['course'] ?? 'N/A');
$student_year = htmlspecialchars($user['course_level'] ?? 'N/A');
$student_email = htmlspecialchars($user['email'] ?? 'N/A');
$student_address = htmlspecialchars($user['address'] ?? 'N/A');
$profile_pic = htmlspecialchars($user['profile_pic'] ?? '');
$student_id = htmlspecialchars($user['id_number'] ?? 'N/A');
$initials = strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1));

// Fetch notifications for the bell icon
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();
$unread_count = count(array_filter($notifications, fn($n) => !$n['is_read']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reservation - CCS Student</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: #F5F7FB;
      font-family: 'Inter', sans-serif;
      color: #1E293B;
      display: flex;
      min-height: 100vh;
    }

    /* ========= SIDEBAR ========= */
    .sidebar {
      width: 280px;
      background: #FFFFFF;
      border-right: 1px solid #E9EEF3;
      display: flex;
      flex-direction: column;
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;
      z-index: 10;
      padding: 28px 20px;
      box-shadow: 0 0 0 1px rgba(0,0,0,0.02);
    }

    .logo-area {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 40px;
      padding-left: 8px;
    }
    
    .logo-image {
      width: 42px;
      height: 42px;
      object-fit: contain;
      border-radius: 12px;
    }
    
    .logo-icon {
      background: #3B82F6;
      width: 42px;
      height: 42px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
      font-weight: 700;
      box-shadow: 0 6px 12px -6px rgba(59,130,246,0.25);
      display: none;
    }
    
    .logo-text {
      font-weight: 800;
      font-size: 20px;
      letter-spacing: -0.3px;
      color: #0F172A;
    }
    .logo-text span {
      color: #3B82F6;
    }

    .nav-menu {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 12px 16px;
      border-radius: 12px;
      color: #5B6E8C;
      font-weight: 500;
      font-size: 14px;
      transition: all 0.2s;
      cursor: pointer;
      text-decoration: none;
    }
    .nav-item i {
      width: 22px;
      font-size: 1.2rem;
      color: #7E8BA0;
    }
    .nav-item:hover {
      background: #F1F5F9;
      color: #1E293B;
    }
    .nav-item.active {
      background: #EFF6FF;
      color: #3B82F6;
    }
    .nav-item.active i {
      color: #3B82F6;
    }

    .bottom-user {
      margin-top: auto;
      border-top: 1px solid #EDF2F7;
      padding-top: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .user-avatar {
      width: 44px;
      height: 44px;
      background: linear-gradient(135deg, #3B82F6, #2563EB);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 16px;
    }
    .user-details h4 {
      font-size: 14px;
      font-weight: 700;
      color: #0F172A;
    }
    .user-details p {
      font-size: 12px;
      color: #6C7A91;
    }
    .logout-icon {
      margin-left: auto;
      color: #EF4444;
      text-decoration: none;
      background: none;
      border: none;
      cursor: pointer;
    }
    .logout-icon:hover {
      opacity: 0.8;
    }

    /* ========= MAIN CONTENT ========= */
    .main-content {
      margin-left: 280px;
      flex: 1;
      padding: 28px 36px;
    }

    /* Top header */
    .top-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 32px;
    }
    .page-breadcrumb h1 {
      font-size: 26px;
      font-weight: 700;
      color: #0F172A;
      letter-spacing: -0.4px;
    }
    
    .breadcrumb-links {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 6px;
      font-size: 13px;
      color: #64748B;
    }
    .breadcrumb-links span:first-child {
      color: #64748B;
    }
    .breadcrumb-links span:last-child {
      color: #3B82F6;
      font-weight: 500;
    }
    .breadcrumb-links i {
      font-size: 10px;
      color: #94A3B8;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .header-actions {
      display: flex;
      gap: 16px;
      align-items: center;
    }
    .notif-btn {
      background: white;
      border-radius: 40px;
      width: 44px;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
      border: 1px solid #E9EEF3;
      cursor: pointer;
      color: #4B5565;
      transition: all 0.2s;
      position: relative;
    }
    .notif-btn:hover {
      background: #F8FAFE;
      border-color: #CBD5E1;
    }
    .notif-dot {
      position: absolute;
      top: 10px;
      right: 12px;
      width: 8px;
      height: 8px;
      background: #EF4444;
      border-radius: 50%;
      display: <?php echo $unread_count > 0 ? 'block' : 'none'; ?>;
    }
    .student-chip {
      background: white;
      border-radius: 40px;
      padding: 6px 18px 6px 12px;
      display: flex;
      align-items: center;
      gap: 10px;
      border: 1px solid #E9EEF3;
      font-weight: 500;
      font-size: 13px;
      color: #1E293B;
    }
    .student-chip i {
      color: #3B82F6;
      font-size: 16px;
    }

    /* ========= RESERVATION CARD ========= */
    .reservation-card {
      background: white;
      border-radius: 24px;
      border: 1px solid #EFF3F8;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
      max-width: 700px;
      margin: 0 auto;
    }

    .section-header {
      background: #F8FAFE;
      padding: 16px 24px;
      border-bottom: 1px solid #F0F2F5;
      font-size: 14px;
      font-weight: 700;
      color: #1E293B;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .section-header i {
      color: #3B82F6;
      font-size: 18px;
    }

    .form-body {
      padding: 24px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 140px 1fr;
      align-items: center;
      gap: 16px;
      margin-bottom: 20px;
    }

    .form-row label {
      font-size: 13px;
      font-weight: 600;
      color: #475569;
    }

    .form-row label .req {
      color: #EF4444;
      margin-left: 2px;
    }

    input[type="text"],
    input[type="time"],
    input[type="date"],
    select {
      width: 100%;
      padding: 10px 14px;
      border: 1.5px solid #E2E8F0;
      border-radius: 12px;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
      color: #1E293B;
      background: white;
      outline: none;
      transition: all 0.2s;
    }

    input:focus, select:focus {
      border-color: #3B82F6;
      box-shadow: 0 0 0 3px rgba(59,130,246,0.08);
    }

    input[readonly] {
      background: #F8FAFE;
      color: #6C7A91;
      cursor: not-allowed;
      border-color: #E2E8F0;
    }

    select {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236C7A91' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      cursor: pointer;
    }

    hr.divider {
      border: none;
      border-top: 1px solid #F0F2F5;
      margin: 0;
    }

    .session-badge {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #EFF6FF;
      border-radius: 12px;
      padding: 10px 14px;
      font-size: 13px;
      color: #3B82F6;
      width: 100%;
    }
    .session-badge .count {
      font-weight: 800;
      font-size: 18px;
      color: #3B82F6;
    }

    .btn-row {
      display: flex;
      gap: 12px;
      padding: 0 24px 24px;
    }

    .btn {
      padding: 10px 24px;
      border: none;
      border-radius: 40px;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-reserve {
      background: #10B981;
      color: white;
    }
    .btn-reserve:hover {
      background: #059669;
      transform: translateY(-1px);
    }

    .btn-clear {
      background: #F1F5F9;
      color: #475569;
    }
    .btn-clear:hover {
      background: #E2E8F0;
    }

    /* Alert Messages */
    .alert-success {
      background: #DCFCE7;
      color: #15803D;
      padding: 16px 20px;
      border-radius: 16px;
      margin-bottom: 20px;
      border-left: 4px solid #10B981;
    }
    .alert-error {
      background: #FEE2E2;
      color: #DC2626;
      padding: 16px 20px;
      border-radius: 16px;
      margin-bottom: 20px;
      border-left: 4px solid #EF4444;
    }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        padding: 16px;
      }
      .form-row {
        grid-template-columns: 1fr;
        gap: 8px;
      }
      .form-row label {
        text-align: left;
      }
      .btn-row {
        flex-wrap: wrap;
      }
      .btn {
        flex: 1;
        justify-content: center;
      }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
  <div class="logo-area">
    <img src="ccslogo2.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('studentFallbackLogo').style.display='flex';">
    <div id="studentFallbackLogo" class="logo-icon" style="display: none;">
      <i class="fas fa-user-graduate"></i>
    </div>
    <div class="logo-text">CCS <span>Student</span></div>
  </div>
  <div class="nav-menu">
    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="editProfile.php" class="nav-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="history.php" class="nav-item"><i class="fas fa-history"></i> History</a>
    <a href="reservation.php" class="nav-item active"><i class="fas fa-calendar-alt"></i> Reservation</a>
  </div>
  <div class="bottom-user">
    <div class="user-avatar"><?php echo $initials ?: '?'; ?></div>
    <div class="user-details">
      <h4><?php echo $student_name; ?></h4>
      <p><?php echo $student_course; ?></p>
    </div>
    <form method="POST" action="logout.php" style="display:inline;">
      <button type="submit" class="logout-icon"><i class="fas fa-sign-out-alt"></i></button>
    </form>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
  <div class="top-header">
    <div class="page-breadcrumb">
      <h1>Reservation</h1>
      <div class="breadcrumb-links">
        <span>Home</span>
        <i class="fas fa-chevron-right"></i>
        <span>Reservation</span>
      </div>
    </div>
    <div class="header-actions">
      <div class="student-chip"><i class="fas fa-user"></i> CCS Student · <strong><?php echo $student_course; ?></strong></div>
    </div>
  </div>

  <?php if ($reservation_success): ?>
    <div class="alert-success">
      <i class="fas fa-check-circle"></i> <strong>Reservation submitted successfully!</strong> Your request is pending approval. You will be notified once approved.
    </div>
  <?php endif; ?>

  <?php if ($reservation_error): ?>
    <div class="alert-error">
      <i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> <?php echo $reservation_error; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="reservation-card">
      <!-- Student Information Section -->
      <div class="section-header">
        <i class="fas fa-id-card"></i>
        Student Information
      </div>
      <div class="form-body">
        <div class="form-row">
          <label>ID Number</label>
          <input type="text" value="<?= $student_id ?>" readonly />
        </div>
        <div class="form-row">
          <label>Student Name</label>
          <input type="text" value="<?= $student_name ?>" readonly />
        </div>
        <div class="form-row">
          <label>Purpose <span class="req">*</span></label>
          <select name="purpose" required>
            <option value="">— Select a purpose —</option>
            <option value="C Programming">C Programming</option>
            <option value="Java Programming">Java Programming</option>
            <option value="Web Development">Web Development</option>
            <option value="Database">Database</option>
            <option value="Research">Research</option>
            <option value="Thesis Writing">Thesis Writing</option>
            <option value="Online Class">Online Class</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-row">
          <label>Lab <span class="req">*</span></label>
          <select name="laboratory" required>
            <option value="">— Select a lab —</option>
            <option value="Lab 524">Lab 524</option>
            <option value="Lab 526">Lab 526</option>
            <option value="Lab 528">Lab 528</option>
            <option value="Lab 530">Lab 530</option>
            <option value="Lab 542">Lab 542</option>
          </select>
        </div>
      </div>

      <hr class="divider" />

      <!-- Reservation Schedule Section -->
      <div class="section-header">
        <i class="fas fa-calendar-alt"></i>
        Reservation Schedule
      </div>
      <div class="form-body">
        <div class="form-row">
          <label>Time In <span class="req">*</span></label>
          <input type="time" name="time_in" id="timeIn" required />
        </div>
        <div class="form-row">
          <label>Date <span class="req">*</span></label>
          <input type="date" name="reservation_date" id="reserveDate" required />
        </div>
        <div class="form-row">
          <label>Remaining Session</label>
          <div class="session-badge">
            <i class="fas fa-ticket-alt"></i>
            <span class="count"><?= $sessions_remaining ?></span>
            <span>sessions remaining</span>
          </div>
        </div>
      </div>
      <div class="btn-row">
        <button type="submit" name="reserve" class="btn btn-reserve" <?= $sessions_remaining <= 0 ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
          <i class="fas fa-check-circle"></i> Reserve
        </button>
        <button type="reset" class="btn btn-clear" onclick="resetForm()">
          <i class="fas fa-sync-alt"></i> Clear
        </button>
      </div>
    </div>
  </form>
</div>

<script>
  // Set default date and time
  (function setDefaults() {
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('reserveDate');
    if (dateInput) dateInput.value = today;
    
    const now = new Date();
    const timeInput = document.getElementById('timeIn');
    if (timeInput) {
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      timeInput.value = `${hours}:${minutes}`;
    }
  })();

  function resetForm() {
    const form = document.querySelector('form');
    if (form) form.reset();
    setDefaults();
    showToast('Form cleared', 'success');
  }

  function showToast(msg, type = 'success') {
    let toast = document.getElementById('toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'toast';
      toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #1E293B;
        color: white;
        padding: 12px 20px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 500;
        transform: translateY(60px);
        opacity: 0;
        transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.22s;
        z-index: 9999;
      `;
      document.body.appendChild(toast);
    }
    
    toast.textContent = msg;
    toast.style.background = type === 'success' ? '#10B981' : '#EF4444';
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
    
    setTimeout(() => {
      toast.style.transform = 'translateY(60px)';
      toast.style.opacity = '0';
    }, 3200);
  }
</script>

</body>
</html>