
<?php
require_once "session_check.php";
session_start();

$logged_in = isset($_SESSION["user_id"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Luxe Beauty Studio</title>
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
    <h2>My Account</h2>

    <?php if ($logged_in): ?>

        <h3>
            Welcome,
            <?php
            echo htmlspecialchars(
                $_SESSION["first_name"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>!
        </h3>

        <p>You are successfully logged into your account.</p>

        <p>From here, you will be able to manage your appointments.</p>

        
<p>
    <a href="change_password.php" class="button">
        Change Password
    </a>
</p>

<p>
    <a href="logout.php" class="button">
        Logout
    </a>
</p>


    <?php else: ?>

        <p>Please log in or create an account to continue.</p>

        <a href="login.php" class="button">Login</a>

        <p>
            New customer?
            <a href="register.php">Create an Account</a>
        </p>

    <?php endif; ?>
</main>

</body>
</html>
