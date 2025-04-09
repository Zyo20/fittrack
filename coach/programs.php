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

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'coach') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get unread messages count 
$unread_count = count_unread_messages($user_id);

// Get all programs
$programs = get_all_programs();

// For each program, get the count of customers using it
foreach ($programs as &$program) {
    $program_id = $program['id'];
    
    // Get customers using this program
    $program_customers_query = "SELECT cp.*, u.name as customer_name, u.email as customer_email
                              FROM customer_programs cp
                              JOIN users u ON cp.customer_id = u.id
                              JOIN coach_customer cc ON cp.customer_id = cc.customer_id
                              WHERE cp.program_id = $program_id
                              AND cp.status = 'approved'
                              AND cc.coach_id = $user_id";
    $program_customers_result = mysqli_query($conn, $program_customers_query);
    
    $program['customers'] = [];
    $program['customer_count'] = mysqli_num_rows($program_customers_result);
    
    while ($row = mysqli_fetch_assoc($program_customers_result)) {
        $program['customers'][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programs - OpFit Coach</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">OpFit Coach</a>
                <button class="md:hidden" type="button" id="navbarToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden md:flex flex-col md:flex-row w-full md:w-auto mt-4 md:mt-0" id="navbarMenu">
                    <ul class="flex flex-col md:flex-row md:mr-6 space-y-2 md:space-y-0 md:space-x-4">
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="customers.php">My Customers</a>
                        </li>
                        <li>
                            <a class="text-white font-medium block py-2" href="programs.php">Programs</a>
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

    <div class="container mx-auto px-4 py-4">
        <div class="flex flex-wrap mb-4">
            <div class="w-full">
                <h1 class="text-2xl font-bold mb-4">Training Programs</h1>
            </div>
        </div>
        
        <div class="flex flex-wrap -mx-2">
            <?php foreach ($programs as $program): ?>
                <div class="w-full md:w-1/2 px-2 mb-4">
                    <div class="bg-white rounded-lg shadow-md h-full flex flex-col">
                        <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg">
                            <div class="flex justify-between items-center">
                                <h5 class="font-semibold"><?php echo $program['name']; ?></h5>
                                <span class="bg-white text-gray-800 px-2 py-1 text-xs rounded-full">
                                    <?php echo ucfirst($program['difficulty']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-4 flex-grow">
                            <p class="text-gray-700 mb-3"><?php echo $program['description']; ?></p>
                            <p class="mb-4"><strong>Duration:</strong> <?php echo $program['duration']; ?> weeks</p>
                            
                            <div class="flex justify-between items-center mb-4">
                                <button type="button" class="text-blue-600 border border-blue-600 hover:bg-blue-50 text-xs py-1 px-2 rounded inline-flex items-center" 
                                        onclick="openStepsModal(<?php echo $program['id']; ?>)">
                                    <i class="fas fa-list-ol mr-1"></i> View Step-by-Step Process
                                </button>
                                <span class="bg-gray-200 text-gray-700 px-2 py-1 text-xs rounded-full">
                                    <?php echo $program['customer_count']; ?> customers
                                </span>
                            </div>
                            
                            <h6 class="font-medium mb-2">
                                Customers Using This Program
                            </h6>
                            
                            <?php if ($program['customer_count'] > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($program['customers'] as $customer): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo $customer['customer_name']; ?></td>
                                                    <td class="px-3 py-2 whitespace-nowrap text-sm"><?php echo $customer['customer_email']; ?></td>
                                                    <td class="px-3 py-2 whitespace-nowrap">
                                                        <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                                    </td>
                                                    <td class="px-3 py-2 whitespace-nowrap">
                                                        <a href="customer_details.php?id=<?php echo $customer['customer_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500">No customers are currently using this program.</p>
                            <?php endif; ?>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 rounded-b-lg border-t border-gray-200">
                            <a href="assign_program.php?program_id=<?php echo $program['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded inline-flex items-center">
                                <i class="fas fa-user-plus mr-2"></i> Assign to Customer
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Program Steps Modal -->
                <div id="stepsModal<?php echo $program['id']; ?>" class="hidden fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex justify-center items-center">
                    <div class="bg-white rounded-lg shadow-xl max-w-4xl mx-auto w-full md:w-3/4 max-h-screen overflow-y-auto">
                        <div class="bg-blue-600 text-white px-4 py-3 flex justify-between items-center rounded-t-lg">
                            <h5 class="font-semibold flex items-center">
                                <i class="fas fa-list-ol mr-2"></i>
                                <?php echo $program['name']; ?> - Step-by-Step Process
                            </h5>
                            <button type="button" class="text-white hover:text-gray-200" onclick="closeStepsModal(<?php echo $program['id']; ?>)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="p-6">
                            <p class="text-lg mb-3"><?php echo nl2br($program['description']); ?></p>
                            <p class="mb-4"><strong>Difficulty:</strong> <?php echo ucfirst($program['difficulty']); ?></p>
                            <p class="mb-6"><strong>Duration:</strong> <?php echo $program['duration']; ?> weeks</p>
                            
                            <hr class="my-4 border-gray-200">
                            
                            <h5 class="font-medium mb-4">Program Steps</h5>
                            
                            <?php 
                            // Get steps for this program
                            $steps_query = "SELECT * FROM program_steps WHERE program_id = {$program['id']} ORDER BY step_number";
                            $steps_result = mysqli_query($conn, $steps_query);
                            
                            if (mysqli_num_rows($steps_result) > 0):
                            ?>
                                <div class="space-y-4">
                                    <?php while ($step = mysqli_fetch_assoc($steps_result)): ?>
                                        <div class="pl-6 border-l-2 border-gray-300 relative">
                                            <div class="absolute w-4 h-4 bg-white border-2 border-gray-300 rounded-full -left-[9px] top-1.5"></div>
                                            <h5 class="font-medium">
                                                Step <?php echo $step['step_number']; ?>: <?php echo $step['title']; ?>
                                            </h5>
                                            <p class="text-gray-700 mt-1"><?php echo nl2br($step['description']); ?></p>
                                            <?php if (!empty($step['duration'])): ?>
                                                <p class="text-gray-500 text-sm mt-1 flex items-center">
                                                    <i class="far fa-clock mr-1"></i> <?php echo $step['duration']; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4">
                                    <i class="fas fa-info-circle mr-2"></i> No steps defined for this program yet.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="bg-gray-100 px-6 py-4 flex justify-end rounded-b-lg">
                            <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded" onclick="closeStepsModal(<?php echo $program['id']; ?>)">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle mobile menu
            const navbarToggle = document.getElementById('navbarToggle');
            const navbarMenu = document.getElementById('navbarMenu');
            
            navbarToggle.addEventListener('click', function() {
                navbarMenu.classList.toggle('hidden');
            });
            
            // Toggle user dropdown
            const userDropdownButton = document.getElementById('userDropdown');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            
            userDropdownButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdownMenu.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userDropdownButton.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                    userDropdownMenu.classList.add('hidden');
                }
            });
            
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    // Close all modals
                    const modals = document.querySelectorAll('[id^="stepsModal"]');
                    modals.forEach(modal => {
                        modal.classList.add('hidden');
                    });
                }
            });
        });
        
        // Modal functions
        function openStepsModal(programId) {
            document.getElementById('stepsModal' + programId).classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
        
        function closeStepsModal(programId) {
            document.getElementById('stepsModal' + programId).classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    </script>
</body>
</html> 