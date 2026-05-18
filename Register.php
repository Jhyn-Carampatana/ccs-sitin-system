<?php
session_start();
$conn = new mysqli("localhost", "root", "", "jhyn");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";
$success_message = "";

// Initialize variables with default values
$id_number = "";
$email = "";
$last_name = "";
$first_name = "";
$middle_name = "";
$year_level = "Year 1";
$course = "BSIT";
$address = "";

// Initialize individual field errors
$errors = [
    'id_number' => '',
    'email' => '',
    'last_name' => '',
    'first_name' => '',
    'address' => '',
    'password' => '',
    'repeat_password' => ''
];

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $id_number = isset($_POST['id_number']) ? trim($_POST['id_number']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $year_level_num = isset($_POST['year_level']) ? $_POST['year_level'] : '1';
    $course = isset($_POST['course']) ? $_POST['course'] : 'BSIT';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $repeat_password = isset($_POST['repeat_password']) ? $_POST['repeat_password'] : '';
    
    // Convert year level number to text format
    $year_level = "Year " . $year_level_num;
    
    $has_errors = false;
    
    // Individual field validation
    if (empty($id_number)) {
        $errors['id_number'] = "ID Number is required";
        $has_errors = true;
    } elseif (!preg_match('/^\d{8}$/', $id_number)) {
        $errors['id_number'] = "ID Number must be 8 digits (e.g., 21450695)";
        $has_errors = true;
    }
    
    if (empty($email)) {
        $errors['email'] = "Email Address is required";
        $has_errors = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
        $has_errors = true;
    }
    
    if (empty($last_name)) {
        $errors['last_name'] = "Last Name is required";
        $has_errors = true;
    }
    
    if (empty($first_name)) {
        $errors['first_name'] = "First Name is required";
        $has_errors = true;
    }
    
    if (empty($address)) {
        $errors['address'] = "Address is required";
        $has_errors = true;
    }
    
    if (empty($password)) {
        $errors['password'] = "Password is required";
        $has_errors = true;
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters long";
        $has_errors = true;
    }
    
    if (empty($repeat_password)) {
        $errors['repeat_password'] = "Please confirm your password";
        $has_errors = true;
    } elseif ($password !== $repeat_password) {
        $errors['repeat_password'] = "Passwords do not match";
        $has_errors = true;
    }
    
    // Check if ID number or email already exists
    if (!$has_errors) {
        $check_sql = "SELECT id FROM students WHERE id_number = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $id_number, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $dup_check_sql = "SELECT id_number, email FROM students WHERE id_number = ? OR email = ?";
            $dup_stmt = $conn->prepare($dup_check_sql);
            $dup_stmt->bind_param("ss", $id_number, $email);
            $dup_stmt->execute();
            $dup_result = $dup_stmt->get_result();
            $dup_row = $dup_result->fetch_assoc();
            $dup_stmt->close();
            
            if ($dup_row && $dup_row['id_number'] == $id_number) {
                $errors['id_number'] = "ID Number already registered!";
                $has_errors = true;
            }
            if ($dup_row && $dup_row['email'] == $email) {
                $errors['email'] = "Email address already registered!";
                $has_errors = true;
            }
        } else {
            $check_stmt->close();
            
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new student - using correct column names
            $insert_sql = "INSERT INTO students (id_number, email, last_name, first_name, middle_name, year_level, course, address, sessions, password, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 30, ?, NOW())";
            
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssssssss", 
                $id_number, 
                $email, 
                $last_name, 
                $first_name, 
                $middle_name, 
                $year_level, 
                $course, 
                $address, 
                $hashed_password
            );
            
            if ($stmt->execute()) {
                $success_message = "Registration successful! Redirecting to login page...";
                // Clear form data
                $id_number = $email = $last_name = $first_name = $middle_name = $address = "";
                $year_level = "Year 1";
                $course = "BSIT";
                $errors = array_fill_keys(array_keys($errors), '');
                echo "<script>
                        setTimeout(function() {
                            window.location.href = 'Login.php';
                        }, 2000);
                      </script>";
            } else {
                $error_message = "Registration failed: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    if ($has_errors) {
        $error_message = "Please fix the errors below to continue.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CCS Sit-in Monitoring System · Register</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --primary: #1a6fc4;
      --primary-dark: #1358a0;
      --navy: #0f2d55;
      --white: #ffffff;
      --gray-50: #f8fafc;
      --gray-100: #f1f5f9;
      --gray-200: #e2e8f0;
      --gray-400: #94a3b8;
      --gray-500: #64748b;
      --gray-700: #334155;
      --danger: #dc2626;
      --success: #10b981;
      --warning: #f59e0b;
      --shadow-lg: 0 20px 60px rgba(15,45,85,0.18), 0 8px 24px rgba(15,45,85,0.10);
      --radius: 16px;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #0f2d55 0%, #1a5fa8 45%, #2176c7 75%, #1a9ed4 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      position: relative;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
      background-size: 40px 40px;
      pointer-events: none;
    }

    body::after {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 55% 45% at 10% 90%, rgba(59,158,255,0.18) 0%, transparent 60%),
        radial-gradient(ellipse 45% 55% at 90% 10%, rgba(255,255,255,0.08) 0%, transparent 55%);
      pointer-events: none;
    }

    nav {
      position: relative;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2.5rem;
      height: 60px;
      background: rgba(255,255,255,0.07);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255,255,255,0.10);
    }

    .nav-brand {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      text-decoration: none;
    }

    .nav-brand-icon {
      width: 32px; height: 32px;
      background: rgba(255,255,255,0.15);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.9rem;
      border: 1px solid rgba(255,255,255,0.2);
    }

    .nav-brand-text {
      font-size: 0.88rem;
      font-weight: 600;
      color: white;
    }

    .nav-brand-text span { font-weight: 300; opacity: 0.75; }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      list-style: none;
    }

    .nav-links a, .nav-links button {
      font-family: 'Poppins', sans-serif;
      font-size: 0.78rem;
      font-weight: 400;
      color: rgba(255,255,255,0.75);
      text-decoration: none;
      padding: 0.38rem 0.85rem;
      border-radius: 8px;
      border: none;
      background: none;
      cursor: pointer;
      transition: all 0.18s ease;
      display: flex; align-items: center; gap: 0.3rem;
    }

    .nav-links a:hover, .nav-links button:hover {
      color: white;
      background: rgba(255,255,255,0.12);
    }

    .nav-links .active { color: white; background: rgba(255,255,255,0.15); font-weight: 500; }
    .nav-links .btn-nav-login { color: var(--navy); background: white; font-weight: 500; padding: 0.38rem 1rem; }
    .nav-links .btn-nav-login:hover { background: var(--gray-100); color: var(--navy); }

    .alert {
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 1.2rem;
      font-size: 0.8rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideIn 0.3s ease;
    }

    .alert-error {
      background: #fee2e2;
      color: var(--danger);
      border-left: 4px solid var(--danger);
    }

    .alert-success {
      background: #d1fae5;
      color: var(--success);
      border-left: 4px solid var(--success);
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .field-error {
      color: var(--danger);
      font-size: 0.65rem;
      margin-top: 0.25rem;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .field-error i { font-size: 0.6rem; }
    
    .input-error {
      border-color: var(--danger) !important;
      background-color: #fff5f5 !important;
    }
    
    .input-error:focus {
      border-color: var(--danger) !important;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
    }

    main {
      position: relative;
      z-index: 1;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.5rem;
    }

    .card {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow-lg);
      width: 100%;
      max-width: 980px;
      display: grid;
      grid-template-columns: 300px 1fr;
      overflow: hidden;
      animation: cardIn 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(28px) scale(0.98); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .left-panel {
      background: linear-gradient(160deg, #418aeb 0%, #80b4ec 60%, #3896e2 100%);
      padding: 3rem 2rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      gap: 1.2rem;
    }

    .left-panel::before {
      content: '';
      position: absolute;
      top: -60px; right: -60px;
      width: 200px; height: 200px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.06);
    }

    .left-panel::after {
      content: '';
      position: absolute;
      bottom: -80px; left: -40px;
      width: 240px; height: 240px;
      border-radius: 50%;
      background: rgba(59,158,255,0.08);
    }

    .left-badge {
      position: relative; z-index: 1;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 50px;
      padding: 0.3rem 1rem;
      font-size: 0.65rem;
      font-weight: 500;
      color: white;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .logo-wrap {
      position: relative; z-index: 1;
      width: 160px; height: 160px;
      border-radius: 50%;
      background: rgba(255,255,255,0.08);
      border: 1.5px solid rgba(255,255,255,0.15);
      display: flex; align-items: center; justify-content: center;
      padding: 1.2rem;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.1);
    }

    .logo-wrap img {
      width: 85%; height: 85%;
      object-fit: contain;
      filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
    }

    .left-text {
      position: relative; z-index: 1;
      text-align: center;
    }

    .left-text h2 {
      font-size: 0.95rem;
      font-weight: 600;
      color: white;
      margin-bottom: 0.3rem;
    }

    .left-text p {
      font-size: 0.68rem;
      color: white;
      font-weight: 300;
      line-height: 1.6;
    }

    .left-dots {
      position: relative; z-index: 1;
      display: flex; gap: 6px;
    }

    .left-dots span {
      width: 5px; height: 5px;
      border-radius: 50%;
      background: rgba(255,255,255,0.25);
    }

    .left-dots span.active { background: white; width: 18px; border-radius: 3px; }

    .right-panel {
      padding: 2.5rem 2.8rem;
      display: flex;
      flex-direction: column;
      background: white;
      overflow-y: auto;
      max-height: 80vh;
    }

    .form-header {
      margin-bottom: 1.5rem;
      animation: fadeUp 0.5s ease 0.15s both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .form-header .eyebrow {
      font-size: 0.65rem;
      font-weight: 600;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--primary);
      margin-bottom: 0.4rem;
    }

    .form-header h1 {
      font-size: 1.45rem;
      font-weight: 700;
      color: var(--navy);
      letter-spacing: -0.025em;
      line-height: 1.2;
      margin-bottom: 0.3rem;
    }

    .form-header p {
      font-size: 0.78rem;
      color: var(--gray-400);
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-family: 'Poppins', sans-serif;
      font-size: 0.72rem;
      font-weight: 500;
      color: var(--primary);
      background: var(--gray-100);
      border: none;
      border-radius: 8px;
      padding: 0.35rem 0.8rem;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.18s;
      margin-bottom: 1.2rem;
      width: fit-content;
    }

    .btn-back:hover { background: var(--gray-200); }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.9rem 1.2rem;
    }

    .field { 
      display: flex; 
      flex-direction: column; 
      gap: 0.35rem;
    }

    .field label {
      font-size: 0.7rem;
      font-weight: 600;
      color: var(--gray-700);
      letter-spacing: 0.03em;
    }

    .field-input-wrap { position: relative; }

    .field-input-wrap svg {
      position: absolute;
      left: 12px; top: 50%;
      transform: translateY(-50%);
      width: 15px; height: 15px;
      color: var(--gray-400);
      pointer-events: none;
      transition: color 0.2s;
    }

    .field-input-wrap input,
    .field-input-wrap select {
      width: 100%;
      padding: 0.68rem 0.9rem 0.68rem 2.5rem;
      border: 1.5px solid var(--gray-200);
      border-radius: 9px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.8rem;
      color: var(--gray-700);
      background: var(--gray-50);
      outline: none;
      transition: all 0.2s ease;
      appearance: none;
    }

    .field-input-wrap input:focus,
    .field-input-wrap select:focus {
      border-color: var(--primary);
      background: white;
      box-shadow: 0 0 0 3px rgba(26,111,196,0.10);
    }

    .field-input-wrap:focus-within svg { color: var(--primary); }

    .btn-register {
      margin-top: 1.4rem;
      width: 100%;
      padding: 0.82rem 1rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      border: none;
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      letter-spacing: 0.04em;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(26,111,196,0.38);
      transition: transform 0.15s ease, box-shadow 0.2s ease;
    }

    .btn-register::before {
      content: '';
      position: absolute;
      top: 0; left: -100%;
      width: 60%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
      transition: left 0.5s ease;
    }

    .btn-register:hover::before { left: 160%; }
    .btn-register:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(26,111,196,0.45); }
    .btn-register:active { transform: translateY(0); }

    .login-link {
      text-align: center;
      margin-top: 1rem;
      font-size: 0.78rem;
      color: var(--gray-500);
    }

    .login-link a {
      color: var(--primary);
      font-weight: 600;
      text-decoration: none;
    }

    .login-link a:hover { opacity: 0.75; }

    footer {
      position: relative; z-index: 1;
      padding: 1rem 2.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-top: 1px solid rgba(255,255,255,0.07);
    }

    .footer-copy {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.85);
      font-weight: 300;
    }

    .footer-links { display: flex; gap: 1.5rem; }

    .footer-links a {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.85);
      text-decoration: none;
      transition: color 0.2s;
    }

    .footer-links a:hover { color: rgba(255,255,255,0.65); }

    @media (max-width: 720px) {
      .card { grid-template-columns: 1fr; }
      .left-panel { padding: 2rem; flex-direction: row; flex-wrap: wrap; justify-content: center; gap: 0.8rem; }
      .logo-wrap { width: 70px; height: 70px; padding: 0.6rem; }
      .left-text p, .left-badge, .left-dots { display: none; }
      .right-panel { padding: 2rem 1.5rem; }
      .form-grid { grid-template-columns: 1fr; }
      footer { flex-direction: column; gap: 0.5rem; text-align: center; }
    }
  </style>
