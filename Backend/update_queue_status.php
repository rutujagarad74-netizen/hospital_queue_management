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

$appointment_id = (int)($_POST["appointment_id"] ?? 0);
$status = $_POST["queue_status"] ?? "";

if ($appointment_id <= 0 ||
    !in_array($status, ["Completed", "No-Show"], true)) {
    header("Location: ../doctor_dashboard.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

$sql = "UPDATE queue_tokens qt
        INNER JOIN appointments a
            ON qt.appointment_id = a.appointment_id
        INNER JOIN doctors d
            ON a.doctor_id = d.doctor_id
        SET qt.queue_status = ?
        WHERE a.appointment_id = ?
          AND d.user_id = ?
          AND qt.queue_status = 'Waiting'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $status, $appointment_id, $user_id);
$stmt->execute();
$stmt->close();

header("Location: ../doctor_dashboard.php");
exit();
?>