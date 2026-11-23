
<?php
error_reporting(E_ALL);
ini_set("display_errors", "1");

session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"])) {
    header("Location: login.html");
    exit();
}

require_once __DIR__ . "/Backend/db.php";

$user_id = (int) $_SESSION["user_id"];

$patientName = "Patient";
$appointmentCount = 0;
$doctorCount = 0;
$waitingCount = 0;
$recentAppointments = [];

try {

    // Get logged-in patient profile
    $stmt = $conn->prepare("
        SELECT p.patient_id, p.name, p.email
        FROM patients p
        INNER JOIN users u ON p.email = u.email
        WHERE u.user_id = ?
        AND u.role = 'patient'
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        die("Patient profile not found. Please check your registration details.");
    }

    $patient_id = (int) $patient["patient_id"];
    $patientName = $patient["name"];

    // Total appointments
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM appointments
        WHERE patient_id = ?
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();

    $appointmentCount = (int) $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();

    // Total doctors
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM doctors
    ");

    $doctorCount = (int) $result->fetch_assoc()["total"];

    // Waiting queue tokens
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM queue_tokens qt
        INNER JOIN appointments a
        ON qt.appointment_id = a.appointment_id
        WHERE a.patient_id = ?
        AND LOWER(TRIM(qt.queue_status)) = 'waiting'
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();

    $waitingCount = (int) $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();

    // Recent appointments
    $stmt = $conn->prepare("
        SELECT
            a.appointment_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            d.doctor_name,
            d.department,
            qt.token_number,
            qt.queue_status
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();

    $recentAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Throwable $e) {
    die("Dashboard Error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Dashboard | HealthCare+</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>

        :root {
            --primary: #087cae;
            --secondary: #0b9b91;
            --light-bg: #f4f8fc;
            --text-dark: #243447;
            --text-muted: #718096;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--light-bg);
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text-dark);
        }

        /* Sidebar */

        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: linear-gradient(180deg, #087cae, #086b91);
            padding: 25px 18px;
            color: white;
            z-index: 1000;
        }

        .brand {
            font-size: 25px;
            font-weight: 800;
            margin-bottom: 40px;
            padding-left: 10px;
        }

        .brand i {
            margin-right: 8px;
            color: #a9f0e5;
        }

        .sidebar-label {
            font-size: 11px;
            letter-spacing: 1.5px;
            color: #c8e7f5;
            padding: 0 12px;
            margin-bottom: 12px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #e5f5ff;
            text-decoration: none;
            padding: 13px 14px;
            margin-bottom: 8px;
            border-radius: 10px;
            transition: 0.3s;
            font-size: 14px;
        }

        .sidebar-link i {
            font-size: 18px;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.18);
            color: white;
        }

        .logout-link {
            position: absolute;
            bottom: 25px;
            left: 18px;
            right: 18px;
            color: white;
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 10px;
        }

        .logout-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        /* Main Content */

        .main-content {
            margin-left: 250px;
            padding: 30px 35px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 15px;
        }

        .page-heading {
            font-size: 27px;
            font-weight: 750;
            margin-bottom: 5px;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0;
        }

        .profile-box {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 10px 16px;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(30, 70, 100, 0.05);
        }

        .profile-avatar {
            width: 43px;
            height: 43px;
            border-radius: 50%;
            background: #e0f4ff;
            color: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 21px;
        }

        .profile-name {
            font-weight: 700;
            font-size: 14px;
        }

        .profile-role {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Welcome Banner */

        .welcome-banner {
            background: linear-gradient(110deg, #087cae, #0b9b91);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner h2 {
            font-size: 26px;
            font-weight: 750;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            margin-bottom: 20px;
            color: #e4f7ff;
            font-size: 14px;
        }

        .welcome-banner .btn {
            background: white;
            color: var(--primary);
            border: none;
            font-weight: 700;
            padding: 11px 18px;
            border-radius: 9px;
        }

        .welcome-banner .btn:hover {
            background: #e9f7ff;
        }

        .banner-icon {
            position: absolute;
            right: 50px;
            top: 20px;
            font-size: 115px;
            color: rgba(255, 255, 255, 0.15);
        }

        /* Statistics */

        .section-heading {
            font-size: 19px;
            font-weight: 750;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 17px;
            padding: 23px;
            border: 1px solid #edf2f7;
            height: 100%;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(30, 70, 100, 0.04);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 22px rgba(30, 70, 100, 0.10);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
        }

        .icon-blue {
            background: #e2f3ff;
            color: #087cae;
        }

        .icon-green {
            background: #e2fbf0;
            color: #119b70;
        }

        .icon-orange {
            background: #fff1df;
            color: #e28a18;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
        }

        /* Recent Appointments */

        .content-card {
            background: white;
            border-radius: 17px;
            padding: 25px;
            border: 1px solid #edf2f7;
            box-shadow: 0 4px 15px rgba(30, 70, 100, 0.04);
        }

        .table thead th {
            background: #f5f9fc;
            color: #6b7b8c;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 14px;
        }

        .table tbody td {
            padding: 15px 14px;
            font-size: 13px;
            vertical-align: middle;
        }

        .doctor-name {
            font-weight: 700;
            color: #243447;
        }

        .department-name {
            font-size: 12px;
            color: var(--text-muted);
        }

        .status-badge {
            background: #fff1d6;
            color: #b7791f;
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
        }

        .service-card {
            background: white;
            padding: 22px;
            border-radius: 16px;
            border: 1px solid #edf2f7;
            height: 100%;
            transition: 0.3s;
        }

        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(30, 70, 100, 0.08);
        }

        .service-icon {
            font-size: 27px;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .service-card h6 {
            font-weight: 750;
            margin-bottom: 8px;
        }

        .service-card p {
            color: var(--text-muted);
            font-size: 13px;
            min-height: 40px;
        }

        .service-card a {
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        .footer {
            color: var(--text-muted);
            font-size: 12px;
            text-align: center;
            padding: 25px 0 5px;
        }

        /* Responsive */

        @media (max-width: 991px) {

            .sidebar {
                width: 210px;
            }

            .main-content {
                margin-left: 210px;
                padding: 25px;
            }

            .banner-icon {
                right: 20px;
            }

        }

        @media (max-width: 767px) {

            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
                padding: 15px;
            }

            .brand {
                margin-bottom: 15px;
            }

            .sidebar-label {
                display: none;
            }

            .sidebar-link {
                display: inline-flex;
                margin: 3px;
                padding: 9px 12px;
            }

            .logout-link {
                position: static;
                display: inline-flex;
                margin-top: 5px;
                padding: 9px 12px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome-banner {
                padding: 24px;
            }

            .welcome-banner h2 {
                font-size: 22px;
            }

            .banner-icon {
                display: none;
            }

            .page-heading {
                font-size: 23px;
            }

            .content-card {
                padding: 15px;
            }

        }

    </style>
</head>

<body>

<!-- Sidebar -->
<aside class="sidebar">

    <div class="brand">
        <i class="bi bi-hospital-fill"></i>
        HealthCare+
    </div>

    <div class="sidebar-label">MAIN MENU</div>

    <a href="dashboard.php" class="sidebar-link active">
        <i class="bi bi-grid-1x2-fill"></i>
        Dashboard
    </a>

    <a href="appointment.html" class="sidebar-link">
        <i class="bi bi-calendar-plus"></i>
        Book Appointment
    </a>

    <a href="my_appointments.php" class="sidebar-link">
        <i class="bi bi-calendar-check"></i>
        My Appointments
    </a>

    <a href="queue.html" class="sidebar-link">
        <i class="bi bi-ticket-perforated"></i>
        Queue Status
    </a>

    <div class="sidebar-label mt-4">ACCOUNT</div>

    <a href="Backend/logout.php" class="logout-link">
        <i class="bi bi-box-arrow-right"></i>
        Logout
    </a>

</aside>


<!-- Main Dashboard -->
<main class="main-content">

    <!-- Topbar -->
    <div class="topbar">

        <div>
            <h1 class="page-heading">Patient Dashboard</h1>

            <p class="page-subtitle">
                Overview of your healthcare activities
            </p>
        </div>

        <div class="profile-box">

            <div class="profile-avatar">
                <i class="bi bi-person-fill"></i>
            </div>

            <div>
                <div class="profile-name">
                    <?php echo htmlspecialchars($patientName); ?>
                </div>

                <div class="profile-role">
                    Patient Account
                </div>
            </div>

        </div>

    </div>


    <!-- Welcome Banner -->
    <section class="welcome-banner">

        <i class="bi bi-heart-pulse-fill banner-icon"></i>

        <h2>
            Welcome back, <?php echo htmlspecialchars($patientName); ?>!
        </h2>

        <p>
            Your health matters to us. Manage your appointments
            and check your queue status easily.
        </p>

        <a href="appointment.html" class="btn">
            <i class="bi bi-calendar-plus me-2"></i>
            Book New Appointment
        </a>

    </section>


    <!-- Statistics -->
    <section class="mb-5">

        <h4 class="section-heading">
            Dashboard Overview
        </h4>

        <div class="row g-4">

            <!-- Appointments -->
            <div class="col-md-6 col-xl-4">

                <div class="stat-card">

                    <div class="stat-top">

                        <div>
                            <div class="stat-label">Total Appointments</div>
                            <div class="stat-number text-primary">
                                <?php echo $appointmentCount; ?>
                            </div>
                        </div>

                        <div class="stat-icon icon-blue">
                            <i class="bi bi-calendar-check"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        <i class="bi bi-clipboard2-check me-1"></i>
                        Your registered appointments
                    </div>

                </div>

            </div>


            <!-- Doctors -->
            <div class="col-md-6 col-xl-4">

                <div class="stat-card">

                    <div class="stat-top">

                        <div>
                            <div class="stat-label">Available Doctors</div>
                            <div class="stat-number text-success">
                                <?php echo $doctorCount; ?>
                            </div>
                        </div>

                        <div class="stat-icon icon-green">
                            <i class="bi bi-person-badge"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        <i class="bi bi-hospital me-1"></i>
                        Doctors registered in the system
                    </div>

                </div>

            </div>


            <!-- Queue -->
            <div class="col-md-6 col-xl-4">

                <div class="stat-card">

                    <div class="stat-top">

                        <div>
                            <div class="stat-label">Waiting Tokens</div>
                            <div class="stat-number text-warning">
                                <?php echo $waitingCount; ?>
                            </div>
                        </div>

                        <div class="stat-icon icon-orange">
                            <i class="bi bi-people"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        <i class="bi bi-hourglass-split me-1"></i>
                        Your appointments in waiting status
                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- Recent Appointments -->
    <section class="mb-5">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <h4 class="section-heading mb-0">
                Recent Appointments
            </h4>

            <a href="my_appointments.php"
               class="text-decoration-none fw-bold small"
               style="color: #087cae;">

                View All
                <i class="bi bi-arrow-right"></i>

            </a>

        </div>

        <div class="content-card">

            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Token</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($recentAppointments) > 0): ?>

                        <?php foreach ($recentAppointments as $appointment): ?>

                            <tr>

                                <td>
                                    <div class="doctor-name">
                                        <?php echo htmlspecialchars($appointment["doctor_name"] ?? "Doctor"); ?>
                                    </div>

                                    <div class="department-name">
                                        <?php echo htmlspecialchars($appointment["department"] ?? "Department"); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        date("d M Y", strtotime($appointment["appointment_date"]))
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        date("h:i A", strtotime($appointment["appointment_time"]))
                                    );
                                    ?>
                                </td>

                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars($appointment["token_number"] ?? "N/A"); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-badge">
                                        <?php echo htmlspecialchars($appointment["queue_status"] ?? $appointment["status"]); ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                                No appointments found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </section>


    <!-- Patient Services -->
    <section>

        <h4 class="section-heading">
            Patient Services
        </h4>

        <div class="row g-4">

            <div class="col-md-6 col-xl-4">

                <div class="service-card">

                    <div class="service-icon">
                        <i class="bi bi-calendar-plus"></i>
                    </div>

                    <h6>Book Appointment</h6>

                    <p>
                        Schedule a new appointment with your preferred department.
                    </p>

                    <a href="appointment.html">
                        Book Now <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            </div>


            <div class="col-md-6 col-xl-4">

                <div class="service-card">

                    <div class="service-icon">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>

                    <h6>Check Queue Status</h6>

                    <p>
                        Track your appointment token and current queue status.
                    </p>

                    <a href="queue.html">
                        Check Status <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            </div>


            <div class="col-md-6 col-xl-4">

                <div class="service-card">

                    <div class="service-icon">
                        <i class="bi bi-calendar2-week"></i>
                    </div>

                    <h6>My Appointments</h6>

                    <p>
                        View your appointment history and booking information.
                    </p>

                    <a href="my_appointments.php">
                        View Appointments <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            </div>

        </div>

    </section>


    <!-- Footer -->
    <footer class="footer">

        <p class="mb-1">
            © 2026 HealthCare+ Hospital Queue Management System
        </p>

        <p class="mb-0">
            Smart Healthcare | Easy Appointments | Better Patient Experience
        </p>

    </footer>

</main>

</body>
</html>
