<?php
// booking_form.php

require 'csrf_helper.php';
require 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Define seat type mappings
$seat_type_mapping = [
    'Economy' => 'seat_economy',
    'Business' => 'seat_business',
    'First Class' => 'seat_first_class'
];

// Initialize arrays for error and success messages
$errors = [];
$success = [];

// Initialize variables for confirmation
$confirmation = null;

// Check if flight_id is set
if (!isset($_GET['flight_id'])) {
    $errors[] = "Invalid flight selection.";
} else {
    $flight_id = intval($_GET['flight_id']);

    // Fetch flight details
    $stmt = $conn->prepare("
        SELECT 
            f.flight_id,
            f.flight_num,
            f.departure_datetime,
            f.arrival_datetime,
            f.seat_economy,
            f.seat_business,
            f.seat_first_class,
            r.price_seat_economy,
            r.price_seat_business,
            r.price_seat_first_class,
            f.status
        FROM flights f
        JOIN routes r ON f.route_id = r.route_id
        WHERE f.flight_id = ? AND f.status IN ('Scheduled', 'Delayed')
    ");
    if (!$stmt) {
        $errors[] = "Error preparing statement: " . htmlspecialchars($conn->error);
    } else {
        $stmt->bind_param("i", $flight_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows != 1) {
            $errors[] = "Flight not found or unavailable for booking.";
        } else {
            $flight = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Handle form submission for selecting number of tickets
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ticket_count'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $ticket_count = intval($_POST['ticket_count']);
        if ($ticket_count < 1 || $ticket_count > 4) {
            $errors[] = "You can book between 1 and 4 tickets.";
        } else {
            // Check if user has already booked the maximum number of tickets
            $stmt_existing = $conn->prepare("
                SELECT COUNT(*) AS total_booked
                FROM bookings b
                WHERE b.user_id = ? AND b.flight_id = ? AND b.status != 'Cancelled'
            ");
            if ($stmt_existing) {
                $stmt_existing->bind_param("ii", $_SESSION['user_id'], $flight_id);
                $stmt_existing->execute();
                $result_existing = $stmt_existing->get_result();
                $row_existing = $result_existing->fetch_assoc();
                $total_booked = intval($row_existing['total_booked']);
                $stmt_existing->close();

                $max_tickets = 4;
                $remaining_tickets = $max_tickets - $total_booked;

                if ($remaining_tickets <= 0) {
                    $errors[] = "You have already booked the maximum of 4 tickets for this flight.";
                } elseif ($ticket_count > $remaining_tickets) {
                    $errors[] = "You can only book $remaining_tickets more ticket(s) for this flight.";
                } else {
                    $_SESSION['ticket_count'] = $ticket_count;
                    header("Location: booking_form.php?flight_id=" . urlencode($flight_id) . "&step=2");
                    exit();
                }
            } else {
                $errors[] = "Error checking existing bookings: " . htmlspecialchars($conn->error);
            }
        }
    }
}

// Handle form submission for passenger details
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_passengers'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        if (!isset($_SESSION['ticket_count'])) {
            $errors[] = "Ticket count not specified.";
        } else {
            $ticket_count = intval($_SESSION['ticket_count']);
            if ($ticket_count < 1 || $ticket_count > 4) {
                $errors[] = "Invalid number of tickets.";
            } else {
                $passengers = [];
                $passport_numbers = [];

                for ($i = 1; $i <= $ticket_count; $i++) {
                    $name = trim($_POST["name_$i"]);
                    $phone_num = trim($_POST["phone_num_$i"]);
                    $dob = trim($_POST["dob_$i"]);
                    $passport_num = strtoupper(trim($_POST["passport_num_$i"])); // Convert to uppercase for consistency
                    $nationality = trim($_POST["nationality_$i"]);
                    $gender = trim($_POST["gender_$i"]);

                    // Basic validation
                    if (empty($name) || empty($dob) || empty($passport_num) || empty($nationality) || empty($gender)) {
                        $errors[] = "All fields are required for Passenger $i.";
                    } else {
                        // Check if the date of birth is valid and not in the future
                        $dob_timestamp = strtotime($dob);
                        $current_timestamp = time();
                
                        if ($dob_timestamp === false) {
                            $errors[] = "Invalid Date of Birth format for Passenger $i.";
                        } elseif ($dob_timestamp > $current_timestamp) {
                            $errors[] = "Date of Birth cannot be in the future for Passenger $i.";
                        }
                    }

                    // Validate passport number format (alphanumeric, 5-20 characters)
                    if (!preg_match('/^[A-Z0-9]{5,20}$/', $passport_num)) {
                        $errors[] = "Invalid passport number format for Passenger $i.";
                    }

                    // Check for duplicate passport numbers within the same booking
                    if (in_array($passport_num, $passport_numbers)) {
                        $errors[] = "Duplicate passport number detected for Passenger $i.";
                    } else {
                        $passport_numbers[] = $passport_num;
                    }

                    // Add passenger to array
                    $passengers[] = [
                        'name' => $name,
                        'phone_num' => $phone_num,
                        'dob' => $dob,
                        'passport_num' => $passport_num,
                        'nationality' => $nationality,
                        'gender' => $gender
                        // 'seat_type' will be collected in seat selection step
                    ];
                }

                // If no errors, store passengers in session and proceed to seat selection
                if (empty($errors)) {
                    $_SESSION['passengers'] = $passengers;
                    $_SESSION['flight_id'] = $flight_id;
                    header("Location: booking_form.php?flight_id=" . urlencode($flight_id) . "&step=3");
                    exit();
                }
            }
        }
    }
}

