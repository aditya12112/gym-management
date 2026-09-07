<?php
/**
 * api_member_bookings.php
 * AJAX endpoint: returns HTML fragment with a member's full booking history.
 * Only accessible to logged-in admins.
 */
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

date_default_timezone_set('Asia/Kolkata');

function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    echo '<div class="alert alert-error">⚠ Invalid member ID.</div>';
    exit;
}

/* ── Resolve member name ── */
$member_name = 'Member';
try {
    $r = $conn->prepare("SELECT name, email, membership_status, membership_start, membership_end FROM users WHERE id = ? LIMIT 1");
    if ($r) {
        $r->bind_param('i', $user_id);
        $r->execute();
        $res = $r->get_result();
        $r->close();
        if ($res && $res->num_rows) {
            $u = $res->fetch_assoc();
            $member_name = $u['name'] ?: ('ID ' . $user_id);
        }
    }
} catch (Exception $e) {}

/* ── Fetch bookings ── */
$bookings = [];
$db_err   = null;
try {
    $stmt = $conn->prepare("
        SELECT b.id, b.slot_id, b.status, b.created_at,
               t.day_of_week, t.start_time, t.end_time
        FROM bookings b
        LEFT JOIN time_slots t ON b.slot_id = t.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
        LIMIT 200
    ");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $bookings[] = $row;
        $stmt->close();
    } else {
        $db_err = $conn->error;
    }
} catch (Exception $e) {
    $db_err = $e->getMessage();
}

/* ── Helper: format time ── */
function fmt($t) {
    if (empty($t)) return '—';
    $p = explode(':', $t);
    if (count($p) < 2) return esc($t);
    $h  = (int)$p[0]; $m = (int)$p[1];
    $ap = $h >= 12 ? 'PM' : 'AM';
    $hh = $h % 12; if (!$hh) $hh = 12;
    return sprintf('%d:%02d %s', $hh, $m, $ap);
}

/* ── Member meta ── */
$ms    = strtolower($u['membership_status'] ?? 'inactive');
$msBdg = $ms === 'active' ? 'badge-green' : ($ms === 'inactive' ? 'badge-gray' : 'badge-red');
$start = !empty($u['membership_start']) ? date('d M Y', strtotime($u['membership_start'])) : '—';
$end   = !empty($u['membership_end'])   ? date('d M Y', strtotime($u['membership_end']))   : '—';
?>

<style>
.drawer-meta { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px; }
.drawer-meta-item { background:var(--bg-3); border:1px solid var(--border); border-radius:var(--radius); padding:12px 14px; }
.drawer-meta-item .dm-label { font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; }
.drawer-meta-item .dm-val { font-size:14px; font-weight:600; color:var(--text-1); }
.bk-empty { text-align:center; padding:36px 0; color:var(--text-3); font-size:14px; }
</style>

<!-- Member info strip -->
<div style="margin-bottom:16px;">
  <div style="font-size:18px;font-weight:700;font-family:'Barlow Condensed',sans-serif;color:var(--text-1);margin-bottom:6px;">
    <?php echo esc($member_name); ?>
  </div>
  <div style="font-size:12px;color:var(--text-3);">User ID: <?php echo $user_id; ?> &nbsp;·&nbsp; <?php echo esc($u['email'] ?? ''); ?></div>
</div>

<!-- Membership quick stats -->
<div class="drawer-meta">
  <div class="drawer-meta-item">
    <div class="dm-label">Membership</div>
    <div class="dm-val"><span class="badge <?php echo $msBdg; ?>"><?php echo ucfirst($ms); ?></span></div>
  </div>
  <div class="drawer-meta-item">
    <div class="dm-label">Total Bookings</div>
    <div class="dm-val"><?php echo count($bookings); ?></div>
  </div>
  <div class="drawer-meta-item">
    <div class="dm-label">Active From</div>
    <div class="dm-val"><?php echo $start; ?></div>
  </div>
  <div class="drawer-meta-item">
    <div class="dm-label">Valid Until</div>
    <div class="dm-val"><?php echo $end; ?></div>
  </div>
</div>

<?php if ($db_err): ?>
  <div class="alert alert-error">⚠ <?php echo esc($db_err); ?></div>
<?php elseif (empty($bookings)): ?>
  <div class="bk-empty">📅 No bookings found for this member.</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Slot</th>
        <th>Time</th>
        <th>Status</th>
        <th>Booked On</th>
      </tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($bookings as $b): $i++;
      $bs  = strtolower($b['status'] ?? 'booked');
      $bbc = $bs === 'booked' ? 'badge-green' : ($bs === 'cancelled' ? 'badge-red' : 'badge-gray');
    ?>
      <tr>
        <td style="color:var(--text-3);font-size:12px;"><?php echo $i; ?></td>
        <td style="font-weight:600;"><?php echo esc($b['day_of_week'] ?? '—'); ?></td>
        <td style="font-size:13px;white-space:nowrap;"><?php echo fmt($b['start_time']); ?> – <?php echo fmt($b['end_time']); ?></td>
        <td><span class="badge <?php echo $bbc; ?>"><?php echo esc(ucfirst($bs)); ?></span></td>
        <td style="font-size:12px;color:var(--text-3);white-space:nowrap;"><?php echo esc($b['created_at'] ?? ''); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
