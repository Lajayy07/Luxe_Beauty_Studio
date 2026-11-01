
<?php
require_once "session_check.php";
require_once "db.php";

// Set salon timezone
date_default_timezone_set("America/New_York");

// Create security token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";

// Get appointment ID
$appointment_id = filter_var(
    $_POST["appointment_id"] ?? $_GET["id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$appointment_id) {
    header("Location: appointments.php");
    exit;
}

// Retrieve this customer's confirmed appointment
// Include service duration and stylist ID
$stmt = $pdo->prepare(
    "SELECT
        a.appointment_id,
        a.employee_id,
        a.appointment_date,
        a.appointment_time,
        s.service_name,
        s.duration_minutes
     FROM appointments a
     INNER JOIN services s
         ON a.service_id = s.service_id
     WHERE a.appointment_id = ?
       AND a.user_id = ?
       AND a.status = 'Confirmed'"
);

$stmt->execute([
    $appointment_id,
    $_SESSION["user_id"]
]);

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    http_response_code(404);
    exit("Appointment not found or cannot be edited.");
}

// Default form values
$display_date = $appointment["appointment_date"];
$display_time = substr(
    $appointment["appointment_time"],
    0,
    5
);

// Process rescheduling
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify security token
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        http_response_code(403);
        exit("Invalid form request.");
    }

    $date = $_POST["appointment_date"] ?? "";
    $time = $_POST["appointment_time"] ?? "";

    if (is_string($date)) {
        $display_date = $date;
    }

    if (is_string($time)) {
        $display_time = $time;
    }

    // Validate date
    $date_obj = is_string($date)
        ? DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $date
        )
        : false;

    $valid_date = $date_obj &&
        $date_obj->format("Y-m-d") === $date;

    // Validate time
    $valid_time = is_string($time) &&
        preg_match(
            "/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/",
            $time
        );

    if (!$valid_date || !$valid_time) {

        $message = "Please enter a valid date and time.";

    } else {

        $start = new DateTimeImmutable(
            $date . " " . $time
        );

        $opening = new DateTimeImmutable(
            $date . " 09:00"
        );

        $closing = new DateTimeImmutable(
            $date . " 18:00"
        );

        // Monday = 1, Sunday = 7
        $day_of_week = (int)$start->format("N");

        $duration = (int)$appointment["duration_minutes"];

        if ($start <= new DateTimeImmutable()) {

            $message = "Please select a future appointment.";

        } elseif ($day_of_week === 7) {

            $message = "Luxe Beauty Studio is closed on Sundays.";

        } elseif (
            $start < $opening ||
            $start >= $closing
        ) {

            $message = "Appointments must start during business hours, 9:00 AM to 6:00 PM.";

        } elseif ($duration <= 0) {

            $message = "This service has an invalid duration.";

        } else {

            // Calculate appointment end time
            $end = $start->modify(
                "+" . $duration . " minutes"
            );

            if ($end > $closing) {

                $message = "This appointment would end after 6:00 PM. Please choose an earlier time.";

            } else {

                try {

                    // Check for overlapping appointments
                    // Exclude the appointment being edited
                    $check = $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM appointments a
                         INNER JOIN services s
                             ON a.service_id = s.service_id
                         WHERE a.employee_id = ?
                           AND a.appointment_date = ?
                           AND a.status = 'Confirmed'
                           AND a.appointment_id != ?
                           AND TIMESTAMP(
                               a.appointment_date,
                               a.appointment_time
                           ) < ?
                           AND DATE_ADD(
                               TIMESTAMP(
                                   a.appointment_date,
                                   a.appointment_time
                               ),
                               INTERVAL s.duration_minutes MINUTE
                           ) > ?"
                    );

                    $check->execute([
                        $appointment["employee_id"],
                        $date,
                        $appointment_id,
                        $end->format("Y-m-d H:i:s"),
                        $start->format("Y-m-d H:i:s")
                    ]);

                    $already_booked = $check->fetchColumn();

                    if ($already_booked > 0) {

                        $message = "This stylist already has an appointment during that time. Please choose another time.";

                    } else {

                        // Update appointment
                        $stmt = $pdo->prepare(
                            "UPDATE appointments
                             SET appointment_date = ?,
                                 appointment_time = ?
                             WHERE appointment_id = ?
                               AND user_id = ?
                               AND status = 'Confirmed'"
                        );

                        $stmt->execute([
                            $date,
                            $time,
                            $appointment_id,
                            $_SESSION["user_id"]
                        ]);

                        header("Location: appointments.php");
                        exit;
                    }

                } catch (PDOException $e) {

                    $message = "An error occurred while rescheduling. Please try again.";

                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Reschedule Appointment - Luxe Beauty Studio</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .business-hours {
            max-width: 550px;
            margin: 15px auto;
            padding: 15px;
            background-color: #fff0f3;
            border-radius: 8px;
            text-align: center;
        }

        .booking-form {
            max-width: 550px;
            margin: 20px auto;
            padding: 25px;
            background-color: #fff8f5;
            border-radius: 10px;
        }

        .booking-form label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .booking-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .booking-form button {
            margin-top: 20px;
            padding: 12px 20px;
            background-color: #d9a7a0;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .error-message {
            max-width: 550px;
            margin: 15px auto;
            padding: 12px;
            background-color: #ffe6e6;
            border-radius: 5px;
            text-align: center;
        }
    </style>
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

    <h2 style="text-align:center;">
        Reschedule Appointment
    </h2>

    <div class="business-hours">
        <strong>Business Hours</strong>
        <p>Monday - Saturday: 9:00 AM - 6:00 PM</p>
        <p>Sunday: Closed</p>
    </div>

    <p style="text-align:center;">
        Select a new date and time for your appointment.
    </p>

    <p style="text-align:center;">
        <strong>Service:</strong>
        <?php echo htmlspecialchars(
            $appointment["service_name"],
            ENT_QUOTES,
            "UTF-8"
        ); ?>
    </p>

    <?php if ($message !== ""): ?>

        <div class="error-message">
            <?php echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ); ?>
        </div>

    <?php endif; ?>

    <form method="POST"
          action="edit_appointment.php"
          class="booking-form">

        <input type="hidden"
               name="appointment_id"
               value="<?php echo (int)$appointment_id; ?>">

        <input type="hidden"
               name="csrf_token"
               value="<?php echo htmlspecialchars(
                   $_SESSION["csrf_token"],
                   ENT_QUOTES,
                   "UTF-8"
               ); ?>">

        <label for="appointment_date">
            New Date:
        </label>

        <input type="date"
               id="appointment_date"
               name="appointment_date"
               min="<?php echo date("Y-m-d"); ?>"
               value="<?php echo htmlspecialchars(
                   $display_date,
                   ENT_QUOTES,
                   "UTF-8"
               ); ?>"
               required>

        <label for="appointment_time">
            New Time:
        </label>

        <input type="time"
               id="appointment_time"
               name="appointment_time"
               value="<?php echo htmlspecialchars(
                   $display_time,
                   ENT_QUOTES,
                   "UTF-8"
               ); ?>"
               required>

        <button type="submit">
            Save Changes
        </button>

    </form>

    <p style="text-align:center;">
        <a href="appointments.php">
            Back to My Appointments
        </a>
    </p>

</main>

</body>
</html>
