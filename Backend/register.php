```php
<?php

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../register.html");
    exit();
}

$name = trim($_POST["name"] ?? "");
$age = (int)($_POST["age"] ?? 0);
$gender = $_POST["gender"] ?? "";
$mobile = trim($_POST["mobile"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($name === "" || $age < 1 || $age > 120 ||
    !in_array($gender, ["Male", "Female", "Other"]) ||
    !preg_match("/^[0-9]{10}$/", $mobile) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($password) < 6) {

    die("Invalid registration details. Please go back and check your information.");
}

try {
    $conn->begin_transaction();

    // Check duplicate email
    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $conn->rollback();
        die("This email is already registered. Please login.");
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $stmt = $conn->prepare(
        "INSERT INTO users (email, password, role) VALUES (?, ?, 'patient')"
    );

    $stmt->bind_param("ss", $email, $hashedPassword);
    $stmt->execute();

    // Insert into patients table
    $stmt2 = $conn->prepare(
        "INSERT INTO patients (name, age, gender, mobile, email)
         VALUES (?, ?, ?, ?, ?)"
    );

    $stmt2->bind_param("sisss", $name, $age, $gender, $mobile, $email);
    $stmt2->execute();

    $conn->commit();

    header("Location: ../login.html?status=registered");
    exit();

} catch (Exception $e) {

    $conn->rollback();
    error_log($e->getMessage());
    die("Registration failed. Please check your database tables.");
}
?>
```