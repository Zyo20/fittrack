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

// Get customer ID from URL
if (isset($_GET['id'])) {
    $customer_id = (int)$_GET['id'];
    
    // Verify this customer is assigned to the current coach
    $verify_query = "SELECT * FROM coach_customer 
                    WHERE coach_id = $user_id 
                    AND customer_id = $customer_id";
    $verify_result = mysqli_query($conn, $verify_query);
    
    if (mysqli_num_rows($verify_result) == 0) {
        header("Location: customers.php");
        exit();
    }
    
    // Get customer details
    $customer_query = "SELECT * FROM users WHERE id = $customer_id";
    $customer_result = mysqli_query($conn, $customer_query);
    $customer = mysqli_fetch_assoc($customer_result);
    
    // Get customer programs
    $programs = get_customer_programs($customer_id);
    
    // Get programs with steps and progress
    $programs_with_steps = [];
    foreach ($programs as $program) {
        $program_id = $program['program_id'];
        $program['steps'] = get_program_steps($program_id);
        $program['step_progress'] = get_step_progress($customer_id, $program_id);
        $program['progress_percentage'] = calculate_program_progress($customer_id, $program_id);
        $program['pending_approvals'] = count_pending_approval_steps($customer_id, $program_id);
        $programs_with_steps[] = $program;
    }
    $programs = $programs_with_steps;
    
    // Get customer progress history
    $progress_history = get_progress_history($customer_id);
    
    // Get customer workout logs
    $workout_logs_query = "SELECT wl.*, p.name as program_name
                          FROM workout_logs wl
                          JOIN programs p ON wl.program_id = p.id
                          WHERE wl.customer_id = $customer_id
                          ORDER BY wl.workout_date DESC
                          LIMIT 10";
    $workout_logs_result = mysqli_query($conn, $workout_logs_query);
    $workout_logs = [];
    while ($row = mysqli_fetch_assoc($workout_logs_result)) {
        $workout_logs[] = $row;
    }
    
} else {
    header("Location: customers.php");
    exit();
}

// Handle program status update
if (isset($_POST['update_program_status'])) {
    $program_id = (int)$_POST['program_id'];
    $status = $_POST['status'];
    
    if (update_program_status($program_id, $customer_id, $status)) {
        $status_success = "Program status updated successfully";
        // Refresh programs list
        $programs = get_customer_programs($customer_id);
        
        // Reload programs with steps and progress
        $programs_with_steps = [];
        foreach ($programs as $program) {
            $program_id = $program['program_id'];
            $program['steps'] = get_program_steps($program_id);
            $program['step_progress'] = get_step_progress($customer_id, $program_id);
            $program['progress_percentage'] = calculate_program_progress($customer_id, $program_id);
            $program['pending_approvals'] = count_pending_approval_steps($customer_id, $program_id);
            $programs_with_steps[] = $program;
        }
        $programs = $programs_with_steps;
    } else {
        $status_error = "Failed to update program status";
    }
}

