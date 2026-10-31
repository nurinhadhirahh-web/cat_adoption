<?php
// student_dashboard.php - only logged-in students can see this page
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: login.html");
    exit;
}

$email = htmlspecialchars($_SESSION["user_email"], ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - PurrFect Home</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center;
               font-family: Arial, sans-serif; color: #fff; background: #0b0a10; }
        .box { width: 90%; max-width: 480px; padding: 36px; text-align: center;
               background: #15141d; border: 1px solid #493044; border-radius: 24px; }
        h1 { margin: 0 0 10px; font-size: 28px; }
        p { color: #c7c4d2; }
        a { display: inline-block; margin-top: 18px; padding: 12px 24px; border-radius: 10px;
            background: #ff2796; color: #fff; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🐾 Welcome, student!</h1>
        <p>You are logged in as <?php echo $id; ?>.</p>
        <p>Your cat adoption pages will go here.</p>
        <a href="logout.php">Logout</a>
    </div>
</body>
</html>