</head>
<body>

  <nav>
    <a class="nav-brand" href="#">
      <div class="nav-brand-icon">🎓</div>
      <span class="nav-brand-text">CCS <span>Sit-in Monitoring System</span></span>
    </a>
    <ul class="nav-links">
      <li><a href="#">Home</a></li>
      <li><button>Community <span style="font-size:0.6rem;opacity:0.7">▾</span></button></li>
      <li><a href="#">About</a></li>
      <li><a href="Login.php">Login</a></li>
      <li><a href="#" class="btn-nav-login">Register</a></li>
    </ul>
  </nav>

  <main>
    <div class="card">

      <div class="left-panel">
        <div class="left-badge">UC · Est. 1983</div>
        <div class="logo-wrap">
          <img src="University_of_Cebu_Logo.png" alt="University of Cebu Logo"/>
        </div>
        <div class="left-text">
          <h2>University of Cebu</h2>
          <p>College of Computer Studies<br/>Inceptum · Innovatio · Muneris</p>
        </div>
        <div class="left-dots">
          <span></span><span class="active"></span><span></span>
        </div>
      </div>

      <div class="right-panel">
        <form action="Register.php" method="POST">
          <a class="btn-back" href="Login.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            Back
          </a>

          <div class="form-header">
            <p class="eyebrow">Registration</p>
            <h1>Create your account</h1>
            <p>Fill in your details to get started</p>
          </div>

          <?php if (!empty($error_message) && $error_message != "Please fix the errors below to continue."): ?>
            <div class="alert alert-error">
              <span>⚠️</span>
              <?php echo $error_message; ?>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
              <span>✓</span>
              <?php echo $success_message; ?>
            </div>
          <?php endif; ?>

          <div class="form-grid">
            <div class="field">
              <label>ID Number</label>
              <div class="field-input-wrap">
                <input type="text" name="id_number" placeholder="e.g., 21450695" value="<?php echo htmlspecialchars($id_number); ?>" class="<?php echo !empty($errors['id_number']) ? 'input-error' : ''; ?>" required/>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
              </div>
              <?php if (!empty($errors['id_number'])): ?>
                <div class="field-error">
                  <span>⚠️</span> <?php echo $errors['id_number']; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label>Email Address</label>
              <div class="field-input-wrap">
                <input type="email" name="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>" class="<?php echo !empty($errors['email']) ? 'input-error' : ''; ?>" required/>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              </div>
              <?php if (!empty($errors['email'])): ?>
                <div class="field-error">
                  <span>⚠️</span> <?php echo $errors['email']; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label>Last Name</label>
              <div class="field-input-wrap">
                <input type="text" name="last_name" placeholder="Enter last name" value="<?php echo htmlspecialchars($last_name); ?>" class="<?php echo !empty($errors['last_name']) ? 'input-error' : ''; ?>" required/>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <?php if (!empty($errors['last_name'])): ?>
                <div class="field-error">
                  <span>⚠️</span> <?php echo $errors['last_name']; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label>First Name</label>
              <div class="field-input-wrap">
                <input type="text" name="first_name" placeholder="Enter first name" value="<?php echo htmlspecialchars($first_name); ?>" class="<?php echo !empty($errors['first_name']) ? 'input-error' : ''; ?>" required/>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <?php if (!empty($errors['first_name'])): ?>
                <div class="field-error">
                  <span>⚠️</span> <?php echo $errors['first_name']; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label>Middle Name</label>
              <div class="field-input-wrap">
                <input type="text" name="middle_name" placeholder="Enter middle name" value="<?php echo htmlspecialchars($middle_name); ?>"/>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
            </div>

            <div class="field">
              <label>Year Level</label>
              <div class="field-input-wrap">
                <select name="year_level">
                  <option value="1" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '1') ? 'selected' : ''; ?>>Year 1</option>
                  <option value="2" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '2') ? 'selected' : ''; ?>>Year 2</option>
                  <option value="3" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '3') ? 'selected' : ''; ?>>Year 3</option>
                  <option value="4" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '4') ? 'selected' : ''; ?>>Year 4</option>
                </select>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
              </div>
            </div>

            <div class="field">
              <label>Course</label>
              <div class="field-input-wrap">
                <select name="course">
                  <option value="BSIT" <?php echo $course == 'BSIT' ? 'selected' : ''; ?>>BSIT</option>
                  <option value="BSCS" <?php echo $course == 'BSCS' ? 'selected' : ''; ?>>BSCS</option>
                  <option value="BSIS" <?php echo $course == 'BSIS' ? 'selected' : ''; ?>>BSIS</option>   
                  <option value="ACT" <?php echo $course == 'ACT' ? 'selected' : ''; ?>>ACT</option>
                </select>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
              </div>
            </div>

            <div class="field">
              <label>Address</label>
              <div class="field-input-wrap">
                <input type="text" name="address" placeholder="Enter your address" value="<?php echo htmlspecialchars($address); ?>" class="<?php echo !empty($errors['address']) ? 'input-error' : ''; ?>" required/>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
              </div>
              <?php if (!empty($errors['address'])): ?>
                <div class="field-error">
                  <span>⚠️</span> <?php echo $errors['address']; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label>Password</label>
              <div class="field-input-wrap">
                <input type="password" name="password" placeholder="Create a password (min. 6 characters)" class="<?php echo !empty($errors['password']) ? 'input-error' : ''; ?>" required/>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </div>
              <?php if (!empty($errors['password'])): ?>
                <div class="field-error">
                  <span>⚠️</span> <?php echo $errors['password']; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label>Repeat Password</label>
              <div class="field-input-wrap">
                <input type="password" name="repeat_password" placeholder="Confirm your password" class="<?php echo !empty($errors['repeat_password']) ? 'input-error' : ''; ?>" required/>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </div>
              <?php if (!empty($errors['repeat_password'])): ?>
                <div class="field-error">
                  <span>⚠️</span> <?php echo $errors['repeat_password']; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <button type="submit" name="submit" class="btn-register">Create Account</button>

          <p class="login-link">Already have an account? <a href="Login.php">Sign in here</a></p>
        </form>
      </div>

    </div>
  </main>

  <footer>
    <span class="footer-copy">&copy; 2024 College of Computer Studies · University of Cebu</span>
    <div class="footer-links">
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Use</a>
      <a href="#">Support</a>
    </div>
  </footer>

</body>
</html>