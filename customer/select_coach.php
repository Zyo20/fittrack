<?php
session_start();

// Session timeout functionality - 5 minutes
$session_timeout = 300; // 5 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Last activity was more than 5 minutes ago
    session_unset();     // Unset all session variables
    session_destroy();   // Destroy the session
    header("Location: ../login.php?timeout=1");
    exit();
}
// Update last activity time
$_SESSION['last_activity'] = time();

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get unread messages count 
$unread_count = count_unread_messages($user_id);

// Check if customer already has an assigned coach
$coach_query = "SELECT * 
               FROM customer_coach_assignments
               WHERE customer_id = $user_id
               AND status = 'approved'";
$coach_result = mysqli_query($conn, $coach_query);

if (mysqli_num_rows($coach_result) > 0) {
    $assignment = mysqli_fetch_assoc($coach_result);
    
    // Get coach details
    $coach_id = $assignment['coach_id'];
    $coach_details_query = "SELECT * FROM users WHERE id = $coach_id";
    $coach_details_result = mysqli_query($conn, $coach_details_query);
    
    if (mysqli_num_rows($coach_details_result) > 0) {
        $coach = mysqli_fetch_assoc($coach_details_result);
        $has_coach = true;
    } else {
        $has_coach = false;
    }
} else {
    $has_coach = false;
}

// Get all available coaches
$coaches_query = "SELECT u.*, COUNT(cca.id) as customer_count
                FROM users u
                LEFT JOIN customer_coach_assignments cca ON u.id = cca.coach_id AND cca.status = 'approved'
                WHERE u.user_type = 'coach' AND u.status = 'active'
                GROUP BY u.id
                ORDER BY u.name";
$coaches_result = mysqli_query($conn, $coaches_query);
$coaches = [];

while ($row = mysqli_fetch_assoc($coaches_result)) {
    $coach_id = $row['id'];
    
    // Get coach specialties
    $specialties_query = "SELECT * FROM coach_specialties WHERE coach_id = $coach_id";
    $specialties_result = mysqli_query($conn, $specialties_query);
    $specialties = [];
    
    while ($specialty = mysqli_fetch_assoc($specialties_result)) {
        $specialties[] = $specialty['specialty'];
    }
    
    $row['specialties'] = $specialties;
    $coaches[] = $row;
}

