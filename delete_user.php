<?php
// delete_user.php
session_start();
require 'config.php';
require 'csrf_helper.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Ensure the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: list_users.php");
    exit();
}

// Check if required POST parameters are set
if (!isset($_POST['user_id']) || !isset($_POST['csrf_token'])) {
    $_SESSION['error_message'] = "Invalid form submission.";
    header("Location: list_users.php");
    exit();
}

$user_id = intval($_POST['user_id']);
$csrf_token = $_POST['csrf_token'];

// Validate CSRF Token
if (!validateCsrfToken($csrf_token)) {
    $_SESSION['error_message'] = "Invalid CSRF token.";
    header("Location: list_users.php");
    exit();
}

// Prevent Admin from deleting themselves
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['error_message'] = "You cannot delete your own account.";
    header("Location: list_users.php");
    exit();
}

// Optional: Check if the user to be deleted exists and is not an Admin
$check_sql = "SELECT role_id FROM users WHERE user_id = ?";
$stmt_check = $conn->prepare($check_sql);
if ($stmt_check) {
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) {
        $_SESSION['error_message'] = "User not found.";
        $stmt_check->close();
        header("Location: list_users.php");
        exit();
    }

    $user = $result_check->fetch_assoc();
    $role_id = $user['role_id'];

    // Fetch role name
    $role_name_sql = "SELECT role_name FROM roles WHERE role_id = ?";
    $stmt_role = $conn->prepare($role_name_sql);
    if ($stmt_role) {
        $stmt_role->bind_param("i", $role_id);
        $stmt_role->execute();
        $role_result = $stmt_role->get_result();
        if ($role_result->num_rows == 1) {
            $role = $role_result->fetch_assoc()['role_name'];
            if ($role == 'Admin') {
                $_SESSION['error_message'] = "You cannot delete another Admin's account.";
                $stmt_role->close();
                $stmt_check->close();
                header("Location: list_users.php");
                exit();
            }
        }
        $stmt_role->close();
    } else {
        $_SESSION['error_message'] = "Error preparing role check statement: " . $conn->error;
        $stmt_check->close();
        header("Location: list_users.php");
        exit();
    }

    $stmt_check->close();
} else {
    $_SESSION['error_message'] = "Error preparing user check statement: " . $conn->error;
    header("Location: list_users.php");
    exit();
}

// Proceed to delete the user
$delete_sql = "DELETE FROM users WHERE user_id = ?";
$stmt_delete = $conn->prepare($delete_sql);

if ($stmt_delete) {
    $stmt_delete->bind_param("i", $user_id);
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $_SESSION['success_message'] = "User deleted successfully.";
        } else {
            $_SESSION['error_message'] = "User not found or already deleted.";
        }
    } else {
        $_SESSION['error_message'] = "Error deleting user: " . $stmt_delete->error;
    }
    $stmt_delete->close();
} else {
    $_SESSION['error_message'] = "Error preparing the delete statement: " . $conn->error;
}

$conn->close();

// Redirect back to the users list
header("Location: list_users.php");
exit();
?>
