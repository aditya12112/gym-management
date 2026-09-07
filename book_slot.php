<?php
// book_slot.php (single-seat-per-day enforcement)
require 'config.php';
require 'auth.php';
require_login();
require_role('member');

date_default_timezone_set('Asia/Kolkata');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Day filter from dropdown
$day_filter = isset($_GET['day']) ? $conn->real_escape_string($_GET['day']) : '';

/* ---------------- POST: attempt to book a slot (1 seat only) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['slot_id'])) {
    require_csrf();
    $slot_id = (int)$_POST['slot_id'];

    // get the slot's day_of_week
    $pst = $conn->prepare("SELECT id, day_of_week, start_time, end_time, capacity FROM time_slots WHERE id = ? LIMIT 1");
    if (!$pst) {
        $msg = "DB error.";
    } else {
        $pst->bind_param('i', $slot_id);
        $pst->execute();
        $slotRes = $pst->get_result();
        if (!$slotRes || $slotRes->num_rows === 0) {
            $msg = "Invalid slot selected.";
        } else {
            $slot = $slotRes->fetch_assoc();
            $slot_day = $slot['day_of_week'];

            // Normalize slot_day for matching: we check exact match to stored value(s)
            // Check if user already has a 'booked' booking on the same day (across all slots)
            $chk = $conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM bookings b
                JOIN time_slots t ON b.slot_id = t.id
                WHERE b.user_id = ? AND b.status = 'booked' AND (t.day_of_week = ? OR t.day_of_week = ?)
                LIMIT 1
            ");
            // We'll test two variants: the exact stored value and a guess of full/short name.
            // Build a second candidate (if slot_day is short like 'Mon' try 'Monday', and vice-versa).
            $second_candidate = $slot_day;
            $short_to_full = ['Mon'=>'Monday','Tue'=>'Tuesday','Wed'=>'Wednesday','Thu'=>'Thursday','Fri'=>'Friday','Sat'=>'Saturday','Sun'=>'Sunday'];
            $full_to_short = array_flip($short_to_full);
            if (isset($short_to_full[$slot_day])) {
                $second_candidate = $short_to_full[$slot_day];
            } elseif (isset($full_to_short[$slot_day])) {
                $second_candidate = $full_to_short[$slot_day];
            }

            if (!$chk) {
                $msg = "DB error.";
            } else {
                $chk->bind_param('iss', $user_id, $slot_day, $second_candidate);
                $chk->execute();
                $cres = $chk->get_result();
                $cnt = 0;
                if ($cres && $row = $cres->fetch_assoc()) $cnt = (int)$row['cnt'];
                $chk->close();

                if ($cnt > 0) {
                    $msg = "You already have a booking on {$slot_day}. You can only have one booking per day.";
                } else {
                    // Check capacity / effective capacity (cap at 20)
                    $capacity = (int)$slot['capacity'];
                    $effective_capacity = min($capacity, 20);

                    $bq = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS c FROM bookings WHERE slot_id = ? AND status = 'booked'");
                    if ($bq) {
                        $bq->bind_param('i', $slot_id);
                        $bq->execute();
                        $br = $bq->get_result()->fetch_assoc();
                        $booked_count = (int)($br['c'] ?? 0);
                        $bq->close();

                        if ($booked_count >= $effective_capacity) {
                            $msg = "Slot is full.";
                        } else {
                            // insert booking with quantity = 1
                            $ins = $conn->prepare("INSERT INTO bookings (user_id, slot_id, status, quantity, created_at) VALUES (?, ?, 'booked', 1, NOW())");
                            if ($ins) {
                                $ins->bind_param('ii', $user_id, $slot_id);
                                $ok = $ins->execute();
                                $ins->close();
                                if ($ok) {
                                    header("Location: ".$_SERVER['PHP_SELF']."?day=".urlencode($day_filter)."&booked=1");
                                    exit;
                                } else {
                                    $msg = "Error booking: " . $conn->error;
                                }
                            } else {
                                $msg = "DB error: " . $conn->error;
                            }
                        }
                    } else {
                        $msg = "DB error: " . $conn->error;
                    }
                }
            }
        }
        $pst->close();
    }
}

if (isset($_GET['booked'])) {
    $msg = "✅ Booking successful.";
}

/* ---------------- Build list of days the user already booked (for quick UI lookup) --------------
   We'll fetch distinct day_of_week values for which the user has a 'booked' booking.
   This lets us disable Book buttons on those days.
*/
$bookedDays = [];
$bdq = $conn->prepare("
    SELECT DISTINCT t.day_of_week AS dow
    FROM bookings b
    JOIN time_slots t ON b.slot_id = t.id
    WHERE b.user_id = ? AND b.status = 'booked'
");
if ($bdq) {
    $bdq->bind_param('i', $user_id);
    $bdq->execute();
    $res = $bdq->get_result();
    while ($r = $res->fetch_assoc()) {
        $bookedDays[] = $r['dow'];
    }
    $bdq->close();
}

/* ---------------- Day name normalization for slot query ---------------- */
$short_to_full = [
    'Mon' => 'Monday',
    'Tue' => 'Tuesday',
    'Wed' => 'Wednesday',
    'Thu' => 'Thursday',
    'Fri' => 'Friday',
    'Sat' => 'Saturday',
    'Sun' => 'Sunday'
];

$where = "1";
$sql_debug = "";

if ($day_filter) {
    $day_filter = $conn->real_escape_string($day_filter);
    $candidates = [$day_filter];
    if (isset($short_to_full[$day_filter])) {
        $candidates[] = $short_to_full[$day_filter];
    } else {
        $short = array_search($day_filter, $short_to_full, true);
        if ($short !== false) $candidates[] = $short;
    }
    $in_list = [];
    foreach ($candidates as $d) $in_list[] = "'" . $conn->real_escape_string($d) . "'";
    $where = "t.day_of_week IN (" . implode(',', array_unique($in_list)) . ")";
    $sql_debug = "Filtering by day: " . implode(', ', array_unique($in_list));
}

/* ---------------- Fetch slots with booked_count ---------------- */
$sql = "
    SELECT t.*,
           (SELECT COALESCE(SUM(quantity),0) FROM bookings b WHERE b.slot_id=t.id AND b.status='booked') AS booked_count
    FROM time_slots t
    WHERE $where
    ORDER BY FIELD(t.day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), t.start_time
";
$slots = $conn->query($sql);
if ($debug_mode) $debug_info = $sql . "\n" . $sql_debug;
else $debug_info = '';

/* ---------------- helpers for output ---------------- */
function html($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fmt_time($t){
    if (empty($t)) return '—';
    $parts = explode(':',$t);
    if (count($parts) >= 2){
        $h = (int)$parts[0]; $m = (int)$parts[1];
        $amp = $h >= 12 ? 'PM' : 'AM';
        $hh = $h % 12; if ($hh === 0) $hh = 12;
        return sprintf('%02d:%02d %s', $hh, $m, $amp);
    }
    return html($t);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <title>Book Slot (one per day)</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/style.css?v=1.4">
    <script src="assets/theme.js?v=2.0"></script>
    <style>
        .note { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
        .available-badge { padding: 4px 8px; border-radius: 6px; background:#10b981; color:white; display:inline-block; font-size:13px; }
        .full-badge { padding: 4px 8px; border-radius: 6px; background:#ef4444; color:white; display:inline-block; font-size:13px; }
        .already-badge { padding:4px 8px; border-radius:6px; background:#f59e0b; color:white; font-size:13px; }
        .btn{padding:8px 12px;border-radius:6px;background:#0b70e0;color:#fff;border:none;cursor:pointer;}
        .btn.disabled{background:#94a3b8;cursor:not-allowed;}
        table{width:100%;border-collapse:collapse;}
        th,td{padding:10px;border-bottom:1px solid #eee;text-align:left;}
        .debug{font-family:monospace;background:#f3f4f6;padding:8px;border-radius:6px;margin-top:8px;white-space:pre-wrap;color:#111827;}
    </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links">
    <a href="member_dashboard.php">← Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>
<div class="container">
    <h2>Available Slots</h2>

    <?php if ($msg): ?><p><?php echo html($msg); ?></p><?php endif; ?>

    <p class="note">You may have at most <strong>one booking per day</strong>. Each booking counts as 1 seat.</p>

    <form method="get" style="margin-bottom:12px;">
        <label>Filter by day</label>
        <select name="day" onchange="this.form.submit()">
            <option value="">All</option>
            <?php
            $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
            foreach ($days as $d) {
                $sel = ($day_filter === $d) ? 'selected' : '';
                echo "<option $sel>".html($d)."</option>";
            }
            ?>
        </select>
    </form>

    <?php
    if ($slots === false) {
        echo "<p style='color:red;'>Database error: " . html($conn->error) . "</p>";
        if ($debug_mode) echo "<div class='debug'>SQL: $sql</div>";
    } else {
        if ($slots->num_rows === 0) {
            echo "<p>No slots available for the selected day.</p>";
            if ($debug_mode && $debug_info) echo "<div class='debug'>DEBUG INFO:\n".html($debug_info)."</div>";
        } else {
            echo '<table>';
            echo '<tr><th>Day</th><th>Time</th><th>Capacity</th><th>Booked</th><th>Available</th><th>Action</th></tr>';
            $last_day = null;
            while ($s = $slots->fetch_assoc()) {
                $capacity = (int)$s['capacity'];
                $effective_capacity = min($capacity, 20);
                $booked_count = (int)$s['booked_count'];
                $available = $effective_capacity - $booked_count;
                if ($available < 0) $available = 0;

                // determine if user already booked that day (simple match against bookedDays array)
                $slot_day = $s['day_of_week'];
                $userHasThatDay = in_array($slot_day, $bookedDays, true);

                // Grouping day display
                $display_day = ($slot_day === $last_day) ? '' : $slot_day;
                $last_day = $slot_day;

                echo '<tr>';
                echo '<td><strong>'.html($display_day).'</strong></td>';
                echo '<td>'.html(fmt_time($s['start_time']).' - '.fmt_time($s['end_time'])).'</td>';
                echo '<td>'.html($capacity).'</td>';
                echo '<td>'.html($booked_count).'</td>';
                echo '<td>'.($available>0?("<span class=\"available-badge\">$available</span>"):"<span class=\"full-badge\">Full</span>").'</td>';
                echo '<td>';
                if ($userHasThatDay) {
                    echo '<span class="already-badge">You already booked this day</span>';
                } else {
                    if ($available <= 0) {
                        echo '—';
                    } else {
                        // Book form (no quantity)
                        echo '<form method="post" onsubmit="return confirm(\'Confirm booking for this slot? (one booking per day)\')">';
                        echo csrf_field();
                        echo '<input type="hidden" name="slot_id" value="'.(int)$s['id'].'">';
                        echo '<button class="btn" type="submit">Book</button>';
                        echo '</form>';
                    }
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    }
    ?>

    <?php if ($debug_mode && $debug_info): ?>
        <div class="debug"><?php echo html($debug_info); ?></div>
    <?php endif; ?>
</div>

</body>
</html>
