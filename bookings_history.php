<?php
// bookings_history.php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

date_default_timezone_set('Asia/Kolkata');

function html($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ── Build member list ── */
$members = [];
try {
    $res = $conn->query("SELECT id, name FROM users WHERE role='member' ORDER BY name LIMIT 2000");
    if ($res) while ($r = $res->fetch_assoc()) $members[(int)$r['id']] = $r['name'];
} catch (Exception $e) {}

/* ── Filters ── */
$from        = isset($_GET['from'])    ? $_GET['from']               : date('Y-m-01');
$to          = isset($_GET['to'])      ? $_GET['to']                 : date('Y-m-d');
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id']       : 0;

$where  = [];
$params = [];
$types  = '';

if ($from)          { $where[] = "DATE(b.created_at) >= ?"; $params[] = $from; $types .= 's'; }
if ($to)            { $where[] = "DATE(b.created_at) <= ?"; $params[] = $to;   $types .= 's'; }
if ($filter_user>0) { $where[] = "b.user_id = ?";           $params[] = $filter_user; $types .= 'i'; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT b.id, b.user_id, b.slot_id, b.created_at, b.status,
           t.day_of_week, t.start_time, t.end_time
    FROM bookings b
    LEFT JOIN time_slots t ON b.slot_id = t.id
    {$where_sql}
    ORDER BY b.created_at DESC
    LIMIT 1000
";

$bookings = [];
$err = null;
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $bookings[] = $row;
    $stmt->close();
} else {
    $err = $conn->error;
}

function resolve_name($members, $id) {
    return isset($members[(int)$id]) ? $members[(int)$id] : ('ID ' . (int)$id);
}

function fmt_time($t) {
    if (empty($t)) return '—';
    $p = explode(':', $t); if (count($p) < 2) return htmlspecialchars($t);
    $h = (int)$p[0]; $m = (int)$p[1]; $ap = $h >= 12 ? 'PM' : 'AM'; $hh = $h % 12; if (!$hh) $hh = 12;
    return sprintf('%d:%02d %s', $hh, $m, $ap);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Booking History — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .filter-row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:0; }
    .filter-row .form-group { margin-bottom:0; }
    .filter-row label { margin-bottom:5px; }
  </style>
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
    <h2>📜 Booking <span class="accent">History</span></h2>
    <span class="badge badge-lime">Admin View</span>
  </div>

  <!-- Filter card -->
  <div class="card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:16px;">Filter Bookings</h3>
    <form method="get">
      <div class="filter-row">
        <div class="form-group">
          <label>From</label>
          <input type="date" name="from" value="<?php echo html($from); ?>">
        </div>
        <div class="form-group">
          <label>To</label>
          <input type="date" name="to" value="<?php echo html($to); ?>">
        </div>
        <div class="form-group">
          <label>Member</label>
          <select name="user_id" style="min-width:180px;">
            <option value="0">All Members</option>
            <?php foreach ($members as $mid => $mn): ?>
              <option value="<?php echo (int)$mid; ?>" <?php if ($filter_user == $mid) echo 'selected'; ?>>
                <?php echo html($mn); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="opacity:0;">.</label>
          <button class="btn" type="submit">🔍 Filter</button>
        </div>
      </div>
    </form>
  </div>

  <?php if ($err): ?>
    <div class="alert alert-error">⚠ Database error: <?php echo html($err); ?></div>
  <?php endif; ?>

  <!-- Results -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;">Results</h3>
      <span class="badge badge-lime"><?php echo count($bookings); ?> records</span>
    </div>

    <?php if (empty($bookings)): ?>
      <div style="text-align:center;padding:50px;">
        <div style="font-size:40px;margin-bottom:12px;">📅</div>
        <p style="color:var(--text-3);">No bookings found for the selected criteria.</p>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Member</th>
            <th>Slot</th>
            <th>Time</th>
            <th>Booked At</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 0; foreach ($bookings as $b): $i++;
            $bs  = strtolower($b['status'] ?? 'booked');
            $bbc = $bs === 'booked' ? 'badge-green' : ($bs === 'cancelled' ? 'badge-red' : 'badge-gray');
          ?>
          <tr>
            <td style="color:var(--text-3);font-size:12px;"><?php echo $i; ?></td>
            <td>
              <div style="font-weight:600;"><?php echo html(resolve_name($members, (int)$b['user_id'])); ?></div>
              <div style="font-size:11px;color:var(--text-3);">ID: <?php echo (int)$b['user_id']; ?></div>
            </td>
            <td style="font-weight:600;"><?php echo html($b['day_of_week'] ?? '—'); ?></td>
            <td style="font-size:13px;white-space:nowrap;">
              <?php echo fmt_time($b['start_time']); ?> – <?php echo fmt_time($b['end_time']); ?>
            </td>
            <td style="font-size:13px;color:var(--text-3);white-space:nowrap;"><?php echo html($b['created_at']); ?></td>
            <td><span class="badge <?php echo $bbc; ?>"><?php echo html(ucfirst($bs)); ?></span></td>
            <td>
              <a href="booking_view.php?user_id=<?php echo (int)$b['user_id']; ?>" class="btn btn-sm btn-outline">👁 View</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>


</body>
</html>
