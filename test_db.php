
<?php
require_once "db.php";

echo "<h2>Database Connection Successful!</h2>";

$stmt = $pdo->query("SELECT COUNT(*) FROM services");
$count = $stmt->fetchColumn();

echo "<p>Luxe Beauty Studio has " .
     (int)$count .
     " services in the database.</p>";
?>
