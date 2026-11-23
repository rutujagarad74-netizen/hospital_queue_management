
<?php
header("Content-Type: application/json; charset=UTF-8");

try {
    require_once __DIR__ . "/db.php";

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method.");
    }

    $token = trim($_POST["token"] ?? "");

    if ($token === "" || !ctype_digit($token)) {
        throw new Exception("Enter a valid token number.");
    }

    $sql = "
        SELECT
            qt.token_number,
            qt.queue_status,
            p.name AS patient_name,
            d.doctor_name,
            d.department,
            a.appointment_date,
            a.appointment_time
        FROM queue_tokens qt
        INNER JOIN appointments a
            ON qt.appointment_id = a.appointment_id
        INNER JOIN patients p
            ON a.patient_id = p.patient_id
        INNER JOIN doctors d
            ON a.doctor_id = d.doctor_id
        WHERE qt.token_number = ?
        ORDER BY a.appointment_date, a.appointment_time
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("i", $token);
    $stmt->execute();

    $result = $stmt->get_result();
    $records = [];

    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        "success" => count($records) > 0,
        "records" => $records,
        "message" => count($records) > 0
            ? "Queue records found."
            : "Token not found."
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "records" => [],
        "message" => $e->getMessage()
    ]);
}
?>
