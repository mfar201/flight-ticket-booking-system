<?php
// edit_user.php
session_start();
require 'config.php';
require 'csrf_helper.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Check if user_id is set in GET parameters
if (!isset($_GET['user_id'])) {
    $_SESSION['error_message'] = "User ID not provided.";
    header("Location: list_users.php");
    exit();
}

$user_id = intval($_GET['user_id']);

// Determine if the Admin is editing their own account
$is_editing_self = ($user_id == $_SESSION['user_id']);

// Initialize variables
$first_name = $last_name = $address = $email = $phone_num = $role_id = "";
$errors = [];
$warning = "";

// Step 1: Retrieve Admin Role ID Dynamically
$admin_role_sql = "SELECT role_id FROM roles WHERE role_name = 'Admin'";
$stmt_admin_role = $conn->prepare($admin_role_sql);
if ($stmt_admin_role) {
    $stmt_admin_role->execute();
    $admin_role_result = $stmt_admin_role->get_result();
    if ($admin_role_result->num_rows == 1) {
        $admin_role = $admin_role_result->fetch_assoc();
        $admin_role_id = $admin_role['role_id'];
    } else {
        $_SESSION['error_message'] = "Admin role not found in the roles table.";
        header("Location: list_users.php");
        exit();
    }
    $stmt_admin_role->close();
} else {
    $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
    header("Location: list_users.php");
    exit();
}

