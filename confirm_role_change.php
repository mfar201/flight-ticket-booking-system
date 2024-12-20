<?php
// confirm_role_change.php
session_start();
require 'config.php';
require_once 'csrf_helper.php'; // Include CSRF helper

// Check if there is a pending role change
if (!isset($_SESSION['pending_role_change_user_id']) || !isset($_SESSION['pending_role_change_new_role_id'])) {
    echo "No pending role change detected.";
    echo "<br><a href='list_users.php'>Back to List</a>";
    exit();
}

$user_id = $_SESSION['pending_role_change_user_id'];
$new_role_id = $_SESSION['pending_role_change_new_role_id'];
$user_data = $_SESSION['pending_role_change_data'];

// Fetch the new role name
$role_name_sql = "SELECT role_name FROM roles WHERE role_id = ?";
$stmt_role = $conn->prepare($role_name_sql);
if ($stmt_role) {
    $stmt_role->bind_param("i", $new_role_id);
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

// Handle form submission for confirmation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        echo "Invalid CSRF token.";
        exit();
    }

    if (isset($_POST['confirm'])) {
        // Proceed with role change

        // Determine managed_by based on role change
        if ($new_role_name == 'Admin') {
            // Promoted to Admin: set managed_by to NULL
            $managed_by = NULL;
        } else {
            // Not promoting to Admin, set managed_by appropriately
            // For this script, it should only be handling promotion to Admin
            $managed_by = 1; // Assuming role_id=1 is Admin
        }

        // Update user in database using prepared statements
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
                $stmt_update->bind_param("sssssii", $user_data['first_name'], $user_data['last_name'], $user_data['address'], $user_data['email'], $user_data['phone_num'], $new_role_id, $user_id);
            } else {
                echo "Error preparing statement: " . $conn->error;
                exit();
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
                $stmt_update->bind_param("sssssiii", $user_data['first_name'], $user_data['last_name'], $user_data['address'], $user_data['email'], $user_data['phone_num'], $new_role_id, $managed_by, $user_id);
            } else {
                echo "Error preparing statement: " . $conn->error;
                exit();
            }
        }

        if ($stmt_update->execute()) {
            $_SESSION['success_message'] = "User successfully promoted to Admin.";
        } else {
            $_SESSION['error_message'] = "Error updating user: " . $stmt_update->error;
        }
        
        // Clear pending role change
        unset($_SESSION['pending_role_change_user_id']);
        unset($_SESSION['pending_role_change_new_role_id']);
        unset($_SESSION['pending_role_change_data']);
        
        // Redirect to list_users.php
        header("Location: list_users.php");
        exit();        

    } elseif (isset($_POST['cancel'])) {
        // Set a flash message indicating the cancellation
        $_SESSION['info_message'] = "Role change has been canceled.";
    
        // Clear pending role change
        unset($_SESSION['pending_role_change_user_id']);
        unset($_SESSION['pending_role_change_new_role_id']);
        unset($_SESSION['pending_role_change_data']);
    
        // Redirect to list_users.php
        header("Location: list_users.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Role Change - Flight Ticket Booking System</title>
    <link rel="icon" type="image/x-icon" href="fav.png">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Optional: Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex-shrink-0 flex items-center">
                    <a href="index.php" class="text-xl font-bold text-indigo-600">Flight Ticket Booking System</a>
                </div>
                <div class="hidden md:flex space-x-4 items-center">
                    <a href="list_users.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Users</a>
                    <a href="manage_roles.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Roles</a>
                    <!-- CSRF-Protected Logout Button -->
                    <form method="POST" action="logout.php" class="inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <button type="submit" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Logout</button>
                    </form>
                </div>
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-button" class="text-gray-700 hover:text-indigo-600 focus:outline-none">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden bg-white shadow-md">
            <a href="list_users.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Users</a>
            <a href="manage_roles.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Roles</a>
            <!-- CSRF-Protected Logout Button for Mobile -->
            <form method="POST" action="logout.php" class="block">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-indigo-50">Logout</button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center px-4">
        <div class="bg-white shadow-lg rounded-lg p-8 max-w-md w-full">
            <h2 class="text-2xl font-semibold text-center text-indigo-600 mb-6">Confirm Role Change</h2>
            <p class="text-gray-700 text-center mb-6">
                Are you sure you want to promote 
                <span class="font-semibold"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></span> 
                to 
                <span class="font-semibold text-indigo-600"><?php echo htmlspecialchars($new_role_name); ?></span>?
            </p>
            <form method="POST" action="confirm_role_change.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <button 
                    type="submit" 
                    name="confirm" 
                    class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-200"
                >
                    Yes, Promote
                </button>
                <button 
                    type="submit" 
                    name="cancel" 
                    class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition duration-200"
                >
                    No, Cancel
                </button>
            </form>
        </div>
    </main>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- Mobile Menu Script -->
    <script>
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');
        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    </script>
</body>
</html>
