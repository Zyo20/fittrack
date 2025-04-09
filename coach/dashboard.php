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
include_once '../includes/feature_announcements.php';

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

// Handle program approval/rejection
if (isset($_POST['update_program_status'])) {
    $program_id = (int)$_POST['program_id'];
    $customer_id = (int)$_POST['customer_id'];
    $status = $_POST['status'];
    
    if (update_program_status($program_id, $customer_id, $status)) {
        $status_success = "Program status updated successfully";
    } else {
        $status_error = "Failed to update program status";
    }
}

// Get pending program requests
$pending_requests_query = "SELECT cp.id, cp.customer_id, cp.program_id, cp.created_at, 
                          u.name as customer_name, p.name as program_name
                          FROM customer_programs cp
                          JOIN users u ON cp.customer_id = u.id
                          JOIN programs p ON cp.program_id = p.id
                          JOIN coach_customer cc ON cp.customer_id = cc.customer_id
                          WHERE cc.coach_id = $user_id AND cp.status = 'pending'
                          ORDER BY cp.created_at DESC";
$pending_requests_result = mysqli_query($conn, $pending_requests_query);
$pending_requests = [];
while ($row = mysqli_fetch_assoc($pending_requests_result)) {
    $pending_requests[] = $row;
}

// Get recent customer progress
$recent_progress_query = "SELECT p.*, u.name as customer_name
                         FROM progress p
                         JOIN users u ON p.customer_id = u.id
                         JOIN coach_customer cc ON p.customer_id = cc.customer_id
                         WHERE cc.coach_id = $user_id
                         ORDER BY p.record_date DESC
                         LIMIT 5";
$recent_progress_result = mysqli_query($conn, $recent_progress_query);
$recent_progress = [];
while ($row = mysqli_fetch_assoc($recent_progress_result)) {
    $recent_progress[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Dashboard - OpFit Coach</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 text-gray-800">

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
                            <a class="text-white font-medium block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="customers.php">My Customers</a>
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
        <div class="flex flex-wrap -mx-2">
            <div class="w-full px-2">
                <h1 class="text-2xl font-bold mb-4">Coach Dashboard</h1>
            </div>
        </div>
        
        <!-- Stats Widgets -->
        <div class="flex flex-wrap -mx-2 mb-4">
            <!-- Feature Announcements -->
            <?php display_feature_announcements(); ?>
            
            <div class="w-full md:w-1/3 px-2 mb-4">
                <div class="bg-blue-600 text-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h6 class="text-sm font-semibold uppercase">Total Customers</h6>
                            <h2 class="text-3xl font-bold"><?php echo count($customers); ?></h2>
                        </div>
                        <i class="fas fa-users text-4xl opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-1/3 px-2 mb-4">
                <div class="bg-yellow-500 text-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h6 class="text-sm font-semibold uppercase">Pending Requests</h6>
                            <h2 class="text-3xl font-bold"><?php echo count($pending_requests); ?></h2>
                        </div>
                        <i class="fas fa-clipboard-list text-4xl opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-1/3 px-2 mb-4">
                <div class="bg-green-600 text-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h6 class="text-sm font-semibold uppercase">Recent Progress Updates</h6>
                            <h2 class="text-3xl font-bold"><?php echo count($recent_progress); ?></h2>
                        </div>
                        <i class="fas fa-chart-line text-4xl opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex flex-wrap -mx-2">
            <!-- Pending Program Requests -->
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-md h-full">
                    <div class="bg-yellow-500 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Pending Program Requests</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($pending_requests) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($pending_requests as $request): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $request['customer_name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $request['program_name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <form action="" method="post" class="inline">
                                                        <input type="hidden" name="program_id" value="<?php echo $request['program_id']; ?>">
                                                        <input type="hidden" name="customer_id" value="<?php echo $request['customer_id']; ?>">
                                                        <input type="hidden" name="status" value="approved">
                                                        <button type="submit" name="update_program_status" class="bg-green-600 hover:bg-green-700 text-white text-xs py-1 px-2 rounded">Approve</button>
                                                    </form>
                                                    <form action="" method="post" class="inline ml-1">
                                                        <input type="hidden" name="program_id" value="<?php echo $request['program_id']; ?>">
                                                        <input type="hidden" name="customer_id" value="<?php echo $request['customer_id']; ?>">
                                                        <input type="hidden" name="status" value="rejected">
                                                        <button type="submit" name="update_program_status" class="bg-red-600 hover:bg-red-700 text-white text-xs py-1 px-2 rounded">Reject</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">No pending program requests.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Customer Progress -->
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-md h-full">
                    <div class="bg-green-600 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Recent Customer Progress</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($recent_progress) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Height</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($recent_progress as $progress): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $progress['customer_name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $progress['weight']; ?> kg</td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $progress['height']; ?> cm</td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($progress['record_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">No recent progress updates from your customers.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer List -->
        <div class="flex flex-wrap mb-4">
            <div class="w-full">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg flex justify-between items-center">
                        <h5 class="font-semibold">My Customers</h5>
                        <a href="customers.php" class="bg-white hover:bg-gray-100 text-blue-600 text-xs py-1 px-3 rounded">View All</a>
                    </div>
                    <div class="p-4">
                        <?php if (count($customers) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach (array_slice($customers, 0, 5) as $customer): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $customer['name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $customer['email']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($customer['assigned_date'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <a href="customer_details.php?id=<?php echo $customer['customer_id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-xs py-1 px-2 rounded inline-flex items-center">
                                                        <i class="fas fa-eye mr-1"></i> View
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

    <?php include_once '../includes/footer.php'; ?>

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
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('opacity-0');
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html> 