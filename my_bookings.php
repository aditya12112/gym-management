<?php
// my_bookings.php
require 'config.php';
require 'auth.php';
require_login();
require_role('member');

$user_id = (int)($_SESSION['user_id'] ?? 0);
function html($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fmt_time($t){
    if (empty($t)) return '—';
    $p=explode(':',$t); if(count($p)<2) return html($t);
    $h=(int)$p[0]; $m=(int)$p[1]; $amp=$h>=12?'PM':'AM'; $hh=$h%12; if(!$hh)$hh=12;
    return sprintf('%02d:%02d %s',$hh,$m,$amp);
}

$msg = html($_GET['msg'] ?? '');

$bookings = [];
if ($stmt=$conn->prepare("SELECT b.id, b.slot_id, b.quantity, b.status, b.created_at, COALESCE(b.cancelled_at,'') AS cancelled_at, t.day_of_week, t.start_time, t.end_time FROM bookings b LEFT JOIN time_slots t ON b.slot_id=t.id WHERE b.user_id=? ORDER BY b.created_at DESC")) {
    $stmt->bind_param('i',$user_id); $stmt->execute();
    $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $bookings[]=$r; $stmt->close();
}

$total   = count($bookings);
$active  = count(array_filter($bookings, fn($b)=>strtolower($b['status']==='booked')));
$cancelled = count(array_filter($bookings, fn($b)=>strtolower($b['status']==='cancelled')));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>My Bookings — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.4">
  <script src="assets/theme.js?v=2.0"></script>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links">
    <a href="member_dashboard.php">← Dashboard</a>
    <a href="book_slot.php" class="btn btn-sm">+ Book Slot</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <h2>My <span class="accent">Bookings</span></h2>
    <a href="book_slot.php" class="btn">+ Book New Slot</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-info"><?php echo $msg; ?></div><?php endif; ?>

  <!-- Stats row -->
  <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
    <div class="stat-card"><div class="stat-label">Total Bookings</div><div class="stat-num"><?php echo $total; ?></div></div>
    <div class="stat-card"><div class="stat-label">Active</div><div class="stat-num" style="color:var(--success);"><?php echo $active; ?></div></div>
    <div class="stat-card"><div class="stat-label">Cancelled</div><div class="stat-num" style="color:var(--text-3);"><?php echo $cancelled; ?></div></div>
  </div>

  <?php if (empty($bookings)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
      <div style="font-size:48px;margin-bottom:12px;">📅</div>
      <h3>No bookings yet</h3>
      <p>Book a time slot to start your fitness journey.</p>
      <a href="book_slot.php" class="btn" style="margin-top:16px;">Browse Available Slots</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Slot</th><th>Day</th><th>Time</th><th>Status</th><th>Booked On</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($bookings as $b):
          $status=strtolower(trim($b['status']??'booked'));
          $bc=$status==='booked'?'badge-green':($status==='cancelled'?'badge-gray':($status==='completed'?'badge-blue':'badge-gray'));
        ?>
          <tr>
            <td style="font-weight:600;color:var(--text-3);font-size:12px;">#<?php echo (int)$b['id']; ?></td>
            <td><?php echo html($b['day_of_week']??'—'); ?></td>
            <td style="white-space:nowrap;"><?php echo fmt_time($b['start_time']).' – '.fmt_time($b['end_time']); ?></td>
            <td>
              <span class="badge <?php echo $bc; ?>"><?php echo html(ucfirst($status)); ?></span>
              <?php if (!empty($b['cancelled_at'])): ?>
                <div style="font-size:11px;color:var(--text-3);margin-top:3px;"><?php echo html($b['cancelled_at']); ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-3);"><?php echo html($b['created_at']); ?></td>
            <td>
              <?php if ($status==='booked'): ?>
                <form method="post" action="cancel_booking.php" onsubmit="return confirm('Cancel this booking?')">
                  <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Cancel</button>
                </form>
              <?php else: ?>
                <span style="color:var(--text-3);font-size:12px;">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

</body>
</html>