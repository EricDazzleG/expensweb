<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trip_id = filter_var($_POST['trip_id'], FILTER_SANITIZE_NUMBER_INT);
    $category_id = filter_var($_POST['category_id'], FILTER_SANITIZE_NUMBER_INT);
    $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    if ($trip_id && $category_id && $amount) {
        $stmt = $conn->prepare("INSERT INTO expenses (trip_id, category_id, amount, date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iid", $trip_id, $category_id, $amount);
        
        if ($stmt->execute()) {
            header("Location: dashboard.php?success=1");
        } else {
            header("Location: dashboard.php?error=1");
        }
    } else {
        header("Location: dashboard.php?error=2");
    }
    exit();
}

header("Location: dashboard.php");
exit();
?>