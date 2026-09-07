<?php
// cancel_booking.php
require 'config.php';
require 'auth.php';
require_login();
require_role('member');

$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_bookings.php');
    exit;
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
if ($booking_id <= 0) {
    header('Location: my_bookings.php?msg=' . urlencode('Invalid booking id.'));
    exit;
}

/* verify booking belongs to user and is active (status = 'booked') */
$check = $conn->prepare("SELECT id, status FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
if (!$check) {
    header('Location: my_bookings.php?msg=' . urlencode('DB error.'));
    exit;
}
$check->bind_param('ii', $booking_id, $user_id);
$check->execute();
$res = $check->get_result();
if (!$res || $res->num_rows === 0) {
    $check->close();
    header('Location: my_bookings.php?msg=' . urlencode('Booking not found or not owned by you.'));
    exit;
}
$row = $res->fetch_assoc();
$check->close();

$status = strtolower(trim($row['status'] ?? ''));
if ($status !== 'booked') {
    header('Location: my_bookings.php?msg=' . urlencode('Booking cannot be cancelled (already '.$status.').'));
    exit;
}

/* perform cancel: set status = 'cancelled', cancelled_at = NOW() */
$upd = $conn->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND user_id = ? LIMIT 1");
if (!$upd) {
    header('Location: my_bookings.php?msg=' . urlencode('DB update error: '.$conn->error));
    exit;
}
$upd->bind_param('ii', $booking_id, $user_id);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    header('Location: my_bookings.php?msg=' . urlencode('Booking cancelled.'));
    exit;
} else {
    header('Location: my_bookings.php?msg=' . urlencode('Failed to cancel: '.$conn->error));
    exit;
}
