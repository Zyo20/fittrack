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

// Get customer's programs
$customer_programs = [];
$programs_query = "SELECT cp.*, p.name, p.description, p.duration, p.difficulty,
                  c.name as coach_name, cc.coach_id 
                  FROM customer_programs cp
                  JOIN programs p ON cp.program_id = p.id
                  LEFT JOIN coach_customer cc ON cp.customer_id = cc.customer_id
                  LEFT JOIN users c ON cc.coach_id = c.id
                  WHERE cp.customer_id = $user_id
                  ORDER BY cp.created_at DESC";
$programs_result = mysqli_query($conn, $programs_query);

while ($row = mysqli_fetch_assoc($programs_result)) {
    $row['progress_percentage'] = calculate_program_progress($user_id, $row['program_id']);
    $customer_programs[] = $row;
}

// Get list of available programs that customer has not enrolled in
$available_programs = [];
if ($customer_programs) {
    $enrolled_program_ids = array_map(function($program) {
        return $program['program_id'];
    }, $customer_programs);
    
    $ids_string = implode(',', $enrolled_program_ids);
    $where_clause = "WHERE p.id NOT IN ($ids_string)";
} else {
    $where_clause = "";
}

$available_query = "SELECT p.*, u.name as coach_name 
                   FROM programs p
                   LEFT JOIN coach_customer cc ON cc.customer_id = $user_id
                   LEFT JOIN users u ON cc.coach_id = u.id
                   $where_clause
                   ORDER BY p.name";
$available_result = mysqli_query($conn, $available_query);

while ($row = mysqli_fetch_assoc($available_result)) {
    $available_programs[] = $row;
}

