<link rel="stylesheet" href="music.css">
<script src="music.js" defer></script>
<?php
session_start();

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.html");
    exit;
}

$username = htmlspecialchars(
    $_SESSION["admin_username"] ?? $_SESSION["user_identity"] ?? "Admin",
    ENT_QUOTES,
    "UTF-8"
);
?>
