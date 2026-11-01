
<?php
require_once "session_check.php";
require_once "db.php";

// Salon operates in Virginia
date_default_timezone_set("America/New_York");

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "";

$selected_service = "";
$selected_employee = "";
$selected_date = "";
$selected_time = "";

// Get services
$stmt = $pdo->query(
    "SELECT service_id, service_name, price, duration_minutes
     FROM services
     ORDER BY service_name"
);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stylists
$stmt = $pdo->query(
    "SELECT employee_id, first_name, last_name
     FROM employees
     ORDER BY first_name"
);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process booking
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $selected_service = $_POST["service_id"] ?? "";
    $selected_employee = $_POST["employee_id"] ?? "";
    $selected_date = $_POST["appointment_date"] ?? "";
    $selected_time = $_POST["appointment_time"] ?? "";

    $service_id = filter_var(
        $selected_service,
        FILTER_VALIDATE_INT
    );

    $employee_id = filter_var(
        $selected_employee,
        FILTER_VALIDATE_INT
    );

    $date = $selected_date;
    $time = $selected_time;

    if (
        !is_string($_POST["csrf_token"] ?? null) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        $message = "Invalid form submission.";

    } elseif (!$service_id || !$employee_id) {

        $message = "Please select a valid service and stylist.";

    } elseif (!is_string($date) || !is_string($time)) {

        $message = "Please enter a valid date and time.";

    } else {

        $date_obj = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $date
        );

        $valid_date = $date_obj &&
            $date_obj->format("Y-m-d") === $date;

        $valid_time = preg_match(
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

            // Sunday = 7
            $day_of_week = (int)$start->format("N");

            if ($start <= new DateTimeImmutable()) {

                $message = "Please select a future appointment.";

            } elseif ($day_of_week === 7) {

                $message = "Luxe Beauty Studio is closed on Sundays.";

            } elseif ($start < $opening || $start >= $closing) {

                $message = "Appointments must begin between 9:00 AM and 6:00 PM.";

            } else {

                try {

                    // Retrieve service duration
                    $stmt = $pdo->prepare(
                        "SELECT duration_minutes
                         FROM services
                         WHERE service_id = ?"
                    );

                    $stmt->execute([$service_id]);
                    $duration = $stmt->fetchColumn();

                    // Confirm stylist exists
                    $stmt = $pdo->prepare(
                        "SELECT employee_id
                         FROM employees
                         WHERE employee_id = ?"
                    );

                    $stmt->execute([$employee_id]);
                    $employee_exists = $stmt->fetchColumn();

                    if ($duration === false || !$employee_exists) {

                        $message = "Please select a valid service and stylist.";

                    } elseif ((int)$duration <= 0) {

                        $message = "This service has an invalid duration.";

                    } else {

                        $end = $start->modify(
                            "+" . (int)$duration . " minutes"
                        );

                        if ($end > $closing) {

                            $message = "This appointment would end after 6:00 PM. Please choose an earlier time.";

                        } else {

                            // Check overlapping appointments
                            $check = $pdo->prepare(
                                "SELECT COUNT(*)
                                 FROM appointments a
                                 INNER JOIN services s
                                   ON a.service_id = s.service_id
                                 WHERE a.employee_id = ?
                                   AND a.appointment_date = ?
                                   AND a.status = 'Confirmed'
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
                                $employee_id,
                                $date,
                                $end->format("Y-m-d H:i:s"),
                                $start->format("Y-m-d H:i:s")
                            ]);

                            if ($check->fetchColumn() > 0) {

                                $message = "This stylist already has an appointment during that time. Please choose another time.";

                            } else {

                                // Save appointment
                                $stmt = $pdo->prepare(
                                    "INSERT INTO appointments
                                     (user_id, service_id, employee_id,
                                      appointment_date, appointment_time, status)
                                     VALUES (?, ?, ?, ?, ?, 'Confirmed')"
                                );

                                $stmt->execute([
                                    $_SESSION["user_id"],
                                    $service_id,
                                    $employee_id,
                                    $date,
                                    $time
                                ]);

                                $message = "Your appointment has been booked successfully!";
                                $message_type = "success";

                                $selected_service = "";
                                $selected_employee = "";
                                $selected_date = "";
                                $selected_time = "";
                            }
                        }
                    }

                } catch (PDOException $e) {

                    $message = "An error occurred while booking. Please try again.";
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

    <title>Book Appointment - Luxe Beauty Studio</title>
    <link rel="stylesheet" href="style.css">

    <style>
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

        .booking-form input,
        .booking-form select {
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

        .booking-form button:hover {
            background-color: #c48f88;
        }

        .message {
            max-width: 550px;
            margin: 20px auto;
            padding: 12px;
            background-color: #ffe6e6;
            border-radius: 5px;
            text-align: center;
        }

        .message.success {
            background-color: #e3f5e5;
        }

        .business-hours {
            text-align: center;
            margin: 15px auto;
            padding: 12px;
            max-width: 550px;
            background-color: #fff0f3;
            border-radius: 8px;
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
        Book an Appointment
    </h2>

    <div class="business-hours">
        <strong>Business Hours</strong>
        <p>Monday - Saturday: 9:00 AM - 6:00 PM</p>
        <p>Sunday: Closed</p>
    </div>

    <?php if ($message !== ""): ?>

        <div class="message <?php
            echo htmlspecialchars(
                $message_type,
                ENT_QUOTES,
                "UTF-8"
            );
        ?>">

            <?php echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

        </div>

    <?php endif; ?>

    <form method="POST"
          action="book.php"
          class="booking-form">

        <input type="hidden"
               name="csrf_token"
               value="<?php echo htmlspecialchars(
                   $_SESSION["csrf_token"],
                   ENT_QUOTES,
                   "UTF-8"
               ); ?>">

        <label for="service_id">
            Select Service
        </label>

        <select name="service_id"
                id="service_id"
                required>

            <option value="">
                -- Choose a Service --
            </option>

            <?php foreach ($services as $service): ?>

                <option
                    value="<?php echo (int)$service["service_id"]; ?>"
                    <?php
                    if (
                        (string)$selected_service ===
                        (string)$service["service_id"]
                    ) {
                        echo "selected";
                    }
                    ?>
                >

                    <?php echo htmlspecialchars(
                        $service["service_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                    - $<?php echo number_format(
                        (float)$service["price"],
                        2
                    ); ?>

                </option>

            <?php endforeach; ?>

        </select>

        <label for="employee_id">
            Select Stylist
        </label>

        <select name="employee_id"
                id="employee_id"
                required>

            <option value="">
                -- Choose a Stylist --
            </option>

            <?php foreach ($employees as $employee): ?>

                <option
                    value="<?php echo (int)$employee["employee_id"]; ?>"
                    <?php
                    if (
                        (string)$selected_employee ===
                        (string)$employee["employee_id"]
                    ) {
                        echo "selected";
                    }
                    ?>
                >

                    <?php echo htmlspecialchars(
                        $employee["first_name"] . " " .
                        $employee["last_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </option>

            <?php endforeach; ?>

        </select>

        <label for="appointment_date">
            Appointment Date
        </label>

        <input type="date"
               id="appointment_date"
               name="appointment_date"
               min="<?php echo date("Y-m-d"); ?>"
               value="<?php echo htmlspecialchars(
                   $selected_date,
                   ENT_QUOTES,
                   "UTF-8"
               ); ?>"
               required>

        <label for="appointment_time">
            Appointment Time
        </label>

        <input type="time"
               id="appointment_time"
               name="appointment_time"
               value="<?php echo htmlspecialchars(
                   $selected_time,
                   ENT_QUOTES,
                   "UTF-8"
               ); ?>"
               required>

        <button type="submit">
            Confirm Appointment
        </button>

    </form>

    <p style="text-align:center;">
        <a href="appointments.php">
            View My Appointments
        </a>
    </p>

</main>

</body>
</html>
