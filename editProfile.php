<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// ── Handle profile picture upload ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $last_name    = trim($_POST['last_name']    ?? '');
    $first_name   = trim($_POST['first_name']   ?? '');
    $middle_name  = trim($_POST['middle_name']  ?? '');
    $course_level = trim($_POST['course_level'] ?? '');
    $email        = trim($_POST['email']        ?? '');
    $course       = trim($_POST['course']       ?? '');
    $address      = trim($_POST['address']      ?? '');

    // Current pic (fallback)
    $profile_pic_path = $_SESSION['profile_pic'] ?? '';

    // Handle file upload
    if (!empty($_FILES['profile_pic']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file    = $_FILES['profile_pic'];
        $mime    = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed)) {
            $error = "Only JPG, PNG, GIF, or WEBP images are allowed.";
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $error = "Image must be under 3MB.";
        } else {
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $profile_pic_path = $dest;
            } else {
                $error = "Failed to upload image. Check folder permissions.";
            }
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("
            UPDATE students
            SET last_name=?, first_name=?, middle_name=?, course_level=?, email=?, course=?, address=?, profile_pic=?
            WHERE id=?
        ");
        $stmt->bind_param("ssssssssi",
            $last_name, $first_name, $middle_name,
            $course_level, $email, $course, $address,
            $profile_pic_path, $user_id
        );

        if ($stmt->execute()) {
            $_SESSION['last_name']    = $last_name;
            $_SESSION['name']         = $first_name;
            $_SESSION['middle_name']  = $middle_name;
            $_SESSION['course_level'] = $course_level;
            $_SESSION['email']        = $email;
            $_SESSION['course']       = $course;
            $_SESSION['address']      = $address;
            $_SESSION['profile_pic']  = $profile_pic_path;
            $success = "Changes saved!";
        } else {
            $error = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

// ── Fetch current data ──
$stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$id_number    = $user['id_number']    ?? $_SESSION['id_number']    ?? '';
$last_name    = $user['last_name']    ?? $_SESSION['last_name']    ?? '';
$first_name   = $user['first_name']   ?? $_SESSION['name']         ?? '';
$middle_name  = $user['middle_name']  ?? '';
$course_level = $user['course_level'] ?? $_SESSION['course_level'] ?? '';
$email        = $user['email']        ?? $_SESSION['email']        ?? '';
$course       = $user['course']       ?? $_SESSION['course']       ?? '';
$address      = $user['address']      ?? $_SESSION['address']      ?? '';
$profile_pic  = $user['profile_pic']  ?? $_SESSION['profile_pic']  ?? '';
$fullname     = trim($first_name . ' ' . $last_name);
$initials     = strtoupper(substr($first_name,0,1) . substr($last_name,0,1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CCS Student - Edit Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet"/>
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
    
    /* ========= FIXED BREADCRUMB WITH ALIGNED ICON ========= */
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
    /* ================================= */

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
      border: 1px solid white;
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

    /* ========= EDIT PROFILE CARD ========= */
    .profile-card {
      max-width: 680px;
      margin: 0 auto;
      background: white;
      border-radius: 24px;
      border: 1px solid #EFF3F8;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    }

    .profile-header {
      background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
      padding: 32px;
      text-align: center;
      position: relative;
    }

    .avatar-upload {
      position: relative;
      width: 120px;
      height: 120px;
      margin: 0 auto 16px;
      cursor: pointer;
    }

    .avatar-img {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
      border: 4px solid #3B82F6;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      transition: filter 0.2s;
    }
    .avatar-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .avatar-img span {
      font-size: 2.5rem;
      font-weight: 700;
      color: #3B82F6;
    }

    .avatar-overlay {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      background: rgba(0,0,0,0.6);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.2s;
      gap: 4px;
    }
    .avatar-upload:hover .avatar-overlay { opacity: 1; }
    .avatar-upload:hover .avatar-img { filter: brightness(0.95); }

    .avatar-overlay i { color: white; font-size: 1.3rem; }
    .avatar-overlay span { color: white; font-size: 0.7rem; font-weight: 500; }

    #pic-input { display: none; }

    .change-photo-btn {
      background: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.3);
      color: white;
      padding: 6px 16px;
      border-radius: 30px;
      font-size: 0.75rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    .change-photo-btn:hover {
      background: rgba(255,255,255,0.3);
    }

    .profile-name-large {
      color: white;
      font-size: 1.3rem;
      font-weight: 700;
      margin-top: 12px;
    }
    .profile-id-large {
      color: #94A3B8;
      font-size: 0.8rem;
      margin-top: 4px;
    }

    .preview-badge {
      margin-top: 8px;
      font-size: 0.7rem;
      color: #10B981;
      font-weight: 500;
      display: none;
    }
    .preview-badge.visible { display: block; }

    /* Form Body */
    .form-body {
      padding: 32px;
    }

    .section-title {
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      color: #3B82F6;
      margin-bottom: 16px;
      padding-bottom: 8px;
      border-bottom: 2px solid #F0F2F5;
    }

    .field { margin-bottom: 18px; }
    .field label {
      display: block;
      font-size: 0.7rem;
      font-weight: 600;
      color: #6C7A91;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .field-wrap {
      position: relative;
    }
    .field-wrap i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #9CA3AF;
      font-size: 0.85rem;
      pointer-events: none;
      transition: color 0.2s;
    }
    .field-wrap:focus-within i { color: #3B82F6; }
    .field-wrap input {
      width: 100%;
      border: 1.5px solid #E8ECF0;
      border-radius: 12px;
      padding: 12px 12px 12px 40px;
      font-family: 'Inter', sans-serif;
      font-size: 0.85rem;
      color: #1E293B;
      background: #FAFBFC;
      outline: none;
      transition: all 0.2s;
    }
    .field-wrap input:focus {
      border-color: #3B82F6;
      background: white;
      box-shadow: 0 0 0 3px rgba(59,130,246,0.08);
    }
    .field-wrap input[readonly] {
      background: #F3F4F6;
      color: #9CA3AF;
      cursor: not-allowed;
    }

    .row-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
    }

    /* Alert */
    .alert {
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 0.8rem;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 500;
    }
    .alert-success { background: #ECFDF5; color: #059669; border: 1px solid #D1FAE5; }
    .alert-error   { background: #FEF2F2; color: #DC2626; border: 1px solid #FEE2E2; }

    /* Save Button */
    .btn-save {
      width: 100%;
      margin-top: 24px;
      padding: 14px;
      background: #3B82F6;
      color: white;
      border: none;
      border-radius: 40px;
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-save:hover {
      background: #2563EB;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(59,130,246,0.3);
    }
    .btn-save:active { transform: translateY(0); }

    @media (max-width: 768px) {
      .main-content { margin-left: 0; padding: 16px; }
      .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
      .row-2 { grid-template-columns: 1fr; gap: 12px; }
      .form-body { padding: 20px; }
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
    <a href="editProfile.php" class="nav-item active"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="history.php" class="nav-item"><i class="fas fa-history"></i> History</a>
    <a href="reservation.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
    <a href="student_rules.php" class="nav-item"><i class="fas fa-gavel"></i> Lab Rules</a>
    <a href="student_rewards.php" class="nav-item"><i class="fas fa-gift"></i> Rewards/Points</a>
  </div>
  <div class="bottom-user">
    <div class="user-avatar"><?php echo $initials ?: '?'; ?></div>
    <div class="user-details">
      <h4><?= htmlspecialchars($fullname ?: 'Student') ?></h4>
      <p><?= htmlspecialchars($course ?: 'N/A') ?></p>
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
      <h1>Edit Profile</h1>
      <!-- BREADCRUMB WITH PROPERLY ALIGNED GREATER THAN ICON -->
      <div class="breadcrumb-links">
        <span>Home</span>
        <i class="fas fa-chevron-right"></i>
        <span>Edit Profile</span>
      </div>
    </div>
    <div class="header-actions">
      <div class="notif-btn">
        <i class="far fa-bell"></i>
        <div class="notif-dot"></div>
      </div>
      <div class="student-chip"><i class="fas fa-user"></i> CCS Student · <strong><?= htmlspecialchars($course ?: 'BSIT') ?></strong></div>
    </div>
  </div>

  <div class="profile-card">
    <!-- Profile Header with Avatar -->
    <div class="profile-header">
      <div class="avatar-upload" onclick="document.getElementById('pic-input').click()">
        <div class="avatar-img" id="avatar-display">
          <?php if (!empty($profile_pic) && file_exists($profile_pic)): ?>
            <img id="avatar-preview" src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile"/>
          <?php else: ?>
            <span id="avatar-initials"><?= htmlspecialchars($initials ?: '?') ?></span>
            <img id="avatar-preview" src="" alt="" style="display:none;"/>
          <?php endif; ?>
        </div>
        <div class="avatar-overlay">
          <i class="fas fa-camera"></i>
          <span>Change</span>
        </div>
      </div>
      <button type="button" class="change-photo-btn" onclick="document.getElementById('pic-input').click()">
        <i class="fas fa-camera" style="margin-right:6px;"></i>Change Photo
      </button>
      <div class="preview-badge" id="preview-badge">
        <i class="fas fa-check-circle"></i> New photo selected — save to apply
      </div>
      <div class="profile-name-large"><?= htmlspecialchars($fullname ?: 'Student') ?></div>
      <div class="profile-id-large">ID: <?= htmlspecialchars($id_number ?: '—') ?></div>
    </div>

    <!-- Form Body -->
    <div class="form-body">
      <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="editProfile.php" enctype="multipart/form-data">
        <input type="file" id="pic-input" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp"/>

        <div class="section-title">Personal Info</div>

        <div class="field">
          <label>ID Number</label>
          <div class="field-wrap">
            <i class="fas fa-id-card"></i>
            <input type="text" value="<?= htmlspecialchars($id_number) ?>" readonly/>
          </div>
        </div>

        <div class="row-2">
          <div class="field">
            <label>First Name</label>
            <div class="field-wrap">
              <i class="fas fa-user"></i>
              <input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" placeholder="First name"/>
            </div>
          </div>
          <div class="field">
            <label>Last Name</label>
            <div class="field-wrap">
              <i class="fas fa-user"></i>
              <input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" placeholder="Last name"/>
            </div>
          </div>
        </div>

        <div class="field">
          <label>Middle Name</label>
          <div class="field-wrap">
            <i class="fas fa-user-tag"></i>
            <input type="text" name="middle_name" value="<?= htmlspecialchars($middle_name) ?>" placeholder="Middle name"/>
          </div>
        </div>

        <div class="section-title" style="margin-top:28px;">Academic Info</div>

        <div class="row-2">
          <div class="field">
            <label>Course</label>
            <div class="field-wrap">
              <i class="fas fa-graduation-cap"></i>
              <input type="text" name="course" value="<?= htmlspecialchars($course) ?>" placeholder="e.g. BSIT"/>
            </div>
          </div>
          <div class="field">
            <label>Year Level</label>
            <div class="field-wrap">
              <i class="fas fa-layer-group"></i>
              <input type="text" name="course_level" value="<?= htmlspecialchars($course_level) ?>" placeholder="e.g. 3"/>
            </div>
          </div>
        </div>

        <div class="section-title" style="margin-top:28px;">Contact Info</div>

        <div class="field">
          <label>Email</label>
          <div class="field-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="you@email.com"/>
          </div>
        </div>

        <div class="field">
          <label>Address</label>
          <div class="field-wrap">
            <i class="fas fa-map-marker-alt"></i>
            <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" placeholder="City, Province"/>
          </div>
        </div>

        <button type="submit" class="btn-save">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </form>
    </div>
  </div>
</div>

<script>
  const picInput    = document.getElementById('pic-input');
  const preview     = document.getElementById('avatar-preview');
  const initials    = document.getElementById('avatar-initials');
  const badge       = document.getElementById('preview-badge');

  picInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!allowed.includes(file.type)) {
      alert('Please choose a JPG, PNG, GIF, or WEBP image.');
      this.value = '';
      return;
    }

    if (file.size > 3 * 1024 * 1024) {
      alert('Image must be under 3MB.');
      this.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
      preview.src   = e.target.result;
      preview.style.display = 'block';
      if (initials) initials.style.display = 'none';
      badge.classList.add('visible');
    };
    reader.readAsDataURL(file);
  });
</script>

</body>
</html>