// Handle program enrollment
if (isset($_POST['request_program'])) {
    $program_id = (int)$_POST['program_id'];
    
    // Check if program exists
    $check_query = "SELECT * FROM programs WHERE id = $program_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Check if already enrolled
        $check_enrolled_query = "SELECT * FROM customer_programs 
                               WHERE customer_id = $user_id AND program_id = $program_id";
        $check_enrolled_result = mysqli_query($conn, $check_enrolled_query);
        
        if (mysqli_num_rows($check_enrolled_result) == 0) {
            // Enroll in program
            $enroll_query = "INSERT INTO customer_programs (customer_id, program_id, status) 
                           VALUES ($user_id, $program_id, 'pending')";
            
            if (mysqli_query($conn, $enroll_query)) {
                $_SESSION['success_message'] = "Program request submitted successfully. Waiting for coach approval.";
                
                // Refresh the programs lists
                header("Location: programs.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Failed to request program enrollment: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error_message'] = "You are already enrolled in this program.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid program selected.";
    }
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
    <title>My Programs - FitTrack</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
<nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">OpFit Customer</a>
                <button class="md:hidden" type="button" id="navbarToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden md:flex flex-col md:flex-row w-full md:w-auto mt-4 md:mt-0 items-center" id="navbarMenu">
                    <ul class="flex flex-col md:flex-row md:mr-6 space-y-2 md:space-y-0 md:space-x-6">
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-white font-medium block py-2" href="programs.php">My Programs</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="progress.php">Progress Tracker</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2 flex items-center" href="messages.php">
                                Messages
                                <?php if ($unread_count > 0): ?>
                                    <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-red-600"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                    <div class="relative mt-4 md:mt-0 md:ml-4" id="userDropdownContainer">
                        <button id="userDropdown" class="flex items-center text-gray-300 hover:text-white py-2">
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
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">My Programs</h1>
                <a href="#availablePrograms" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded transition-colors">
                    <i class="fas fa-plus-circle mr-2"></i> Join New Program
                </a>
            </div>
            
            <?php if ($success_message): ?>
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mt-4">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mt-4">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Current Programs -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Current Programs</h2>
            
            <?php if (count($customer_programs) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($customer_programs as $program): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
                            <div class="bg-blue-600 text-white px-4 py-3">
                                <div class="flex justify-between items-center">
                                    <h5 class="font-medium"><?php echo $program['name']; ?></h5>
                                    <span class="bg-white text-gray-800 text-xs px-2 py-1 rounded">
                                        <?php echo ucfirst($program['difficulty']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="p-4 flex-grow">
                                <div class="flex justify-between mb-2">
                                    <span class="text-sm bg-<?php 
                                        echo ($program['status'] == 'approved') ? 'blue-100 text-blue-800' : 
                                            (($program['status'] == 'completed') ? 'blue-100 text-blue-800' : 
                                            'yellow-100 text-yellow-800'); 
                                        ?> px-2 py-1 rounded-full">
                                        <?php 
                                            echo ucfirst($program['status']); 
                                            if ($program['status'] == 'pending') echo ' Approval';
                                        ?>
                                    </span>
                                    <span class="text-sm text-gray-600">
                                        <i class="fas fa-calendar-alt mr-1"></i> <?php echo $program['duration']; ?> weeks
                                    </span>
                                </div>
                                
                                <p class="text-gray-700 mb-4 line-clamp-3"><?php echo $program['description']; ?></p>
                                
                                <?php if ($program['status'] == 'approved' || $program['status'] == 'completed'): ?>
                                    <div class="mb-3">
                                        <div class="flex items-center mb-1">
                                            <span class="mr-3 text-sm font-medium"><?php echo $program['progress_percentage']; ?>%</span>
                                            <div class="relative flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                <div class="absolute h-full bg-blue-500" style="width: <?php echo $program['progress_percentage']; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-sm text-gray-600 mb-4">
                                    <i class="fas fa-user-md mr-1"></i> Coach: <a href="coach_details.php?id=<?php echo $program['coach_id']; ?>" class="text-blue-600 hover:underline"><?php echo $program['coach_name']; ?></a>
                                </div>
                                
                                <div class="flex justify-between items-center mt-auto">
                                    <?php if ($program['status'] == 'approved' || $program['status'] == 'completed'): ?>
                                        <a href="program_progress.php?id=<?php echo $program['program_id']; ?>" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm transition-colors">
                                            <i class="fas fa-tasks mr-2"></i> View Progress
                                        </a>
                                        <a href="messages.php?coach_id=<?php echo $program['coach_id']; ?>" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded text-sm transition-colors">
                                            <i class="fas fa-comments mr-2"></i> Message Coach
                                        </a>
                                    <?php elseif ($program['status'] == 'pending'): ?>
                                        <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 p-3 w-full text-sm">
                                            <div class="flex">
                                                <i class="fas fa-hourglass-half mr-2 mt-0.5"></i>
                                                <p>Waiting for coach approval</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-4 flex">
                    <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                    <p>You haven't enrolled in any programs yet. Browse the available programs below to get started!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Available Programs -->
        <div id="availablePrograms" class="py-2">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Available Programs</h2>
            
            <?php if (count($available_programs) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($available_programs as $program): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="bg-blue-600 text-white px-4 py-3">
                                <div class="flex justify-between items-center">
                                    <h5 class="font-medium"><?php echo $program['name']; ?></h5>
                                    <span class="bg-white text-gray-800 text-xs px-2 py-1 rounded">
                                        <?php echo ucfirst($program['difficulty']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="p-4">
                                <p class="text-gray-700 mb-4 line-clamp-3"><?php echo $program['description']; ?></p>
                                
                                <div class="flex justify-between text-sm text-gray-600 mb-4">
                                    <span>
                                        <i class="fas fa-calendar-alt mr-1"></i> <?php echo $program['duration']; ?> weeks
                                    </span>
                                    <span>
                                        <i class="fas fa-user-md mr-1"></i> <?php echo $program['coach_name']; ?>
                                    </span>
                                </div>
                                
                                <form action="" method="post">
                                    <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                                    <button type="submit" name="request_program" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors flex items-center justify-center">
                                        <i class="fas fa-plus-circle mr-2"></i> Request to Join
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-4 flex">
                    <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                    <p>You're already enrolled in all available programs!</p>
                </div>
            <?php endif; ?>
        </div>
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