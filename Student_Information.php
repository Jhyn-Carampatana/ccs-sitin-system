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

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $id_number = trim($_POST['id_number']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $user_name = trim($_POST['user_name']);
    $year_level = trim($_POST['year_level']);
    $course = trim($_POST['course']);
    $email = trim($_POST['email']);
    $password = password_hash($id_number, PASSWORD_DEFAULT);
    $sessions = trim($_POST['sessions']);
    
    $stmt = $conn->prepare("INSERT INTO students (id_number, user_name, first_name, middle_name, last_name, year_level, course, email, password, sessions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssi", $id_number, $user_name, $first_name, $middle_name, $last_name, $year_level, $course, $email, $password, $sessions);
    if ($stmt->execute()) {
        $success_msg = "Student added successfully!";
    } else {
        $error_msg = "Error adding student: " . $conn->error;
    }
    $stmt->close();
}

// Handle Delete Student
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success_msg = "Student deleted successfully!";
    }
    $stmt->close();
}

// Handle Reset All Sessions
if (isset($_POST['reset_sessions'])) {
    $stmt = $conn->prepare("UPDATE students SET sessions = 30");
    if ($stmt->execute()) {
        $success_msg = "All student sessions have been reset to 30!";
    }
    $stmt->close();
}

// Handle Edit Student via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $student_id = $_POST['student_id'];
    $id_number = trim($_POST['id_number']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $year_level = trim($_POST['year_level']);
    $course = trim($_POST['course']);
    $email = trim($_POST['email']);
    $sessions = trim($_POST['sessions']);
    
    $stmt = $conn->prepare("UPDATE students SET id_number=?, first_name=?, middle_name=?, last_name=?, year_level=?, course=?, email=?, sessions=? WHERE id=?");
    $stmt->bind_param("ssssssssi", $id_number, $first_name, $middle_name, $last_name, $year_level, $course, $email, $sessions, $student_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit();
}

// Pagination variables
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $entries_per_page;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build search query
$search_condition = "";
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $search_condition = "WHERE id_number LIKE '%$search_escaped%' OR first_name LIKE '%$search_escaped%' OR last_name LIKE '%$search_escaped%' OR course LIKE '%$search_escaped%' OR email LIKE '%$search_escaped%'";
}

// Get total records
$total_query = "SELECT COUNT(*) as total FROM students $search_condition";
$total_result = $conn->query($total_query);
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $entries_per_page);

