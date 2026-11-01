
<?php
require_once "session_check.php";
session_start();
require_once "db.php";

// Create a security token for cancellation forms
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}


// Only logged-in customers can view appointments
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Retrieve appointments for the logged-in customer
$stmt = $pdo->prepare(
    "SELECT
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        s.service_name,
        s.price,
        e.first_name AS stylist_first,
        e.last_name AS stylist_last
     FROM appointments a
     INNER JOIN services s
        ON a.service_id = s.service_id
     INNER JOIN employees e
        ON a.employee_id = e.employee_id
     WHERE a.user_id = ?
     ORDER BY a.appointment_date DESC,
              a.appointment_time DESC"
);

$stmt->execute([$_SESSION["user_id"]]);
$appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Luxe Beauty Studio</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Luxe Beauty Studio</h1>
    <p>Beauty, Confidence, and Style</p>
</header>

<nav>
    <a href="index.php">Home</a>
    <a href="services.php">Services</a>
    <a href="book.php">Book Appointment</a>
    <a href="appointments.php">My Appointments</a>
    <a href="account.php">My Account</a>
</nav>

<main>
    <h2>My Appointments</h2>
    <p>View your Luxe Beauty Studio bookings below.</p>

    <?php if (count($appointments) > 0): ?>

        <?php foreach ($appointments as $appointment): ?>

            <div class="service">

                <h3>
                    <?php echo htmlspecialchars(
                        $appointment["service_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </h3>

                <p>
                    <strong>Appointment #:</strong>
                    <?php echo (int)$appointment["appointment_id"]; ?>
                </p>

                <p>
                    <strong>Date:</strong>
                    <?php echo htmlspecialchars(
                        $appointment["appointment_date"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </p>

                <p>
                    <strong>Time:</strong>
                    <?php echo date(
                        "g:i A",
                        strtotime($appointment["appointment_time"])
                    ); ?>
                </p>

                <p>
                    <strong>Stylist:</strong>
                    <?php echo htmlspecialchars(
                        $appointment["stylist_first"] . " " .
                        $appointment["stylist_last"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </p>

                <p>
                    <strong>Price:</strong>
                    $<?php echo number_format(
                        (float)$appointment["price"],
                        2
                    ); ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?php echo htmlspecialchars(
                        $appointment["status"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </p>
				
<?php if ($appointment["status"] === "Confirmed"): ?>

    <form method="POST"
          action="cancel_appointment.php"
          onsubmit="return confirm('Are you sure you want to cancel this appointment?');">

        <input type="hidden"
               name="csrf_token"
               value="<?php echo htmlspecialchars(
                   $_SESSION["csrf_token"],
                   ENT_QUOTES,
                   "UTF-8"
               ); ?>">

        <input type="hidden"
               name="appointment_id"
               value="<?php echo (int)$appointment["appointment_id"]; ?>">

        <button type="submit" class="cancel-button">
            Cancel Appointment
        </button>
		
    </form>

<p>
    <a href="edit_appointment.php?id=<?php echo (int)$appointment["appointment_id"]; ?>"
       class="button">
        Reschedule Appointment
    </a>
</p>

<?php endif; ?>


            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p>You have not booked any appointments yet.</p>

    <?php endif; ?>

    <p>
        <a href="book.php" class="button">
            Book Another Appointment
        </a>
    </p>
</main>

</body>
</html>
