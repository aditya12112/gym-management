<?php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$members        = $conn->query("SELECT COUNT(*) c FROM users WHERE role='member'")->fetch_assoc()['c'];
$trainers       = $conn->query("SELECT COUNT(*) c FROM users WHERE role='trainer'")->fetch_assoc()['c'];
$bookings_today = $conn->query("SELECT COUNT(*) c FROM bookings WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$open_complaints= $conn->query("SELECT COUNT(*) c FROM complaints WHERE status='open'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard — GymPro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.4">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .greeting { font-size: 14px; color: var(--text-3); margin-bottom: 6px; }
    .dash-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 28px; }
    .recent-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; }
    .recent-card h4 { margin-bottom: 16px; }
    .quick-action { display: flex; flex-direction: column; gap: 12px; }
    .qa-item {
      display: flex; align-items: center; gap: 14px;
      padding: 14px 18px;
      background: var(--bg-3);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      text-decoration: none;
      color: var(--text-1);
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
    }
    .qa-item:hover {
      border-color: var(--accent);
      background: var(--card);
      color: var(--accent);
      opacity: 1;
    }
    .qa-icon { font-size: 20px; width: 36px; text-align: center; }
    @media (max-width: 900px) {
      .dash-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<nav class="navbar">
  <div class="navbar-brand">🏋️ <?php echo htmlspecialchars(get_setting('gym_name', 'GymPro')); ?></div>
  <div class="navbar-links"></div>
  <div class="navbar-actions">
    <span class="navbar-user">Hello, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></span>
    <a href="manage_users.php">Members</a>
    <a href="manage_trainers.php">Trainers</a>
    <a href="complaints.php">Complaints</a>
    <a href="admin_settings.php">⚙️ Settings</a>
    <button class="theme-btn" id="themeBtn" title="Toggle theme" aria-label="Toggle dark/light mode">
      <span class="moon">🌙</span>
      <span class="sun">☀️</span>
    </button>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">

  <div class="page-header">
    <div>
      <div class="greeting">Admin Panel · <?php echo date('l, d F Y'); ?></div>
      <h2>Dashboard <span class="accent">Overview</span></h2>
    </div>
    <a href="admin_add_member.php" class="btn">+ Add Member</a>
  </div>

  <!-- Stats -->
  <div class="stat-grid">
    <div class="stat-card">
      <span class="stat-icon">👤</span>
      <div class="stat-label">Total Members</div>
      <div class="stat-num"><?php echo (int)$members; ?></div>
      <a href="manage_users.php" class="stat-link">Manage members →</a>
    </div>
    <div class="stat-card">
      <span class="stat-icon">🧑‍🏫</span>
      <div class="stat-label">Trainers</div>
      <div class="stat-num"><?php echo (int)$trainers; ?></div>
      <a href="manage_trainers.php" class="stat-link">Manage trainers →</a>
    </div>
    <div class="stat-card">
      <span class="stat-icon">📅</span>
      <div class="stat-label">Bookings Today</div>
      <div class="stat-num"><?php echo (int)$bookings_today; ?></div>
      <a href="bookings_today.php" class="stat-link">View today →</a>
    </div>
    <div class="stat-card">
      <span class="stat-icon">💬</span>
      <div class="stat-label">Open Complaints</div>
      <div class="stat-num" style="color:<?php echo $open_complaints > 0 ? 'var(--danger)' : 'var(--text-1)'; ?>">
        <?php echo (int)$open_complaints; ?>
      </div>
      <a href="complaints.php" class="stat-link">Manage →</a>
    </div>
  </div>

  <!-- Manage Grid -->
  <div class="section-label">Quick Access</div>
  <div class="manage-grid">
    <a href="manage_exercises.php" class="manage-item"><span class="icon">🏋️</span>Exercises</a>
    <a href="manage_timeslots.php" class="manage-item"><span class="icon">🕐</span>Time Slots</a>
    <a href="complaints.php"       class="manage-item"><span class="icon">💬</span>Complaints</a>
    <a href="trainer_leave.php"    class="manage-item"><span class="icon">🏖️</span>Trainer Leaves</a>
    <a href="payments.php"         class="manage-item"><span class="icon">💳</span>Payments</a>
    <a href="admin_add_member.php" class="manage-item"><span class="icon">➕</span>Add Member</a>
    <a href="admin_add_trainer.php"class="manage-item"><span class="icon">➕</span>Add Trainer</a>
    <a href="admin_add_admin.php"  class="manage-item"><span class="icon">👑</span>Add Admin</a>
    <a href="manage_admins.php"    class="manage-item"><span class="icon">👥</span>View Admins</a>
    <a href="bookings_today.php"   class="manage-item"><span class="icon">📋</span>Today's Bookings</a>
    <a href="bookings_history.php" class="manage-item"><span class="icon">📜</span>Booking History</a>
    <a href="manage_users.php"     class="manage-item"><span class="icon">👤</span>All Members</a>
  </div>

  <!-- Bottom section -->
  <div class="dash-grid">
    <div class="recent-card">
      <h4>Recent Activity</h4>
      <?php
        $recent = $conn->query("
          SELECT b.created_at, u.name, t.day_of_week
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          LEFT JOIN time_slots t ON b.slot_id = t.id
          ORDER BY b.created_at DESC LIMIT 6
        ");
        if ($recent && $recent->num_rows > 0):
      ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Member</th><th>Slot Day</th><th>Booked At</th></tr></thead>
          <tbody>
            <?php while($r = $recent->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['name']); ?></td>
              <td><?php echo htmlspecialchars($r['day_of_week'] ?? '—'); ?></td>
              <td style="color:var(--text-3);font-size:13px;"><?php echo htmlspecialchars($r['created_at']); ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p style="color:var(--text-3);font-size:14px;">No bookings recorded yet.</p>
      <?php endif; ?>
    </div>

    <div class="recent-card">
      <h4>Quick Actions</h4>
      <div class="quick-action">
        <a href="admin_add_member.php" class="qa-item"><span class="qa-icon">👤</span>Add New Member</a>
        <a href="admin_add_trainer.php" class="qa-item"><span class="qa-icon">🧑‍🏫</span>Add New Trainer</a>
        <a href="bookings_today.php" class="qa-item"><span class="qa-icon">📅</span>Today's Bookings</a>
        <a href="complaints.php" class="qa-item"><span class="qa-icon">💬</span>View Complaints</a>
        <a href="payments.php" class="qa-item"><span class="qa-icon">💳</span>Manage Payments</a>
        <a href="admin_settings.php" class="qa-item"><span class="qa-icon">⚙️</span>System Settings</a>
      </div>
    </div>
  </div>

</div>

</body>
</html>