// Step 2: Prevent Admin from modifying other Admins' accounts
if (!$is_editing_self) {
    // Fetch target user's role
    $role_sql = "SELECT roles.role_name FROM users JOIN roles ON users.role_id = roles.role_id WHERE users.user_id = ?";
    $stmt_role = $conn->prepare($role_sql);
    if ($stmt_role) {
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $role_result = $stmt_role->get_result();
        if ($role_result->num_rows == 1) {
            $user_role = $role_result->fetch_assoc()['role_name'];
            if ($user_role == 'Admin') {
                $_SESSION['error_message'] = "You cannot edit another Admin's account.";
                header("Location: list_users.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "User not found.";
            header("Location: list_users.php");
            exit();
        }
        $stmt_role->close();
    } else {
        $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
        header("Location: list_users.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    }

    // Retrieve and sanitize input
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $phone_num = trim($_POST['phone_num']);
    $role_id = intval($_POST['role_id']);
    $new_password = trim($_POST['new_password']);
    $confirm_new_password = trim($_POST['confirm_new_password']);

    // Validate input
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    if (empty($phone_num)) {
        $errors[] = "Phone number is required.";
    }

    // Check if email is unique (excluding current user)
    $check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $stmt_check = $conn->prepare($check_sql);
    if ($stmt_check) {
        $stmt_check->bind_param("si", $email, $user_id);
        $stmt_check->execute();
        $email_check_result = $stmt_check->get_result();

        if ($email_check_result->num_rows > 0) {
            $errors[] = "Email is already in use by another user.";
        }

        $stmt_check->close();
    } else {
        $errors[] = "Error preparing statement: " . $conn->error;
    }

    // Handle password change if new password fields are filled
    $password_changed = false;
    if (!empty($new_password) || !empty($confirm_new_password)) {
        if (empty($new_password)) {
            $errors[] = "New password is required.";
        }
        if (empty($confirm_new_password)) {
            $errors[] = "Confirm new password is required.";
        }
        if ($new_password !== $confirm_new_password) {
            $errors[] = "New password and confirmation do not match.";
        }
        if (empty($errors)) {
            // **Important Security Note**: Passwords should be hashed before storing.
            // However, in your original code, passwords are stored in plain text.
            // It's highly recommended to hash passwords using password_hash().
            // For demonstration, we'll proceed as per your current implementation.

            $plain_password = $new_password;
            $password_changed = true;
        }
    }

    if (empty($errors)) {
        // Determine the new role name
        $role_name_sql = "SELECT role_name FROM roles WHERE role_id = ?";
        $stmt_role = $conn->prepare($role_name_sql);
        if ($stmt_role) {
            $stmt_role->bind_param("i", $role_id);
            $stmt_role->execute();
            $role_name_result = $stmt_role->get_result();

            if ($role_name_result->num_rows == 1) {
                $role = $role_name_result->fetch_assoc();
                $new_role_name = $role['role_name'];
            } else {
                echo "Selected role does not exist.";
                exit();
            }

            $stmt_role->close();
        } else {
            echo "Error preparing statement: " . $conn->error;
            exit();
        }

        // Fetch current role of the user
        $current_role_sql = "SELECT role_id FROM users WHERE user_id = ?";
        $stmt_current_role = $conn->prepare($current_role_sql);
        if ($stmt_current_role) {
            $stmt_current_role->bind_param("i", $user_id);
            $stmt_current_role->execute();
            $current_role_result = $stmt_current_role->get_result();

            if ($current_role_result->num_rows == 1) {
                $current_role = $current_role_result->fetch_assoc();
                $current_role_id = $current_role['role_id'];

                // Fetch current role name
                $current_role_name_sql = "SELECT role_name FROM roles WHERE role_id = ?";
                $stmt_current_role_name = $conn->prepare($current_role_name_sql);
                if ($stmt_current_role_name) {
                    $stmt_current_role_name->bind_param("i", $current_role_id);
                    $stmt_current_role_name->execute();
                    $current_role_name_result = $stmt_current_role_name->get_result();
                    $current_role_name = $current_role_name_result->fetch_assoc()['role_name'];

                    $stmt_current_role_name->close();
                } else {
                    echo "Error preparing statement: " . $conn->error;
                    exit();
                }
            } else {
                echo "User not found.";
                exit();
            }

            $stmt_current_role->close();
        } else {
            echo "Error preparing statement: " . $conn->error;
            exit();
        }

        // Prevent Admin from modifying other Admins
        if (!$is_editing_self && $current_role_name == 'Admin') {
            $_SESSION['error_message'] = "You cannot edit another Admin's account.";
            header("Location: list_users.php");
            exit();
        }

        // If Admin is editing their own account, ensure the role remains 'Admin'
        if ($is_editing_self) {
            // Override role_id to Admin's role_id to prevent demotion
            $stmt_admin_role = $conn->prepare("SELECT role_id FROM roles WHERE role_name = 'Admin'");
            if ($stmt_admin_role) {
                $stmt_admin_role->execute();
                $admin_role_result = $stmt_admin_role->get_result();
                if ($admin_role_result->num_rows == 1) {
                    $admin_role = $admin_role_result->fetch_assoc();
                    $role_id = $admin_role['role_id'];
                } else {
                    $_SESSION['error_message'] = "Admin role not found.";
                    header("Location: list_users.php");
                    exit();
                }
                $stmt_admin_role->close();
            } else {
                $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
                header("Location: list_users.php");
                exit();
            }
        }

        // Determine managed_by based on role change
        if ($current_role_name != 'Admin' && $new_role_name == 'Admin') {
            $managed_by = NULL;
            $warning = "Warning: You are promoting this user to Admin. They will no longer be managed by you.";
        } elseif ($current_role_name == 'Admin' && $new_role_name != 'Admin') {
            $managed_by = $admin_role_id;
        } else {
            // Preserve existing managed_by
            $managed_by_sql = "SELECT managed_by FROM users WHERE user_id = ?";
            $stmt_managed_by = $conn->prepare($managed_by_sql);
            if ($stmt_managed_by) {
                $stmt_managed_by->bind_param("i", $user_id);
                $stmt_managed_by->execute();
                $managed_by_result = $stmt_managed_by->get_result();
                $managed_by = $managed_by_result->fetch_assoc()['managed_by'];

                $stmt_managed_by->close();
            } else {
                echo "Error preparing statement: " . $conn->error;
                exit();
            }
        }

        // If the admin is promoting the user to Admin, display a warning and require confirmation
        if ($current_role_name != 'Admin' && $new_role_name == 'Admin') {
            $_SESSION['pending_role_change_user_id'] = $user_id;
            $_SESSION['pending_role_change_new_role_id'] = $role_id;
            $_SESSION['pending_role_change_data'] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'address' => $address,
                'email' => $email,
                'phone_num' => $phone_num
            ];
            header("Location: confirm_role_change.php");
            exit();
       }

        // Prepare the UPDATE SQL statement
        if ($password_changed) {
            if (is_null($managed_by)) {
                $update_sql = "UPDATE users 
                             SET first_name = ?, 
                                 last_name = ?, 
                                 address = ?, 
                                 email = ?, 
                                 phone_num = ?, 
                                 role_id = ?, 
                                 managed_by = NULL, 
                                 password = ?
                             WHERE user_id = ?";
                $stmt_update = $conn->prepare($update_sql);
                if ($stmt_update) {
                    $stmt_update->bind_param("sssssisi", $first_name, $last_name, $address, $email, $phone_num, $role_id, $plain_password, $user_id);
                }
            } else {
                $update_sql = "UPDATE users 
                             SET first_name = ?, 
                                 last_name = ?, 
                                 address = ?, 
                                 email = ?, 
                                 phone_num = ?, 
                                 role_id = ?, 
                                 managed_by = ?, 
                                 password = ?
                             WHERE user_id = ?";
                $stmt_update = $conn->prepare($update_sql);
                if ($stmt_update) {
                    $stmt_update->bind_param("sssssiisi", $first_name, $last_name, $address, $email, $phone_num, $role_id, $managed_by, $plain_password, $user_id);
                }
            }
        } else {
            if (is_null($managed_by)) {
                $update_sql = "UPDATE users 
                             SET first_name = ?, 
                                 last_name = ?, 
                                 address = ?, 
                                 email = ?, 
                                 phone_num = ?, 
                                 role_id = ?, 
                                 managed_by = NULL
                             WHERE user_id = ?";
                $stmt_update = $conn->prepare($update_sql);
                if ($stmt_update) {
                    $stmt_update->bind_param("sssssii", $first_name, $last_name, $address, $email, $phone_num, $role_id, $user_id);
                }
            } else {
                $update_sql = "UPDATE users 
                             SET first_name = ?, 
                                 last_name = ?, 
                                 address = ?, 
                                 email = ?, 
                                 phone_num = ?, 
                                 role_id = ?, 
                                 managed_by = ?
                             WHERE user_id = ?";
                $stmt_update = $conn->prepare($update_sql);
                if ($stmt_update) {
                    $stmt_update->bind_param("sssssiii", $first_name, $last_name, $address, $email, $phone_num, $role_id, $managed_by, $user_id);
                }
            }
        }

        if (!$stmt_update) {
            echo "Error preparing statement: " . $conn->error;
            exit();
        }

        // Execute the UPDATE statement
        if ($stmt_update->execute()) {
            $_SESSION['success_message'] = "User updated successfully.";
            if ($is_editing_self) {
                header("Location: welcome_admin.php");
            } else {
                header("Location: list_users.php");
            }
            exit(); // Ensure no further output or operations occur
        } else {
            echo "Error updating user: " . $stmt_update->error;
            exit();
        }
        
        

        $stmt_update->close();
    }
} else {
    // Fetch current user data to pre-fill the form
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $first_name = $user['first_name'];
            $last_name = $user['last_name'];
            $address = $user['address'];
            $email = $user['email'];
            $phone_num = $user['phone_num'];
            $role_id = $user['role_id'];
        } else {
            echo "User not found.";
            exit();
        }

        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
        exit();
    }
}

// Fetch all roles for the dropdown
$roles_sql = "SELECT role_id, role_name FROM roles ORDER BY role_name ASC";
$roles_result = $conn->query($roles_sql);
$roles = [];
if ($roles_result->num_rows > 0) {
    while ($role = $roles_result->fetch_assoc()) {
        $roles[] = $role;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Flight Ticket Booking System</title>
    <link rel="icon" type="image/x-icon" href="fav.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styles for dropdown */
        .dropdown:hover .dropdown-menu {
            display: block;
        }
        /* Ensure dropdown-menu is above other elements */
        .dropdown-menu {
            z-index: 10;
        }
    </style>
    <script>
        // Define a JavaScript variable to indicate if the admin is editing themselves
        var isEditingSelf = <?php echo $is_editing_self ? 'true' : 'false'; ?>;
        
        // Optional: Add confirmation for role changes if promoting to Admin
        function checkRoleChange() {
            if (isEditingSelf) {
                // No confirmation needed when editing self
                return true;
            }
            var roleSelect = document.getElementById("role_id");
            var selectedRole = roleSelect.options[roleSelect.selectedIndex].text;
            if (selectedRole === "Admin") {
                return confirm("Are you sure you want to promote this user to Admin? They will no longer be managed by you.");
            }
            return true;
        }
    </script>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <!-- Navbar (Same as welcome_admin.php) -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="welcome_admin.php" class="text-xl font-bold text-indigo-600">Flight Ticket Booking System (Admin)</a>
                </div>
                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-4 items-center">
                    <a href="list_users.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">List Users</a>
                    
                    <!-- Manage Dropdown -->
                    <div class="relative dropdown">
                        <button class="flex items-center text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium focus:outline-none">
                            Manage
                            <svg class="ml-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M5.516 7.548a.75.75 0 011.06.02L10 10.939l3.424-3.37a.75.75 0 111.06 1.06l-4 3.92a.75.75 0 01-1.06 0l-4-3.92a.75.75 0 01.02-1.06z" />
                            </svg>
                        </button>
                        <div class="dropdown-menu absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg hidden">
                            <a href="manage_airlines.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Airlines</a>
                            <a href="manage_aircrafts.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Aircrafts</a>
                            <a href="manage_airports.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Airports</a>
                            <a href="manage_routes.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Routes</a>
                            <a href="manage_flights.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Manage Flights</a>
                        </div>
                    </div>

                    <a href="admin_confirmed_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Confirmed Bookings</a>
                    <a href="admin_pending_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Pending Bookings</a>
                    <a href="admin_cancelled_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Cancelled Bookings</a>
                    
                    <form action="logout.php" method="POST">
                        <!-- Include CSRF token as a hidden input -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <button type="submit" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Logout</button>
                    </form>
                </div>
                <!-- Mobile Menu Button -->
                <div class="flex items-center md:hidden">
                    <button id="mobile-menu-button" class="text-gray-700 hover:text-indigo-600 focus:outline-none focus:text-indigo-600">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white shadow-md">
            <a href="list_users.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">List Users</a>
            
            <!-- Manage Dropdown for Mobile -->
            <div class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">
                <span class="flex items-center justify-between">
                    <span>Manage</span>
                    <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path d="M5.516 7.548a.75.75 0 011.06.02L10 10.939l3.424-3.37a.75.75 0 111.06 1.06l-4 3.92a.75.75 0 01-1.06 0l-4-3.92a.75.75 0 01.02-1.06z" />
                    </svg>
                </span>
                <div class="mt-2 ml-4 space-y-1">
                    <a href="manage_airlines.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Airlines</a>
                    <a href="manage_aircrafts.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Aircrafts</a>
                    <a href="manage_airports.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Airports</a>
                    <a href="manage_routes.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Routes</a>
                    <a href="manage_flights.php" class="block px-2 py-1 text-gray-700 hover:bg-gray-100">Manage Flights</a>
                </div>
            </div>
            
            <a href="admin_confirmed_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Confirmed Bookings</a>
            <a href="admin_pending_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Pending Bookings</a>
            <a href="admin_cancelled_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Cancelled Bookings</a>
            <form action="logout.php" method="POST" class="px-4 py-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <button type="submit" class="w-full text-left text-gray-700 hover:bg-indigo-50">Logout</button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8 max-w-7xl">
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-green-100 text-green-700">
                <div class="flex justify-between items-center">
                    <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                    <button onclick="this.parentElement.parentElement.style.display='none';" class="text-green-700 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-red-100 text-red-700">
                <div class="flex justify-between items-center">
                    <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                    <button onclick="this.parentElement.parentElement.style.display='none';" class="text-red-700 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Edit User Form -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-6 text-indigo-600">Edit User</h2>

            <?php
            if (!empty($errors)) {
                echo "<div class='mb-6 px-4 py-3 rounded-md bg-red-100 text-red-700'>";
                echo "<ul class='list-disc pl-5'>";
                foreach ($errors as $error) {
                    echo "<li>" . htmlspecialchars($error) . "</li>";
                }
                echo "</ul>";
                echo "</div>";
            }

            if (!empty($warning)) {
                echo "<div class='mb-6 px-4 py-3 rounded-md bg-yellow-100 text-yellow-700'>";
                echo htmlspecialchars($warning);
                echo "</div>";
            }
            ?>

            <form method="POST" action="edit_user.php?user_id=<?php echo urlencode($user_id); ?>" onsubmit="return checkRoleChange();" class="space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name<span class="text-red-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name<span class="text-red-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address<span class="text-red-500">*</span></label>
                    <textarea id="address" name="address" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($address); ?></textarea>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email<span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="phone_num" class="block text-sm font-medium text-gray-700">Phone Number<span class="text-red-500">*</span></label>
                    <input type="text" id="phone_num" name="phone_num" value="<?php echo htmlspecialchars($phone_num); ?>" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="role_id" class="block text-sm font-medium text-gray-700">Role</label>
                    <?php if ($is_editing_self): ?>
                        <!-- If Admin is editing their own account, display the role but disable the dropdown -->
                        <select id="role_id" name="role_id" disabled class="mt-1 block w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm cursor-not-allowed">
                            <?php
                            foreach ($roles as $role) {
                                $selected = ($role['role_id'] == $role_id) ? "selected" : "";
                                echo "<option value='" . htmlspecialchars($role['role_id']) . "' $selected>" . htmlspecialchars($role['role_name']) . "</option>";
                            }
                            ?>
                        </select>
                        <!-- Hidden input to retain the role_id -->
                        <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($role_id); ?>">
                        <p class="text-sm text-gray-500 mt-1">You cannot change your own role.</p>
                    <?php else: ?>
                        <!-- If Admin is editing another user, allow role selection -->
                        <select id="role_id" name="role_id" required class="mt-1 block w-full px-4 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <?php
                            foreach ($roles as $role) {
                                $selected = ($role['role_id'] == $role_id) ? "selected" : "";
                                echo "<option value='" . htmlspecialchars($role['role_id']) . "' $selected>" . htmlspecialchars($role['role_name']) . "</option>";
                            }
                            ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div>
                    <h3 class="text-lg font-medium text-gray-700">Change Password</h3>
                    <p class="text-sm text-gray-500">Leave the fields below blank if you do not wish to change the user's password.</p>
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="confirm_new_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <?php if ($is_editing_self): ?>
                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            Save Changes
                        </button>
                    <?php else: ?>
                        <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            Update User
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <div class="mt-6">
                <a href="list_users.php" class="text-indigo-600 hover:text-indigo-900 font-medium"><i class="fas fa-arrow-left"></i> Back to List</a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Tailwind CSS Mobile Menu Toggle and Dropdown Toggle Script -->
    <script>
        // Toggle Mobile Menu
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // Toggle Manage Dropdown in Navbar
        const manageDropdown = document.querySelectorAll('.dropdown');
        manageDropdown.forEach(dropdown => {
            const button = dropdown.querySelector('button');
            const dropdownMenu = dropdown.querySelector('.dropdown-menu');

            button.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!dropdown.contains(e.target)) {
                    dropdownMenu.classList.add('hidden');
                }
            });
        });
    </script>

</body>
</html>
<?php
$conn->close();
?>
