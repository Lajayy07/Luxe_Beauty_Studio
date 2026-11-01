
<?php
require_once "session_check.php";
session_start();
require_once "db.php";

// Only logged-in customers can access this page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";
$success = false;

// Create a security token for the form
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Invalid form request.");
    }

    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {
        $message = "Please complete all fields.";

    } elseif (strlen($new_password) < 8) {
        $message = "New password must be at least 8 characters.";

    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";

    } else {

        // Find the logged-in customer's password
        $stmt = $pdo->prepare(
            "SELECT password_hash FROM users WHERE user_id = ?"
        );

        $stmt->execute([$_SESSION["user_id"]]);
        $user = $stmt->fetch();

        if (!$user || !password_verify(
            $current_password,
            $user["password_hash"]
        )) {
            $message = "Current password is incorrect.";

        } elseif (password_verify(
            $new_password,
            $user["password_hash"]
        )) {
            $message = "Choose a different password.";

        } else {

            // Hash and save the new password
            $new_hash = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET password_hash = ?
                 WHERE user_id = ?"
            );

            $stmt->execute([
                $new_hash,
                $_SESSION["user_id"]
            ]);

            $success = true;
            $message = "Password changed successfully!";

            // Refresh the form security token
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Luxe Beauty Studio</title>
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
    <h2>Change Password</h2>
    <p>Update your account password below.</p>

    <?php if ($message): ?>
        <p style="color: <?php echo $success ? 'green' : 'red'; ?>;">
            <?php echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ); ?>
        </p>
    <?php endif; ?>

    <form method="POST"
          action="change_password.php"
          class="booking-form">

        <input type="hidden"
               name="csrf_token"
               value="<?php echo htmlspecialchars(
                   $_SESSION["csrf_token"],
                   ENT_QUOTES,
                   'UTF-8'
               ); ?>">

        <label for="current_password">
            Current Password:
        </label>
        <input type="password"
               id="current_password"
               name="current_password"
               autocomplete="current-password"
               required>

        <label for="new_password">
            New Password:
        </label>
        <input type="password"
               id="new_password"
               name="new_password"
               minlength="8"
               autocomplete="new-password"
               required>

        <label for="confirm_password">
            Confirm New Password:
        </label>
        <input type="password"
               id="confirm_password"
               name="confirm_password"
               minlength="8"
               autocomplete="new-password"
               required>

        <button type="submit">
            Change Password
        </button>
    </form>

    <p>
        <a href="account.php">Back to My Account</a>
    </p>
</main>

</body>
</html>
