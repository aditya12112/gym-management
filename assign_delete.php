<?php
// assign_delete.php
require 'config.php';
require 'auth.php';
require_login();
require_role('trainer');

$trainer_id = (int)($_SESSION['user_id'] ?? 0);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = "Invalid assignment id.";
    header('Location: trainer_dashboard.php');
    exit;
}

// ensure the assignment belongs to this trainer
$pst = $conn->prepare("SELECT id FROM user_plan_assignments WHERE id = ? AND trainer_id = ? LIMIT 1");
if (!$pst) {
    $_SESSION['error'] = "DB error: " . $conn->error;
    header('Location: trainer_dashboard.php');
    exit;
}
$pst->bind_param('ii', $id, $trainer_id);
$pst->execute();
$res = $pst->get_result();
if (!$res || $res->num_rows === 0) {
    $_SESSION['error'] = "Assignment not found or you don't have permission to delete it.";
    $pst->close();
    header('Location: trainer_dashboard.php');
    exit;
}
$pst->close();

// delete
$del = $conn->prepare("DELETE FROM user_plan_assignments WHERE id = ? AND trainer_id = ? LIMIT 1");
if ($del) {
    $del->bind_param('ii', $id, $trainer_id);
    $ok = $del->execute();
    $del->close();
    if ($ok) {
        $_SESSION['success'] = "Assignment deleted.";
    } else {
        $_SESSION['error'] = "Unable to delete assignment: " . $conn->error;
    }
} else {
    $_SESSION['error'] = "DB error: " . $conn->error;
}

header('Location: trainer_dashboard.php');
exit;
