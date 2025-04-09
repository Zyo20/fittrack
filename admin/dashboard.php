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

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get unread messages count 
$unread_count = count_unread_messages($user_id);

// Initialize arrays for dashboard data
$dashboard_data = [
    'customers' => 0,
    'coaches' => 0,
    'programs' => 0,
    'pending' => 0,
    'recent_users' => [],
    'recent_requests' => []
];

// Use prepared statements and transactions for better performance and security
mysqli_autocommit($conn, false);
try {
    // Get counts for dashboard widgets
    $count_queries = [
        "SELECT COUNT(*) as count FROM users WHERE user_type = ?",
        "SELECT COUNT(*) as count FROM programs",
        "SELECT COUNT(*) as count FROM customer_programs WHERE status = ?"
    ];
    
    // Customers count
    $stmt = mysqli_prepare($conn, $count_queries[0]);
    mysqli_stmt_bind_param($stmt, "s", $user_type);
    $user_type = 'customer';
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dashboard_data['customers'] = mysqli_fetch_assoc($result)['count'];
    
    // Coaches count
    $user_type = 'coach';
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dashboard_data['coaches'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
    
    // Programs count
    $stmt = mysqli_prepare($conn, $count_queries[1]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dashboard_data['programs'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
    
    // Pending requests count
    $stmt = mysqli_prepare($conn, $count_queries[2]);
    mysqli_stmt_bind_param($stmt, "s", $status);
    $status = 'pending';
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dashboard_data['pending'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
    
    // Get recent users
    $query_recent_users = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
    $stmt = mysqli_prepare($conn, $query_recent_users);
    mysqli_stmt_execute($stmt);
    $result_recent_users = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result_recent_users)) {
        $dashboard_data['recent_users'][] = $row;
    }
    mysqli_stmt_close($stmt);
    
    // Get recent program requests
    $query_recent_requests = "SELECT cp.*, u.name as customer_name, p.name as program_name 
                          FROM customer_programs cp 
                          JOIN users u ON cp.customer_id = u.id 
                          JOIN programs p ON cp.program_id = p.id 
                          ORDER BY cp.created_at DESC LIMIT 5";
    $stmt = mysqli_prepare($conn, $query_recent_requests);
    mysqli_stmt_execute($stmt);
    $result_recent_requests = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result_recent_requests)) {
        $dashboard_data['recent_requests'][] = $row;
    }
    mysqli_stmt_close($stmt);
    
    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error in admin dashboard: " . $e->getMessage());
    // You could set an error message to display to the user here
}
mysqli_autocommit($conn, true);

