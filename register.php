
<?php
require_once "db.php";

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($email) ||
        empty($password)
    ) {
        $message = "Please fill out all fields.";

    } elseif (
        strlen($first_name) > 50 ||
        strlen($last_name) > 50
    ) {
        $message = "Names must be 50 characters or fewer.";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 100
    ) {
        $message = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";

    } else {

        // Check whether email is already registered
        $stmt = $pdo->prepare(
            "SELECT user_id FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $message = "An account with this email already exists.";

        } else {

            // Securely hash the password
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users
                    (first_name, last_name, email, password_hash)
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->execute([
                    $first_name,
                    $last_name,
                    $email,
                    $hashed_password
                ]);

                $success = true;
                $message = "Registration successful! You can now log in.";

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $message = "Unable to register. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Luxe Beauty Studio</title>
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
    <h2>Create an Account</h2>
    <p>Register to book and manage your appointments.</p>

    <?php if ($message): ?>
        <p style="color: <?php echo $success ? 'green' : 'red'; ?>;">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <?php if ($success): ?>

        <a href="login.php" class="button">Go to Login</a>

    <?php else: ?>

        <form method="POST" action="register.php" class="booking-form">

            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name"
                   maxlength="50" required>

            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name"
                   maxlength="50" required>

            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email"
                   maxlength="100" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password"
                   minlength="8" autocomplete="new-password" required>

            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password"
                   name="confirm_password" minlength="8"
                   autocomplete="new-password" required>

            <button type="submit">Create Account</button>

        </form>

        <p>Already have an account?
            <a href="login.php">Login here</a>
        </p>

    <?php endif; ?>
</main>

</body>
</html>
