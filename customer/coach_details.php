<?php
session_start();

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

// Check if coach ID is provided
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$coach_id = (int)$_GET['id'];

// Verify this coach is assigned to the current customer
$verify_query = "SELECT * FROM coach_customer 
                WHERE coach_id = $coach_id 
                AND customer_id = $user_id";
$verify_result = mysqli_query($conn, $verify_query);

if (mysqli_num_rows($verify_result) == 0) {
    $_SESSION['error_message'] = "Invalid coach or you don't have access to view this coach.";
    header("Location: dashboard.php");
    exit();
}

// Get coach details
$coach_query = "SELECT * FROM users WHERE id = $coach_id AND user_type = 'coach'";
$coach_result = mysqli_query($conn, $coach_query);
$coach = mysqli_fetch_assoc($coach_result);

// Get coach's specialization or additional info if available
// This would require additional fields in the database

// Get all programs assigned by this coach
$assigned_programs_query = "SELECT cp.*, p.name, p.description, p.difficulty
                          FROM customer_programs cp
                          JOIN programs p ON cp.program_id = p.id
                          WHERE cp.customer_id = $user_id
                          AND EXISTS (
                              SELECT 1 FROM coach_customer 
                              WHERE coach_id = $coach_id 
                              AND customer_id = $user_id
                          )
                          ORDER BY cp.created_at DESC";
$assigned_programs_result = mysqli_query($conn, $assigned_programs_query);
$assigned_programs = [];
while ($row = mysqli_fetch_assoc($assigned_programs_result)) {
    $assigned_programs[] = $row;
}

// Get coach's other customers count (for experience level display)
$other_customers_query = "SELECT COUNT(*) as count FROM coach_customer WHERE coach_id = $coach_id";
$other_customers_result = mysqli_query($conn, $other_customers_query);
$other_customers_count = mysqli_fetch_assoc($other_customers_result)['count'];

// Check if assignment date exists in the table structure
$assignment_date = null;
try {
    $assignment_query = "SHOW COLUMNS FROM coach_customer LIKE 'created_at'";
    $assignment_result = mysqli_query($conn, $assignment_query);
    
    if (mysqli_num_rows($assignment_result) > 0) {
        // The created_at column exists, so we can safely query it
        $assignment_query = "SELECT created_at FROM coach_customer WHERE coach_id = $coach_id AND customer_id = $user_id";
        $assignment_result = mysqli_query($conn, $assignment_query);
        $assignment_date = mysqli_fetch_assoc($assignment_result)['created_at'];
    }
} catch (Exception $e) {
    // Column doesn't exist or other error, continue without the date
    $assignment_date = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Details - FitTrack</title>
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
                            <a class="text-white font-medium block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">My Programs</a>
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
        <div class="mb-4">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="dashboard.php" class="text-gray-700 hover:text-blue-600">
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-gray-500">Coach: <?php echo $coach['name']; ?></span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="md:col-span-1">
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Coach Information</h5>
                    </div>
                    <div class="p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-tie text-6xl text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-center mb-2"><?php echo $coach['name']; ?></h3>
                        <p class="text-center text-gray-500 mb-4"><?php echo $coach['email']; ?></p>
                        <hr class="my-4">
                        <div>
                            <p class="flex items-center mb-2">
                                <i class="fas fa-users text-gray-600 mr-2 w-6"></i>
                                <span><strong>Experience:</strong> 
                                    <?php 
                                    if ($other_customers_count > 10) echo "Expert (10+ clients)";
                                    else if ($other_customers_count > 5) echo "Intermediate (6-10 clients)";
                                    else echo "Beginner (1-5 clients)";
                                    ?>
                                </span>
                            </p>
                            <?php if ($assignment_date): ?>
                            <p class="flex items-center mb-2">
                                <i class="fas fa-calendar-check text-gray-600 mr-2 w-6"></i>
                                <span><strong>Your Coach Since:</strong> 
                                    <?php echo date('F d, Y', strtotime($assignment_date)); ?>
                                </span>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-cyan-600 text-white px-4 py-3">
                        <h5 class="font-medium">Quick Communication</h5>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600 mb-4">You can communicate with your coach through the following methods:</p>
                        <div class="space-y-2">
                            <a href="mailto:<?php echo $coach['email']; ?>" class="flex items-center justify-center w-full border border-blue-600 text-blue-600 hover:bg-blue-50 px-4 py-2 rounded transition-colors">
                                <i class="fas fa-envelope mr-2"></i>Send Email
                            </a>
                            <a href="messages.php" class="flex items-center justify-center w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                                <i class="fas fa-comment-dots mr-2"></i>Send Message
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Programs Assigned by Your Coach</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($assigned_programs) > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($assigned_programs as $program): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                        <div class="flex flex-wrap justify-between mb-2">
                                            <h5 class="text-lg font-medium"><?php echo $program['name']; ?></h5>
                                            <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                                                <?php 
                                                if ($program['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                                else if ($program['status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                                else if ($program['status'] == 'completed') echo 'bg-blue-100 text-blue-800';
                                                else echo 'bg-red-100 text-red-800';
                                                ?>">
                                                <?php echo ucfirst($program['status']); ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-600 mb-3"><?php echo $program['description']; ?></p>
                                        <div class="flex flex-wrap items-center text-sm text-gray-500 mb-3">
                                            <span class="flex items-center mr-4">
                                                <i class="fas fa-dumbbell mr-1"></i>Difficulty: <?php echo ucfirst($program['difficulty']); ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-calendar-alt mr-1"></i>Assigned: <?php echo date('M d, Y', strtotime($program['created_at'])); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <a href="program_progress.php?id=<?php echo $program['program_id']; ?>" 
                                               class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                                                View Program Details
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10">
                                <i class="fas fa-clipboard-list text-gray-400 text-5xl mb-4"></i>
                                <p class="text-gray-500">No programs have been assigned by this coach yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
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