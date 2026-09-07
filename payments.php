<?php
require 'config.php';
require 'auth.php';
require_login();
require 'mailer.php';

$role    = $_SESSION['role']    ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);
$msg = ''; $msg_type = '';

/* ── Ensure `method` column exists (auto-fix for missing column) ── */
$col = $conn->query("SHOW COLUMNS FROM payments LIKE 'method'");
if (!$col || $col->num_rows === 0) {
    $conn->query("ALTER TABLE payments ADD COLUMN `method` VARCHAR(50) NOT NULL DEFAULT 'Cash' AFTER `amount`");
}
/* ── Ensure `txn_ref` column exists ── */
$col2 = $conn->query("SHOW COLUMNS FROM payments LIKE 'txn_ref'");
if (!$col2 || $col2->num_rows === 0) {
    $conn->query("ALTER TABLE payments ADD COLUMN `txn_ref` VARCHAR(100) DEFAULT NULL AFTER `method`");
}
/* ── Ensure `approved_at` column exists ── */
$col3 = $conn->query("SHOW COLUMNS FROM payments LIKE 'approved_at'");
if (!$col3 || $col3->num_rows === 0) {
    $conn->query("ALTER TABLE payments ADD COLUMN `approved_at` DATETIME DEFAULT NULL AFTER `txn_ref`");
}

/* ── Package prices ── */
$package_prices = [
    '1 month'   => 1000.00,
    '3 months'  => 2700.00,
    '6 months'  => 5000.00,
    '12 months' => 9000.00,
];

/* ── Helper: months from plan name ── */
function plan_months_from_name($p) {
    $p = strtolower((string)$p);
    if (strpos($p,'12') !== false || strpos($p,'year') !== false) return 12;
    if (strpos($p,'6')  !== false) return 6;
    if (strpos($p,'3')  !== false) return 3;
    return 1;
}

/* ── Recompute membership ── */
function recompute_membership($conn, $member_id) {
    $member_id = (int)$member_id;
    if ($member_id <= 0) return;
    $q = $conn->query("SELECT plan_name, payment_date FROM payments WHERE user_id=$member_id AND status='paid' ORDER BY COALESCE(payment_date,'1970-01-01') ASC, id ASC");
    if (!$q) return;
    $today = new DateTime('now');
    $current_start = $current_end = null;
    while ($row = $q->fetch_assoc()) {
        $months = plan_months_from_name($row['plan_name']);
        $pay_dt = null;
        if (!empty($row['payment_date'])) { try { $pay_dt = new DateTime($row['payment_date']); } catch(Exception $e){} }
        if ($current_end instanceof DateTime) {
            $next = (clone $current_end)->modify('+1 day');
            $use_start = ($pay_dt && $pay_dt > $next) ? $pay_dt : $next;
        } else {
            // Always start from the payment_date the member entered, not today's approval date
            $use_start = $pay_dt ? $pay_dt : clone $today;
        }
        $current_end = (clone $use_start)->modify("+{$months} months")->modify('-1 day');
        if (!$current_start) $current_start = $use_start;
    }
    if (!$current_start || !$current_end) {
        $conn->query("UPDATE users SET membership_start=NULL,membership_end=NULL,membership_status='inactive' WHERE id=$member_id");
    } else {
        $s = $current_start->format('Y-m-d');
        $e = $current_end->format('Y-m-d');
        $ms = ($current_end >= $today) ? 'active' : 'inactive';
        $conn->query("UPDATE users SET membership_start='$s',membership_end='$e',membership_status='$ms' WHERE id=$member_id");
    }
}

/* ── MEMBER: Submit payment ── */
if ($role === 'member' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_submit'])) {
    require_csrf();
    $plan_name_raw = $_POST['plan_name'] ?? '';
    $method        = trim($_POST['method'] ?? 'Cash');
    $date          = trim($_POST['payment_date'] ?? '');
    $txn_ref       = trim($_POST['txn_ref'] ?? '');
    $amount        = isset($package_prices[$plan_name_raw]) ? (float)$package_prices[$plan_name_raw] : 0;

    if ($amount <= 0) {
        $msg = "Invalid package selected."; $msg_type = 'error';
    } elseif (empty($plan_name_raw) || empty($date)) {
        $msg = "All fields are required."; $msg_type = 'error';
    } else {
        $ins = $conn->prepare("INSERT INTO payments (user_id, amount, plan_name, method, txn_ref, status, payment_date, created_at)
                               VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())");
        if ($ins) {
            $ins->bind_param('idssss', $user_id, $amount, $plan_name_raw, $method, $txn_ref, $date);
            if ($ins->execute()) {
                $ins->close();
                header("Location: ".$_SERVER['PHP_SELF']."?success=1");
                exit;
            } else {
                $msg = "Error saving: " . htmlspecialchars($conn->error);
                $msg_type = 'error';
            }
            $ins->close();
        } else {
            $msg = "Database error. Please try again.";
            $msg_type = 'error';
        }
    }
}

