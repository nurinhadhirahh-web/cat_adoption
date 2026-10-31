
<?php
session_start();

require_once __DIR__ . '/../db.php';

// Destinasi selepas login berjaya
$STUDENT_HOME = "../homepage/homepage.html";
$ADMIN_HOME   = "../adminpage/admin.php";

// Pastikan hanya borang POST diproses
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit;
}

$role = $_POST["role"] ?? "";
$password = $_POST["password"] ?? "";

// Semak role
if (!in_array($role, ["student", "admin"], true)) {
    header("Location: login.html?error=invalid");
    exit;
}

// Ambil email student atau username admin
if ($role === "student") {
    $identity = trim($_POST["email"] ?? "");
} else {
    $identity = trim($_POST["username"] ?? "");
}

// Semak input kosong
if ($identity === "" || $password === "") {
    header(
        "Location: login.html?error=empty&type=" .
        urlencode($role)
    );
    exit;
}

try {
    // Cari akaun berdasarkan role
    if ($role === "student") {
        $stmt = $pdo->prepare(
            "SELECT id, nama, email, password
             FROM login_student
             WHERE email = ?
             LIMIT 1"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, username, password
             FROM login_admin
             WHERE username = ?
             LIMIT 1"
        );
    }

    $stmt->execute([$identity]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Sahkan password
    $valid = false;

    if ($user) {
        $storedPassword = (string) $user["password"];

        if (password_verify($password, $storedPassword)) {
            $valid = true;
        } elseif (hash_equals($storedPassword, $password)) {
            // Sokongan password lama dalam teks biasa
            $valid = true;
        }
    }

    // Jika login gagal
    if (!$valid) {
        header(
            "Location: login.html?error=invalid&type=" .
            urlencode($role)
        );
        exit;
    }

    // Login berjaya
    session_regenerate_id(true);

    $_SESSION["user_id"] = $user["id"];
    $_SESSION["role"] = $role;

    // Login student
    if ($role === "student") {
        $_SESSION["student_id"] = $user["id"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["student_name"] = $user["nama"];

        header("Location: " . $STUDENT_HOME);
        exit;
    }

    // Login admin
    $_SESSION["admin_username"] = $user["username"];
    $_SESSION["user_identity"] = $user["username"];

    header("Location: " . $ADMIN_HOME);
    exit;

} catch (PDOException $e) {
    // Catat ralat database dalam log pelayan
    error_log("Purrfect Home login error: " . $e->getMessage());

    header(
        "Location: login.html?error=database&type=" .
        urlencode($role)
    );
    exit;
}
