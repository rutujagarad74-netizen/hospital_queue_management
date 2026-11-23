<?php

session_start();

require_once __DIR__ . "/db.php";

// Check POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request. Please submit the appointment form.");
}

// Check login
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'patient'
) {
    exit("Please login with your patient account before booking.");
}

// Get form details
$patient_name = trim($_POST['patient_name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$department = trim($_POST['department'] ?? '');
$date = trim($_POST['appointment_date'] ?? '');
$time = trim($_POST['appointment_time'] ?? '');

// Validate form
$allowed_departments = [
    "General Medicine",
    "Cardiology",
    "Orthopedics",
    "Dermatology",
    "Pediatrics"
];

$allowed_times = [
    "09:00", "10:00", "11:00",
    "12:00", "14:00", "15:00"
];

if (
    $patient_name === '' ||
    !preg_match('/^[0-9]{10}$/', $mobile) ||
    !in_array($department, $allowed_departments, true) ||
    !in_array($time, $allowed_times, true)
) {
    exit("Please enter valid appointment details.");
}

// Validate date
$dateObj = DateTime::createFromFormat('!Y-m-d', $date);

if (
    !$dateObj ||
    $dateObj->format('Y-m-d') !== $date ||
    $date < date('Y-m-d')
) {
    exit("Please select a valid appointment date.");
}

$transactionStarted = false;

try {

    // Find registered patient
    $stmt = $conn->prepare(
        "SELECT p.patient_id, p.name, p.mobile
         FROM patients p
         INNER JOIN users u ON u.email = p.email
         WHERE u.user_id = ? AND u.role = 'patient'
         LIMIT 1"
    );

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        throw new Exception(
            "Patient profile not found. Please register first."
        );
    }

    // Check patient details
    if (
        strcasecmp($patient['name'], $patient_name) !== 0 ||
        $patient['mobile'] !== $mobile
    ) {
        throw new Exception(
            "Patient name or mobile does not match your registered profile."
        );
    }

    $patient_id = (int)$patient['patient_id'];

    // Find doctor
    $stmt = $conn->prepare(
        "SELECT doctor_id, doctor_name
         FROM doctors
         WHERE department = ?
         ORDER BY doctor_id ASC
         LIMIT 1"
    );

    $stmt->bind_param("s", $department);
    $stmt->execute();

    $doctor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doctor) {
        throw new Exception(
            "No doctor is available in this department."
        );
    }

    $doctor_id = (int)$doctor['doctor_id'];

    // Start transaction
    $conn->begin_transaction();
    $transactionStarted = true;

    // Insert appointment
    $stmt = $conn->prepare(
        "INSERT INTO appointments
        (patient_id, doctor_id, appointment_date,
         appointment_time, status)
         VALUES (?, ?, ?, ?, 'Pending')"
    );

    $stmt->bind_param(
        "iiss",
        $patient_id,
        $doctor_id,
        $date,
        $time
    );

    $stmt->execute();

    $appointment_id = $conn->insert_id;

    $stmt->close();

    // Generate next token
    $stmt = $conn->prepare(
        "SELECT COALESCE(MAX(q.token_number), 0) + 1 AS next_token
         FROM queue_tokens q
         INNER JOIN appointments a
         ON q.appointment_id = a.appointment_id
         WHERE a.doctor_id = ?
         AND a.appointment_date = ?"
    );

    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    $token = (int)$result['next_token'];

    $stmt->close();

    // Insert queue token
    $stmt = $conn->prepare(
        "INSERT INTO queue_tokens
        (appointment_id, token_number, queue_status)
         VALUES (?, ?, 'Waiting')"
    );

    $stmt->bind_param("ii", $appointment_id, $token);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();
    $transactionStarted = false;

    // Success page
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, initial-scale=1.0">

        <title>Appointment Successful</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
              rel="stylesheet">
    </head>

    <body class="bg-light">

        <div class="container mt-5">

            <div class="card shadow p-4 text-center mx-auto"
                 style="max-width:550px;">

                <h2 class="text-success">
                    Appointment Booked Successfully!
                </h2>

                <hr>

                <p>
                    <strong>Appointment ID:</strong>
                    <?php echo $appointment_id; ?>
                </p>

                <p>
                    <strong>Patient:</strong>
                    <?php echo htmlspecialchars($patient_name); ?>
                </p>

                <p>
                    <strong>Doctor:</strong>
                    <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                </p>

                <p>
                    <strong>Department:</strong>
                    <?php echo htmlspecialchars($department); ?>
                </p>

                <p>
                    <strong>Date:</strong>
                    <?php echo htmlspecialchars($date); ?>
                </p>

                <p>
                    <strong>Time:</strong>
                    <?php echo htmlspecialchars($time); ?>
                </p>

                <h3 class="text-primary">
                    Queue Token: <?php echo $token; ?>
                </h3>

                <p class="text-warning">
                    Status: Waiting
                </p>

                <a href="../index.html"
                   class="btn btn-primary">
                    Back to Home
                </a>

            </div>

        </div>

    </body>
    </html>

    <?php

} catch (Throwable $e) {

    if ($transactionStarted) {
        $conn->rollback();
    }

    error_log("Appointment Error: " . $e->getMessage());

    http_response_code(400);

    echo "<div style='font-family:Arial;margin:50px'>";
    echo "<h2>Appointment Booking Failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo '<a href="../appointment.html">Try Again</a>';
    echo "</div>";
}

$conn->close();

?>
