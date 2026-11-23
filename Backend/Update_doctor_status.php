<?php
session_start();

require_once "db.php";

if (!isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "doctor") {
    http_response_code(403);
    exit("Access denied");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../doctor_dashboard.php");
    exit();
}

$is_present = (int)($_POST["is_present"] ?? -1);

if (!in_array($is_present, [0, 1], true)) {
    header("Location: ../doctor_dashboard.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

$sql = "UPDATE doctors
        SET is_present = ?
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $is_present, $user_id);
$stmt->execute();
$stmt->close();

header("Location: ../doctor_dashboard.php");
exit();
?>