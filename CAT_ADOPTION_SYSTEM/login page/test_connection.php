
<?php
require_once __DIR__ . '/../db.php';
try {
    $result = $pdo->query("SELECT DATABASE()")->fetchColumn();

    echo "<h2>Database Connection Successful!</h2>";
    echo "<p>Connected database: " .
         htmlspecialchars($result, ENT_QUOTES, "UTF-8") .
         "</p>";

} catch (PDOException $e) {
    echo "Database query failed.";
    error_log($e->getMessage());
}
?>
