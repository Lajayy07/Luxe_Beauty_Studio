
<?php
require_once "db.php";

// Get the customer's search term
$search = trim($_GET["search"] ?? "");

// Search services by name or category
if ($search !== "") {

    $stmt = $pdo->prepare(
        "SELECT
            services.service_name,
            services.description,
            services.price,
            services.duration_minutes,
            categories.category_name
         FROM services
         INNER JOIN categories
            ON services.category_id = categories.category_id
         WHERE services.service_name LIKE ?
            OR categories.category_name LIKE ?
         ORDER BY services.service_name"
    );

    $search_term = "%" . $search . "%";

    $stmt->execute([
        $search_term,
        $search_term
    ]);

} else {

    // Display all services if no search is entered
    $stmt = $pdo->prepare(
        "SELECT
            services.service_name,
            services.description,
            services.price,
            services.duration_minutes,
            categories.category_name
         FROM services
         INNER JOIN categories
            ON services.category_id = categories.category_id
         ORDER BY services.service_name"
    );

    $stmt->execute();
}

$services = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Services - Luxe Beauty Studio</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .search-form {
            margin: 20px 0;
            padding: 20px;
            background-color: #fff0f3;
            border-radius: 8px;
        }

        .search-form label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .search-form input {
            padding: 10px;
            width: 60%;
            max-width: 350px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-right: 10px;
            box-sizing: border-box;
        }

        .search-form button {
            background-color: #d9a7a0;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .search-form button:hover {
            background-color: #c48f88;
        }

        .search-form a {
            margin-left: 10px;
            color: #8b5e5a;
        }

        .service {
            margin-bottom: 20px;
            padding: 20px;
            background-color: #fff8f5;
            border-radius: 8px;
        }

        .service h3 {
            margin-top: 0;
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

    <h2>Our Beauty Services</h2>

    <p>Explore our salon services and pricing.</p>

    <!-- Service Search Form -->

    <form method="GET"
          action="services.php"
          class="search-form">

        <label for="search">
            Search Services:
        </label>

        <input
            type="text"
            id="search"
            name="search"
            placeholder="Search by service or category"
            maxlength="100"
            value="<?php echo htmlspecialchars(
                $search,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
        >

        <button type="submit">
            Search
        </button>

        <a href="services.php">
            Show All
        </a>

    </form>

    <!-- Display Services From MySQL -->

    <?php if (count($services) > 0): ?>

        <?php foreach ($services as $service): ?>

            <div class="service">

                <h3>
                    <?php echo htmlspecialchars(
                        $service["service_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </h3>

                <p>
                    <strong>Category:</strong>

                    <?php echo htmlspecialchars(
                        $service["category_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </p>

                <p>
                    <?php echo htmlspecialchars(
                        $service["description"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </p>

                <p>
                    <strong>Price:</strong>

                    $<?php echo number_format(
                        (float)$service["price"],
                        2
                    ); ?>
                </p>

                <p>
                    <strong>Duration:</strong>

                    <?php echo (int)$service["duration_minutes"]; ?>
                    minutes
                </p>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p>
            No services found matching your search.
        </p>

        <p>
            <a href="services.php">
                View All Services
            </a>
        </p>

    <?php endif; ?>

    <p>
        <a href="book.php" class="button">
            Book an Appointment
        </a>
    </p>

</main>

</body>
</html>
