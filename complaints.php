<?php
// complaints.php
require 'config.php';
require 'auth.php';
require_login();

$role    = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

if ($role === 'member' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    require_csrf();
    $title = trim($_POST['title'] ?? '');
    $msg   = trim($_POST['message'] ?? '');
    if ($title !== '' && $msg !== '') {
        $ins = $conn->prepare("INSERT INTO complaints (user_id, title, message, status, created_at) VALUES (?, ?, ?, 'open', NOW())");
        if ($ins) {
            $ins->bind_param('iss', $user_id, $title, $msg);
            $ins->execute();
            $ins->close();
        }
    }
    header("Location: complaints.php?submitted=1"); exit;
}

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_id'])) {
    require_csrf();
    $id     = (int)$_POST['reply_id'];
    $reply  = trim($_POST['reply'] ?? '');
    $status = trim($_POST['status'] ?? 'open');
    $valid_status = in_array($status, ['open', 'in_progress', 'closed']) ? $status : 'open';

    $upd = $conn->prepare("UPDATE complaints SET reply = ?, status = ?, updated_at = NOW() WHERE id = ?");
    if ($upd) {
        $upd->bind_param('ssi', $reply, $valid_status, $id);
        $upd->execute();
        $upd->close();
    }
    header("Location: complaints.php?updated=1"); exit;
}

if ($role === 'admin') {
    $complaints = $conn->query("SELECT c.*, u.name FROM complaints c JOIN users u ON c.user_id=u.id ORDER BY c.created_at DESC");
} else {
    $c_stmt = $conn->prepare("SELECT * FROM complaints WHERE user_id = ? ORDER BY created_at DESC");
    $c_stmt->bind_param('i', $user_id);
    $c_stmt->execute();
    $complaints = $c_stmt->get_result();
}
$back = $role === 'admin' ? 'admin_dashboard.php' : 'member_dashboard.php';

function statusBadge($s) {
    $map = ['open'=>'badge-red','in_progress'=>'badge-yellow','closed'=>'badge-green'];
    $cls = $map[$s] ?? 'badge-gray';
    return "<span class='badge {$cls}'>".htmlspecialchars($s)."</span>";
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Complaints — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .reply-form textarea { min-height: 70px; }
    .reply-form select { margin-top: 8px; }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ <?php echo $gym_name; ?></div>
  <div class="navbar-links">
    <a href="<?php echo $back; ?>">← Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">

  <div class="page-header">
    <h2><?php echo $role === 'admin' ? 'Manage' : 'My'; ?> <span class="accent">Complaints</span></h2>
  </div>

  <?php if (isset($_GET['submitted'])): ?>
    <div class="alert alert-success">✅ Complaint submitted successfully.</div>
  <?php endif; ?>
  <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">✅ Reply updated successfully.</div>
  <?php endif; ?>

  <?php if ($role === 'member'): ?>
  <div class="card" style="max-width:580px;margin-bottom:28px;">
    <h3 style="margin-bottom:20px;">Submit a New Complaint</h3>
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" required placeholder="Brief subject of your complaint">
      </div>
      <div class="form-group">
        <label>Message</label>
        <textarea name="message" required placeholder="Describe your issue in detail..."></textarea>
      </div>
      <button class="btn" type="submit">Submit Complaint</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="section-label">Complaint Records</div>

  <?php if (!$complaints || $complaints->num_rows === 0): ?>
    <div class="card" style="text-align:center;padding:40px;">
      <p style="color:var(--text-3);">No complaints found.</p>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <?php if($role==='admin') echo '<th>Member</th>'; ?>
          <th>Title</th><th>Message</th><th>Status</th><th>Reply</th><th>Date</th>
          <?php if($role==='admin') echo '<th>Action</th>'; ?>
        </tr>
      </thead>
      <tbody>
        <?php while($c = $complaints->fetch_assoc()): ?>
        <tr>
          <?php if($role==='admin') echo '<td>'.htmlspecialchars($c['name'] ?? '—').'</td>'; ?>
          <td style="font-weight:600;"><?php echo htmlspecialchars($c['title']); ?></td>
          <td style="max-width:220px;"><?php echo nl2br(htmlspecialchars($c['message'])); ?></td>
          <td><?php echo statusBadge($c['status']); ?></td>
          <td style="max-width:180px;color:var(--text-2);font-size:13px;"><?php echo nl2br(htmlspecialchars($c['reply'] ?? '')); ?></td>
          <td style="font-size:12px;color:var(--text-3);white-space:nowrap;"><?php echo htmlspecialchars($c['created_at']); ?></td>
          <?php if($role==='admin'): ?>
          <td>
            <form method="post" class="reply-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="reply_id" value="<?php echo (int)$c['id']; ?>">
              <textarea name="reply" placeholder="Write a reply..."><?php echo htmlspecialchars($c['reply'] ?? ''); ?></textarea>
              <select name="status">
                <option value="open"        <?php if($c['status']==='open')        echo 'selected'; ?>>Open</option>
                <option value="in_progress" <?php if($c['status']==='in_progress') echo 'selected'; ?>>In Progress</option>
                <option value="closed"      <?php if($c['status']==='closed')      echo 'selected'; ?>>Closed</option>
              </select>
              <button class="btn btn-sm" type="submit" style="margin-top:8px;">Update</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</body>
</html>