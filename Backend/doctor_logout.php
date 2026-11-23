<?php
session_start();

$_SESSION = [];

session_destroy();

header("Location: ../doctor_login.html");
exit();
?>