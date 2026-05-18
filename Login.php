<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "jhyn");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ============================================
// CREATE MISSING TABLES AUTOMATICALLY
// ============================================

// Create students table
$create_students_table = "
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) DEFAULT '',
    middle_name VARCHAR(100) DEFAULT '',
    year_level VARCHAR(20) DEFAULT 'Year 1',
    course VARCHAR(20) DEFAULT 'BSIT',
    email VARCHAR(100) DEFAULT '',
    address TEXT DEFAULT '',
    sessions_used INT DEFAULT 0,
    sessions_total INT DEFAULT 30,
    profile_pic VARCHAR(255) DEFAULT '',
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create admin table if not exists
$create_admin_table = "
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create announcements table if not exists
$create_announcements_table = "
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) DEFAULT '',
    content TEXT NOT NULL,
    created_by VARCHAR(100) DEFAULT 'CCS Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create sit_in_records table if not exists
$create_sitin_table = "
CREATE TABLE IF NOT EXISTS sit_in_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    lab VARCHAR(20) NOT NULL,
    session_time VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'Pending',
    login_time DATETIME DEFAULT NULL,
    logout_time DATETIME DEFAULT NULL,
    date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create notifications table if not exists
$create_notifications_table = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'announcement',
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Execute table creation
$conn->query($create_students_table);
$conn->query($create_admin_table);
$conn->query($create_announcements_table);
$conn->query($create_sitin_table);
$conn->query($create_notifications_table);

// ============================================
// INSERT DEFAULT DATA IF TABLES ARE EMPTY
// ============================================

