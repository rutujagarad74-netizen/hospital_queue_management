
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"])) {
    header("Location: login.html");
    exit();
}

require_once __DIR__ . "/Backend/db.php";

$email = $_SESSION["email"];

try {
    $stmt = $conn->prepare("
        SELECT
            qt.token_number,
            qt.queue_status,
            p.name AS patient_name,
            d.doctor_name,
            d.department,
            a.appointment_date,
            a.appointment_time
        FROM queue_tokens qt
        JOIN appointments a ON qt.appointment_id = a.appointment_id
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE p.email = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

} catch (Throwable $e) {
    die("Queue Error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Queue Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">HealthCare+</a>
        <a href="Backend/logout.php" class="btn btn-light">Logout</a>
    </div>
</nav>

<div class="container py-5">
    <h2 class="text-primary mb-4">My Queue Status</h2>

    <div class="card shadow-sm border-0">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped text-center">
                <thead class="table-primary">
                    <tr>
                        <th>Token</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["token_number"]); ?></td>
                            <td><?php echo htmlspecialchars($row["patient_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["doctor_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["department"]); ?></td>
                            <td><?php echo htmlspecialchars($row["appointment_date"]); ?></td>
                            <td><?php echo htmlspecialchars($row["appointment_time"]); ?></td>
                            <td>
                                <span class="badge bg-warning text-dark">
                                    <?php echo htmlspecialchars($row["queue_status"]); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No queue records found for your account.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <a href="dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
</div>

</body>
</html>
