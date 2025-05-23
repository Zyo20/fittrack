<?php
session_start();

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'];

// Check if user ID is provided
if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$user_id = (int)$_GET['id'];

// Get unread messages count 
$unread_count = count_unread_messages($admin_id);

// Get user details
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);

if (mysqli_num_rows($user_result) == 0) {
    header("Location: users.php");
    exit();
}

$user = mysqli_fetch_assoc($user_result);

// Load additional user data based on their type
if ($user['user_type'] == 'customer') {
    // Get customer's coach
    $coach_query = "SELECT u.id, u.name FROM users u
                   JOIN coach_customer cc ON u.id = cc.coach_id
                   WHERE cc.customer_id = $user_id";
    $coach_result = mysqli_query($conn, $coach_query);
    $coach = mysqli_num_rows($coach_result) > 0 ? mysqli_fetch_assoc($coach_result) : null;
    
    // Get assigned programs
    $programs_query = "SELECT p.*, cp.status as program_status, cp.id as assignment_id, 
                      cp.created_at as assigned_date
                      FROM programs p
                      JOIN customer_programs cp ON p.id = cp.program_id
                      WHERE cp.customer_id = $user_id
                      ORDER BY cp.created_at DESC";
    $programs_result = mysqli_query($conn, $programs_query);
    
    // Load programs with steps and progress
    $programs = [];
    while ($program = mysqli_fetch_assoc($programs_result)) {
        $program_id = $program['id'];
        
        // Get program steps
        $program['steps'] = get_program_steps($program_id);
        
        // Get step progress
        $program['step_progress'] = get_step_progress($user_id, $program_id);
        
        // Calculate progress percentage
        $program['progress_percentage'] = calculate_program_progress($user_id, $program_id);
        
        // Count pending approvals
        $program['pending_approvals'] = count_pending_approval_steps($user_id, $program_id);
        
        $programs[] = $program;
    }
    
    // Get workout logs
    $logs_query = "SELECT * FROM workout_logs 
                  WHERE customer_id = $user_id
                  ORDER BY workout_date DESC
                  LIMIT 10";
    $logs_result = mysqli_query($conn, $logs_query);
    $workout_logs = [];
    while ($log = mysqli_fetch_assoc($logs_result)) {
        $workout_logs[] = $log;
    }
} else if ($user['user_type'] == 'coach') {
    // Get coach's customers
    $customers_query = "SELECT u.id, u.name, u.email, cc.assigned_date 
                       FROM users u
                       JOIN coach_customer cc ON u.id = cc.customer_id
                       WHERE cc.coach_id = $user_id
                       ORDER BY cc.assigned_date DESC";
    $customers_result = mysqli_query($conn, $customers_query);
    $customers = [];
    while ($customer = mysqli_fetch_assoc($customers_result)) {
        $customers[] = $customer;
    }
    
    // Get pending approvals
    $pending_query = "SELECT c.name as customer_name, c.id as customer_id, 
                     p.name as program_name, p.id as program_id,
                     ps.title as step_title, ps.id as step_id,
                     sp.id as progress_id
                     FROM step_progress sp
                     JOIN users c ON sp.customer_id = c.id
                     JOIN programs p ON sp.program_id = p.id
                     JOIN program_steps ps ON sp.step_id = ps.id
                     JOIN coach_customer cc ON c.id = cc.customer_id
                     WHERE cc.coach_id = $user_id
                     AND sp.status = 'pending_approval'
                     ORDER BY sp.updated_at DESC";
    $pending_result = mysqli_query($conn, $pending_query);
    $pending_approvals = [];
    while ($approval = mysqli_fetch_assoc($pending_result)) {
        $pending_approvals[] = $approval;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - OpFit Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .collapse {
            transition: all 0.2s ease-in-out;
        }
        .rotate-180 {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <nav class="bg-gray-800 text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap justify-between items-center">
                <a class="text-xl font-bold" href="dashboard.php">OpFit Admin</a>
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
                            <a class="text-white font-medium block py-2" href="users.php">Users</a>
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
                    <div class="relative mt-4 md:mt-0 md:ml-4">
                        <button id="userDropdown" class="flex items-center text-gray-300 hover:text-white py-2">
                            <?php echo $admin_name; ?>
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
        <div class="flex flex-wrap -mx-2 mb-4">
            <div class="w-full md:w-1/2 px-2">
                <h1 class="text-2xl font-bold">User Details</h1>
            </div>
            <div class="w-full md:w-1/2 px-2 flex justify-end space-x-2">
                <a href="users.php" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Users
                </a>
                <a href="edit_user.php?id=<?php echo $user_id; ?>" class="bg-amber-500 hover:bg-amber-600 text-white font-medium py-2 px-4 rounded flex items-center">
                    <i class="fas fa-edit mr-2"></i> Edit User
                </a>
            </div>
        </div>
        
        <div class="flex flex-wrap -mx-2">
            <div class="w-full md:w-1/3 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">User Information</h5>
                    </div>
                    <div class="p-4">
                        <p class="py-1"><span class="font-semibold">ID:</span> <?php echo $user['id']; ?></p>
                        <p class="py-1"><span class="font-semibold">Name:</span> <?php echo $user['name']; ?></p>
                        <p class="py-1"><span class="font-semibold">Email:</span> <?php echo $user['email']; ?></p>
                        <p class="py-1">
                            <span class="font-semibold">User Type:</span> 
                            <?php if ($user['user_type'] == 'customer'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Customer</span>
                            <?php elseif ($user['user_type'] == 'coach'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Coach</span>
                            <?php elseif ($user['user_type'] == 'admin'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Admin</span>
                            <?php endif; ?>
                        </p>
                        <p class="py-1"><span class="font-semibold">Joined:</span> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                        <p class="py-1"><span class="font-semibold">Last Updated:</span> <?php echo date('F d, Y', strtotime($user['updated_at'])); ?></p>
                        
                        <?php if ($user['user_type'] == 'customer' && isset($coach)): ?>
                            <p class="py-1"><span class="font-semibold">Assigned Coach:</span> <a href="user_details.php?id=<?php echo $coach['id']; ?>" class="text-blue-600 hover:text-blue-800"><?php echo $coach['name']; ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($user['user_type'] == 'coach' && isset($customers)): ?>
                <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Assigned Customers</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($customers) > 0): ?>
                            <div class="space-y-2">
                                <?php foreach ($customers as $customer): ?>
                                    <a href="user_details.php?id=<?php echo $customer['id']; ?>" class="block p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition duration-150">
                                        <div class="flex justify-between items-center">
                                            <h6 class="text-sm font-medium"><?php echo $customer['name']; ?></h6>
                                            <span class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($customer['assigned_date'])); ?></span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo $customer['email']; ?></p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center">No customers assigned to this coach.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
                    <div class="bg-amber-500 text-white px-4 py-3">
                        <h5 class="font-medium">Pending Approvals</h5>
                    </div>
                    <div class="p-4">
                        <?php if (isset($pending_approvals) && count($pending_approvals) > 0): ?>
                            <div class="space-y-2">
                                <?php foreach ($pending_approvals as $approval): ?>
                                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-md">
                                        <h6 class="text-sm font-medium mb-1">
                                            <i class="fas fa-user mr-2 text-amber-500"></i> 
                                            <a href="user_details.php?id=<?php echo $approval['customer_id']; ?>" class="text-blue-600 hover:text-blue-800"><?php echo $approval['customer_name']; ?></a>
                                        </h6>
                                        <p class="text-xs text-gray-600 mb-1">
                                            <i class="fas fa-dumbbell mr-2 text-amber-500"></i> 
                                            Program: <?php echo $approval['program_name']; ?>
                                        </p>
                                        <p class="text-xs text-gray-600 mb-1">
                                            <i class="fas fa-tasks mr-2 text-amber-500"></i> 
                                            Step: <?php echo $approval['step_title']; ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center">No pending approvals.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="w-full md:w-2/3 px-2">
                <?php if ($user['user_type'] == 'customer'): ?>
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
                        <div class="bg-blue-600 text-white px-4 py-3">
                            <h5 class="font-medium">Assigned Programs</h5>
                        </div>
                        <div class="p-4">
                            <?php if (count($programs) > 0): ?>
                                <div class="space-y-4">
                                    <?php foreach ($programs as $index => $program): ?>
                                        <div class="border-2 border-gray-300 rounded-lg overflow-hidden bg-white shadow-md hover:shadow-lg transition-shadow duration-300">
                                            <!-- Program header - always visible -->
                                            <div class="border-b-2 border-gray-300 bg-blue-50 px-4 py-4">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex-grow">
                                                        <span class="font-bold text-lg text-blue-800"><?php echo $program['name']; ?></span>
                                                        <span class="ml-2 <?php 
                                                            if ($program['program_status'] == 'completed') echo 'bg-green-100 text-green-800 border border-green-300';
                                                            else if ($program['program_status'] == 'approved') echo 'bg-blue-100 text-blue-800 border border-blue-300';
                                                            else if ($program['program_status'] == 'rejected') echo 'bg-red-100 text-red-800 border border-red-300';
                                                            else echo 'bg-gray-100 text-gray-800 border border-gray-300';
                                                        ?> px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                            <?php echo ucfirst($program['program_status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="w-32 flex items-center ml-3 bg-white p-2 rounded-lg border border-gray-200">
                                                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $program['progress_percentage']; ?>%"></div>
                                                        </div>
                                                        <span class="text-xs font-bold text-blue-800"><?php echo $program['progress_percentage']; ?>%</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Program content - directly visible -->
                                            <div class="p-5 bg-white">
                                                <div class="mb-5">
                                                    <h6 class="font-bold text-gray-800 mb-3 border-b pb-2">Program Details</h6>
                                                    <p class="text-gray-700 mb-3 leading-relaxed"><?php echo $program['description']; ?></p>
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors duration-200">
                                                            <p class="text-sm text-gray-700"><span class="font-bold text-gray-800">Difficulty:</span> <?php echo ucfirst($program['difficulty']); ?></p>
                                                        </div>
                                                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors duration-200">
                                                            <p class="text-sm text-gray-700"><span class="font-bold text-gray-800">Duration:</span> <?php echo $program['duration']; ?> weeks</p>
                                                        </div>
                                                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors duration-200">
                                                            <p class="text-sm text-gray-700"><span class="font-bold text-gray-800">Assigned:</span> <?php echo date('F d, Y', strtotime($program['assigned_date'])); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($program['pending_approvals'] > 0): ?>
                                                    <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-md mb-5 shadow-sm">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-exclamation-triangle text-amber-500 mr-2 text-lg"></i>
                                                            <p class="text-sm font-medium text-amber-700">This program has <?php echo $program['pending_approvals']; ?> step(s) awaiting coach approval</p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Program Steps Section -->
                                                <div class="mt-6 border-t-2 border-gray-200 pt-5">
                                                    <div class="flex items-center mb-4">
                                                        <i class="fas fa-list-ol text-blue-600 mr-2"></i>
                                                        <h6 class="font-bold text-blue-800 text-lg">Program Steps (<?php echo count($program['steps']); ?>)</h6>
                                                    </div>
                                                    
                                                    <?php if (empty($program['steps'])): ?>
                                                        <div class="p-4 bg-red-50 border border-red-200 rounded-md text-red-700 shadow-sm">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                                                                <p>No steps found for this program.</p>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="space-y-5">
                                                            <?php foreach ($program['steps'] as $step): ?>
                                                                <?php 
                                                                    $step_status = 'pending';
                                                                    $step_notes = '';
                                                                    $completion_date = null;
                                                                    
                                                                    if (isset($program['step_progress'][$step['id']])) {
                                                                        $progress = $program['step_progress'][$step['id']];
                                                                        $step_status = $progress['status'];
                                                                        $step_notes = $progress['notes'];
                                                                        $completion_date = $progress['completion_date'];
                                                                    }
                                                                    
                                                                    // Set style based on status
                                                                    if ($step_status == 'completed') {
                                                                        $status_class = 'bg-green-50 border-green-300';
                                                                        $status_icon = '<i class="fas fa-check-circle text-green-500 mr-2 text-lg"></i>';
                                                                        $status_text = 'Completed';
                                                                        $badge_class = 'bg-green-100 text-green-800 border border-green-300';
                                                                    } else if ($step_status == 'in_progress') {
                                                                        $status_class = 'bg-blue-50 border-blue-300';
                                                                        $status_icon = '<i class="fas fa-spinner fa-spin text-blue-500 mr-2 text-lg"></i>';
                                                                        $status_text = 'In Progress';
                                                                        $badge_class = 'bg-blue-100 text-blue-800 border border-blue-300';
                                                                    } else if ($step_status == 'pending_approval') {
                                                                        $status_class = 'bg-amber-50 border-amber-300';
                                                                        $status_icon = '<i class="fas fa-hourglass-half text-amber-500 mr-2 text-lg"></i>';
                                                                        $status_text = 'Awaiting Approval';
                                                                        $badge_class = 'bg-amber-100 text-amber-800 border border-amber-300';
                                                                    } else {
                                                                        $status_class = 'bg-gray-50 border-gray-300';
                                                                        $status_icon = '<i class="far fa-circle text-gray-400 mr-2 text-lg"></i>';
                                                                        $status_text = 'Pending';
                                                                        $badge_class = 'bg-gray-100 text-gray-800 border border-gray-300';
                                                                    }
                                                                ?>
                                                                
                                                                <div class="p-5 border-2 rounded-lg shadow-sm <?php echo $status_class; ?> hover:shadow-md transition-shadow duration-200">
                                                                    <div class="flex justify-between items-center border-b pb-3 mb-3">
                                                                        <h6 class="text-base font-bold flex items-center">
                                                                            <?php echo $status_icon; ?> 
                                                                            <span class="mr-2 text-blue-800">Step <?php echo $step['step_number']; ?>:</span> <?php echo $step['title']; ?>
                                                                        </h6>
                                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $badge_class; ?>">
                                                                            <?php echo $status_text; ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="mt-3 text-sm text-gray-700 leading-relaxed"><?php echo nl2br($step['description']); ?></div>
                                                                    <div class="mt-3 flex flex-wrap gap-3">
                                                                        <div class="text-xs bg-white px-3 py-2 rounded-md border border-gray-200 text-gray-700">
                                                                            <i class="far fa-clock text-blue-500 mr-1"></i>
                                                                            <span class="font-bold">Recommended:</span> <?php echo $step['duration']; ?>
                                                                        </div>
                                                                        
                                                                        <?php if ($completion_date): ?>
                                                                            <div class="text-xs bg-white px-3 py-2 rounded-md border border-gray-200 text-gray-700">
                                                                                <i class="fas fa-calendar-check text-green-500 mr-1"></i> 
                                                                                <span class="font-bold">Completed:</span> <?php echo date('M d, Y', strtotime($completion_date)); ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    
                                                                    <?php if ($step_notes): ?>
                                                                        <div class="mt-4 p-3 bg-white rounded-md border border-gray-200 text-sm">
                                                                            <div class="flex items-center mb-2">
                                                                                <i class="fas fa-comment-dots text-blue-500 mr-2"></i>
                                                                                <span class="font-bold text-blue-800">Coach Notes:</span>
                                                                            </div>
                                                                            <p class="text-gray-700 pl-6"><?php echo nl2br($step_notes); ?></p>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 text-center">No programs assigned to this user.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="bg-blue-600 text-white px-4 py-3">
                            <h5 class="font-medium">Recent Workout Logs</h5>
                        </div>
                        <div class="p-4">
                            <?php if (isset($workout_logs) && count($workout_logs) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($workout_logs as $log): ?>
                                                <?php 
                                                    // Get program name
                                                    $program_name_query = "SELECT name FROM programs WHERE id = " . $log['program_id'];
                                                    $program_name_result = mysqli_query($conn, $program_name_query);
                                                    $program_name = mysqli_fetch_assoc($program_name_result)['name'];
                                                ?>
                                                <tr class="hover:bg-gray-50 transition duration-150">
                                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700"><?php echo date('M d, Y', strtotime($log['workout_date'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700"><?php echo $program_name; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700"><?php echo $log['duration']; ?> min</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700"><?php echo $log['notes'] ? $log['notes'] : '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 text-center">No workout logs found for this user.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded');
            
            // Basic mobile menu toggle
            const navbarToggle = document.getElementById('navbarToggle');
            const navbarMenu = document.getElementById('navbarMenu');
            if (navbarToggle) {
                navbarToggle.addEventListener('click', function() {
                    navbarMenu.classList.toggle('hidden');
                });
            }
            
            // User dropdown toggle
            const userDropdown = document.getElementById('userDropdown');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            if (userDropdown && userDropdownMenu) {
                userDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdownMenu.classList.toggle('hidden');
                });
                
                document.addEventListener('click', function() {
                    if (!userDropdownMenu.classList.contains('hidden')) {
                        userDropdownMenu.classList.add('hidden');
                    }
                });
            }
            
            // Very simple toggle function that just toggles display
            window.toggleCollapse = function(id) {
                console.log('Toggle called for: ' + id);
                const element = document.getElementById(id);
                
                if (!element) {
                    console.error('Element not found: ' + id);
                    return;
                }
                
                // Close all other collapses
                document.querySelectorAll('.collapse').forEach(function(collapse) {
                    if (collapse.id !== id) {
                        collapse.style.display = 'none';
                    }
                });
                
                // Toggle this one
                if (element.style.display === 'none' || element.style.display === '') {
                    element.style.display = 'block';
                    console.log('Showing ' + id);
                } else {
                    element.style.display = 'none';
                    console.log('Hiding ' + id);
                }
            };
            
            // Debug - check all collapse elements
            const collapses = document.querySelectorAll('.collapse');
            console.log('Found ' + collapses.length + ' collapse elements');
            
            // Force first one to be visible by default and rest hidden
            collapses.forEach(function(collapse, index) {
                if (index === 0) {
                    collapse.style.display = 'block';
                    console.log('Set first collapse visible: ' + collapse.id);
                } else {
                    collapse.style.display = 'none';
                }
            });
            
            // Function to directly show steps in emergency mode
            window.showStepsDirectly = function(id) {
                const directStepsDiv = document.getElementById(id);
                if (directStepsDiv) {
                    // Toggle visibility
                    if (directStepsDiv.style.display === 'none' || directStepsDiv.style.display === '') {
                        directStepsDiv.style.display = 'block';
                    } else {
                        directStepsDiv.style.display = 'none';
                    }
                } else {
                    console.error('Direct steps container not found:', id);
                }
            };
        });
    </script>
</body>
</html> 