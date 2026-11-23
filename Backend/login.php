
<?php
// Start session before sending any output
session_start();

// Connect to database
require_once __DIR__ . "/db.php";

// Allow only POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.html");
    exit();
}

// Get login details
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

// Validate input
if ($email === "" || $password === "") {
    header("Location: ../login.html?status=error");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../login.html?status=error");
    exit();
}

try {
    // Find user by email
    $sql = "SELECT user_id, email, password, role
            FROM users
            WHERE email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Database query failed.");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    // Verify password
    if ($user && password_verify($password, $user["password"])) {

        // Regenerate session ID
        session_regenerate_id(true);

        // Store user details in session
        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["role"] = $user["role"];

        // Redirect to dashboard
        header("Location: ../dashboard.php");
        exit();
    }

    // Invalid email or password
    header("Location: ../login.html?status=error");
    exit();

} catch (Throwable $e) {

    error_log("Login Error: " . $e->getMessage());

    http_response_code(500);
    echo "Login failed due to a server error. Please try again.";
}

$conn->close();
?>