// Handle form submission for booking confirmation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_booking'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        if (!isset($_SESSION['passengers']) || !isset($_SESSION['flight_id']) || !isset($_SESSION['ticket_count'])) {
            $errors[] = "Invalid booking session.";
        } else {
            $passengers = $_SESSION['passengers'];
            $flight_id = $_SESSION['flight_id'];
            $ticket_count = $_SESSION['ticket_count'];

            // Fetch updated flight details
            $stmt = $conn->prepare("
                SELECT 
                    f.flight_id,
                    f.flight_num,
                    f.seat_economy,
                    f.seat_business,
                    f.seat_first_class,
                    r.price_seat_economy,
                    r.price_seat_business,
                    r.price_seat_first_class
                FROM flights f
                JOIN routes r ON f.route_id = r.route_id
                WHERE f.flight_id = ? AND f.status IN ('Scheduled', 'Delayed')
            ");
            if ($stmt) {
                $stmt->bind_param("i", $flight_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows != 1) {
                    $errors[] = "Flight not found or unavailable for booking.";
                } else {
                    $flight = $result->fetch_assoc();
                }
                $stmt->close();
            } else {
                $errors[] = "Error fetching flight details: " . htmlspecialchars($conn->error);
            }

            // Proceed only if flight is valid
            if (empty($errors)) {
                // Check if user has already booked the maximum number of tickets
                $stmt_count = $conn->prepare("
                    SELECT COUNT(*) AS total_booked
                    FROM bookings b
                    WHERE b.user_id = ? AND b.flight_id = ? AND b.status != 'Cancelled'
                ");
                if ($stmt_count) {
                    $stmt_count->bind_param("ii", $_SESSION['user_id'], $flight_id);
                    $stmt_count->execute();
                    $result_count = $stmt_count->get_result();
                    $row_count = $result_count->fetch_assoc();
                    $total_booked = intval($row_count['total_booked']);
                    $stmt_count->close();

                    $max_tickets = 4;
                    $remaining_tickets = $max_tickets - $total_booked;

                    if ($remaining_tickets <= 0) {
                        $errors[] = "You have already booked the maximum of 4 tickets for this flight.";
                    } elseif ($ticket_count > $remaining_tickets) {
                        $errors[] = "You can only book $remaining_tickets more ticket(s) for this flight.";
                    }
                } else {
                    $errors[] = "Error checking existing bookings: " . htmlspecialchars($conn->error);
                }

                // Proceed only if no errors
                if (empty($errors)) {
                    // Begin transaction
                    $conn->begin_transaction();
                    $assigned_seats = [];
                    $fare_total = 0.00;

                    try {
                        foreach ($passengers as $index => $passenger) {
                            $seat_type = $_POST["seat_type_" . ($index + 1)];

                            // Validate seat type
                            if (!in_array($seat_type, ['Economy', 'Business', 'First Class'])) {
                                throw new Exception("Invalid seat type selected for Passenger " . ($index + 1) . ".");
                            }

                            // Determine seat letter and corresponding column
                            switch ($seat_type) {
                                case 'Economy':
                                    $seat_letter = 'E';
                                    $seat_column = $seat_type_mapping['Economy'];
                                    $price = $flight['price_seat_economy'];
                                    break;
                                case 'Business':
                                    $seat_letter = 'B';
                                    $seat_column = $seat_type_mapping['Business'];
                                    $price = $flight['price_seat_business'];
                                    break;
                                case 'First Class':
                                    $seat_letter = 'F';
                                    $seat_column = $seat_type_mapping['First Class'];
                                    $price = $flight['price_seat_first_class'];
                                    break;
                                default:
                                    throw new Exception("Invalid seat type for Passenger " . ($index + 1) . ".");
                            }

                            // Check seat availability
                            if ($flight[$seat_column] < 1) {
                                throw new Exception("No more available seats for " . htmlspecialchars($seat_type) . " class.");
                            }

                            // **Prevent Same Passenger Booking Multiple Times on the Same Flight**
                            $stmt_duplicate = $conn->prepare("
                                SELECT 1 
                                FROM bookings b
                                JOIN passengers p ON b.passenger_id = p.passenger_id
                                WHERE b.flight_id = ? AND p.passport_num = ? AND b.status != 'Cancelled'
                                LIMIT 1
                            ");
                            if ($stmt_duplicate) {
                                $stmt_duplicate->bind_param("is", $flight_id, $passenger['passport_num']);
                                $stmt_duplicate->execute();
                                $result_duplicate = $stmt_duplicate->get_result();
                                if ($result_duplicate->num_rows > 0) {
                                    throw new Exception("Passenger with passport number " . htmlspecialchars($passenger['passport_num']) . " has already booked this flight.");
                                }
                                $stmt_duplicate->close();
                            } else {
                                throw new Exception("Error preparing duplicate booking check: " . htmlspecialchars($conn->error));
                            }

                            // **Check if Passenger Exists**
                            $stmt_check = $conn->prepare("SELECT passenger_id FROM passengers WHERE passport_num = ?");
                            if ($stmt_check) {
                                $stmt_check->bind_param("s", $passenger['passport_num']);
                                $stmt_check->execute();
                                $result_check = $stmt_check->get_result();

                                if ($result_check->num_rows > 0) {
                                    // Passenger exists, fetch passenger_id
                                    $existing_passenger = $result_check->fetch_assoc();
                                    $passenger_id = $existing_passenger['passenger_id'];
                                } else {
                                    // Passenger does not exist, insert new passenger
                                    $stmt_insert = $conn->prepare("INSERT INTO passengers (name, phone_num, dob, passport_num, nationality, gender) VALUES (?, ?, ?, ?, ?, ?)");
                                    if ($stmt_insert) {
                                        $stmt_insert->bind_param(
                                            "ssssss",
                                            $passenger['name'],
                                            $passenger['phone_num'],
                                            $passenger['dob'],
                                            $passenger['passport_num'],
                                            $passenger['nationality'],
                                            $passenger['gender']
                                        );
                                        if ($stmt_insert->execute()) {
                                            $passenger_id = $stmt_insert->insert_id;
                                        } else {
                                            throw new Exception("Error inserting passenger: " . htmlspecialchars($stmt_insert->error));
                                        }
                                        $stmt_insert->close();
                                    } else {
                                        throw new Exception("Error preparing passenger insertion statement: " . htmlspecialchars($conn->error));
                                    }
                                }
                                $stmt_check->close();
                            } else {
                                throw new Exception("Error preparing passenger check statement: " . htmlspecialchars($conn->error));
                            }

                            // **Determine Next Available Seat Number (Lowest Available)**
                            // Fetch all booked seat numbers for this flight and seat type
                            $stmt_seats = $conn->prepare("SELECT seat_num FROM bookings WHERE flight_id = ? AND seat_type = ? AND status != 'Cancelled'");
                            if ($stmt_seats) {
                                $stmt_seats->bind_param("is", $flight_id, $seat_type);
                                $stmt_seats->execute();
                                $result_seats = $stmt_seats->get_result();
                                $booked_seats = [];

                                while ($row_seat = $result_seats->fetch_assoc()) {
                                    // Extract the numerical part of the seat number
                                    $booked_seats[] = intval(substr($row_seat['seat_num'], 0, -1));
                                }

                                $stmt_seats->close();
                            } else {
                                throw new Exception("Error preparing seat fetching statement: " . htmlspecialchars($conn->error));
                            }

                            // Determine the total seats available for the seat type
                            $total_seats = intval($flight[$seat_column]);

                            // Find the lowest available seat number
                            $assigned_number = 1;
                            while (in_array($assigned_number, $booked_seats) && $assigned_number <= $total_seats) {
                                $assigned_number++;
                            }

                            // Check if there are available seats
                            if ($assigned_number > $total_seats) {
                                throw new Exception("No available seats for " . htmlspecialchars($seat_type) . " class.");
                            }

                            $seat_num = $assigned_number . $seat_letter;

                            // Assign fare based on seat type
                            $fare = floatval($price);
                            $fare_total += $fare;

                            // Insert booking into bookings table with status 'Pending'
                            $stmt_booking = $conn->prepare("INSERT INTO bookings (user_id, flight_id, passenger_id, seat_type, seat_num, fare, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
                            if ($stmt_booking) {
                                $stmt_booking->bind_param(
                                    "iiissd",
                                    $_SESSION['user_id'],
                                    $flight_id,
                                    $passenger_id,
                                    $seat_type,
                                    $seat_num,
                                    $fare
                                );
                                if ($stmt_booking->execute()) {
                                    // Successfully booked
                                    // No logging as per your requirements
                                } else {
                                    throw new Exception("Error inserting booking: " . htmlspecialchars($stmt_booking->error));
                                }
                                $stmt_booking->close();
                            } else {
                                throw new Exception("Error preparing booking statement: " . htmlspecialchars($conn->error));
                            }

                            // Decrement available seats in flights table
                            $stmt_update_seats = $conn->prepare("UPDATE flights SET $seat_column = $seat_column - 1 WHERE flight_id = ?");
                            if ($stmt_update_seats) {
                                $stmt_update_seats->bind_param("i", $flight_id);
                                if (!$stmt_update_seats->execute()) {
                                    throw new Exception("Error updating seat counts: " . htmlspecialchars($stmt_update_seats->error));
                                }
                                $stmt_update_seats->close();
                            } else {
                                throw new Exception("Error preparing seat update statement: " . htmlspecialchars($conn->error));
                            }

                            // Store assigned seat for confirmation display
                            $assigned_seats[] = [
                                'passenger_name' => $passenger['name'],
                                'seat_num' => $seat_num,
                                'fare' => $fare
                            ];

                            // Update available seats for subsequent iterations
                            $flight[$seat_column] -= 1;
                        }

                        // Commit Transaction
                        $conn->commit();

                        // Store flight_num for confirmation before unsetting session
                        $confirmed_flight_num = htmlspecialchars($flight['flight_num']);

                        // Prepare confirmation data
                        $confirmation = [
                            'flight_num' => $confirmed_flight_num,
                            'assigned_seats' => $assigned_seats,
                            'fare_total' => $fare_total
                        ];

                        // Clear session variables
                        unset($_SESSION['passengers']);
                        unset($_SESSION['flight_id']);
                        unset($_SESSION['ticket_count']);
                    } catch (Exception $e) {
                        // Rollback Transaction
                        $conn->rollback();
                        $errors[] = $e->getMessage();
                    }

                    // No need to display errors here; they will be shown in the HTML below
                }
            }
        }
    }
}

// Generate a CSRF token for the logout form
$logout_csrf_token = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Form - Flight Ticket Booking System</title>
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
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($logout_csrf_token); ?>">
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($logout_csrf_token); ?>">
                <button type="submit" class="w-full text-left text-gray-700 hover:bg-indigo-50">Logout</button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <!-- Display Success and Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                <ul class="list-disc pl-5">
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($confirmation): ?>
            <!-- Booking Confirmation -->
            <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-2xl font-semibold text-green-600 mb-4">Booking Confirmation</h3>
                <h2 class="text-xl font-semibold mb-2">Flight <?php echo $confirmation['flight_num'] ? htmlspecialchars($confirmation['flight_num']) : 'N/A'; ?></h2>
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200">Passenger Name</th>
                            <th class="py-2 px-4 border-b border-gray-200">Seat Number</th>
                            <th class="py-2 px-4 border-b border-gray-200">Fare ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($confirmation['assigned_seats'] as $assigned): ?>
                            <tr class="hover:bg-gray-100">
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($assigned['passenger_name']); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($assigned['seat_num']); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo number_format($assigned['fare'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="2" class="py-2 px-4 border-b border-gray-200 font-semibold">Total Price</td>
                            <td class="py-2 px-4 border-b border-gray-200 font-semibold"><?php echo number_format($confirmation['fare_total'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-4"><strong>Status:</strong> Pending</p>
                <div class="mt-6 flex space-x-4">
                    <a href="view_bookings.php" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">View My Bookings</a>
                    <a href="book_flight.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Book Another Flight</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Step 1: Select Number of Tickets -->
            <?php if (!isset($_GET['step']) || $_GET['step'] == 1): ?>
                <div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-indigo-600 mb-4">Step 1: Select Number of Tickets</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="mb-4">
                            <label for="ticket_count" class="block text-gray-700 text-sm font-bold mb-2">Number of Tickets (Max 4):</label>
                            <select name="ticket_count" id="ticket_count" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">--Select--</option>
                                <?php
                                // Determine remaining tickets user can book for this flight
                                if (isset($flight)) {
                                    $stmt_remaining = $conn->prepare("
                                        SELECT COUNT(*) AS total_booked
                                        FROM bookings b
                                        WHERE b.user_id = ? AND b.flight_id = ? AND b.status != 'Cancelled'
                                    ");
                                    if ($stmt_remaining) {
                                        $stmt_remaining->bind_param("ii", $_SESSION['user_id'], $flight_id);
                                        $stmt_remaining->execute();
                                        $result_remaining = $stmt_remaining->get_result();
                                        $row_remaining = $result_remaining->fetch_assoc();
                                        $total_booked = intval($row_remaining['total_booked']);
                                        $stmt_remaining->close();

                                        $max_tickets = 4;
                                        $remaining_tickets = $max_tickets - $total_booked;

                                        if ($remaining_tickets <= 0) {
                                            echo "<option value='0' disabled>You have reached the maximum bookings for this flight.</option>";
                                        } else {
                                            for ($i = 1; $i <= min(4, $remaining_tickets); $i++) {
                                                echo "<option value='$i'>$i</option>";
                                            }
                                        }
                                    } else {
                                        echo "<option value='0' disabled>Unable to determine availability.</option>";
                                    }
                                } else {
                                    echo "<option value='0' disabled>Flight details unavailable.</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Next
                            </button>
                            <a href="book_flight.php" class="inline-block align-baseline font-bold text-sm text-indigo-600 hover:text-indigo-800">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>

            <!-- Step 2: Enter Passenger Details -->
            <?php elseif ($_GET['step'] == '2' && isset($_SESSION['ticket_count'])): ?>
                <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-indigo-600 mb-4">Step 2: Enter Passenger Details</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <?php for ($i = 1; $i <= $_SESSION['ticket_count']; $i++): ?>
                            <fieldset class="border border-gray-300 p-4 rounded mb-6">
                                <legend class="text-lg font-semibold text-gray-700">Passenger <?php echo $i; ?></legend>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label for="name_<?php echo $i; ?>" class="block text-gray-700 text-sm font-bold mb-2">Name <span class="text-red-500">*</span></label>
                                        <input type="text" id="name_<?php echo $i; ?>" name="name_<?php echo $i; ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    </div>
                                    <div>
                                        <label for="phone_num_<?php echo $i; ?>" class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                                        <input type="text" id="phone_num_<?php echo $i; ?>" name="phone_num_<?php echo $i; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="e.g., +1-555-1234">
                                    </div>
                                    <div>
                                        <label for="dob_<?php echo $i; ?>" class="block text-gray-700 text-sm font-bold mb-2">Date of Birth <span class="text-red-500">*</span></label>
                                        <input type="date" id="dob_<?php echo $i; ?>" name="dob_<?php echo $i; ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div>
                                        <label for="passport_num_<?php echo $i; ?>" class="block text-gray-700 text-sm font-bold mb-2">Passport Number <span class="text-red-500">*</span></label>
                                        <input type="text" id="passport_num_<?php echo $i; ?>" name="passport_num_<?php echo $i; ?>" required pattern="[A-Z0-9]{5,20}" title="Passport number should be alphanumeric and between 5 to 20 characters." class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    </div>
                                    <div>
                                        <label for="nationality_<?php echo $i; ?>" class="block text-gray-700 text-sm font-bold mb-2">Nationality <span class="text-red-500">*</span></label>
                                        <input type="text" id="nationality_<?php echo $i; ?>" name="nationality_<?php echo $i; ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    </div>
                                    <div>
                                        <label for="gender_<?php echo $i; ?>" class="block text-gray-700 text-sm font-bold mb-2">Gender <span class="text-red-500">*</span></label>
                                        <select id="gender_<?php echo $i; ?>" name="gender_<?php echo $i; ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                            <option value="">--Select--</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </fieldset>
                        <?php endfor; ?>
                        <div class="flex items-center justify-between">
                            <button type="submit" name="submit_passengers" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Next
                            </button>
                            <a href="book_flight.php?flight_id=<?php echo urlencode($flight_id); ?>" class="inline-block align-baseline font-bold text-sm text-indigo-600 hover:text-indigo-800">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>

            <!-- Step 3: Seat Selection and Confirmation -->
            <?php elseif ($_GET['step'] == '3' && isset($_SESSION['passengers'])): ?>
                <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-indigo-600 mb-4">Step 3: Seat Selection</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-2 px-4 border-b border-gray-200">Passenger Name</th>
                                    <th class="py-2 px-4 border-b border-gray-200">Seat Type</th>
                                    <th class="py-2 px-4 border-b border-gray-200">Fare ($)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['passengers'] as $index => $passenger): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($passenger['name']); ?></td>
                                        <td class="py-2 px-4 border-b border-gray-200">
                                            <select name="seat_type_<?php echo ($index + 1); ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                <option value="">--Select Seat Type--</option>
                                                <option value="Economy">Economy (<?php echo htmlspecialchars($flight['seat_economy']); ?> available)</option>
                                                <option value="Business">Business (<?php echo htmlspecialchars($flight['seat_business']); ?> available)</option>
                                                <option value="First Class">First Class (<?php echo htmlspecialchars($flight['seat_first_class']); ?> available)</option>
                                            </select>
                                        </td>
                                        <td id="fare_<?php echo ($index + 1); ?>" class="py-2 px-4 border-b border-gray-200">0.00</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="flex items-center justify-between mt-6">
                            <button type="submit" name="confirm_booking" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Confirm Booking
                            </button>
                            <div class="flex space-x-4">
                                <a href="booking_form.php?flight_id=<?php echo urlencode($flight_id); ?>&step=2" class="inline-block align-baseline font-bold text-sm text-indigo-600 hover:text-indigo-800">
                                    Back
                                </a>
                                <a href="book_flight.php" class="inline-block align-baseline font-bold text-sm text-indigo-600 hover:text-indigo-800">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Tailwind CSS Mobile Menu Toggle Script -->
    <script>
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // JavaScript to dynamically update fare based on seat type selection
        document.addEventListener('DOMContentLoaded', function() {
            const seatTypeSelects = document.querySelectorAll('select[name^="seat_type_"]');
            seatTypeSelects.forEach(function(select) {
                select.addEventListener('change', function() {
                    const seatType = this.value;
                    const seatNumber = this.name.split('_')[2];
                    const fareCell = document.getElementById('fare_' + seatNumber);
                    if (seatType) {
                        // Fetch fare from PHP (embedded data)
                        let fare = 0.00;
                        const fareData = {
                            'Economy': <?php echo json_encode($flight['price_seat_economy']); ?>,
                            'Business': <?php echo json_encode($flight['price_seat_business']); ?>,
                            'First Class': <?php echo json_encode($flight['price_seat_first_class']); ?>
                        };
                        if (fareData[seatType]) {
                            fare = fareData[seatType];
                        }
                        fareCell.textContent = parseFloat(fare).toFixed(2);
                    } else {
                        fareCell.textContent = "0.00";
                    }
                });
            });
        });
    </script>
</body>
</html>
