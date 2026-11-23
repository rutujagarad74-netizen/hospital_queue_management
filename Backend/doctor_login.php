<?php
session_start();

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../doctor_login.html");
    exit();
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    header("Location: ../doctor_login.html?error=empty");
    exit();
}

$sql = "SELECT user_id, email, password, role
        FROM users
        WHERE email = ? AND role = 'doctor'
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user["password"])) {
    header("Location: ../doctor_login.html?error=invalid");
    exit();
}

session_regenerate_id(true);

$_SESSION["user_id"] = $user["user_id"];
$_SESSION["role"] = "doctor";

header("Location: ../doctor_dashboard.php");
exit();
?>