// Insert default admin if none exists
$check_admin = $conn->query("SELECT COUNT(*) as count FROM admin");
if ($check_admin && $check_admin->fetch_assoc()['count'] == 0) {
    $default_admin_password = password_hash("admin123", PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO admin (username, password, full_name) VALUES 
        ('admin', '$default_admin_password', 'CCS Administrator')";
    $conn->query($insert_admin);
}

// Insert default test students if none exists
$check_student = $conn->query("SELECT COUNT(*) as count FROM students");
if ($check_student && $check_student->fetch_assoc()['count'] == 0) {
    $default_password = password_hash("password123", PASSWORD_DEFAULT);
    $insert_default = "INSERT INTO students (id_number, first_name, last_name, email, course, year_level, sessions_used, sessions_total, password) VALUES 
        ('2024-0001', 'John', 'Doe', 'john.doe@example.com', 'BSIT', 'Year 3', 0, 30, '$default_password'),
        ('2024-0002', 'Jane', 'Smith', 'jane.smith@example.com', 'BSCS', 'Year 2', 0, 30, '$default_password'),
        ('2024-0003', 'Michael', 'Santos', 'michael.santos@example.com', 'BSIT', 'Year 1', 0, 30, '$default_password')";
    $conn->query($insert_default);
}

// Insert default announcement if none exists
$check_announcement = $conn->query("SELECT COUNT(*) as count FROM announcements");
if ($check_announcement && $check_announcement->fetch_assoc()['count'] == 0) {
    $insert_announcement = "INSERT INTO announcements (title, content, created_by) VALUES 
        ('Welcome to CCS Sit-in Monitoring System', 'This system allows you to manage sit-in sessions, track student attendance, and monitor laboratory usage.', 'CCS Admin')";
    $conn->query($insert_announcement);
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_num = trim($_POST['id_number']);
    $pass   = $_POST['password'];
    
    // CHECK ADMIN LOGIN FIRST
    $sql = "SELECT * FROM admin WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id_num);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($admin = $result->fetch_assoc()) {
        if (password_verify($pass, $admin['password'])) {
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name']     = $admin['full_name'];
            $_SESSION['is_admin']       = true;
            
            $stmt->close();
            $conn->close();
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error_message = "Invalid admin password!";
        }
        $stmt->close();
    } else {
        // CHECK STUDENT LOGIN
        $sql = "SELECT * FROM students WHERE id_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id_num);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($pass, $user['password'])) {
                // Calculate remaining sessions
                $sessions_used = $user['sessions_used'] ?? 0;
                $sessions_total = $user['sessions_total'] ?? 30;
                $sessions_remaining = $sessions_total - $sessions_used;
                
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['id_number']    = $user['id_number'];
                $_SESSION['name']         = $user['first_name'];
                $_SESSION['last_name']    = $user['last_name']    ?? '';
                $_SESSION['middle_name']  = $user['middle_name']  ?? '';
                $_SESSION['email']        = $user['email']        ?? '';
                $_SESSION['course']       = $user['course']       ?? '';
                $_SESSION['course_level'] = $user['year_level']   ?? '';
                $_SESSION['address']      = $user['address']      ?? '';
                $_SESSION['sessions_used'] = $sessions_used;
                $_SESSION['sessions']     = $sessions_remaining;
                $_SESSION['profile_pic']  = $user['profile_pic']  ?? '';
                $_SESSION['is_admin']     = false;
                
                $stmt->close();
                $conn->close();
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Invalid password!";
            }
        } else {
            $error_message = "ID Number / Username not found!";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CCS Sit-in Monitoring System</title>
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
      --shadow-lg: 0 20px 60px rgba(15,45,85,0.18), 0 8px 24px rgba(15,45,85,0.10);
      --radius: 16px;
    }

    html, body { height: 100%; }

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
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
      background-size: 40px 40px;
      pointer-events: none;
    }

    body::after {
      content: '';
      position: fixed; inset: 0;
      background:
        radial-gradient(ellipse 55% 45% at 10% 90%, rgba(59,158,255,0.18) 0%, transparent 60%),
        radial-gradient(ellipse 45% 55% at 90% 10%, rgba(255,255,255,0.08) 0%, transparent 55%);
      pointer-events: none;
    }

    /* NAV */
    nav {
      position: relative; z-index: 100;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 2.5rem; height: 60px;
      background: rgba(255,255,255,0.07);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255,255,255,0.10);
    }

    .nav-brand { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; }

    .system-logo {
      width: 40px; height: 40px; object-fit: contain;
      background-color: rgba(255,255,255,0.12);
      padding: 5px; border-radius: 8px;
    }

    .nav-brand-text { font-size: 0.88rem; font-weight: 600; color: white; }
    .nav-brand-text span { font-weight: 300; opacity: 0.75; }

    .nav-links { display: flex; align-items: center; gap: 0.25rem; list-style: none; }

    .nav-links a,
    .nav-links button {
      font-family: 'Poppins', sans-serif;
      font-size: 0.78rem; font-weight: 400;
      color: rgba(255,255,255,0.75);
      text-decoration: none;
      padding: 0.38rem 0.85rem;
      border-radius: 8px; border: none; background: none;
      cursor: pointer; transition: all 0.18s ease;
      display: flex; align-items: center; gap: 0.3rem;
    }

    .nav-links a:hover, .nav-links button:hover {
      color: white; background: rgba(255,255,255,0.12);
    }

    .nav-links .active { color: white; background: rgba(255,255,255,0.15); font-weight: 500; }

    .nav-links .btn-nav-register {
      color: var(--navy); background: white; font-weight: 500; padding: 0.38rem 1rem;
    }
    .nav-links .btn-nav-register:hover { background: var(--gray-100); }

    /* MAIN */
    main {
      position: relative; z-index: 1; flex: 1;
      display: flex; align-items: center; justify-content: center;
      padding: 2rem 1.5rem;
    }

    .card {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow-lg);
      width: 100%; max-width: 900px;
      display: grid; grid-template-columns: 1fr 1fr;
      overflow: hidden;
      animation: cardIn 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(28px) scale(0.98); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* LEFT */
    .left-panel {
      background: linear-gradient(160deg, #418aeb 0%, #80b4ec 60%, #3896e2 100%);
      padding: 3.5rem 2.5rem;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      position: relative; overflow: hidden; gap: 1.5rem;
    }

    .left-panel::before {
      content: ''; position: absolute;
      top: -60px; right: -60px;
      width: 200px; height: 200px; border-radius: 50%;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.06);
    }

    .left-panel::after {
      content: ''; position: absolute;
      bottom: -80px; left: -40px;
      width: 240px; height: 240px; border-radius: 50%;
      background: rgba(59,158,255,0.08);
    }

    .left-badge {
      position: relative; z-index: 1;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 50px; padding: 0.3rem 1rem;
      font-size: 0.65rem; font-weight: 500; color: white;
      letter-spacing: 0.12em; text-transform: uppercase;
    }

    .logo-wrap {
      position: relative; z-index: 1;
      width: 200px; height: 200px; border-radius: 50%;
      background: rgba(255,255,255,0.08);
      border: 1.5px solid rgba(255,255,255,0.15);
      display: flex; align-items: center; justify-content: center;
      padding: 1.5rem;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.1);
      animation: logoIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
    }

    @keyframes logoIn {
      from { opacity: 0; transform: scale(0.8); }
      to   { opacity: 1; transform: scale(1); }
    }

    .logo-wrap img { width: 100%; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3)); }

    .left-text { position: relative; z-index: 1; text-align: center; }
    .left-text h2 { font-size: 1rem; font-weight: 600; color: white; margin-bottom: 0.35rem; }
    .left-text p  { font-size: 0.72rem; color: white; font-weight: 300; line-height: 1.6; }

    .left-dots { position: relative; z-index: 1; display: flex; gap: 6px; }
    .left-dots span { width: 5px; height: 5px; border-radius: 50%; background: rgba(255,255,255,0.25); }
    .left-dots span.active { background: white; width: 18px; border-radius: 3px; }

    /* RIGHT */
    .right-panel {
      padding: 3.5rem 3rem;
      display: flex; flex-direction: column; justify-content: center;
      background: white;
    }

    .form-header { margin-bottom: 2rem; animation: fadeUp 0.5s ease 0.15s both; }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .form-header .eyebrow {
      font-size: 0.65rem; font-weight: 600;
      letter-spacing: 0.14em; text-transform: uppercase;
      color: var(--primary); margin-bottom: 0.5rem;
    }

    .form-header h1 {
      font-size: 1.6rem; font-weight: 700; color: var(--navy);
      letter-spacing: -0.025em; line-height: 1.2; margin-bottom: 0.4rem;
    }

    .form-header p { font-size: 0.8rem; color: var(--gray-400); }

    .field { margin-bottom: 1.1rem; animation: fadeUp 0.5s ease both; }
    .field:nth-of-type(1) { animation-delay: 0.22s; }
    .field:nth-of-type(2) { animation-delay: 0.30s; }

    .field-label {
      display: block; font-size: 0.72rem; font-weight: 600;
      color: var(--gray-700); margin-bottom: 0.45rem; letter-spacing: 0.03em;
    }

    .field-input-wrap { position: relative; }

    .field-input-wrap svg {
      position: absolute; left: 13px; top: 50%;
      transform: translateY(-50%);
      width: 16px; height: 16px; color: var(--gray-400);
      transition: color 0.2s; pointer-events: none;
    }

    .field-input-wrap input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.75rem;
      border: 1.5px solid var(--gray-200); border-radius: 10px;
      font-family: 'Poppins', sans-serif; font-size: 0.83rem;
      color: var(--gray-700); background: var(--gray-50);
      outline: none; transition: all 0.2s ease;
    }

    .field-input-wrap input::placeholder { color: var(--gray-400); font-weight: 300; }

    .field-input-wrap input:focus {
      border-color: var(--primary); background: white;
      box-shadow: 0 0 0 3px rgba(26,111,196,0.10);
    }

    .field-input-wrap:focus-within svg { color: var(--primary); }

    .options-row {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 1.6rem; animation: fadeUp 0.5s ease 0.36s both;
    }

    .remember-label {
      display: flex; align-items: center; gap: 0.5rem;
      cursor: pointer; font-size: 0.76rem; color: var(--gray-500);
    }

    .remember-label input[type="checkbox"] {
      width: 15px; height: 15px; accent-color: var(--primary);
    }

    .forgot-link {
      font-size: 0.76rem; color: var(--primary);
      text-decoration: none; font-weight: 500;
    }
    .forgot-link:hover { opacity: 0.7; }

    .btn-login {
      width: 100%; padding: 0.85rem 1rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white; border: none; border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.85rem; font-weight: 600; cursor: pointer;
      letter-spacing: 0.04em; position: relative; overflow: hidden;
      box-shadow: 0 4px 16px rgba(26,111,196,0.38);
      transition: transform 0.15s ease, box-shadow 0.2s ease;
      animation: fadeUp 0.5s ease 0.42s both;
    }

    .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(26,111,196,0.48); }
    .btn-login:active { transform: translateY(0); }

    .btn-login::before {
      content: ''; position: absolute; top: 0; left: -100%;
      width: 60%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
      transition: left 0.5s ease;
    }
    .btn-login:hover::before { left: 160%; }

    .divider {
      display: flex; align-items: center; gap: 0.75rem;
      margin: 1.3rem 0; animation: fadeUp 0.5s ease 0.48s both;
    }
    .divider hr { flex: 1; border: none; border-top: 1px solid var(--gray-200); }
    .divider span { font-size: 0.7rem; color: var(--gray-400); }

    .register-link {
      text-align: center; font-size: 0.78rem; color: var(--gray-500);
      animation: fadeUp 0.5s ease 0.52s both;
    }
    .register-link a { color: var(--danger); font-weight: 600; text-decoration: none; }
    .register-link a:hover { opacity: 0.75; }

    .alert {
      padding: 12px 16px; border-radius: 10px;
      margin-bottom: 1rem; font-size: 0.8rem; font-weight: 500;
      background: #fee2e2; color: var(--danger);
      border-left: 4px solid var(--danger);
    }

    .demo-credentials {
      background: #e0f2fe;
      padding: 10px 15px;
      border-radius: 10px;
      margin-top: 15px;
      font-size: 0.7rem;
      color: #0369a1;
      text-align: center;
    }

    footer {
      position: relative; z-index: 1;
      padding: 1.1rem 2.5rem;
      display: flex; align-items: center; justify-content: space-between;
      border-top: 1px solid rgba(255,255,255,0.07);
    }

    .footer-copy { font-size: 0.72rem; color: rgba(255,255,255,0.85); font-weight: 300; }
    .footer-links { display: flex; gap: 1.5rem; }
    .footer-links a { font-size: 0.72rem; color: rgba(255,255,255,0.85); text-decoration: none; }
    .footer-links a:hover { color: rgba(255,255,255,0.65); }

    @media (max-width: 680px) {
      .card { grid-template-columns: 1fr; max-width: 420px; }
      .left-panel { padding: 2rem; flex-direction: row; flex-wrap: wrap; justify-content: center; gap: 1rem; }
      .logo-wrap { width: 80px; height: 80px; padding: 0.75rem; }
      .left-text p, .left-badge, .left-dots { display: none; }
      .right-panel { padding: 2.5rem 1.8rem; }
      footer { flex-direction: column; gap: 0.5rem; text-align: center; }
    }
  </style>