// Get students for current page
$query = "SELECT id, id_number, first_name, middle_name, last_name, year_level, course, email, sessions, total_points FROM students $search_condition ORDER BY id DESC LIMIT $offset, $entries_per_page";
$students = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>CCS Admin - Student Information</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    background: #F5F7FB;
    font-family: 'Inter', sans-serif;
    display: flex;
    min-height: 100vh;
  }

  /* Sidebar */
  .sidebar {
    width: 260px;
    background: #FFFFFF;
    border-right: 1px solid #E9EEF3;
    position: fixed;
    height: 100vh;
    padding: 28px 20px;
    display: flex;
    flex-direction: column;
  }

  .logo-area {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 40px;
  }

  .logo-image {
    width: 38px;
    height: 38px;
    object-fit: contain;
    border-radius: 10px;
  }

  .logo-icon {
    background: #3B82F6;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
  }

  .logo-text {
    font-weight: 800;
    font-size: 20px;
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
    text-decoration: none;
    transition: all 0.2s;
  }

  .nav-item:hover {
    background: #F1F5F9;
    color: #1E293B;
  }

  .nav-item.active {
    background: #EFF6FF;
    color: #3B82F6;
  }

  .nav-item i {
    width: 20px;
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
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
  }

  .logout-icon {
    margin-left: auto;
    color: #EF4444;
    text-decoration: none;
  }

  .logout-icon:hover {
    opacity: 0.8;
  }

  /* Main Content */
  .main-content {
    margin-left: 260px;
    flex: 1;
    padding: 28px 36px;
  }

  /* Header */
  .header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
  }

  .header-section h1 {
    font-size: 26px;
    font-weight: 700;
    color: #0F172A;
  }

  .action-buttons {
    display: flex;
    gap: 12px;
  }

  /* Buttons */
  .btn {
    padding: 10px 20px;
    border-radius: 40px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .btn-primary {
    background: #3B82F6;
    color: white;
  }

  .btn-primary:hover {
    background: #2563EB;
    transform: translateY(-1px);
  }

  .btn-warning {
    background: #F59E0B;
    color: white;
  }

  .btn-warning:hover {
    background: #D97706;
    transform: translateY(-1px);
  }

  /* Table Card */
  .table-card {
    background: white;
    border-radius: 24px;
    border: 1px solid #EFF3F8;
    overflow: hidden;
  }

  /* Toolbar */
  .toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    background: white;
    border-bottom: 1px solid #EDF2F7;
    flex-wrap: wrap;
    gap: 12px;
  }

  .entries-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #5B6E8C;
  }

  .entries-select {
    padding: 10px 32px 10px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    background: white;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236C7A91' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
  }

  .search-box {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .search-box input {
    padding: 10px 16px;
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    font-size: 14px;
    width: 320px;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s;
  }

  .search-box input:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }

  .search-box input::placeholder {
    color: #94A3B8;
  }

  .search-box button {
    background: #3B82F6;
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
  }

  .search-box button:hover {
    background: #2563EB;
    transform: translateY(-1px);
  }

  /* Table */
  .table-wrapper {
    overflow-x: auto;
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }

  th {
    text-align: left;
    padding: 14px 16px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    color: #6C7A91;
    background: #FCFDFF;
    border-bottom: 1px solid #EDF2F7;
  }

  td {
    padding: 14px 16px;
    font-size: 13px;
    color: #1E293B;
    border-bottom: 1px solid #F1F5F9;
  }

  tr:hover td {
    background: #F8FAFE;
  }

  .action-icons {
    display: flex;
    gap: 12px;
  }

  .action-icons i {
    cursor: pointer;
    font-size: 16px;
    transition: opacity 0.2s;
  }

  .action-icons i:hover {
    opacity: 0.7;
  }

  .fa-edit {
    color: #3B82F6;
  }

  .fa-trash-alt {
    color: #EF4444;
  }

  .points-badge {
    font-weight: 600;
    color: #F59E0B;
  }

  /* Pagination */
  .pagination {
    display: flex;
    gap: 6px;
    padding: 16px 24px;
    justify-content: flex-end;
    border-top: 1px solid #F0F2F5;
    flex-wrap: wrap;
  }

  .page-btn {
    width: 36px;
    height: 36px;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    background: white;
    color: #3B82F6;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
  }

  .page-btn:hover:not(:disabled) {
    background: #3B82F6;
    color: white;
    border-color: #3B82F6;
  }

  .page-btn.active {
    background: #3B82F6;
    color: white;
    border-color: #3B82F6;
  }

  .page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }

  /* Modal */
  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
  }

  .modal-content {
    background: white;
    border-radius: 24px;
    width: 500px;
    max-width: 90%;
    padding: 28px;
  }

  .modal-content h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 20px;
    color: #0F172A;
  }

  .modal input,
  .modal select {
    width: 100%;
    padding: 12px 14px;
    margin-bottom: 12px;
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    transition: all 0.2s;
  }

  .modal input:focus,
  .modal select:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }

  .modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 16px;
  }

  /* Toast */
  .toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1E293B;
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 14px;
    transform: translateY(60px);
    opacity: 0;
    transition: all 0.3s;
    z-index: 9999;
  }

  .toast.show {
    transform: translateY(0);
    opacity: 1;
  }

  .toast.success {
    background: #10B981;
  }

  .toast.error {
    background: #EF4444;
  }

  /* Empty state */
  .empty-row td {
    text-align: center;
    padding: 48px;
    color: #8A99B0;
  }

  /* Responsive */
  @media (max-width: 1000px) {
    .main-content {
      margin-left: 0;
      padding: 20px;
    }

    .sidebar {
      transform: translateX(-100%);
      transition: transform 0.3s ease;
    }

    .toolbar {
      flex-direction: column;
      align-items: stretch;
    }

    .search-box {
      flex-direction: column;
    }

    .search-box input {
      width: 100%;
    }

    .search-box button {
      justify-content: center;
    }

    .header-section {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
</head>
<body>

<!-- UNIFIED SIDEBAR - Same on all pages -->
<div class="sidebar">
  <div class="logo-area">
    <img src="ccslogo2.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('adminFallbackLogo').style.display='flex';">
    <div id="adminFallbackLogo" class="logo-icon" style="display: none;">
      <i class="fas fa-graduation-cap"></i>
    </div>
    <div class="logo-text">CCS <span>Admin</span></div>
  </div>
  <div class="nav-menu">
    <a href="admin_dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
      <i class="fas fa-chart-line"></i> Dashboard
    </a>
    <a href="Search_Student.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'Search_Student.php' ? 'active' : ''; ?>">
      <i class="fas fa-search"></i> Search Student
    </a>
    <a href="Student_Information.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'Student_Information.php' ? 'active' : ''; ?>">
      <i class="fas fa-users"></i> Students
    </a>
    <a href="sit_in_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sit_in_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-chair"></i> Sit-in
    </a>
    <a href="reservation_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-calendar-alt"></i> Reservation
    </a>
    <a href="announcement_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'announcement_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-bullhorn"></i> Announcements
    </a>
    <a href="reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_reports.php' ? 'active' : ''; ?>">
      <i class="fas fa-chart-pie"></i> Reports
    </a>
    <a href="leaderboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : ''; ?>">
      <i class="fas fa-trophy"></i> Leaderboard
    </a>
  </div>
  <div class="bottom-user">
    <div class="user-avatar"><?php echo $admin_initial; ?></div>
    <div class="user-details">
      <h4><?php echo htmlspecialchars($admin_name); ?></h4>
      <p>Administrator</p>
    </div>
    <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
  <div class="header-section">
    <h1>Students Information</h1>
    <div class="action-buttons">
      <button class="btn btn-primary" onclick="openAddModal()">
        <i class="fas fa-plus"></i> Add Student
      </button>
      <form method="POST" style="display: inline;" onsubmit="return confirmReset()">
        <button type="submit" name="reset_sessions" class="btn btn-warning">
          <i class="fas fa-sync-alt"></i> Reset All Sessions
        </button>
      </form>
    </div>
  </div>

  <div class="table-card">
    <div class="toolbar">
      <div class="entries-label">
        Show
        <select class="entries-select" id="entriesSelect" onchange="changeEntries()">
          <option value="5" <?php echo $entries_per_page == 5 ? 'selected' : ''; ?>>5</option>
          <option value="10" <?php echo $entries_per_page == 10 ? 'selected' : ''; ?>>10</option>
          <option value="25" <?php echo $entries_per_page == 25 ? 'selected' : ''; ?>>25</option>
          <option value="50" <?php echo $entries_per_page == 50 ? 'selected' : ''; ?>>50</option>
        </select>
        entries
      </div>
      <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by ID, Name, Course or Email..." value="<?php echo htmlspecialchars($search); ?>">
        <button onclick="searchStudents()">
          <i class="fas fa-search"></i> Search
        </button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>ID Number</th>
            <th>Name</th>
            <th>Year Level</th>
            <th>Course</th>
            <th>Email</th>
            <th>Sessions Left</th>
            <th>Points</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($students && $students->num_rows > 0): ?>
            <?php while ($row = $students->fetch_assoc()): ?>
              <?php
                $full_name = $row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name'];
              ?>
              <tr>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($full_name); ?></td>
                <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                <td><?php echo htmlspecialchars($row['course']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo $row['sessions']; ?></td>
                <td class="points-badge">⭐ <?php echo $row['total_points'] ?? 0; ?></td>
                <td class="action-icons">
                  <i class="fas fa-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)' title="Edit"></i>
                  <i class="fas fa-trash-alt" onclick="confirmDelete(<?php echo $row['id']; ?>)" title="Delete"></i>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row">
              <td colspan="8">
                <i class="fas fa-user-graduate" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                No students found
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <button class="page-btn" onclick="goToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
        <i class="fas fa-chevron-left"></i>
      </button>
      <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
        <button class="page-btn <?php echo $i == $page ? 'active' : ''; ?>" onclick="goToPage(<?php echo $i; ?>)">
          <?php echo $i; ?>
        </button>
      <?php endfor; ?>
      <?php if ($total_pages > 10): ?>
        <span style="padding: 0 8px;">...</span>
        <button class="page-btn" onclick="goToPage(<?php echo $total_pages; ?>)"><?php echo $total_pages; ?></button>
      <?php endif; ?>
      <button class="page-btn" onclick="goToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Student Modal -->
<div id="addModal" class="modal">
  <div class="modal-content">
    <h3><i class="fas fa-user-plus"></i> Add New Student</h3>
    <form method="POST" id="addForm">
      <input type="text" name="id_number" placeholder="ID Number" required>
      <input type="text" name="user_name" placeholder="Username" required>
      <input type="text" name="first_name" placeholder="First Name" required>
      <input type="text" name="middle_name" placeholder="Middle Name">
      <input type="text" name="last_name" placeholder="Last Name" required>
      <select name="year_level" required>
        <option value="">Select Year Level</option>
        <option>Year 1</option>
        <option>Year 2</option>
        <option>Year 3</option>
        <option>Year 4</option>
      </select>
      <select name="course" required>
        <option value="">Select Course</option>
        <option>BSIT</option>
        <option>BSCS</option>
        <option>BSIS</option>
      </select>
      <input type="email" name="email" placeholder="Email Address" required>
      <input type="number" name="sessions" placeholder="Initial Sessions" value="30" required>
      <div class="modal-buttons">
        <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
        <button type="button" class="btn" onclick="closeModal('addModal')" style="background:#E2E8F0;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Student Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <h3><i class="fas fa-user-edit"></i> Edit Student</h3>
    <form id="editForm">
      <input type="hidden" name="student_id" id="edit_id">
      <input type="text" name="id_number" id="edit_id_number" placeholder="ID Number" required>
      <input type="text" name="first_name" id="edit_first_name" placeholder="First Name" required>
      <input type="text" name="middle_name" id="edit_middle_name" placeholder="Middle Name">
      <input type="text" name="last_name" id="edit_last_name" placeholder="Last Name" required>
      <select name="year_level" id="edit_year_level" required>
        <option>Year 1</option>
        <option>Year 2</option>
        <option>Year 3</option>
        <option>Year 4</option>
      </select>
      <select name="course" id="edit_course" required>
        <option>BSIT</option>
        <option>BSCS</option>
        <option>BSIS</option>
      </select>
      <input type="email" name="email" id="edit_email" placeholder="Email Address" required>
      <input type="number" name="sessions" id="edit_sessions" placeholder="Sessions" required>
      <div class="modal-buttons">
        <button type="button" class="btn btn-primary" onclick="submitEditForm()">Save Changes</button>
        <button type="button" class="btn" onclick="closeModal('editModal')" style="background:#E2E8F0;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
  function changeEntries() {
    const entries = document.getElementById('entriesSelect').value;
    const search = document.getElementById('searchInput').value;
    window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}`;
  }

  function searchStudents() {
    const search = document.getElementById('searchInput').value;
    const entries = document.getElementById('entriesSelect').value;
    window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}`;
  }

  function goToPage(page) {
    const entries = document.getElementById('entriesSelect').value;
    const search = document.getElementById('searchInput').value;
    window.location.href = `?page=${page}&entries=${entries}&search=${encodeURIComponent(search)}`;
  }

  function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
  }

  function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
  }

  function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this student?')) {
      const entries = document.getElementById('entriesSelect').value;
      const search = document.getElementById('searchInput').value;
      const page = <?php echo $page; ?>;
      window.location.href = `?delete_id=${id}&entries=${entries}&search=${encodeURIComponent(search)}&page=${page}`;
    }
  }

  function confirmReset() {
    return confirm('Are you sure you want to reset all student sessions to 30? This action cannot be undone.');
  }

  function openEditModal(student) {
    document.getElementById('edit_id').value = student.id;
    document.getElementById('edit_id_number').value = student.id_number;
    document.getElementById('edit_first_name').value = student.first_name || '';
    document.getElementById('edit_middle_name').value = student.middle_name || '';
    document.getElementById('edit_last_name').value = student.last_name || '';
    document.getElementById('edit_year_level').value = student.year_level;
    document.getElementById('edit_course').value = student.course;
    document.getElementById('edit_email').value = student.email;
    document.getElementById('edit_sessions').value = student.sessions;
    document.getElementById('editModal').style.display = 'flex';
  }

  function submitEditForm() {
    const formData = new FormData(document.getElementById('editForm'));
    formData.append('edit_student', '1');
    
    fetch(window.location.href, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast('Student updated successfully!', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('Error updating student', 'error');
      }
    })
    .catch(error => {
      showToast('Error: ' + error, 'error');
    });
    
    closeModal('editModal');
  }

  function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    setTimeout(() => {
      toast.classList.remove('show');
    }, 3000);
  }

  // Close modals when clicking outside
  window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
      event.target.style.display = 'none';
    }
  }

  // Press Enter to search
  document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      searchStudents();
    }
  });

  <?php if (isset($success_msg)): ?>
  showToast('<?php echo addslashes($success_msg); ?>', 'success');
  <?php endif; ?>

  <?php if (isset($error_msg)): ?>
  showToast('<?php echo addslashes($error_msg); ?>', 'error');
  <?php endif; ?>
</script>
</body>
</html>