// Handle coach request
if (isset($_POST['request_coach'])) {
    $coach_id = (int)$_POST['coach_id'];
    
    // Check if already has a request pending
    $check_query = "SELECT * FROM customer_coach_assignments
                  WHERE customer_id = $user_id
                  AND status = 'pending'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error_message'] = "You already have a pending coach request. Please wait for a response before requesting another coach.";
    } else {
        // Request coach
        $request_query = "INSERT INTO customer_coach_assignments (customer_id, coach_id, status)
                        VALUES ($user_id, $coach_id, 'pending')";
        
        if (mysqli_query($conn, $request_query)) {
            $_SESSION['success_message'] = "Coach request submitted successfully. Please wait for the coach to approve your request.";
        } else {
            $_SESSION['error_message'] = "Failed to request coach: " . mysqli_error($conn);
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: select_coach.php");
    exit();
}

// Handle cancel coach request
if (isset($_POST['cancel_request'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    
    // Verify this assignment belongs to the current user
    $verify_query = "SELECT * FROM customer_coach_assignments
                   WHERE id = $assignment_id
                   AND customer_id = $user_id
                   AND status = 'pending'";
    $verify_result = mysqli_query($conn, $verify_query);
    
    if (mysqli_num_rows($verify_result) > 0) {
        // Delete the assignment
        $delete_query = "DELETE FROM customer_coach_assignments WHERE id = $assignment_id";
        
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success_message'] = "Coach request cancelled successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to cancel request: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error_message'] = "Invalid request or you don't have permission.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: select_coach.php");
    exit();
}

// Get any pending coach requests
$pending_query = "SELECT cca.*, u.name as coach_name, u.email as coach_email, u.bio
                 FROM customer_coach_assignments cca
                 JOIN users u ON cca.coach_id = u.id
                 WHERE cca.customer_id = $user_id
                 AND cca.status = 'pending'";
$pending_result = mysqli_query($conn, $pending_query);
$pending_requests = [];

while ($row = mysqli_fetch_assoc($pending_result)) {
    $pending_requests[] = $row;
}

// Store success/error messages from session and then clear them
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear the session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Coach - FitTrack</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">FitTrack</a>
                <button class="md:hidden" type="button" id="navbarToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden md:flex flex-col md:flex-row w-full md:w-auto mt-4 md:mt-0" id="navbarMenu">
                    <ul class="flex flex-col md:flex-row md:mr-auto space-y-2 md:space-y-0 md:space-x-6">
                        <li>
                            <a class="text-gray-300 hover:text-white block py-1" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-1" href="programs.php">My Programs</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-1" href="progress.php">Progress Tracker</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-1 flex items-center" href="messages.php">
                                Messages
                                <?php if ($unread_count > 0): ?>
                                    <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-red-600"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                    <div class="relative mt-4 md:mt-0">
                        <button id="userDropdown" class="flex items-center text-gray-300 hover:text-white">
                            <?php echo $user_name; ?>
                            <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="userDropdownMenu" class="absolute right-0 mt-2 py-2 w-48 bg-white rounded-md shadow-lg hidden z-10">
                            <a href="../profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                            <div class="border-t border-gray-100"></div>
                            <a href="../logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Select Your Coach</h1>
            
            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mt-4">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mt-4">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($has_coach): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-blue-600 text-white px-4 py-3">
                    <h5 class="font-medium">Your Current Coach</h5>
                </div>
                <div class="p-4">
                    <div class="flex flex-col md:flex-row md:items-center mb-4">
                        <div class="flex-shrink-0 mb-4 md:mb-0 md:mr-4">
                            <img src="<?php echo $coach['profile_image'] ? '../uploads/' . $coach['profile_image'] : '../assets/images/default-avatar.png'; ?>" 
                                 alt="<?php echo $coach['name']; ?>" 
                                 class="w-32 h-32 object-cover rounded-full border-4 border-blue-100">
                        </div>
                        <div>
                            <h4 class="text-xl font-bold mb-2"><?php echo $coach['name']; ?></h4>
                            <p class="text-gray-700 mb-2"><i class="fas fa-envelope mr-2 text-blue-500"></i> <?php echo $coach['email']; ?></p>
                            <p class="text-gray-700 mb-4"><?php echo $coach['bio'] ?: 'No bio available'; ?></p>
                            
                            <div class="flex flex-wrap gap-2 mb-4">
                                <?php 
                                $coach_id = $coach['id'];
                                $specialties_query = "SELECT * FROM coach_specialties WHERE coach_id = $coach_id";
                                $specialties_result = mysqli_query($conn, $specialties_query);
                                
                                while ($specialty = mysqli_fetch_assoc($specialties_result)): 
                                ?>
                                    <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                        <?php echo $specialty['specialty']; ?>
                                    </span>
                                <?php endwhile; ?>
                            </div>
                            
                            <div class="flex space-x-3">
                                <a href="messages.php?coach_id=<?php echo $coach['id']; ?>" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm transition-colors">
                                    <i class="fas fa-comments mr-2"></i> Message Coach
                                </a>
                                <a href="programs.php" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm transition-colors">
                                    <i class="fas fa-dumbbell mr-2"></i> View Programs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (count($pending_requests) > 0): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-yellow-600 text-white px-4 py-3">
                    <h5 class="font-medium">Pending Coach Request</h5>
                </div>
                <?php foreach ($pending_requests as $request): ?>
                    <div class="p-4 border-b border-gray-200 last:border-b-0">
                        <div class="flex flex-col md:flex-row md:items-center">
                            <div class="md:w-3/4">
                                <h5 class="font-medium text-lg mb-2">Coach: <?php echo $request['coach_name']; ?></h5>
                                <p class="text-gray-600 mb-3"><?php echo $request['bio'] ?: 'No bio available'; ?></p>
                                <p class="text-sm text-gray-500 mb-4">
                                    <i class="fas fa-calendar-alt mr-1"></i> 
                                    Requested on: <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                </p>
                            </div>
                            <div class="md:w-1/4 flex flex-col">
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 p-3 mb-3">
                                    <div class="flex">
                                        <i class="fas fa-hourglass-half mr-2 mt-0.5"></i>
                                        <p>Waiting for coach approval</p>
                                    </div>
                                </div>
                                <form action="" method="post">
                                    <input type="hidden" name="assignment_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="cancel_request" class="inline-flex items-center justify-center bg-red-100 hover:bg-red-200 text-red-700 px-3 py-2 rounded text-sm transition-colors w-full">
                                        <i class="fas fa-times-circle mr-2"></i> Cancel Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$has_coach && count($pending_requests) == 0): ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-4 mb-6 flex">
                <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                <p>You don't have a coach yet. Select one from the list below to request their guidance.</p>
            </div>
        <?php endif; ?>
        
        <?php if (!$has_coach): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-blue-600 text-white px-4 py-3">
                    <h5 class="font-medium">Available Coaches</h5>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($coaches as $coach): ?>
                            <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                                <div class="p-4">
                                    <div class="flex flex-col md:flex-row items-start">
                                        <div class="flex-shrink-0 mb-4 md:mb-0 md:mr-4">
                                            <img src="<?php echo $coach['profile_image'] ? '../uploads/' . $coach['profile_image'] : '../assets/images/default-avatar.png'; ?>" 
                                                 alt="<?php echo $coach['name']; ?>" 
                                                 class="w-20 h-20 object-cover rounded-full">
                                        </div>
                                        <div class="flex-grow">
                                            <h5 class="font-medium text-lg mb-1"><?php echo $coach['name']; ?></h5>
                                            
                                            <div class="flex flex-wrap gap-1 mb-2">
                                                <?php foreach ($coach['specialties'] as $specialty): ?>
                                                    <span class="inline-block px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs">
                                                        <?php echo $specialty; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <p class="text-sm text-gray-600 mb-3 line-clamp-3"><?php echo $coach['bio'] ?: 'No bio available'; ?></p>
                                            
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-500">
                                                    <i class="fas fa-users mr-1"></i> <?php echo $coach['customer_count']; ?> clients
                                                </span>
                                                
                                                <?php if (count($pending_requests) == 0): ?>
                                                    <form action="" method="post">
                                                        <input type="hidden" name="coach_id" value="<?php echo $coach['id']; ?>">
                                                        <button type="submit" name="request_coach" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm transition-colors">
                                                            <i class="fas fa-user-plus mr-2"></i> Request Coach
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="inline-flex items-center bg-gray-300 text-gray-500 px-3 py-2 rounded text-sm cursor-not-allowed" disabled>
                                                        <i class="fas fa-user-plus mr-2"></i> Request Coach
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="../js/unread-messages.js"></script>
    
    <script>
        // Mobile menu toggle
        document.getElementById('navbarToggle').addEventListener('click', function() {
            const menu = document.getElementById('navbarMenu');
            menu.classList.toggle('hidden');
        });
        
        // User dropdown toggle
        const userDropdown = document.getElementById('userDropdown');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            if (!userDropdownMenu.classList.contains('hidden')) {
                userDropdownMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html> 