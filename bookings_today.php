<?php
// bookings_today.php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

date_default_timezone_set('Asia/Kolkata');
function html($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function resolve_member_name($conn, $user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) return "ID 0";
    $candidates = ['users','user_login','members'];
    $singleCols = ['fullname','full_name','name','username','display_name','email'];
    foreach ($candidates as $tbl) {
        try {
            $tblCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tbl) . "'");
            if (!$tblCheck || $tblCheck->num_rows === 0) continue;
        } catch (Exception $e) { continue; }
        foreach ($singleCols as $col) {
            try {
                $colCheck = $conn->query("SHOW COLUMNS FROM `{$tbl}` LIKE '" . $conn->real_escape_string($col) . "'");
                if ($colCheck && $colCheck->num_rows) {
                    $pst = $conn->prepare("SELECT `{$col}` AS nm FROM `{$tbl}` WHERE id = ? LIMIT 1");
                    if (!$pst) continue;
                    $pst->bind_param('i', $user_id);
                    $pst->execute();
                    $res = $pst->get_result();
                    $pst->close();
                    if ($res && $res->num_rows) {
                        $row = $res->fetch_assoc();
                        $nm = trim($row['nm'] ?? '');
                        if ($nm !== '') return $nm;
                    }
                }
            } catch (Exception $e) {}
        }
    }
    return "ID " . $user_id;
}

$sql = "
    SELECT b.id AS booking_id, b.user_id, b.slot_id, b.created_at, b.status,
           t.day_of_week, t.start_time, t.end_time
    FROM bookings b
    LEFT JOIN time_slots t ON b.slot_id = t.id
    WHERE DATE(b.created_at) = CURDATE()
    ORDER BY b.created_at DESC
";
$res = $conn->query($sql);
$bookings_today = [];
if ($res) while ($r = $res->fetch_assoc()) $bookings_today[] = $r;
else $error = $conn->error;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Bookings Today — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links">
    <a href="admin_dashboard.php">← Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <div>
      <h2>Bookings — <span class="accent">Today</span></h2>
      <p style="margin:0;color:var(--text-3);font-size:14px;"><?php echo date('l, d F Y'); ?></p>
    </div>
    <div style="display:flex;gap:10px;">
      <span class="badge badge-lime" style="padding:8px 16px;font-size:14px;">
        <?php echo count($bookings_today); ?> booking<?php echo count($bookings_today) !== 1 ? 's' : ''; ?>
      </span>
      <a href="bookings_history.php" class="btn btn-outline">View History</a>
    </div>
  </div>

  <?php if (isset($error)): ?>
    <div class="alert alert-error">Database error: <?php echo html($error); ?></div>
  <?php endif; ?>

  <?php if (empty($bookings_today)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
      <div style="font-size:48px;margin-bottom:12px;">📅</div>
      <h3>No bookings today</h3>
      <p>No members have booked a slot for today yet.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Member</th><th>Slot</th><th>Status</th><th>Booked At</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php $i=0; foreach ($bookings_today as $b): $i++;
            $name = resolve_member_name($conn, (int)$b['user_id']);
            $slot = trim(($b['day_of_week']??'') . ' ' . ($b['start_time']?$b['start_time'].' - '.$b['end_time']:''));
            $status = $b['status'] ?? 'booked';
            $badgeCls = $status === 'booked' ? 'badge-green' : ($status === 'cancelled' ? 'badge-red' : 'badge-gray');
          ?>
          <tr>
            <td style="color:var(--text-3);"><?php echo $i; ?></td>
            <td>
              <strong><?php echo html($name); ?></strong>
              <div class="td-muted">ID: <?php echo (int)$b['user_id']; ?></div>
            </td>
            <td><?php echo html($slot ?: '—'); ?></td>
            <td><span class="badge <?php echo $badgeCls; ?>"><?php echo html($status); ?></span></td>
            <td style="font-size:13px;color:var(--text-3);"><?php echo html($b['created_at']); ?></td>
            <td><a class="btn btn-sm btn-outline" href="booking_view.php?user_id=<?php echo (int)$b['user_id']; ?>">View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

</body>
</html>