// Extract data for easier access in the view
$customer_count = $dashboard_data['customers'];
$coach_count = $dashboard_data['coaches'];
$program_count = $dashboard_data['programs'];
$pending_count = $dashboard_data['pending'];
$recent_users = $dashboard_data['recent_users'];
$recent_requests = $dashboard_data['recent_requests'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OpFit</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <style>
        /* Custom styles for the dashboard widgets */
        .col-md-3 a:hover {
            text-decoration: none;
        }
        
        .col-md-3 a:hover .card {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .col-md-3 .card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .col-md-3 a:hover .card-footer {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .col-md-3 a:hover .fa-angle-right {
            transform: translateX(3px);
            transition: transform 0.3s ease;
        }
        
        .fa-angle-right {
            transition: transform 0.3s ease;
        }

        /* Session timeout modal styles */
        #sessionTimeoutModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 90%;
        }
    </style>
    <script>
        // Session timeout handling
        const sessionTimeout = <?php echo $session_timeout; ?> * 1000; // Convert to milliseconds
        const warningTime = 60000; // Show warning 1 minute before timeout
        let timeoutWarningShown = false;

        function showTimeoutWarning() {
            const modal = document.getElementById('sessionTimeoutModal');
            modal.style.display = 'block';
            timeoutWarningShown = true;
        }

        function extendSession() {
            const modal = document.getElementById('sessionTimeoutModal');
            modal.style.display = 'none';
            timeoutWarningShown = false;
            window.location.reload();
        }

        function logout() {
            window.location.href = '../logout.php';
        }

        // Set up the timeout warning
        setTimeout(function() {
            showTimeoutWarning();
        }, sessionTimeout - warningTime);

        // Auto-logout after session timeout
        setTimeout(function() {
            if (!timeoutWarningShown) {
                window.location.href = '../login.php?timeout=1';
            }
        }, sessionTimeout);
    </script>
</head>
<body class="bg-gray-100 text-gray-800">
    <!-- Session Timeout Modal -->
    <div id="sessionTimeoutModal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">Session Timeout Warning</h3>
            <p class="mb-4">Your session will expire in 1 minute due to inactivity. Would you like to extend your session?</p>
            <div class="flex justify-end space-x-4">
                <button onclick="logout()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded">Logout</button>
                <button onclick="extendSession()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">Extend Session</button>
            </div>
        </div>
    </div>

    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">OpFit Admin</a>
                <button class="md:hidden" type="button" id="navbarToggle" onclick="toggleMobileMenu()"> 
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden w-full md:flex flex-col md:flex-row md:w-auto mt-4 md:mt-0 items-center" id="navbarMenu">
                    <ul class="flex flex-col md:flex-row md:mr-6 space-y-2 md:space-y-0 md:space-x-6">
                        <li>
                            <a class="text-white font-medium block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="users.php">Users</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">Programs</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="assignments.php">Coach Assignments</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="reports.php">Reports</a>
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
                    <div class="hidden md:block">
                        <div class="relative">
                            <div class="group">
                                <button class="flex items-center text-sm px-4 py-2 leading-none rounded text-white hover:text-white hover:bg-gray-700 focus:outline-none" id="userDropdown">
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
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-4">
        <div class="flex flex-wrap -mx-2">
            <div class="w-full px-2">
                <h1 class="text-2xl font-bold mb-4">Admin Dashboard</h1>
            </div>
        </div>
        
        <!-- Stats Widgets -->
        <div class="flex flex-wrap -mx-2 mb-4">
            <!-- Feature Announcements -->
            <?php display_feature_announcements(); ?>
            
            <div class="w-full md:w-1/4 px-2 mb-3">
                <a href="users.php?type=customer" class="block">
                    <div class="bg-blue-600 text-white rounded-lg shadow-md h-full transition-transform hover:translate-y-[-5px] hover:shadow-lg">
                        <div class="p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h5 class="text-lg font-medium">Customers</h5>
                                    <h2 class="text-2xl font-bold mb-0"><?php echo htmlspecialchars($customer_count); ?></h2>
                                </div>
                                <i class="fas fa-users text-3xl opacity-50"></i>
                            </div>
                        </div>
                        <div class="px-4 py-2 border-t border-blue-700 flex items-center justify-between">
                            <span class="text-white">View Details</span>
                            <i class="fas fa-angle-right transition-transform group-hover:translate-x-1"></i>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="w-full md:w-1/4 px-2 mb-3">
                <a href="users.php?type=coach" class="block">
                    <div class="bg-green-600 text-white rounded-lg shadow-md h-full transition-transform hover:translate-y-[-5px] hover:shadow-lg">
                        <div class="p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h5 class="text-lg font-medium">Coaches</h5>
                                    <h2 class="text-2xl font-bold mb-0"><?php echo htmlspecialchars($coach_count); ?></h2>
                                </div>
                                <i class="fas fa-user-tie text-3xl opacity-50"></i>
                            </div>
                        </div>
                        <div class="px-4 py-2 border-t border-green-700 flex items-center justify-between">
                            <span class="text-white">View Details</span>
                            <i class="fas fa-angle-right transition-transform group-hover:translate-x-1"></i>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="w-full md:w-1/4 px-2 mb-3">
                <a href="programs.php" class="block">
                    <div class="bg-blue-400 text-white rounded-lg shadow-md h-full transition-transform hover:translate-y-[-5px] hover:shadow-lg">
                        <div class="p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h5 class="text-lg font-medium">Programs</h5>
                                    <h2 class="text-2xl font-bold mb-0"><?php echo htmlspecialchars($program_count); ?></h2>
                                </div>
                                <i class="fas fa-dumbbell text-3xl opacity-50"></i>
                            </div>
                        </div>
                        <div class="px-4 py-2 border-t border-blue-500 flex items-center justify-between">
                            <span class="text-white">View Details</span>
                            <i class="fas fa-angle-right transition-transform group-hover:translate-x-1"></i>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="w-full md:w-1/4 px-2 mb-3">
                <a href="requests.php?status=pending" class="block">
                    <div class="bg-yellow-500 text-white rounded-lg shadow-md h-full transition-transform hover:translate-y-[-5px] hover:shadow-lg">
                        <div class="p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h5 class="text-lg font-medium">Pending Requests</h5>
                                    <h2 class="text-2xl font-bold mb-0"><?php echo htmlspecialchars($pending_count); ?></h2>
                                </div>
                                <i class="fas fa-clock text-3xl opacity-50"></i>
                            </div>
                        </div>
                        <div class="px-4 py-2 border-t border-yellow-600 flex items-center justify-between">
                            <span class="text-white">View Details</span>
                            <i class="fas fa-angle-right transition-transform group-hover:translate-x-1"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        
        <div class="flex flex-wrap -mx-2">
            <!-- Recent Users -->
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-md h-full">
                    <div class="bg-blue-600 text-white px-4 py-3 rounded-t-lg">
                        <h5 class="font-medium mb-0">Recent Users</h5>
                    </div>
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-2 text-left">Name</th>
                                        <th class="px-4 py-2 text-left">Email</th>
                                        <th class="px-4 py-2 text-left">Type</th>
                                        <th class="px-4 py-2 text-left">Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 border-t">
                                                <a href="user_details.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="text-blue-600 hover:text-blue-800"><?php echo htmlspecialchars($user['name']); ?></a>
                                            </td>
                                            <td class="px-4 py-2 border-t"><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td class="px-4 py-2 border-t">
                                                <?php 
                                                $badge_class = 'bg-blue-500';
                                                if ($user['user_type'] == 'coach') $badge_class = 'bg-green-500';
                                                elseif ($user['user_type'] == 'admin') $badge_class = 'bg-red-500';
                                                ?>
                                                <span class="<?php echo $badge_class; ?> text-white text-xs px-2 py-1 rounded-full uppercase">
                                                    <?php echo ucfirst(htmlspecialchars($user['user_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 border-t"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-4">
                            <a href="users.php" class="inline-block bg-white hover:bg-gray-100 text-blue-700 font-medium py-1 px-4 border border-blue-500 rounded text-sm">View All Users</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Program Requests -->
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-md h-full">
                    <div class="bg-green-600 text-white px-4 py-3 rounded-t-lg">
                        <h5 class="font-medium mb-0">Recent Program Requests</h5>
                    </div>
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-2 text-left">Customer</th>
                                        <th class="px-4 py-2 text-left">Program</th>
                                        <th class="px-4 py-2 text-left">Status</th>
                                        <th class="px-4 py-2 text-left">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_requests as $request): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 border-t"><?php echo htmlspecialchars($request['customer_name']); ?></td>
                                            <td class="px-4 py-2 border-t"><?php echo htmlspecialchars($request['program_name']); ?></td>
                                            <td class="px-4 py-2 border-t">
                                                <?php 
                                                $badge_class = 'bg-yellow-500';
                                                if ($request['status'] == 'approved') $badge_class = 'bg-green-500';
                                                elseif ($request['status'] == 'rejected') $badge_class = 'bg-red-500';
                                                elseif ($request['status'] == 'completed') $badge_class = 'bg-blue-500';
                                                ?>
                                                <span class="<?php echo $badge_class; ?> text-white text-xs px-2 py-1 rounded-full uppercase">
                                                    <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 border-t"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-4">
                            <a href="requests.php" class="inline-block bg-white hover:bg-gray-100 text-green-700 font-medium py-1 px-4 border border-green-500 rounded text-sm">View All Requests</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script src="../js/tailwind-utilities.js"></script>
    <script>
        // Toggle mobile navigation function
        function toggleMobileMenu() {
            console.log('Toggle function called directly');
            const menu = document.getElementById('navbarMenu');
            if (menu) {
                if (menu.classList.contains('hidden')) {
                    menu.classList.remove('hidden');
                    menu.classList.add('block');
                } else {
                    menu.classList.add('hidden');
                    menu.classList.remove('block');
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Toggle mobile navigation
            const navbarToggle = document.getElementById('navbarToggle');
            const navbarMenu = document.getElementById('navbarMenu');
            
            console.log('Toggle button exists:', !!navbarToggle);
            console.log('Menu exists:', !!navbarMenu);
            
            if (navbarToggle) {
                navbarToggle.addEventListener('click', function(e) {
                    console.log('Toggle button clicked');
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (navbarMenu) {
                        console.log('Current menu class:', navbarMenu.className);
                        if (navbarMenu.classList.contains('hidden')) {
                            navbarMenu.classList.remove('hidden');
                            navbarMenu.classList.add('block');
                        } else {
                            navbarMenu.classList.add('hidden');
                            navbarMenu.classList.remove('block');
                        }
                        console.log('After toggle class:', navbarMenu.className);
                    }
                });
            }

            // Make widget cards clickable
            document.querySelectorAll('.w-full.md\\:w-1\\/4 .bg-blue-600, .w-full.md\\:w-1\\/4 .bg-green-600, .w-full.md\\:w-1\\/4 .bg-blue-400, .w-full.md\\:w-1\\/4 .bg-yellow-500').forEach(function(card) {
                card.addEventListener('click', function() {
                    const link = this.closest('a');
                    if (link && link.href) {
                        window.location.href = link.href;
                    }
                });
            });
        });
    </script>
</body>
</html> 