<?php
session_start();

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

// Get coach's customers
$customers = get_coach_customers($user_id);

// Get progress data for each customer
$customer_progress = [];
foreach ($customers as $customer) {
    $customer_id = $customer['customer_id'];
    $progress_query = "SELECT * FROM progress 
                      WHERE customer_id = $customer_id 
                      ORDER BY record_date DESC 
                      LIMIT 1";
    $progress_result = mysqli_query($conn, $progress_query);
    
    if (mysqli_num_rows($progress_result) > 0) {
        $customer_progress[$customer_id] = mysqli_fetch_assoc($progress_result);
    } else {
        $customer_progress[$customer_id] = null;
    }
    
    // Get active programs for this customer
    $programs_query = "SELECT cp.*, p.name as program_name, p.difficulty
                     FROM customer_programs cp
                     JOIN programs p ON cp.program_id = p.id
                     WHERE cp.customer_id = $customer_id
                     AND cp.status IN ('approved', 'pending')
                     ORDER BY cp.created_at DESC";
    $programs_result = mysqli_query($conn, $programs_query);
    
    $customer['programs'] = [];
    while ($row = mysqli_fetch_assoc($programs_result)) {
        // Check for pending approval steps in this program
        $program_id = $row['program_id'];
        
        // Count pending approvals
        $pending_query = "SELECT COUNT(*) as pending_count 
                         FROM step_progress sp
                         JOIN program_steps ps ON sp.step_id = ps.id
                         WHERE sp.customer_id = $customer_id 
                         AND ps.program_id = $program_id 
                         AND sp.status = 'pending_approval'";
        $pending_result = mysqli_query($conn, $pending_query);
        $pending_row = mysqli_fetch_assoc($pending_result);
        $row['pending_approvals'] = (int)$pending_row['pending_count'];
        
        $customer['programs'][] = $row;
    }
    
    // Store updated customer data
    $customers_with_data[] = $customer;
}

// If customers_with_data is set, replace customers array
if (isset($customers_with_data)) {
    $customers = $customers_with_data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Customers - OpFit Coach</title>
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
                            <a class="text-white font-medium block py-2" href="customers.php">My Customers</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">Programs</a>
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
                <h1 class="text-2xl font-bold mb-4">My Customers</h1>
            </div>
        </div>
        
        <div class="flex flex-wrap">
            <div class="w-full">
                <div class="bg-white rounded-lg shadow-md mb-4">
                    <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Customer List</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($customers) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Weight</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Height</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active Programs</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Since</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($customers as $customer): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $customer['name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $customer['email']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php 
                                                    if (isset($customer_progress[$customer['customer_id']]) && $customer_progress[$customer['customer_id']]) {
                                                        echo $customer_progress[$customer['customer_id']]['weight'] . ' kg';
                                                    } else {
                                                        echo 'No data';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php 
                                                    if (isset($customer_progress[$customer['customer_id']]) && $customer_progress[$customer['customer_id']]) {
                                                        echo $customer_progress[$customer['customer_id']]['height'] . ' cm';
                                                    } else {
                                                        echo 'No data';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if (!empty($customer['programs'])): ?>
                                                        <?php foreach($customer['programs'] as $program): ?>
                                                            <span class="inline-block px-2 py-1 text-xs rounded-full mb-1 <?php echo $program['status'] == 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                                <?php echo $program['program_name']; ?>
                                                                (<?php echo ucfirst($program['status']); ?>)
                                                                <?php if ($program['pending_approvals'] > 0): ?>
                                                                    <span class="inline-block px-1.5 py-0.5 bg-red-600 text-white text-xs rounded-full ml-1"><?php echo $program['pending_approvals']; ?> approval<?php echo $program['pending_approvals'] > 1 ? 's' : ''; ?> pending</span>
                                                                <?php endif; ?>
                                                            </span><br>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-500">No active programs</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($customer['assigned_date'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <a href="customer_details.php?id=<?php echo $customer['customer_id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-xs py-1 px-2 rounded inline-flex items-center">
                                                        <i class="fas fa-eye mr-1"></i> View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">You don't have any customers assigned yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
        });
    </script>
</body>
</html> 