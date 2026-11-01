
<?php
require_once "session_check.php";
session_start();
require_once "db.php";

// Only logged-in customers can cancel appointments
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Only accept form submissions
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: appointments.php");
    exit;
}

// Verify the form security token
$token = $_POST["csrf_token"] ?? "";

if (
    !is_string($token) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $token)
) {
    http_response_code(403);
    exit("Invalid form request.");
}

// Validate the appointment ID
$appointment_id = filter_var(
    $_POST["appointment_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$appointment_id) {
    header("Location: appointments.php");
    exit;
}

// Cancel only this customer's confirmed appointment
$stmt = $pdo->prepare(
    "UPDATE appointments
     SET status = 'Cancelled'
     WHERE appointment_id = ?
       AND user_id = ?
       AND status = 'Confirmed'"
);

$stmt->execute([
    $appointment_id,
    $_SESSION["user_id"]
]);

header("Location: appointments.php");
exit;
?>