// Handle step progress update
if (isset($_POST['update_step_progress'])) {
    $progress_id = (int)$_POST['progress_id'];
    $status = $_POST['step_status'];
    $notes = isset($_POST['step_notes']) ? $_POST['step_notes'] : '';
    
    if (update_step_progress($progress_id, $status, $notes)) {
        $status_success = "Step progress updated successfully";
        
        // Refresh programs list
        $programs = get_customer_programs($customer_id);
        
        // Reload programs with steps and progress
        $programs_with_steps = [];
        foreach ($programs as $program) {
            $program_id = $program['program_id'];
            $program['steps'] = get_program_steps($program_id);
            $program['step_progress'] = get_step_progress($customer_id, $program_id);
            $program['progress_percentage'] = calculate_program_progress($customer_id, $program_id);
            $program['pending_approvals'] = count_pending_approval_steps($customer_id, $program_id);
            $programs_with_steps[] = $program;
        }
        $programs = $programs_with_steps;
    } else {
        $status_error = "Failed to update step progress";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details - OpFit</title>
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
                <nav class="mb-4">
                    <ol class="flex list-none p-0">
                        <li class="flex items-center">
                            <a href="customers.php" class="text-blue-600 hover:text-blue-800">My Customers</a>
                            <svg class="h-4 w-4 mx-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </li>
                        <li class="text-gray-700"><?php echo $customer['name']; ?></li>
                    </ol>
                </nav>
                
                <?php if (isset($status_success)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <?php echo $status_success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($status_error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                        <?php echo $status_error; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="flex flex-wrap -mx-2 mb-6">
            <div class="w-full md:w-1/3 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Customer Information</h5>
                    </div>
                    <div class="p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-circle text-6xl text-blue-600"></i>
                        </div>
                        <h4 class="text-xl font-semibold text-center mb-2"><?php echo $customer['name']; ?></h4>
                        <p class="text-center text-gray-500 mb-4"><?php echo $customer['email']; ?></p>
                        <hr class="my-4 border-gray-200">
                        <p class="mb-4"><strong>Member Since:</strong> <?php echo date('F d, Y', strtotime($customer['created_at'])); ?></p>
                        <div class="mt-4">
                            <a href="assign_program.php?customer_id=<?php echo $customer_id; ?>" 
                               class="block bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-4 rounded">
                                <i class="fas fa-plus-circle mr-2"></i> Assign New Program
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="w-full md:w-2/3 px-2">
                <div class="bg-white rounded-lg shadow-md mb-4">
                    <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Progress History</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($progress_history) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Height (cm)</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($progress_history as $progress): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($progress['record_date'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $progress['weight']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $progress['height']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $progress['notes']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">No progress records available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md">
                    <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Recent Workout Logs</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($workout_logs) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration (min)</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($workout_logs as $log): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($log['workout_date'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $log['program_name']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $log['duration']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $log['notes']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">No workout logs available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex flex-wrap mb-4">
            <div class="w-full">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="bg-blue-600 text-white py-3 px-4 rounded-t-lg">
                        <h5 class="font-semibold">Training Programs</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($programs) > 0): ?>
                            <div>
                                <?php foreach ($programs as $index => $program): ?>
                                    <div class="mb-4 border border-gray-200 rounded-lg overflow-hidden">
                                        <div class="border-b border-gray-200">
                                            <button class="w-full flex justify-between items-center p-4 text-left focus:outline-none" 
                                                    onclick="toggleProgram(<?php echo $index; ?>)">
                                                <div class="flex items-center">
                                                    <span class="font-medium"><?php echo $program['name']; ?></span>
                                                    <span class="ml-2 px-2 py-1 text-xs rounded-full
                                                        <?php 
                                                        if ($program['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                                        else if ($program['status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                                        else if ($program['status'] == 'completed') echo 'bg-blue-100 text-blue-800';
                                                        else echo 'bg-red-100 text-red-800';
                                                        ?>">
                                                        <?php echo ucfirst($program['status']); ?>
                                                    </span>
                                                    <?php if ($program['pending_approvals'] > 0): ?>
                                                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                                            <?php echo $program['pending_approvals']; ?> approval<?php echo $program['pending_approvals'] > 1 ? 's' : ''; ?> pending
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center">
                                                    <?php if ($program['status'] == 'approved'): ?>
                                                        <div class="mr-4 w-36 bg-gray-200 rounded-full h-2">
                                                            <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $program['progress_percentage']; ?>%"></div>
                                                        </div>
                                                        <span class="text-xs text-gray-600"><?php echo $program['progress_percentage']; ?>%</span>
                                                    <?php endif; ?>
                                                    <svg class="ml-2 h-5 w-5 transform transition-transform duration-200" id="arrow<?php echo $index; ?>" 
                                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                            </button>
                                        </div>
                                        <div class="p-4 hidden" id="program<?php echo $index; ?>">
                                            <div class="flex justify-between mb-4">
                                                <p class="text-gray-700"><?php echo $program['description']; ?></p>
                                                <div>
                                                    <?php if ($program['status'] == 'pending'): ?>
                                                        <form action="" method="post" class="inline-block">
                                                            <input type="hidden" name="program_id" value="<?php echo $program['program_id']; ?>">
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" name="update_program_status" class="bg-green-600 hover:bg-green-700 text-white text-sm py-1 px-3 rounded mr-1">
                                                                <i class="fas fa-check mr-1"></i> Approve
                                                            </button>
                                                        </form>
                                                        <form action="" method="post" class="inline-block">
                                                            <input type="hidden" name="program_id" value="<?php echo $program['program_id']; ?>">
                                                            <input type="hidden" name="status" value="rejected">
                                                            <button type="submit" name="update_program_status" class="bg-red-600 hover:bg-red-700 text-white text-sm py-1 px-3 rounded">
                                                                <i class="fas fa-times mr-1"></i> Reject
                                                            </button>
                                                        </form>
                                                    <?php elseif ($program['status'] == 'approved'): ?>
                                                        <form action="" method="post" class="inline-block">
                                                            <input type="hidden" name="program_id" value="<?php echo $program['program_id']; ?>">
                                                            <input type="hidden" name="status" value="completed">
                                                            <button type="submit" name="update_program_status" class="bg-blue-600 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded">
                                                                <i class="fas fa-check-double mr-1"></i> Mark Completed
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($program['steps'])): ?>
                                                <h6 class="text-lg font-medium mt-6 mb-3">Program Steps</h6>
                                                <div class="space-y-3">
                                                    <?php foreach ($program['steps'] as $step): ?>
                                                        <?php 
                                                            $step_status = 'pending';
                                                            $step_notes = '';
                                                            $progress_id = 0;
                                                            $completion_date = null;
                                                            
                                                            if (isset($program['step_progress'][$step['id']])) {
                                                                $progress = $program['step_progress'][$step['id']];
                                                                $step_status = $progress['status'];
                                                                $step_notes = $progress['notes'];
                                                                $progress_id = $progress['id'];
                                                                $completion_date = $progress['completion_date'];
                                                            }
                                                            
                                                            $status_class = '';
                                                            $status_icon = '';
                                                            
                                                            if ($step_status == 'completed') {
                                                                $status_class = 'bg-green-50 border-green-200';
                                                                $status_icon = '<i class="fas fa-check-circle text-green-500 mr-2"></i>';
                                                            } else if ($step_status == 'in_progress') {
                                                                $status_class = 'bg-blue-50 border-blue-200';
                                                                $status_icon = '<i class="fas fa-spinner text-blue-500 mr-2"></i>';
                                                            } else if ($step_status == 'pending_approval') {
                                                                $status_class = 'bg-yellow-50 border-yellow-200';
                                                                $status_icon = '<i class="fas fa-hourglass-half text-yellow-500 mr-2"></i>';
                                                            } else {
                                                                $status_class = 'bg-gray-50 border-gray-200';
                                                                $status_icon = '<i class="far fa-circle text-gray-400 mr-2"></i>';
                                                            }
                                                        ?>
                                                        <div class="p-4 rounded-lg border <?php echo $status_class; ?>">
                                                            <div class="flex justify-between items-center mb-2">
                                                                <h6 class="font-medium flex items-center">
                                                                    <?php echo $status_icon; ?> 
                                                                    Step <?php echo $step['step_number']; ?>: <?php echo $step['title']; ?>
                                                                </h6>
                                                                <?php if ($program['status'] == 'approved'): ?>
                                                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium
                                                                        <?php 
                                                                        if ($step_status == 'completed') echo 'bg-green-100 text-green-800';
                                                                        else if ($step_status == 'in_progress') echo 'bg-blue-100 text-blue-800';
                                                                        else if ($step_status == 'pending_approval') echo 'bg-yellow-100 text-yellow-800';
                                                                        else echo 'bg-gray-100 text-gray-800';
                                                                        ?>">
                                                                        <?php 
                                                                        if ($step_status == 'completed') echo 'Completed';
                                                                        else if ($step_status == 'in_progress') echo 'In Progress';
                                                                        else if ($step_status == 'pending_approval') echo 'Pending Approval';
                                                                        else echo 'Pending';
                                                                        ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <p class="text-gray-700 mb-2"><?php echo nl2br($step['description']); ?></p>
                                                            <div class="text-sm text-gray-500">
                                                                <span class="flex items-center">
                                                                    <i class="fas fa-clock mr-1"></i> <?php echo $step['duration']; ?>
                                                                    <?php if ($completion_date): ?>
                                                                        <span class="ml-3 flex items-center">
                                                                            <i class="fas fa-calendar-check mr-1"></i> Completed on: <?php echo date('M d, Y', strtotime($completion_date)); ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <?php if ($step_status == 'pending_approval'): ?>
                                                                <div class="mt-3">
                                                                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-3 mb-3">
                                                                        <strong>Customer has requested completion approval</strong>
                                                                    </div>
                                                                    <form action="" method="post" class="inline-block">
                                                                        <input type="hidden" name="progress_id" value="<?php echo $progress_id; ?>">
                                                                        <input type="hidden" name="step_status" value="completed">
                                                                        <button type="submit" name="update_step_progress" class="bg-green-600 hover:bg-green-700 text-white text-sm py-1 px-3 rounded mr-2">
                                                                            <i class="fas fa-check mr-1"></i> Approve Completion
                                                                        </button>
                                                                    </form>
                                                                    <form action="" method="post" class="inline-block">
                                                                        <input type="hidden" name="progress_id" value="<?php echo $progress_id; ?>">
                                                                        <input type="hidden" name="step_status" value="in_progress">
                                                                        <button type="submit" name="update_step_progress" class="bg-red-600 hover:bg-red-700 text-white text-sm py-1 px-3 rounded">
                                                                            <i class="fas fa-times mr-1"></i> Reject & Keep In Progress
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($program['status'] == 'approved'): ?>
                                                                <div class="hidden mt-3" id="stepEdit<?php echo $step['id']; ?>">
                                                                    <div class="bg-white p-4 rounded-lg border border-gray-300">
                                                                        <form action="" method="post">
                                                                            <input type="hidden" name="progress_id" value="<?php echo $progress_id; ?>">
                                                                            <div class="mb-3">
                                                                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                                                                <select name="step_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                    <option value="pending" <?php echo ($step_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                                                    <option value="in_progress" <?php echo ($step_status == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                                                                    <option value="completed" <?php echo ($step_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                                                    <?php if ($step_status == 'pending_approval'): ?>
                                                                                        <option value="pending_approval" selected>Pending Approval</option>
                                                                                    <?php endif; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                                                                <textarea name="step_notes" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" rows="2"><?php echo $step_notes; ?></textarea>
                                                                            </div>
                                                                            <button type="submit" name="update_step_progress" class="bg-blue-600 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded">
                                                                                Save Progress
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-gray-500">No steps defined for this program.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">No training programs assigned.</p>
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
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
            alerts.forEach(alert => {
                if (!alert.closest('.p-4')) { // Only target top-level alerts, not nested ones
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 300ms';
                        setTimeout(() => {
                            alert.remove();
                        }, 300);
                    }, 5000);
                }
            });
            
            // Show the first program by default
            if (document.getElementById('program0')) {
                toggleProgram(0);
            }
        });
        
        function toggleProgram(index) {
            const content = document.getElementById('program' + index);
            const arrow = document.getElementById('arrow' + index);
            
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }
        
        function toggleStepEdit(stepId) {
            const editForm = document.getElementById('stepEdit' + stepId);
            editForm.classList.toggle('hidden');
        }
    </script>
</body>
</html> 