</head>
<body>

  <nav>
    <a class="nav-brand" href="#">
      <img src="ccslogo.png" alt="CCS Logo" class="system-logo" onerror="this.src='https://via.placeholder.com/40'">
      <span class="nav-brand-text">CCS <span>Sit-in Monitoring System</span></span>
    </a>
    <ul class="nav-links">
      <li><a href="#" class="active">Home</a></li>
      <li><button>Community <span>▾</span></button></li>
      <li><a href="#">About</a></li>
      <li><a href="Login.php" class="btn-nav-register">Login</a></li>
      <li><a href="Register.php">Register</a></li>
    </ul>
  </nav>

  <main>
    <div class="card">
      <div class="left-panel">
        <div class="left-badge">UC · Est. 1983</div>
        <div class="logo-wrap">
          <img src="University_of_Cebu_Logo.png" alt="University of Cebu Logo" onerror="this.src='https://via.placeholder.com/150'"/>
        </div>
        <div class="left-text">
          <h2>University of Cebu</h2>
          <p>College of Computer Studies<br/>Inceptum · Innovatio · Muneris</p>
        </div>
        <div class="left-dots">
          <span class="active"></span><span></span><span></span>
        </div>
      </div>

      <div class="right-panel">
        <div class="form-header">
          <p class="eyebrow">Access Portal</p>
          <h1>Sign in to<br/>your account</h1>
          <p>Enter your credentials to continue</p>
        </div>

        <?php if ($error_message): ?>
          <div class="alert"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="Login.php">
          <div class="field">
            <label class="field-label">ID Number / Username</label>
            <div class="field-input-wrap">
              <input type="text" name="id_number"
                     placeholder="Enter ID Number/Username"
                     value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>" required/>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
              </svg>
            </div>
          </div>

          <div class="field">
            <label class="field-label">Password</label>
            <div class="field-input-wrap">
              <input type="password" name="password" placeholder="Enter Password" required/>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </div>
          </div>

          <div class="options-row">
            <label class="remember-label">
              <input type="checkbox"/> Remember me
            </label>
            <a class="forgot-link" href="#">Forgot password?</a>
          </div>

          <button type="submit" class="btn-login">Sign In</button>
        </form>

        
        <div class="divider"><hr/><span>or</span><hr/></div>

        <p class="register-link">Don't have an account? <a href="Register.php">Register here</a></p>
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