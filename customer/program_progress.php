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

// Get program ID from URL
if (isset($_GET['id'])) {
    $program_id = (int)$_GET['id'];
    
    // Verify this program is assigned to the current customer
    $verify_query = "SELECT * FROM customer_programs 
                    WHERE customer_id = $user_id 
                    AND program_id = $program_id
                    AND status = 'approved'";
    $verify_result = mysqli_query($conn, $verify_query);
    
    if (mysqli_num_rows($verify_result) == 0) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Get program details
    $program_query = "SELECT * FROM programs WHERE id = $program_id";
    $program_result = mysqli_query($conn, $program_query);
    $program = mysqli_fetch_assoc($program_result);
    
    // Get program steps with progress
    $steps = get_program_steps($program_id);
    $step_progress = get_step_progress($user_id, $program_id);
    $progress_percentage = calculate_program_progress($user_id, $program_id);
    
    // Initialize progress for steps if not already initialized
    if (count($step_progress) < count($steps)) {
        initialize_step_progress($user_id, $program_id);
        // Refresh step progress after initialization
        $step_progress = get_step_progress($user_id, $program_id);
    }
    
} else {
    header("Location: dashboard.php");
    exit();
}

// Handle workout log update
if (isset($_POST['update_progress'])) {
    $log_date = date('Y-m-d');
    $log_duration = (int)$_POST['duration'];
    $log_notes = sanitize_input($_POST['notes']);
    
    // Add workout log
    $log_query = "INSERT INTO workout_logs (customer_id, program_id, duration, notes, workout_date) 
                 VALUES ($user_id, $program_id, $log_duration, '$log_notes', '$log_date')";
    
    if (mysqli_query($conn, $log_query)) {
        $_SESSION['success_message'] = "Workout log added successfully";
    } else {
        $_SESSION['error_message'] = "Failed to add workout log: " . mysqli_error($conn);
    }
    
    // Redirect to avoid form resubmission
    header("Location: program_progress.php?id=$program_id");
    exit();
}

// Handle completion request
if (isset($_POST['request_completion'])) {
    $progress_id = (int)$_POST['progress_id'];
    $status = $_POST['step_status'];
    
    if (update_step_progress($progress_id, $status, 'Customer requested completion')) {
        $_SESSION['success_message'] = "Completion request sent to coach for approval";
    } else {
        $_SESSION['error_message'] = "Failed to request completion";
    }
    
    // Redirect to avoid form resubmission
    header("Location: program_progress.php?id=$program_id");
    exit();
}

// Handle step status update
if (isset($_POST['start_step']) && isset($_POST['progress_id']) && isset($_POST['step_status'])) {
    $progress_id = (int)$_POST['progress_id'];
    $status = $_POST['step_status'];
    
    // Get step info to check prerequisite
    $step_query = "SELECT sp.*, ps.step_number, ps.program_id 
                  FROM step_progress sp
                  JOIN program_steps ps ON sp.step_id = ps.id
                  WHERE sp.id = $progress_id";
    $step_result = mysqli_query($conn, $step_query);
    
    if (mysqli_num_rows($step_result) > 0) {
        $step_info = mysqli_fetch_assoc($step_result);
        $can_start = false;
        
        // Check if this is the first step (always allowed)
        if ($step_info['step_number'] == 1) {
            $can_start = true;
        } else {
            // Check if previous step is completed or pending approval
            $prev_step_number = $step_info['step_number'] - 1;
            $prev_step_query = "SELECT sp.status
                               FROM step_progress sp
                               JOIN program_steps ps ON sp.step_id = ps.id
                               WHERE ps.program_id = " . $step_info['program_id'] . "
                               AND ps.step_number = $prev_step_number
                               AND sp.customer_id = $user_id";
            $prev_result = mysqli_query($conn, $prev_step_query);
            
            if (mysqli_num_rows($prev_result) > 0) {
                $prev_status = mysqli_fetch_assoc($prev_result)['status'];
                if ($prev_status == 'completed' || $prev_status == 'pending_approval') {
                    $can_start = true;
                }
            }
        }
        
        if ($can_start) {
            if (update_step_progress($progress_id, $status)) {
                $_SESSION['success_message'] = "Step status updated successfully";
            } else {
                $_SESSION['error_message'] = "Failed to update step status";
            }
        } else {
            $_SESSION['error_message'] = "You must complete the previous step before starting this one";
        }
    } else {
        $_SESSION['error_message'] = "Invalid step";
    }
    
    // Redirect to avoid form resubmission
    header("Location: program_progress.php?id=$program_id");
    exit();
}