/* ── ADMIN: Approve / Reject / Pending ── */
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    require_csrf();
    $action  = $_POST['admin_action'];
    $pay_id  = (int)($_POST['payment_id'] ?? 0);
    $ret     = $_POST['return_status'] ?? '';
    $map     = ['approve'=>'paid', 'reject'=>'failed', 'pending'=>'pending'];
    if ($pay_id > 0 && isset($map[$action])) {
        $ns = $map[$action];

        /* Fetch payment + member details before updating using prepared query */
        $pr = $conn->prepare("
            SELECT p.user_id, p.plan_name, p.amount, p.payment_date,
                   u.name, u.email
            FROM payments p
            JOIN users u ON u.id = p.user_id
            WHERE p.id = ? LIMIT 1
        ");
        $pdata = null;
        if ($pr) {
            $pr->bind_param('i', $pay_id);
            $pr->execute();
            $p_res = $pr->get_result();
            if ($p_res && $p_res->num_rows) $pdata = $p_res->fetch_assoc();
            $pr->close();
        }
        $uid = $pdata ? (int)$pdata['user_id'] : 0;

        $approved_sql = ($action === 'approve') ? ", approved_at = NOW(), approved_by = ?" : (($action === 'reject') ? ", approved_at = NULL, approved_by = ?" : '');
        $upd_sql = "UPDATE payments SET status = ?" . $approved_sql . " WHERE id = ?";
        $upd = $conn->prepare($upd_sql);
        if ($upd) {
            if ($approved_sql !== '') {
                $admin_id = (int)$_SESSION['user_id'];
                $upd->bind_param('sii', $ns, $admin_id, $pay_id);
            } else {
                $upd->bind_param('si', $ns, $pay_id);
            }
            if ($upd->execute()) {
                $upd->close();
                if ($uid > 0) recompute_membership($conn, $uid);

                /* ── Send email notification ── */
                if ($pdata && !empty($pdata['email'])) {
                    if ($action === 'approve') {
                        $er = $conn->prepare("SELECT membership_end FROM users WHERE id = ? LIMIT 1");
                        $er->bind_param('i', $uid);
                        $er->execute();
                        $er_res = $er->get_result();
                        $end = ($er_res && $er_res->num_rows) ? ($er_res->fetch_assoc()['membership_end'] ?? '—') : '—';
                        $er->close();

                        mail_payment_confirmed(
                            $pdata['email'],
                            $pdata['name'],
                            $pdata['plan_name'],
                            (float)$pdata['amount'],
                            $pdata['payment_date'] ?? '—',
                            $end
                        );
                    } elseif ($action === 'reject') {
                        mail_payment_rejected(
                            $pdata['email'],
                            $pdata['name'],
                            $pdata['plan_name'],
                            (float)$pdata['amount']
                        );
                    }
                }

                header("Location: payments.php" . ($ret ? "?status=" . urlencode($ret) . "&updated=1" : "?updated=1"));
                exit;
            } else {
                $msg = "Error updating payment: " . htmlspecialchars($conn->error);
                $msg_type = 'error';
                $upd->close();
            }
        }
    }
}

/* ── Build query ── */
$status_filter = '';
if ($role === 'admin' && isset($_GET['status']) && in_array($_GET['status'], ['all','pending','paid','failed'])) {
    $status_filter = $_GET['status'];
}

