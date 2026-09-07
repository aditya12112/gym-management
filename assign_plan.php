<?php
// assign_plan.php
require 'config.php';
require 'auth.php';
require_login();
require_role('trainer');

session_start(); // just in case

$trainer_id = (int)($_SESSION['user_id'] ?? 0);
if ($trainer_id <= 0) {
    $_SESSION['error'] = "Invalid trainer session.";
    header('Location: trainer_dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: trainer_dashboard.php'); exit;
}

$plan_type = trim($_POST['plan_type'] ?? '');
$user_id   = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$plan_text = trim($_POST['plan_text'] ?? '');

if (!in_array($plan_type, ['diet','exercise'], true) || $user_id <= 0 || $plan_text === '') {
    $_SESSION['error'] = "Please choose a member and enter a description.";
    header('Location: trainer_dashboard.php'); exit;
}

/* ---------- helper: detect users-like table that contains the target user ---------- */
function detect_user_table_for_id($conn, $user_id) {
    $candidates = ['users','user_login','members'];
    foreach ($candidates as $tbl) {
        try {
            $r = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($tbl)."'");
            if (!$r || $r->num_rows === 0) continue;
            // check row exists
            $pst = $conn->prepare("SELECT id FROM `{$tbl}` WHERE id = ? LIMIT 1");
            if (!$pst) continue;
            $pst->bind_param('i', $user_id);
            $pst->execute();
            $res = $pst->get_result();
            $pst->close();
            if ($res && $res->num_rows) return $tbl;
        } catch (Exception $e) { continue; }
    }
    return null;
}

/* ---------- helper: check role column if present and ensure user is member ---------- */
function is_user_member($conn, $tbl, $user_id) {
    // if table has 'role' column, ensure role='member'
    try {
        $r = $conn->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'role'");
        if ($r && $r->num_rows) {
            $pst = $conn->prepare("SELECT role FROM `{$tbl}` WHERE id = ? LIMIT 1");
            if ($pst) {
                $pst->bind_param('i', $user_id);
                $pst->execute();
                $res = $pst->get_result();
                $pst->close();
                if ($res && $res->num_rows) {
                    $rw = $res->fetch_assoc();
                    $role = strtolower(trim((string)($rw['role'] ?? '')));
                    return $role === 'member';
                }
            }
            // if something went wrong, deny by default
            return false;
        }
    } catch (Exception $e) {}
    // no role column -> treat all rows as members (legacy)
    return true;
}

/* ---------- locate target user ---------- */
$tbl = detect_user_table_for_id($conn, $user_id);
if (!$tbl) {
    $_SESSION['error'] = "Selected member not found.";
    header('Location: trainer_dashboard.php'); exit;
}

if (!is_user_member($conn, $tbl, $user_id)) {
    $_SESSION['error'] = "Selected user is not a member.";
    header('Location: trainer_dashboard.php'); exit;
}

/* ---------- check active membership ---------- */
if ($tbl === 'users') {
    $memb_res = $conn->query("SELECT membership_status FROM `{$tbl}` WHERE id=$user_id LIMIT 1");
    if ($memb_res && $memb_res->num_rows) {
        $memb_row = $memb_res->fetch_assoc();
        if (strtolower(trim($memb_row['membership_status'] ?? '')) !== 'active') {
            $_SESSION['error'] = "Cannot assign plans: Member does not have an active subscription.";
            header('Location: trainer_dashboard.php'); exit;
        }
    }
}

/* ---------- ensure assignments table exists (non-destructive) ---------- */
$create_sql = "
CREATE TABLE IF NOT EXISTS user_plan_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  trainer_id INT NOT NULL,
  plan_type VARCHAR(50) NOT NULL,
  plan_id INT NOT NULL DEFAULT 0,
  plan_text TEXT NULL,
  status VARCHAR(30) DEFAULT 'assigned',
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if ($conn->query($create_sql) === false) {
    $_SESSION['error'] = "DB error: failed to ensure assignments table (" . $conn->error . ")";
    header('Location: trainer_dashboard.php'); exit;
}

/* ---------- ensure plan_text column exists (if older schema used custom_notes) ---------- */
try {
    $col = $conn->query("SHOW COLUMNS FROM user_plan_assignments LIKE 'plan_text'");
    if (!$col || $col->num_rows === 0) {
        // attempt add column
        $conn->query("ALTER TABLE user_plan_assignments ADD COLUMN plan_text TEXT NULL");
    }
} catch (Exception $e) {
    // ignore - we'll still try to insert into columns that exist
}

/* ---------- perform insert (use plan_id = 0 because trainer is entering text directly) ---------- */
$insert_sql = "
    INSERT INTO user_plan_assignments (user_id, trainer_id, plan_type, plan_id, plan_text, status, assigned_at)
    VALUES (?, ?, ?, 0, ?, 'assigned', NOW())
";
$stmt = $conn->prepare($insert_sql);
if (!$stmt) {
    $_SESSION['error'] = "DB error: prepare failed (" . $conn->error . ")";
    header('Location: trainer_dashboard.php'); exit;
}
$stmt->bind_param('iiss', $user_id, $trainer_id, $plan_type, $plan_text);
$ok = $stmt->execute();
if (! $ok) {
    $_SESSION['error'] = "DB error: insert failed (" . $stmt->error . ")";
    $stmt->close();
    header('Location: trainer_dashboard.php'); exit;
}
$inserted_id = $stmt->insert_id;
$stmt->close();

/* ---------- Send plan assigned email ---------- */
require_once __DIR__ . '/mailer.php';
$member_r  = $conn->query("SELECT name, email FROM users WHERE id=$user_id LIMIT 1");
$trainer_r = $conn->query("SELECT name FROM users WHERE id=$trainer_id LIMIT 1");
if ($member_r && $member_r->num_rows) {
    $member  = $member_r->fetch_assoc();
    $trainer = ($trainer_r && $trainer_r->num_rows) ? $trainer_r->fetch_assoc()['name'] : 'Your Trainer';
    if (!empty($member['email'])) {
        mail_plan_assigned($member['email'], $member['name'], $plan_type, $plan_text, $trainer);
    }
}

/* ---------- success ---------- */
// optional: you may want to record an audit log here
$_SESSION['success'] = ucfirst($plan_type) . " assigned to member successfully.";

// redirect back to dashboard (trainer view)
header('Location: trainer_dashboard.php');
exit;