// Store success/error messages from session and then clear them
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear the session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get recent workout logs for this program
$logs_query = "SELECT * FROM workout_logs 
              WHERE customer_id = $user_id 
              AND program_id = $program_id
              ORDER BY workout_date DESC
              LIMIT 5";
$logs_result = mysqli_query($conn, $logs_query);
$workout_logs = [];
while ($row = mysqli_fetch_assoc($logs_result)) {
    $workout_logs[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Progress - FitTrack</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .refresh-btn {
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .refresh-btn:hover {
            transform: rotate(180deg);
        }
    </style>
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
                            <a class="text-gray-300 hover:text-white block py-2"" href="dashboard.php">Dashboard</a>
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
                <nav class="flex">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="programs.php" class="text-gray-700 hover:text-blue-600">My Programs</a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-1 text-gray-500"><?php echo $program['name']; ?></span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <a href="program_progress.php?id=<?php echo $program_id; ?>" 
                   class="inline-flex items-center text-sm border border-gray-300 rounded px-3 py-1 hover:bg-gray-100 transition refresh-btn">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh
                </a>
            </div>
                
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
        
        <div class="mb-6">
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
                    <p class="text-gray-700 text-lg mb-4"><?php echo $program['description']; ?></p>
                    <p class="mb-4"><strong>Duration:</strong> <?php echo $program['duration']; ?> weeks</p>
                    
                    <div class="relative pt-1 mb-4">
                        <div class="flex mb-2 items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200">
                                    Progress
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-semibold inline-block text-blue-600">
                                    <?php echo $progress_percentage; ?>%
                                </span>
                            </div>
                        </div>
                        <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200">
                            <div style="width:<?php echo $progress_percentage; ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-green-500"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Program Steps</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($steps) > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($steps as $step): ?>
                                    <?php 
                                        $step_status = 'pending';
                                        $step_notes = '';
                                        $completion_date = null;
                                        
                                        if (isset($step_progress[$step['id']])) {
                                            $progress = $step_progress[$step['id']];
                                            $step_status = $progress['status'];
                                            $step_notes = $progress['notes'];
                                            $completion_date = $progress['completion_date'];
                                        }
                                        
                                        $status_class = '';
                                        $status_icon = '';
                                        $status_text = '';
                                        
                                        if ($step_status == 'completed') {
                                            $status_class = 'bg-green-50 border-green-200';
                                            $status_icon = '<i class="fas fa-check-circle text-green-500 mr-2"></i>';
                                            $status_text = 'Completed';
                                        } else if ($step_status == 'in_progress') {
                                            $status_class = 'bg-blue-50 border-blue-200';
                                            $status_icon = '<i class="fas fa-spinner fa-spin text-blue-500 mr-2"></i>';
                                            $status_text = 'In Progress';
                                        } else if ($step_status == 'pending_approval') {
                                            $status_class = 'bg-yellow-50 border-yellow-200';
                                            $status_icon = '<i class="fas fa-hourglass-half text-yellow-500 mr-2"></i>';
                                            $status_text = 'Awaiting Coach Approval';
                                        } else {
                                            $status_class = 'bg-gray-50 border-gray-200';
                                            $status_icon = '<i class="far fa-circle text-gray-400 mr-2"></i>';
                                            $status_text = 'Pending';
                                        }
                                    ?>
                                    <div class="border rounded-lg p-4 <?php echo $status_class; ?>">
                                        <div class="flex justify-between items-start mb-2">
                                            <h5 class="font-medium">
                                                <?php echo $status_icon; ?> Step <?php echo $step['step_number']; ?>: <?php echo $step['title']; ?>
                                            </h5>
                                            <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                                                <?php 
                                                if ($step_status == 'completed') echo 'bg-green-100 text-green-800';
                                                else if ($step_status == 'in_progress') echo 'bg-blue-100 text-blue-800';
                                                else if ($step_status == 'pending_approval') echo 'bg-yellow-100 text-yellow-800';
                                                else echo 'bg-gray-100 text-gray-800';
                                                ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-700 mb-3"><?php echo nl2br($step['description']); ?></p>
                                        <div class="text-sm text-gray-500 flex items-center">
                                            <i class="fas fa-clock mr-1"></i> <?php echo $step['duration']; ?>
                                            <?php if ($completion_date): ?>
                                                <span class="ml-4 flex items-center">
                                                    <i class="fas fa-calendar-check mr-1"></i> Completed on: <?php echo date('M d, Y', strtotime($completion_date)); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($step_notes): ?>
                                            <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                                                <p class="text-sm"><strong>Coach Notes:</strong> <?php echo $step_notes; ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($step_status == 'pending'): ?>
                                            <?php
                                                // Check if this is the first step or if previous step is completed/pending_approval
                                                $can_start = false;
                                                
                                                if ($step['step_number'] == 1) {
                                                    // First step can always be started
                                                    $can_start = true;
                                                } else {
                                                    // Find the previous step
                                                    $prev_step_number = $step['step_number'] - 1;
                                                    
                                                    // Look for the previous step in the steps array
                                                    foreach ($steps as $prev_step) {
                                                        if ($prev_step['step_number'] == $prev_step_number) {
                                                            // Check if previous step progress exists
                                                            if (isset($step_progress[$prev_step['id']])) {
                                                                $prev_status = $step_progress[$prev_step['id']]['status'];
                                                                
                                                                // Can start if previous step is completed or pending approval
                                                                if ($prev_status == 'completed' || $prev_status == 'pending_approval') {
                                                                    $can_start = true;
                                                                }
                                                            }
                                                            break;
                                                        }
                                                    }
                                                }
                                            ?>
                                            
                                            <div class="mt-4">
                                                <?php if ($can_start): ?>
                                                    <form action="" method="post" class="inline-block">
                                                        <input type="hidden" name="progress_id" value="<?php echo $progress['id']; ?>">
                                                        <input type="hidden" name="step_status" value="in_progress">
                                                        <button type="submit" name="start_step" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm transition-colors">
                                                            <i class="fas fa-play-circle mr-2"></i> Start Step
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-3 rounded">
                                                        <div class="flex">
                                                            <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                                                            <p>You need to complete the previous step before starting this one.</p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($step_status == 'in_progress'): ?>
                                            <div class="mt-4">
                                                <form action="" method="post">
                                                    <input type="hidden" name="progress_id" value="<?php echo $progress['id']; ?>">
                                                    <input type="hidden" name="step_status" value="completed">
                                                    <button type="submit" name="request_completion" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm transition-colors">
                                                        <i class="fas fa-check-circle mr-2"></i> Request Step Completion
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($step_status == 'pending_approval'): ?>
                                            <div class="mt-4">
                                                <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 p-3 rounded">
                                                    <div class="flex">
                                                        <i class="fas fa-clock mr-2 mt-0.5"></i>
                                                        <p>Waiting for coach approval</p>
                                                    </div>
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
            </div>
            
            <div class="md:col-span-1">
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Log Your Workout</h5>
                    </div>
                    <div class="p-4">
                        <form action="" method="post">
                            <div class="mb-4">
                                <label for="duration" class="block text-gray-700 mb-2">Duration (minutes)</label>
                                <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       id="duration" name="duration" required min="1">
                            </div>
                            <div class="mb-4">
                                <label for="notes" class="block text-gray-700 mb-2">Notes</label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                          id="notes" name="notes" rows="3" placeholder="What did you do today?"></textarea>
                            </div>
                            <button type="submit" name="update_progress" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors flex items-center justify-center">
                                <i class="fas fa-save mr-2"></i> Log Workout
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Recent Workouts</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($workout_logs) > 0): ?>
                            <div class="divide-y divide-gray-200">
                                <?php foreach ($workout_logs as $log): ?>
                                    <div class="py-3 hover:bg-gray-50 transition-colors">
                                        <div class="flex justify-between items-center mb-1">
                                            <h6 class="font-medium"><?php echo date('M d, Y', strtotime($log['workout_date'])); ?></h6>
                                            <span class="text-sm text-gray-500"><?php echo $log['duration']; ?> min</span>
                                        </div>
                                        <p class="text-gray-600 text-sm"><?php echo $log['notes'] ? $log['notes'] : 'No notes'; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 py-4 text-center">No workout logs yet. Start logging your workouts!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
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