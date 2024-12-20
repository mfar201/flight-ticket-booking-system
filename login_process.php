<?php
session_start();
require 'csrf_helper.php';
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        die("Invalid CSRF token. Please refresh the page and try again.");
    }

    // Retrieve and sanitize input
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    
    // Query to find user with matching email and password
    $sql = "SELECT users.*, roles.role_name 
            FROM users 
            JOIN roles ON users.role_id = roles.role_id 
            WHERE users.email = '$email' AND users.password = '$password'";

    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        // Fetch user data
        $user = $result->fetch_assoc();
        
        // Store user data in session (excluding password)
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['address'] = $user['address'];
        $_SESSION['registration_date'] = $user['registration_date'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['phone_num'] = $user['phone_num'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['managed_by'] = $user['managed_by'];
        
        // Redirect based on role
        if ($user['role_name'] == 'Admin') {
            header("Location: welcome_admin.php");
            exit();
        } elseif ($user['role_name'] == 'User') {
            header("Location: welcome_user.php");
            exit();
        } else {
            echo "Undefined user role.";
        }
    } else {
        // Set flash message for invalid credentials
        $_SESSION['error_message'] = "Invalid email or password. Please try again.";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
