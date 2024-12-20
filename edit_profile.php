<?php
// edit_profile.php
session_start();
require 'config.php';
require 'csrf_helper.php';

// Check if user is logged in and is a User
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] != 'User') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize variables
$first_name = $last_name = $address = $email = $phone_num = "";
$errors = [];
$success = "";

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
    $new_password = trim($_POST['new_password']);
    $confirm_new_password = trim($_POST['confirm_new_password']);

    // Validate input fields
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
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
            // **Security Note:** Storing passwords in plain text is insecure.
            // This is implemented only for demonstration purposes.
            $plain_password = $new_password;
            $password_changed = true;
        }
    }

    if (empty($errors)) {
        // Prepare the UPDATE SQL statement
        if ($password_changed) {
            $update_sql = "UPDATE users 
                           SET first_name = ?, 
                               last_name = ?, 
                               address = ?, 
                               email = ?, 
                               phone_num = ?, 
                               password = ?
                           WHERE user_id = ?";
            $stmt_update = $conn->prepare($update_sql);
            if ($stmt_update) {
                $stmt_update->bind_param("ssssssi", $first_name, $last_name, $address, $email, $phone_num, $plain_password, $user_id);
            }
        } else {
            $update_sql = "UPDATE users 
                           SET first_name = ?, 
                               last_name = ?, 
                               address = ?, 
                               email = ?, 
                               phone_num = ?
                           WHERE user_id = ?";
            $stmt_update = $conn->prepare($update_sql);
            if ($stmt_update) {
                $stmt_update->bind_param("sssssi", $first_name, $last_name, $address, $email, $phone_num, $user_id);
            }
        }

        if ($stmt_update->execute()) {
            // Set success message in session
            $_SESSION['success_message'] = "Profile updated successfully.";
        
            // Redirect to welcome_user.php
            header("Location: welcome_user.php");
            exit();
        } else {
            $errors[] = "Error updating profile: " . $stmt_update->error;
        }
        
    }
} else {
    // Fetch current user data to pre-fill the form
    $stmt = $conn->prepare("SELECT first_name, last_name, address, email, phone_num FROM users WHERE user_id = ?");
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

// Generate a new CSRF token for the form
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Flight Ticket Booking System</title>
    <link rel="icon" type="image/x-icon" href="fav.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="welcome_user.php" class="text-xl font-bold text-indigo-600">Flight Ticket Booking System</a>
                </div>
                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-4 items-center">
                    <a href="book_flight.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Book Flights</a>
                    <a href="view_bookings.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">My Bookings</a>
                    <form action="logout.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
            <a href="book_flight.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Book Flights</a>
            <a href="view_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">My Bookings</a>
            <form action="logout.php" method="POST" class="px-4 py-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" class="w-full text-left text-gray-700 hover:bg-indigo-50">Logout</button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8 max-w-2xl">
        <!-- Flash Messages -->
        <?php if (!empty($success)): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-green-100 text-green-700">
                <div class="flex justify-between items-center">
                    <span><?php echo htmlspecialchars($success); ?></span>
                    <button onclick="this.parentElement.parentElement.style.display='none';" class="text-green-700 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 px-4 py-3 rounded-md bg-red-100 text-red-700">
                <div class="flex justify-between items-center">
                    <ul class="list-disc pl-5">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button onclick="this.parentElement.parentElement.style.display='none';" class="text-red-700 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Edit Profile Form -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-6 text-indigo-600">Edit Your Profile</h2>
            <form method="POST" action="edit_profile.php" class="space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

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
                    <h3 class="text-lg font-medium text-gray-700">Change Password</h3>
                    <p class="text-sm text-gray-500">Leave the fields below blank if you do not wish to change your password.</p>
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
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- Tailwind CSS Mobile Menu Toggle Script -->
    <script>
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    </script>

</body>
</html>
<?php
$conn->close();
?>
