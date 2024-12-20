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
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone_num = $conn->real_escape_string(trim($_POST['phone_num']));
    $password = $conn->real_escape_string(trim($_POST['password']));
    
    // Check if email already exists
    $check_sql = "SELECT * FROM users WHERE email = '$email'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Email already registered. Please try a different email.";
        header("Location: register.php");
        exit();
    } else {
        // Get role_id for 'User' role
        $role_sql = "SELECT role_id FROM roles WHERE role_name = 'User'";
        $role_result = $conn->query($role_sql);
        
        if ($role_result->num_rows == 1) {
            $role = $role_result->fetch_assoc();
            $role_id = $role['role_id'];
            
            // Get current date for registration_date
            $registration_date = date('Y-m-d');
            
            // Insert new user into database
            $insert_sql = "INSERT INTO users (first_name, last_name, address, registration_date, email, phone_num, password, role_id, managed_by) 
                           VALUES ('$first_name', '$last_name', '$address', '$registration_date', '$email', '$phone_num', '$password', '$role_id', 1)";
            
            if ($conn->query($insert_sql) === TRUE) {
                $_SESSION['success_message'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit();
            } else {
                echo "Error: " . $insert_sql . "<br>" . $conn->error;
            }
        } else {
            echo "User role not found. Please contact the administrator.";
        }
    }
} else {
    header("Location: register.php");
    exit();
}

$conn->close();
