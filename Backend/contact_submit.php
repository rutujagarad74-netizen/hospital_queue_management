
<?php
require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../contact.html");
    exit();
}

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$mobile = trim($_POST["mobile"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$message = trim($_POST["message"] ?? "");

if (
    $name === "" ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !preg_match("/^[0-9]{10}$/", $mobile) ||
    $subject === "" ||
    $message === ""
) {
    die("Invalid details. Please go back and fill the form correctly.");
}

$sql = "INSERT INTO contact_messages
        (name, email, mobile, subject, message)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "sssss",
    $name,
    $email,
    $mobile,
    $subject,
    $message
);

if ($stmt->execute()) {
    echo "<script>
        alert('Your message has been sent successfully!');
        window.location.href='../contact.html';
    </script>";
} else {
    echo "Unable to send your message. Please try again.";
}

$stmt->close();
$conn->close();
?>
