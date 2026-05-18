<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: Login.php');
    exit;
}

$student_name = htmlspecialchars(trim(
    ($_SESSION['name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')
));
$student_course = htmlspecialchars($_SESSION['course'] ?? 'N/A');
$profile_pic = htmlspecialchars($_SESSION['profile_pic'] ?? '');
$initials = strtoupper(substr($_SESSION['name'] ?? '', 0, 1) . substr($_SESSION['last_name'] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>History Information - CCS Student</title>
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
      gap: 10px;
      margin-bottom: 40px;
      padding-left: 8px;
    }
    .logo-icon {
      background: #3B82F6;
      width: 38px;
      height: 38px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 18px;
      font-weight: 700;
      box-shadow: 0 6px 12px -6px rgba(59,130,246,0.25);
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

    /* ========= HISTORY CARD ========= */
    .history-card {
      background: white;
      border-radius: 24px;
      border: 1px solid #EFF3F8;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    }

    /* Toolbar */
    .toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid #F0F2F5;
      flex-wrap: wrap;
      gap: 12px;
    }

    .entries-label {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: #6C7A91;
    }

    .entries-select {
      border: 1.5px solid #E2E8F0;
      border-radius: 10px;
      padding: 8px 32px 8px 14px;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
      color: #1E293B;
      background: white;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236C7A91' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
    }

    .search-box {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: #6C7A91;
    }

    .search-box input {
      border: 1.5px solid #E2E8F0;
      border-radius: 10px;
      padding: 8px 14px;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
      width: 240px;
      outline: none;
      transition: all 0.2s;
    }

    .search-box input:focus {
      border-color: #3B82F6;
      box-shadow: 0 0 0 3px rgba(59,130,246,0.08);
    }

    /* Table */
    .table-wrapper {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead tr {
      background: #F8FAFE;
    }

    th {
      text-align: left;
      padding: 14px 18px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #6C7A91;
      cursor: pointer;
      user-select: none;
      transition: background 0.2s;
    }

    th:hover {
      background: #F1F5F9;
    }

    .sort-icon {
      display: inline-flex;
      flex-direction: column;
      vertical-align: middle;
      margin-left: 6px;
    }
    .sort-icon .asc {
      border-left: 4px solid transparent;
      border-right: 4px solid transparent;
      border-bottom: 5px solid #CBD5E1;
      margin-bottom: 2px;
    }
    .sort-icon .desc {
      border-left: 4px solid transparent;
      border-right: 4px solid transparent;
      border-top: 5px solid #CBD5E1;
    }

    td {
      padding: 14px 18px;
      font-size: 13px;
      color: #1E293B;
      border-bottom: 1px solid #F0F2F5;
    }

    tbody tr:hover td {
      background: #F8FAFE;
    }

    .no-data {
      text-align: center;
      color: #6C7A91;
      padding: 48px !important;
      font-style: italic;
    }

    /* Action Buttons */
    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
      margin: 0 3px;
    }
    .btn-view {
      background: #EFF6FF;
      color: #3B82F6;
    }
    .btn-view:hover {
      background: #3B82F6;
      color: white;
    }
    .btn-delete {
      background: #FEF2F2;
      color: #EF4444;
    }
    .btn-delete:hover {
      background: #EF4444;
      color: white;
    }

    /* Footer */
    .footer-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 24px;
      border-top: 1px solid #F0F2F5;
      flex-wrap: wrap;
      gap: 12px;
    }

    .showing-info {
      font-size: 12px;
      color: #6C7A91;
    }

    .pagination {
      display: flex;
      gap: 6px;
    }

    .page-btn {
      width: 36px;
      height: 36px;
      border: 1.5px solid #E2E8F0;
      border-radius: 10px;
      background: white;
      color: #3B82F6;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
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
      cursor: default;
    }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        padding: 16px;
      }
      .toolbar {
        flex-direction: column;
        align-items: stretch;
      }
      .search-box input {
        width: 100%;
      }
      .footer-bar {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
  <div class="logo-area">
    <div class="logo-icon"><i class="fas fa-user-graduate"></i></div>
    <div class="logo-text">CCS <span>Student</span></div>
  </div>
  <div class="nav-menu">
    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="editProfile.php" class="nav-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="history.php" class="nav-item active"><i class="fas fa-history"></i> History</a>
    <a href="reservation.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
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
      <h1>History Information</h1>
      <!-- FIXED BREADCRUMB WITH PROPERLY ALIGNED GREATER THAN ICON -->
      <div class="breadcrumb-links">
        <span>Home</span>
        <i class="fas fa-chevron-right"></i>
        <span>History</span>
      </div>
    </div>
    <div class="header-actions">
      <div class="notif-btn">
        <i class="far fa-bell"></i>
        <div class="notif-dot"></div>
      </div>
      <div class="student-chip"><i class="fas fa-user"></i> CCS Student · <strong><?php echo $student_course; ?></strong></div>
    </div>
  </div>

  <div class="history-card">
    <div class="toolbar">
      <div class="entries-label">
        <select class="entries-select" id="entriesSelect" onchange="changeEntries(this.value)">
          <option value="5">5</option>
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
        </select>
        entries per page
      </div>
      <div class="search-box">
        <i class="fas fa-search" style="color: #9CA3AF;"></i>
        <input type="text" id="searchInput" placeholder="Search by ID, Name, Purpose..." oninput="filterTable()"/>
      </div>
    </div>

    <div class="table-wrapper">
      <table id="historyTable">
        <thead>
          <tr>
            <th onclick="sortTable(0)">ID Number <span class="sort-icon"><span class="asc"></span><span class="desc"></span></span></th>
            <th onclick="sortTable(1)">Name <span class="sort-icon"><span class="asc"></span><span class="desc"></span></span></th>
            <th onclick="sortTable(2)">Sit Purpose <span class="sort-icon"><span class="asc"></span><span class="desc"></span></span></th>
            <th onclick="sortTable(3)">Laboratory <span class="sort-icon"><span class="asc"></span><span class="desc"></span></span></th>
            <th onclick="sortTable(4)">Login <span class="sort-icon"><span class="asc"></span><span class="desc"></span></span></th>
            <th onclick="sortTable(5)">Logout <span class="sort-icon"><span class="asc"></span><span class="desc"></span></span></th>
            <th onclick="sortTable(6)">Date <span class="sort-icon"><span class="asc"></span><span class="desc"></span></span></th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="tableBody"></tbody>
      </table>
    </div>

    <div class="footer-bar">
      <div class="showing-info" id="showingInfo">Showing 0 to 0 of 0 entries</div>
      <div class="pagination" id="pagination"></div>
    </div>
  </div>
</div>

<script>
  // Sample data - replace with actual database data via PHP
  const allData = [
    // { id: "2021-00123", name: "Juan dela Cruz", purpose: "Programming", lab: "Lab A", login: "08:15 AM", logout: "10:30 AM", date: "2024-01-15" },
    // { id: "2022-00456", name: "Maria Santos", purpose: "Research", lab: "Lab B", login: "09:00 AM", logout: "11:45 AM", date: "2024-01-16" },
    // { id: "2023-00789", name: "Jose Reyes", purpose: "Assignment", lab: "Lab C", login: "01:00 PM", logout: "03:30 PM", date: "2024-01-17" }
  ];

  let filtered = [...allData];
  let currentPage = 1;
  let perPage = 10;
  let sortCol = -1;
  let sortAsc = true;

  function render() {
    const tbody = document.getElementById('tableBody');
    const start = (currentPage - 1) * perPage;
    const end = Math.min(start + perPage, filtered.length);
    const pageData = filtered.slice(start, end);

    tbody.innerHTML = '';

    if (pageData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fas fa-folder-open" style="margin-right: 8px;"></i>No entries found</td></tr>';
    } else {
      pageData.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(row.id)}</td>
          <td>${escapeHtml(row.name)}</td>
          <td>${escapeHtml(row.purpose)}</td>
          <td>${escapeHtml(row.lab)}</td>
          <td>${escapeHtml(row.login)}</td>
          <td>${escapeHtml(row.logout)}</td>
          <td>${escapeHtml(row.date)}</td>
          <td>
            <button class="btn-action btn-view" onclick="viewRow('${escapeHtml(row.id)}')">
              <i class="fas fa-eye"></i> View
            </button>
            <button class="btn-action btn-delete" onclick="deleteRow('${escapeHtml(row.id)}')">
              <i class="fas fa-trash-alt"></i> Delete
            </button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    const total = filtered.length;
    const showingInfo = document.getElementById('showingInfo');
    if (total === 0) {
      showingInfo.textContent = 'Showing 0 to 0 of 0 entries';
    } else {
      showingInfo.textContent = `Showing ${start + 1} to ${end} of ${total} entries`;
    }
    renderPagination(total);
  }

  function renderPagination(total) {
    const pages = Math.ceil(total / perPage) || 1;
    const container = document.getElementById('pagination');
    container.innerHTML = '';

    const createButton = (label, page, disabled, isActive) => {
      const btn = document.createElement('button');
      btn.className = 'page-btn' + (isActive ? ' active' : '');
      btn.innerHTML = label;
      btn.disabled = disabled;
      btn.onclick = () => {
        currentPage = page;
        render();
      };
      container.appendChild(btn);
    };

    createButton('«', 1, currentPage === 1, false);
    createButton('‹', currentPage - 1, currentPage === 1, false);

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(pages, currentPage + 2);

    for (let p = startPage; p <= endPage; p++) {
      createButton(p, p, false, p === currentPage);
    }

    createButton('›', currentPage + 1, currentPage === pages, false);
    createButton('»', pages, currentPage === pages, false);
  }

  function filterTable() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    if (!query) {
      filtered = [...allData];
    } else {
      filtered = allData.filter(row => 
        row.id.toLowerCase().includes(query) ||
        row.name.toLowerCase().includes(query) ||
        row.purpose.toLowerCase().includes(query) ||
        row.lab.toLowerCase().includes(query)
      );
    }
    currentPage = 1;
    render();
  }

  function changeEntries(val) {
    perPage = parseInt(val);
    currentPage = 1;
    render();
  }

  const KEYS = ['id', 'name', 'purpose', 'lab', 'login', 'logout', 'date'];
  function sortTable(col) {
    if (sortCol === col) {
      sortAsc = !sortAsc;
    } else {
      sortCol = col;
      sortAsc = true;
    }
    const key = KEYS[col];
    filtered.sort((a, b) => {
      const valA = a[key].toLowerCase();
      const valB = b[key].toLowerCase();
      if (sortAsc) {
        return valA < valB ? -1 : valA > valB ? 1 : 0;
      } else {
        return valA > valB ? -1 : valA < valB ? 1 : 0;
      }
    });
    render();
  }

  function viewRow(id) {
    alert('View record: ' + id);
    // Add your view logic here
  }

  function deleteRow(id) {
    if (!confirm('Are you sure you want to delete record ' + id + '?')) return;
    const index = allData.findIndex(r => r.id === id);
    if (index > -1) {
      allData.splice(index, 1);
      filterTable();
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
      if (m === '&') return '&amp;';
      if (m === '<') return '&lt;';
      if (m === '>') return '&gt;';
      return m;
    });
  }

  // Initialize
  render();
</script>
</body>
</html>