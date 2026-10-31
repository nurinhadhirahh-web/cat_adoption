
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student" ||
    !isset($_SESSION["student_id"])
) {
    http_response_code(401);

    echo json_encode([
        "logged_in" => false
    ]);

    exit;
}

echo json_encode([
    "logged_in" => true,
    "student_id" => $_SESSION["student_id"],
    "name" => $_SESSION["student_name"] ?? "",
    "email" => $_SESSION["user_email"] ?? ""
]);