if ($role === 'admin') {
    $where = "1";
    if ($status_filter && $status_filter !== 'all') {
        $sf = $conn->real_escape_string($status_filter);
        $where = "p.status='$sf'";
    }
    $payments = $conn->query("
        SELECT p.id, p.plan_name, p.amount, p.method, p.txn_ref, p.status, p.payment_date, u.name
        FROM payments p
        JOIN users u ON p.user_id = u.id
        WHERE $where
        ORDER BY p.id DESC
    ");
} else {
    $payments = $conn->query("
        SELECT id, plan_name, amount, method, txn_ref, status, payment_date
        FROM payments
        WHERE user_id = $user_id
        ORDER BY id DESC
    ");
}

/* ── Error guard: if query failed show a useful message ── */
$query_error = '';
if ($payments === false) {
    $query_error = "Database error loading payments: " . htmlspecialchars($conn->error);
    $payments = null;
}

/* ── Stats for admin ── */
$stats = ['total'=>0, 'pending'=>0, 'paid'=>0, 'failed'=>0, 'revenue'=>0];
if ($role === 'admin') {
    $sr = $conn->query("SELECT status, COUNT(*) c, SUM(amount) s FROM payments GROUP BY status");
    if ($sr) while ($r = $sr->fetch_assoc()) {
        $stats['total']  += (int)$r['c'];
        $key = strtolower($r['status']);
        if (isset($stats[$key])) $stats[$key] = (int)$r['c'];
        if ($r['status'] === 'paid') $stats['revenue'] = (float)$r['s'];
    }
}

$back = $role === 'admin' ? 'admin_dashboard.php' : 'member_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Payments — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.4">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .pay-layout { display:grid; grid-template-columns:360px 1fr; gap:24px; align-items:start; }
    .filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
    .filter-btn { padding:7px 16px; border-radius:var(--radius); border:1px solid var(--border);
      background:var(--bg-3); color:var(--text-2); text-decoration:none; font-size:13px;
      font-weight:600; transition:all 0.2s; }
    .filter-btn:hover, .filter-btn.active { background:var(--accent); color:var(--bg); border-color:var(--accent); }
    .pkg-card { border:1px solid var(--border); border-radius:var(--radius); padding:14px 16px;
      cursor:pointer; transition:all 0.2s; position:relative; }
    .pkg-card:hover { border-color:var(--accent-dim); }
    .pkg-card.selected { border-color:var(--accent); background:var(--accent-glow); }
    .pkg-card input[type=radio] { position:absolute; opacity:0; pointer-events:none; }
    .pkg-name { font-family:'Barlow Condensed',sans-serif; font-size:1.1rem; font-weight:700; }
    .pkg-price { font-size:1.4rem; font-weight:800; font-family:'Barlow Condensed',sans-serif; color:var(--accent); }
    .pkg-save { font-size:11px; color:var(--success); margin-top:2px; }
    .pkg-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px; }
    @media (max-width:900px) { .pay-layout { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links">
    <a href="<?php echo $back; ?>">← Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <h2>💳 <span class="accent">Payments</span></h2>
    <?php if ($role === 'admin'): ?>
      <span class="badge badge-lime">Admin View</span>
    <?php endif; ?>
  </div>

  <?php if (!empty($_GET['success'])): ?><div class="alert alert-success">✅ Payment submitted successfully — awaiting admin approval.</div><?php endif; ?>
  <?php if (!empty($_GET['updated'])): ?><div class="alert alert-success">✅ Payment status updated.</div><?php endif; ?>
  <?php if ($msg):     ?><div class="alert alert-<?php echo $msg_type==='error'?'error':'success'; ?>"><?php echo $msg_type==='error'?'⚠':'✅'; ?> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <?php if ($query_error): ?><div class="alert alert-error">⚠ <?php echo $query_error; ?></div><?php endif; ?>

  <?php if ($role === 'admin'): ?>
  <!-- Admin Stats -->
  <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:28px;">
    <div class="stat-card"><div class="stat-label">Total Payments</div><div class="stat-num"><?php echo $stats['total']; ?></div></div>
    <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-num" style="color:var(--warning);"><?php echo $stats['pending']; ?></div></div>
    <div class="stat-card"><div class="stat-label">Approved</div><div class="stat-num" style="color:var(--success);"><?php echo $stats['paid']; ?></div></div>
    <div class="stat-card"><div class="stat-label">Revenue (₹)</div><div class="stat-num" style="color:var(--accent);">₹<?php echo number_format($stats['revenue'],0); ?></div></div>
  </div>
  <?php endif; ?>

  <?php if ($role === 'member'): ?>
  <div class="pay-layout">
    <!-- Left: Submit form -->
    <div class="form-card">
      <h3 style="margin-bottom:4px;">Buy Membership</h3>
      <p class="subtitle">Select a plan — amount is fixed and set by the gym.</p>

      <form method="post" id="payForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="member_submit" value="1">
        <input type="hidden" name="plan_name" id="planNameHidden">
        <input type="hidden" name="amount"    id="amountHidden">

        <!-- Package cards -->
        <div class="pkg-grid" id="pkgGrid">
          <?php
          $savings = ['1 month'=>null,'3 months'=>'Save ₹300','6 months'=>'Save ₹1,000','12 months'=>'Save ₹3,000'];
          foreach ($package_prices as $plan => $price):
          ?>
          <div class="pkg-card" id="pkg-<?php echo str_replace(' ','-',$plan); ?>" onclick="selectPkg('<?php echo htmlspecialchars($plan,ENT_QUOTES); ?>',<?php echo $price; ?>)">
            <div class="pkg-name"><?php echo htmlspecialchars($plan); ?></div>
            <div class="pkg-price">₹<?php echo number_format($price,0); ?></div>
            <?php if ($savings[$plan]): ?><div class="pkg-save">🎉 <?php echo $savings[$plan]; ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="form-group">
          <label>Payment Method</label>
          <select name="method" id="methodSel" required>
            <option value="Cash">💵 Cash</option>
            <option value="UPI">📱 UPI</option>
            <option value="Card">💳 Card</option>
            <option value="Net Banking">🏦 Net Banking</option>
          </select>
        </div>

        <div class="form-group" id="txnGroup" style="display:none;">
          <label>Transaction Reference / UTR <span style="color:var(--text-3);font-size:11px;">(optional)</span></label>
          <input type="text" name="txn_ref" placeholder="e.g. UPI ref number">
        </div>

        <div class="form-group">
          <label>Payment Date</label>
          <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
        </div>

        <div id="selectedSummary" style="display:none;background:var(--accent-glow);border:1px solid rgba(200,241,53,0.2);border-radius:var(--radius);padding:12px;margin-bottom:16px;">
          <div style="font-size:12px;color:var(--text-3);">Selected Plan</div>
          <div style="font-family:'Barlow Condensed',sans-serif;font-size:1.3rem;font-weight:800;" id="summaryText"></div>
        </div>

        <button class="btn" type="submit" id="submitBtn" disabled style="width:100%;justify-content:center;padding:13px;opacity:0.5;">
          Submit for Approval →
        </button>
      </form>
    </div>

    <!-- Right: Payment history for member -->
    <div class="card">
      <h3 style="margin-bottom:16px;">Payment History</h3>
      <?php if ($payments && $payments->num_rows > 0): ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Payment Date</th></tr></thead>
            <tbody>
            <?php while($p = $payments->fetch_assoc()):
              $ps = strtolower($p['status']??'');
              $bc = $ps==='paid'?'badge-green':($ps==='failed'||$ps==='rejected'?'badge-red':'badge-yellow');
              $pd = !empty($p['payment_date']) ? date('D, d M Y', strtotime($p['payment_date'])) : '—';
            ?>
              <tr>
                <td style="font-weight:600;"><?php echo htmlspecialchars($p['plan_name']); ?></td>
                <td>₹<?php echo number_format((float)$p['amount'],0); ?></td>
                <td><?php echo htmlspecialchars($p['method']??'—'); ?></td>
                <td><span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars(ucfirst($ps==='paid'?'Approved':($ps==='failed'?'Rejected':$ps))); ?></span></td>
                <td style="white-space:nowrap;">
                  <div style="font-size:13px;color:var(--text-1);font-weight:600;"><?php echo htmlspecialchars($pd); ?></div>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--text-3);">No payment history yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <?php else: /* ADMIN VIEW */ ?>

  <!-- Filter bar -->
  <div class="filter-bar">
    <a href="payments.php" class="filter-btn <?php echo $status_filter===''?'active':''; ?>">All</a>
    <a href="payments.php?status=pending" class="filter-btn <?php echo $status_filter==='pending'?'active':''; ?>">⏳ Pending</a>
    <a href="payments.php?status=paid"    class="filter-btn <?php echo $status_filter==='paid'?'active':''; ?>">✅ Approved</a>
    <a href="payments.php?status=failed"  class="filter-btn <?php echo $status_filter==='failed'?'active':''; ?>">❌ Rejected</a>
  </div>

  <div class="card">
    <?php if ($payments && $payments->num_rows > 0): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Member</th><th>Plan</th><th>Amount (₹)</th><th>Method</th><th>Status</th><th>Payment Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php while($p = $payments->fetch_assoc()):
          $ps = strtolower($p['status']??'pending');
          $bc = $ps==='paid'?'badge-green':($ps==='failed'?'badge-red':'badge-yellow');
          $pd = !empty($p['payment_date']) ? date('D, d M Y', strtotime($p['payment_date'])) : '—';
        ?>
          <tr>
            <td style="font-weight:600;"><?php echo htmlspecialchars($p['name']); ?></td>
            <td><?php echo htmlspecialchars($p['plan_name']); ?></td>
            <td style="font-weight:700;">₹<?php echo number_format((float)$p['amount'],0); ?></td>
            <td><?php echo htmlspecialchars($p['method']??'—'); ?></td>
            <td><span class="badge <?php echo $bc; ?>"><?php echo $ps==='paid'?'Approved':($ps==='failed'?'Rejected':ucfirst($ps)); ?></span></td>
            <td style="white-space:nowrap;">
              <div style="font-size:13px;color:var(--text-1);font-weight:600;"><?php echo htmlspecialchars($pd); ?></div>
            </td>
            <td style="white-space:nowrap;">
              <?php if ($ps !== 'paid'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Approve this payment?')">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="payment_id"   value="<?php echo (int)$p['id']; ?>">
                <input type="hidden" name="admin_action" value="approve">
                <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <button class="btn btn-sm" style="background:var(--success);" type="submit">✅ Approve</button>
              </form>
              <?php endif; ?>
              <?php if ($ps !== 'failed'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Reject this payment?')">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="payment_id"   value="<?php echo (int)$p['id']; ?>">
                <input type="hidden" name="admin_action" value="reject">
                <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <button class="btn btn-sm btn-danger" type="submit">❌ Reject</button>
              </form>
              <?php endif; ?>
              <?php if ($ps !== 'pending'): ?>
              <form method="post" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="payment_id"   value="<?php echo (int)$p['id']; ?>">
                <input type="hidden" name="admin_action" value="pending">
                <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <button class="btn btn-sm btn-outline" type="submit">⏳ Pending</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php elseif (!$query_error): ?>
      <div style="text-align:center;padding:50px;">
        <div style="font-size:40px;margin-bottom:12px;">💳</div>
        <p style="color:var(--text-3);">No payments found<?php echo $status_filter?(' for filter: '.$status_filter):''; ?>.</p>
      </div>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div>

<script>
function selectPkg(plan, amount) {
  // update hidden fields
  document.getElementById('planNameHidden').value = plan;
  document.getElementById('amountHidden').value   = amount;

  // highlight card
  document.querySelectorAll('.pkg-card').forEach(c => c.classList.remove('selected'));
  const id = 'pkg-' + plan.replace(/ /g, '-');
  const card = document.getElementById(id);
  if (card) card.classList.add('selected');

  // update summary
  const s = document.getElementById('selectedSummary');
  const t = document.getElementById('summaryText');
  if (s && t) { s.style.display='block'; t.textContent = plan + ' — ₹' + amount.toLocaleString('en-IN'); }

  // enable submit
  const btn = document.getElementById('submitBtn');
  if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
}

// Show/hide txn ref based on method
const methodSel = document.getElementById('methodSel');
if (methodSel) {
  methodSel.addEventListener('change', function() {
    const g = document.getElementById('txnGroup');
    if (g) g.style.display = (this.value === 'UPI' || this.value === 'Card' || this.value === 'Net Banking') ? 'block' : 'none';
  });
}

// Pre-select plan from URL ?plan=...
const urlPlan = new URLSearchParams(window.location.search).get('plan');
if (urlPlan) {
  const prices = <?php echo json_encode($package_prices); ?>;
  if (prices[urlPlan]) selectPkg(urlPlan, prices[urlPlan]);
}
</script>

</body>
</html>