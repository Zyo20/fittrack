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

// Get customer's progress history
$progress_history = get_progress_history($user_id);

// Get latest progress record if available
$latest_progress = !empty($progress_history) ? $progress_history[0] : null;

// Get customer's programs with progress
$programs = get_customer_programs($user_id);
$active_programs = [];

foreach ($programs as &$program) {
    if ($program['status'] == 'approved' || $program['status'] == 'completed') {
        $program_id = $program['program_id'];
        
        // Calculate progress percentage
        $program['progress_percentage'] = calculate_program_progress($user_id, $program_id);
        
        // Get completed steps
        $completed_steps_query = "SELECT COUNT(*) as completed 
                                FROM step_progress sp
                                JOIN program_steps ps ON sp.step_id = ps.id
                                WHERE sp.customer_id = $user_id 
                                AND ps.program_id = $program_id 
                                AND sp.status = 'completed'";
        $completed_steps_result = mysqli_query($conn, $completed_steps_query);
        $completed_steps_row = mysqli_fetch_assoc($completed_steps_result);
        $program['completed_steps'] = $completed_steps_row['completed'];
        
        // Total steps
        $total_steps_query = "SELECT COUNT(*) as total FROM program_steps WHERE program_id = $program_id";
        $total_steps_result = mysqli_query($conn, $total_steps_query);
        $total_steps_row = mysqli_fetch_assoc($total_steps_result);
        $program['total_steps'] = $total_steps_row['total'];
        
        // Get recent workout logs for this program
        $logs_query = "SELECT * FROM workout_logs 
                      WHERE customer_id = $user_id 
                      AND program_id = $program_id
                      ORDER BY workout_date DESC
                      LIMIT 3";
        $logs_result = mysqli_query($conn, $logs_query);
        $program['recent_logs'] = [];
        while ($row = mysqli_fetch_assoc($logs_result)) {
            $program['recent_logs'][] = $row;
        }
        
        $active_programs[] = $program;
    }
}

// Get recent workout logs across all programs
$recent_workouts_query = "SELECT wl.*, p.name as program_name
                        FROM workout_logs wl
                        JOIN programs p ON wl.program_id = p.id
                        WHERE wl.customer_id = $user_id
                        ORDER BY wl.workout_date DESC
                        LIMIT 5";
$recent_workouts_result = mysqli_query($conn, $recent_workouts_query);
$recent_workouts = [];
while ($row = mysqli_fetch_assoc($recent_workouts_result)) {
    $recent_workouts[] = $row;
}

// Handle form submission for adding new progress record
if (isset($_POST['add_progress'])) {
    $data = [
        'weight' => (float)$_POST['weight'],
        'height' => (float)$_POST['height'],
        'notes' => $_POST['notes']
    ];
    
    if (add_progress_record($user_id, $data)) {
        $_SESSION['success_message'] = "Progress record added successfully";
        // Refresh progress history
        $progress_history = get_progress_history($user_id);
        $latest_progress = !empty($progress_history) ? $progress_history[0] : null;
    } else {
        $_SESSION['error_message'] = "Failed to add progress record";
    }
    
    // Redirect to avoid form resubmission on page refresh
    header("Location: progress.php");
    exit();
}

// Store success/error messages from session and then clear them
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear the session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Prepare data for charts
$weight_data = [];
$height_data = [];
$dates = [];

// Get the last 10 progress records for charts (in reverse order for chronological display)
$chart_data = array_slice(array_reverse($progress_history), 0, 10);

foreach ($chart_data as $record) {
    $weight_data[] = $record['weight'];
    $height_data[] = $record['height'];
    $dates[] = date('M d', strtotime($record['record_date']));
}

