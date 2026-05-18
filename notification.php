<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications - Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Sora:wght@600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:       #1a3a6b;
      --navy-dark:  #122850;
      --navy-mid:   #1f4590;
      --accent:     #f5a623;
      --accent-h:   #e09010;
      --white:      #ffffff;
      --bg:         #f4f6fb;
      --border:     #d8e2f5;
      --text:       #1e2a45;
      --muted:      #6b7a99;
      --shadow:     0 4px 24px rgba(26,58,107,.10);
      --unread-bg:  #eef3fd;
      --unread-dot: #1f4590;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* ── NAV ── */
    nav {
      background: var(--navy);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 32px;
      height: 58px;
      box-shadow: 0 2px 12px rgba(0,0,0,.25);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .nav-brand {
      font-family: 'Sora', sans-serif;
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--white);
      letter-spacing: .02em;
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 4px;
      list-style: none;
    }

    .nav-links a {
      color: rgba(255,255,255,.82);
      text-decoration: none;
      font-size: .875rem;
      font-weight: 500;
      padding: 6px 14px;
      border-radius: 6px;
      transition: background .18s, color .18s;
    }

    .nav-links a:hover,
    .nav-links a.active {
      background: rgba(255,255,255,.12);
      color: var(--white);
    }

    .dropdown { position: relative; }
    .dropdown-toggle { cursor: pointer; display: flex; align-items: center; gap: 5px; }
    .dropdown-toggle::after { content: '▾'; font-size: .7rem; }

    /* nav badge */
    .notif-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #e53935;
      color: #fff;
      font-size: .65rem;
      font-weight: 700;
      width: 17px;
      height: 17px;
      border-radius: 50%;
      margin-left: 4px;
      line-height: 1;
    }

    .dropdown-menu {
      display: none;
      position: absolute;
      top: calc(100% + 8px);
      left: 0;
      background: var(--white);
      border-radius: 8px;
      box-shadow: 0 8px 28px rgba(0,0,0,.15);
      min-width: 160px;
      overflow: hidden;
    }
    .dropdown:hover .dropdown-menu { display: block; }
    .dropdown-menu a {
      display: block;
      color: var(--text) !important;
      padding: 10px 16px !important;
      border-radius: 0 !important;
    }
    .dropdown-menu a:hover { background: var(--bg) !important; }

    .btn-logout {
      background: var(--accent);
      color: var(--navy-dark) !important;
      font-weight: 600 !important;
      border-radius: 7px !important;
      padding: 7px 18px !important;
      transition: background .18s !important;
    }
    .btn-logout:hover { background: var(--accent-h) !important; }

    /* ── MAIN ── */
    main {
      max-width: 760px;
      margin: 48px auto;
      padding: 0 24px;
    }

    h1 {
      font-family: 'Sora', sans-serif;
      font-size: 2rem;
      font-weight: 700;
      text-align: center;
      color: var(--navy-dark);
      margin-bottom: 10px;
      letter-spacing: -.01em;
    }

    /* ── FILTER TABS ── */
    .filter-bar {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-bottom: 28px;
    }

    .filter-btn {
      padding: 7px 22px;
      border-radius: 20px;
      border: 1.5px solid var(--border);
      background: var(--white);
      color: var(--muted);
      font-size: .85rem;
      font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      transition: background .18s, color .18s, border-color .18s;
    }

    .filter-btn:hover { border-color: var(--navy-mid); color: var(--navy-mid); }
    .filter-btn.active { background: var(--navy-mid); color: var(--white); border-color: var(--navy-mid); }

    /* ── CARD ── */
    .card {
      background: var(--white);
      border-radius: 14px;
      box-shadow: var(--shadow);
      overflow: hidden;
      animation: fadeUp .4s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── CARD TOOLBAR ── */
    .card-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 14px 20px;
      border-bottom: 1px solid var(--border);
    }

    .toolbar-title {
      font-weight: 600;
      font-size: .9rem;
      color: var(--navy-dark);
    }

    .mark-all-btn {
      background: none;
      border: none;
      font-size: .8rem;
      font-family: 'DM Sans', sans-serif;
      color: var(--navy-mid);
      cursor: pointer;
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 5px;
      transition: background .15s;
    }
    .mark-all-btn:hover { background: var(--unread-bg); }

    /* ── NOTIFICATION ITEM ── */
    .notif-list { list-style: none; }

    .notif-item {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      transition: background .15s;
      animation: fadeIn .35s ease both;
      cursor: pointer;
      position: relative;
    }

    .notif-item:last-child { border-bottom: none; }
    .notif-item:hover { background: var(--unread-bg); }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateX(-8px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    /* unread indicator dot */
    .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--unread-dot);
      flex-shrink: 0;
      margin-top: 5px;
      transition: background .2s;
    }

    .notif-item.read .dot {
      background: transparent;
      border: 1.5px solid var(--border);
    }

    .notif-item.read { background: var(--white); }

    /* icon */
    .notif-icon {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 1rem;
    }

    .icon-reservation { background: #ddeaff; }
    .icon-alert       { background: #fdecea; }
    .icon-info        { background: #e8f5e9; }

    /* content */
    .notif-content { flex: 1; min-width: 0; }

    .notif-title {
      font-weight: 600;
      font-size: .9rem;
      color: var(--navy-dark);
      margin-bottom: 3px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .notif-item.read .notif-title { font-weight: 500; color: var(--muted); }

    .notif-body {
      font-size: .82rem;
      color: var(--muted);
      line-height: 1.5;
    }

    .notif-time {
      font-size: .75rem;
      color: var(--muted);
      white-space: nowrap;
      flex-shrink: 0;
      margin-top: 3px;
    }

    /* delete btn */
    .notif-delete {
      background: none;
      border: none;
      color: #ccc;
      font-size: 1rem;
      cursor: pointer;
      padding: 2px 6px;
      border-radius: 4px;
      line-height: 1;
      transition: color .15s, background .15s;
      flex-shrink: 0;
    }
    .notif-delete:hover { color: #e53935; background: #fdecea; }

    /* ── EMPTY STATE ── */
    .empty-state {
      text-align: center;
      padding: 52px 24px;
      color: var(--muted);
    }

    .empty-state .empty-icon {
      font-size: 2.8rem;
      margin-bottom: 12px;
      opacity: .5;
    }

    .empty-state p {
      font-size: .9rem;
    }

    /* ── TOAST ── */
    .toast {
      position: fixed;
      bottom: 28px;
      right: 28px;
      background: var(--navy-dark);
      color: var(--white);
      padding: 12px 20px;
      border-radius: 10px;
      font-size: .85rem;
      font-weight: 500;
      box-shadow: 0 8px 24px rgba(0,0,0,.22);
      transform: translateY(80px);
      opacity: 0;
      transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .3s;
      pointer-events: none;
      z-index: 999;
    }
    .toast.show { transform: translateY(0); opacity: 1; }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <span class="nav-brand">Dashboard</span>
  <ul class="nav-links">
    <li class="dropdown">
      <a href="#" class="dropdown-toggle active">
        Notification
        <span class="notif-badge" id="navBadge">2</span>
      </a>
      <div class="dropdown-menu">
        <a href="notification.html">All Notifications</a>
        <a href="notification.html?filter=unread">Unread</a>
      </div>
    </li>
    <li><a href="dashboard.php">Home</a></li>
    <li><a href="editProfile.php">Edit Profile</a></li>
    <li><a href="history.php">History</a></li>
    <li><a href="reservation.php">Reservation</a></li>
    <li><a href="notification.php">Notification</a></li>
    <li><a href="#" class="btn-logout">Log out</a></li>
  </ul>
</nav>

<!-- MAIN -->
<main>
  <h1>Notifications</h1>

  <!-- Filter Tabs -->
  <div class="filter-bar">
    <button class="filter-btn active" onclick="setFilter('all', this)">All</button>
    <button class="filter-btn"        onclick="setFilter('unread', this)">Unread</button>
    <button class="filter-btn"        onclick="setFilter('read', this)">Read</button>
  </div>

  <div class="card">
    <div class="card-toolbar">
      <span class="toolbar-title" id="toolbarTitle">All Notifications</span>
      <button class="mark-all-btn" onclick="markAllRead()">Mark all as read</button>
    </div>
    <ul class="notif-list" id="notifList"></ul>
  </div>
</main>

<div class="toast" id="toast"></div>

<script>
  // ── Data ─────────────────────────────────────────────────────────────────────
  // Replace with data from your PHP/database backend.
  let notifications = [
    {
      id: 1,
      type: 'reservation',
      icon: '📅',
      iconClass: 'icon-reservation',
      title: 'Reservation Confirmed! | 2026-02-20',
      body: 'You have successfully submitted a reservation.',
      time: '2026-02-20 08:14 AM',
      read: false
    },
    {
      id: 2,
      type: 'reservation',
      icon: '📅',
      iconClass: 'icon-reservation',
      title: 'Reservation Confirmed! | 2026-02-18',
      body: 'You have successfully submitted a reservation.',
      time: '2026-02-18 10:32 AM',
      read: false
    },
    {
      id: 3,
      type: 'info',
      icon: '✅',
      iconClass: 'icon-info',
      title: 'Sit-in Session Completed | 2026-02-15',
      body: 'Your sit-in session in Lab 524 has been recorded successfully.',
      time: '2026-02-15 03:00 PM',
      read: true
    },
    {
      id: 4,
      type: 'alert',
      icon: '⚠️',
      iconClass: 'icon-alert',
      title: 'Low Sessions Remaining',
      body: 'You only have 5 remaining sit-in sessions. Please coordinate with your administrator.',
      time: '2026-02-10 09:00 AM',
      read: true
    }
  ];

  let currentFilter = 'all';

  // ── Render ───────────────────────────────────────────────────────────────────
  function render() {
    const list = document.getElementById('notifList');
    list.innerHTML = '';

    const filtered = notifications.filter(n => {
      if (currentFilter === 'unread') return !n.read;
      if (currentFilter === 'read')   return  n.read;
      return true;
    });

    if (filtered.length === 0) {
      list.innerHTML = `
        <li>
          <div class="empty-state">
            <div class="empty-icon">🔔</div>
            <p>No ${currentFilter === 'all' ? '' : currentFilter} notifications found.</p>
          </div>
        </li>`;
    } else {
      filtered.forEach((n, i) => {
        const li = document.createElement('li');
        li.className = `notif-item ${n.read ? 'read' : ''}`;
        li.style.animationDelay = `${i * 50}ms`;
        li.innerHTML = `
          <div class="dot"></div>
          <div class="notif-icon ${n.iconClass}">${n.icon}</div>
          <div class="notif-content" onclick="markRead(${n.id})">
            <div class="notif-title">${n.title}</div>
            <div class="notif-body">${n.body}</div>
          </div>
          <div class="notif-time">${n.time}</div>
          <button class="notif-delete" title="Delete" onclick="deleteNotif(${n.id})">✕</button>
        `;
        list.appendChild(li);
      });
    }

    // update nav badge
    const unreadCount = notifications.filter(n => !n.read).length;
    const badge = document.getElementById('navBadge');
    badge.textContent = unreadCount;
    badge.style.display = unreadCount > 0 ? 'inline-flex' : 'none';

    // toolbar label
    const labels = { all: 'All Notifications', unread: 'Unread', read: 'Read' };
    document.getElementById('toolbarTitle').textContent = labels[currentFilter];
  }

  // ── Mark single as read ──────────────────────────────────────────────────────
  function markRead(id) {
    const n = notifications.find(n => n.id === id);
    if (n && !n.read) {
      n.read = true;
      render();
    }
  }

  // ── Mark all read ────────────────────────────────────────────────────────────
  function markAllRead() {
    notifications.forEach(n => n.read = true);
    render();
    showToast('All notifications marked as read.');
  }

  // ── Delete ───────────────────────────────────────────────────────────────────
  function deleteNotif(id) {
    notifications = notifications.filter(n => n.id !== id);
    render();
    showToast('Notification removed.');
  }

  // ── Filter ───────────────────────────────────────────────────────────────────
  function setFilter(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    render();
  }

  // ── Toast ────────────────────────────────────────────────────────────────────
  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
  }

  // ── Init ─────────────────────────────────────────────────────────────────────
  render();
</script>
</body>
</html>