// Convert to JSON for chart.js
$weight_json = json_encode($weight_data);
$height_json = json_encode($height_data);
$dates_json = json_encode($dates);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracker - FitTrack</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">My Programs</a>
                        </li>
                        <li>
                            <a class="text-white font-medium block py-2" href="progress.php">Progress Tracker</a>
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
            <h1 class="text-2xl font-bold text-gray-800">Progress Tracker</h1>
            
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
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="md:col-span-1">
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Current Stats</h5>
                    </div>
                    <div class="p-4">
                        <?php if ($latest_progress): ?>
                            <div class="flex justify-between mb-4">
                                <div class="text-center">
                                    <h3 class="text-xl font-bold"><?php echo $latest_progress['weight']; ?> kg</h3>
                                    <p class="text-gray-500">Weight</p>
                                </div>
                                <div class="text-center">
                                    <h3 class="text-xl font-bold"><?php echo $latest_progress['height']; ?> cm</h3>
                                    <p class="text-gray-500">Height</p>
                                </div>
                                <?php 
                                    // Calculate BMI if both weight and height are available
                                    if ($latest_progress['weight'] > 0 && $latest_progress['height'] > 0) {
                                        $height_m = $latest_progress['height'] / 100;
                                        $bmi = round($latest_progress['weight'] / ($height_m * $height_m), 1);
                                        
                                        $bmi_class = 'text-blue-600';
                                        if ($bmi < 18.5) $bmi_class = 'text-yellow-600';
                                        else if ($bmi >= 25) $bmi_class = 'text-yellow-600';
                                        else if ($bmi >= 30) $bmi_class = 'text-red-600';

                                        // Define weight ranges based on BMI and height
                                        $ideal_weight_lower = round(18.5 * $height_m * $height_m, 1);
                                        $ideal_weight_upper = round(24.9 * $height_m * $height_m, 1);
                                        // For scale visualization
                                        $weight_min = max(round($ideal_weight_lower * 0.7), 40);
                                        $weight_max = round($ideal_weight_upper * 1.3);
                                        $weight_percent = min(100, max(0, (($latest_progress['weight'] - $weight_min) / ($weight_max - $weight_min)) * 100));
                                    }

                                    // Define height ranges (for adults)
                                    $height_min = 150;
                                    $height_max = 200;
                                    if (isset($latest_progress['height']) && $latest_progress['height'] > 0) {
                                        $height_percent = min(100, max(0, (($latest_progress['height'] - $height_min) / ($height_max - $height_min)) * 100));
                                    }
                                ?>
                                <div class="text-center">
                                    <h3 class="text-xl font-bold <?php echo isset($bmi) ? $bmi_class : ''; ?>">
                                        <?php echo isset($bmi) ? $bmi : 'N/A'; ?>
                                    </h3>
                                    <p class="text-gray-500">BMI</p>
                                </div>
                            </div>
                            
                            <!-- Linear Scales for Weight and Height -->
                            <div class="mt-6">
                                <h6 class="font-medium mb-2">Weight Scale</h6>
                                <?php if (isset($weight_percent)): ?>
                                <div class="relative mb-4">
                                    <!-- Position the arrow and current weight above the scale -->
                                    <div class="absolute" style="left: <?php echo $weight_percent; ?>%; top: -30px; transform: translateX(-50%);">
                                        <div class="flex flex-col items-center">
                                            <span class="px-2 py-1 text-sm bg-blue-600 text-white rounded"><?php echo $latest_progress['weight']; ?> kg</span>
                                            <i class="fas fa-caret-down text-blue-600 text-2xl"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="h-8 bg-gray-200 rounded-lg overflow-hidden">
                                        <?php if (isset($ideal_weight_lower) && isset($ideal_weight_upper)): ?>
                                        <div class="h-full bg-gray-100 inline-block" 
                                            style="width: <?php echo (($ideal_weight_lower - $weight_min) / ($weight_max - $weight_min)) * 100; ?>%">
                                        </div>
                                        <div class="h-full bg-green-500 inline-block" 
                                            style="width: <?php echo (($ideal_weight_upper - $ideal_weight_lower) / ($weight_max - $weight_min)) * 100; ?>%">
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex justify-between mt-1">
                                        <span class="text-xs text-gray-500"><?php echo $weight_min; ?> kg</span>
                                        <?php if (isset($ideal_weight_lower) && isset($ideal_weight_upper)): ?>
                                        <span class="text-xs text-green-600"><?php echo $ideal_weight_lower; ?>-<?php echo $ideal_weight_upper; ?> kg</span>
                                        <?php endif; ?>
                                        <span class="text-xs text-gray-500"><?php echo $weight_max; ?> kg</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <h6 class="font-medium mb-2">Height Scale</h6>
                                <?php if (isset($height_percent)): ?>
                                <div class="relative mb-4">
                                    <!-- Position the arrow and current height above the scale -->
                                    <div class="absolute" style="left: <?php echo $height_percent; ?>%; top: -30px; transform: translateX(-50%);">
                                        <div class="flex flex-col items-center">
                                            <span class="px-2 py-1 text-sm bg-blue-600 text-white rounded"><?php echo $latest_progress['height']; ?> cm</span>
                                            <i class="fas fa-caret-down text-blue-600 text-2xl"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="h-8 bg-gray-200 rounded-lg overflow-hidden">
                                        <div class="h-full bg-gray-100 w-full"></div>
                                    </div>
                                    
                                    <div class="flex justify-between mt-1">
                                        <span class="text-xs text-gray-500"><?php echo $height_min; ?> cm</span>
                                        <span class="text-xs text-green-600">Ideal Range</span>
                                        <span class="text-xs text-gray-500"><?php echo $height_max; ?> cm</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-500 text-sm mt-4">Last updated: <?php echo date('M d, Y', strtotime($latest_progress['record_date'])); ?></p>
                            <?php if ($latest_progress['notes']): ?>
                                <div class="mt-4 p-3 bg-gray-50 rounded">
                                    <p class="text-sm"><strong>Notes:</strong> <?php echo $latest_progress['notes']; ?></p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-weight text-4xl text-gray-400 mb-3"></i>
                                <p class="text-gray-700">No progress data recorded yet</p>
                                <p class="text-gray-500 text-sm">Add your first progress record below</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Update Progress</h5>
                    </div>
                    <div class="p-4">
                        <form action="" method="post">
                            <div class="mb-4">
                                <label for="weight" class="block text-gray-700 mb-2">Weight (kg)</label>
                                <input type="number" step="0.1" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="weight" name="weight" required>
                            </div>
                            <div class="mb-4">
                                <label for="height" class="block text-gray-700 mb-2">Height (cm)</label>
                                <input type="number" step="0.1" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="height" name="height" required>
                            </div>
                            <div class="mb-4">
                                <label for="notes" class="block text-gray-700 mb-2">Notes</label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" id="notes" name="notes" rows="3" placeholder="How are you feeling today?"></textarea>
                            </div>
                            <button type="submit" name="add_progress" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors flex items-center justify-center">
                                <i class="fas fa-save mr-2"></i> Save Progress
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Weight & Height History</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($progress_history) > 1): ?>
                            <canvas id="progressChart" height="100"></canvas>
                        <?php elseif (count($progress_history) == 1): ?>
                            <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-4 flex">
                                <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                                <p>Add more progress entries to see your trends over time.</p>
                            </div>
                        <?php else: ?>
                            <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-4 flex">
                                <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                                <p>No progress data available yet. Start tracking to see your history.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Program Progress</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($active_programs) > 0): ?>
                            <?php foreach ($active_programs as $program): ?>
                                <div class="mb-6">
                                    <div class="flex justify-between items-center mb-2">
                                        <h5 class="font-medium text-lg flex items-center">
                                            <?php echo $program['name']; ?>
                                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full 
                                                  <?php echo $program['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo ucfirst($program['status']); ?>
                                            </span>
                                        </h5>
                                        <a href="program_progress.php?id=<?php echo $program['program_id']; ?>" 
                                           class="inline-flex items-center text-sm bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded transition-colors">
                                            <i class="fas fa-eye mr-1"></i> View Details
                                        </a>
                                    </div>
                                    
                                    <div class="flex items-center mb-2">
                                        <span class="mr-3 text-sm font-medium"><?php echo $program['progress_percentage']; ?>%</span>
                                        <div class="relative flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="absolute h-full bg-green-500" style="width: <?php echo $program['progress_percentage']; ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-tasks mr-1"></i>
                                        <strong><?php echo $program['completed_steps']; ?> of <?php echo $program['total_steps']; ?></strong> steps completed
                                    </p>
                                
                                <?php if (!empty($program['recent_logs'])): ?>
                                    <div class="mt-2 ml-4 text-sm">
                                        <p class="font-medium mb-1">Recent workouts:</p>
                                        <?php foreach ($program['recent_logs'] as $log): ?>
                                            <div class="text-gray-500">
                                                <i class="fas fa-calendar-day mr-1"></i>
                                                <?php echo date('M d', strtotime($log['workout_date'])); ?> - 
                                                <?php echo $log['duration']; ?> min
                                                <?php echo $log['notes'] ? '- ' . substr($log['notes'], 0, 30) . (strlen($log['notes']) > 30 ? '...' : '') : ''; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                </div>
                                
                                <hr class="my-4 border-gray-200">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-4 flex">
                                <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                                <p>You don't have any active programs yet. <a href="programs.php" class="underline hover:no-underline">Check available programs</a> to get started.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">Progress History</h5>
                    </div>
                    <div class="p-4">
                        <?php if (count($progress_history) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="px-4 py-2 border border-gray-200">Date</th>
                                            <th class="px-4 py-2 border border-gray-200">Weight (kg)</th>
                                            <th class="px-4 py-2 border border-gray-200">Height (cm)</th>
                                            <th class="px-4 py-2 border border-gray-200">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($progress_history as $progress): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 border border-gray-200"><?php echo date('M d, Y', strtotime($progress['record_date'])); ?></td>
                                                <td class="px-4 py-2 border border-gray-200">
                                                    <?php echo $progress['weight']; ?>
                                                    <?php 
                                                    // Show weight change compared to previous record
                                                    if (isset($prev_weight)) {
                                                        $change = $progress['weight'] - $prev_weight;
                                                        if ($change != 0) {
                                                            echo ' <span class="inline-block px-2 py-0.5 text-xs rounded-full ' . 
                                                                 ($change < 0 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800') . '">';
                                                            echo ($change < 0 ? '' : '+') . number_format($change, 1);
                                                            echo '</span>';
                                                        }
                                                    }
                                                    $prev_weight = $progress['weight'];
                                                    ?>
                                                </td>
                                                <td class="px-4 py-2 border border-gray-200"><?php echo $progress['height']; ?></td>
                                                <td class="px-4 py-2 border border-gray-200"><?php echo $progress['notes']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 py-4 text-center">No progress records available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../js/unread-messages.js"></script>
    
    <?php echo modal_fix_script(); ?>
    
    <?php if (count($progress_history) > 1): ?>
    <script>
        // Initialize chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('progressChart').getContext('2d');
            
            const weightData = <?php echo $weight_json; ?>;
            const heightData = <?php echo $height_json; ?>;
            const dates = <?php echo $dates_json; ?>;
            
            const progressChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Weight (kg)',
                            data: weightData,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Height (cm)',
                            data: heightData,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.1,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Weight (kg)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Height (cm)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
    
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