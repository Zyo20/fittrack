<?php
session_start();

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get unread messages count 
$unread_count = count_unread_messages($user_id);

// Get user statistics
$users_query = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
$users_result = mysqli_query($conn, $users_query);

$user_stats = [
    'customer' => 0,
    'coach' => 0,
    'admin' => 0
];

while ($row = mysqli_fetch_assoc($users_result)) {
    $user_stats[$row['user_type']] = $row['count'];
}

// Get program statistics
$programs_query = "SELECT difficulty, COUNT(*) as count FROM programs GROUP BY difficulty";
$programs_result = mysqli_query($conn, $programs_query);

$program_stats = [
    'beginner' => 0,
    'intermediate' => 0,
    'advanced' => 0
];

while ($row = mysqli_fetch_assoc($programs_result)) {
    $program_stats[$row['difficulty']] = $row['count'];
}

// Get program requests statistics
$requests_query = "SELECT status, COUNT(*) as count FROM customer_programs GROUP BY status";
$requests_result = mysqli_query($conn, $requests_query);

$request_stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'completed' => 0
];

while ($row = mysqli_fetch_assoc($requests_result)) {
    $request_stats[$row['status']] = $row['count'];
}

// Get top programs
$top_programs_query = "SELECT p.id, p.name, COUNT(cp.id) as usage_count
                       FROM programs p
                       JOIN customer_programs cp ON p.id = cp.program_id
                       GROUP BY p.id, p.name
                       ORDER BY usage_count DESC
                       LIMIT 5";
$top_programs_result = mysqli_query($conn, $top_programs_query);

// Get user registrations over time
$registrations_query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
                        FROM users
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY month
                        ORDER BY month";
$registrations_result = mysqli_query($conn, $registrations_query);

$registration_data = [];
while ($row = mysqli_fetch_assoc($registrations_result)) {
    $registration_data[] = $row;
}

// Get coach workload
$workload_query = "SELECT u.id, u.name, COUNT(cc.id) as customer_count
                  FROM users u
                  LEFT JOIN coach_customer cc ON u.id = cc.coach_id
                  WHERE u.user_type = 'coach'
                  GROUP BY u.id, u.name
                  ORDER BY customer_count DESC";
$workload_result = mysqli_query($conn, $workload_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - OpFit Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a class="text-gray-300 hover:text-white block py-2" href="users.php">Users</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">Programs</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="assignments.php">Coach Assignments</a>
                        </li>
                        <li>
                            <a class="text-white font-medium block py-2" href="reports.php">Reports</a>
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
        <h1 class="text-2xl font-bold mb-4">System Reports</h1>
        
        <div class="flex flex-wrap -mx-2 mb-4">
            <!-- User Distribution Chart -->
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-sm overflow-hidden h-full">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">User Distribution</h5>
                    </div>
                    <div class="p-4">
                        <canvas id="userChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Program Distribution Chart -->
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-sm overflow-hidden h-full">
                    <div class="bg-green-600 text-white px-4 py-3">
                        <h5 class="font-medium">Program Difficulty Distribution</h5>
                    </div>
                    <div class="p-4">
                        <canvas id="programChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Registration Over Time Chart -->
            <div class="w-full px-2 mb-4">
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="bg-cyan-600 text-white px-4 py-3">
                        <h5 class="font-medium">User Registrations Over Time</h5>
                    </div>
                    <div class="p-4">
                        <canvas id="registrationChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Program Request Status Chart -->
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-sm overflow-hidden h-full">
                    <div class="bg-amber-500 text-white px-4 py-3">
                        <h5 class="font-medium">Program Request Status</h5>
                    </div>
                    <div class="p-4">
                        <canvas id="requestChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top Programs Table -->
            <div class="w-full md:w-1/2 px-2 mb-4">
                <div class="bg-white rounded-lg shadow-sm overflow-hidden h-full">
                    <div class="bg-red-600 text-white px-4 py-3">
                        <h5 class="font-medium">Top 5 Programs</h5>
                    </div>
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Usage Count</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($program = mysqli_fetch_assoc($top_programs_result)): ?>
                                        <tr class="hover:bg-gray-50 transition duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap text-center"><?php echo $program['name']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center"><?php echo $program['usage_count']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Coach Workload Table -->
            <div class="w-full px-2 mb-4">
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="bg-gray-600 text-white px-4 py-3">
                        <h5 class="font-medium">Coach Workload</h5>
                    </div>
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Coach</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Number of Customers</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Workload</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($coach = mysqli_fetch_assoc($workload_result)): ?>
                                        <tr class="hover:bg-gray-50 transition duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap text-center"><?php echo $coach['name']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center"><?php echo $coach['customer_count']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                    <div class="bg-cyan-600 h-2.5 rounded-full" style="width: <?php echo min($coach['customer_count'] * 10, 100); ?>%"></div>
                                                </div>
                                                <div class="text-center text-xs mt-1 text-gray-500">
                                                    <?php echo $coach['customer_count']; ?> / 10
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navbar toggle for mobile
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
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function() {
                    if (!userDropdownMenu.classList.contains('hidden')) {
                        userDropdownMenu.classList.add('hidden');
                    }
                });
            }
            
            // User Distribution Chart
            const userCtx = document.getElementById('userChart').getContext('2d');
            const userChart = new Chart(userCtx, {
                type: 'pie',
                data: {
                    labels: ['Customers', 'Coaches', 'Admins'],
                    datasets: [{
                        data: [
                            <?php echo $user_stats['customer']; ?>,
                            <?php echo $user_stats['coach']; ?>,
                            <?php echo $user_stats['admin']; ?>
                        ],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(16, 185, 129, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Program Distribution Chart
            const programCtx = document.getElementById('programChart').getContext('2d');
            const programChart = new Chart(programCtx, {
                type: 'bar',
                data: {
                    labels: ['Beginner', 'Intermediate', 'Advanced'],
                    datasets: [{
                        label: 'Number of Programs',
                        data: [
                            <?php echo $program_stats['beginner']; ?>,
                            <?php echo $program_stats['intermediate']; ?>,
                            <?php echo $program_stats['advanced']; ?>
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgba(16, 185, 129, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Program Request Status Chart
            const requestCtx = document.getElementById('requestChart').getContext('2d');
            const requestChart = new Chart(requestCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Approved', 'Rejected', 'Completed'],
                    datasets: [{
                        data: [
                            <?php echo $request_stats['pending']; ?>,
                            <?php echo $request_stats['approved']; ?>,
                            <?php echo $request_stats['rejected']; ?>,
                            <?php echo $request_stats['completed']; ?>
                        ],
                        backgroundColor: [
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(6, 182, 212, 0.8)'
                        ],
                        borderColor: [
                            'rgba(245, 158, 11, 1)',
                            'rgba(16, 185, 129, 1)',
                            'rgba(239, 68, 68, 1)',
                            'rgba(6, 182, 212, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Registration Over Time Chart
            const registrationCtx = document.getElementById('registrationChart').getContext('2d');
            const registrationChart = new Chart(registrationCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php
                            foreach ($registration_data as $data) {
                                $dateObj = DateTime::createFromFormat('Y-m', $data['month']);
                                echo "'" . $dateObj->format('M Y') . "',";
                            }
                        ?>
                    ],
                    datasets: [{
                        label: 'New Users',
                        data: [
                            <?php
                                foreach ($registration_data as $data) {
                                    echo $data['count'] . ",";
                                }
                            ?>
                        ],
                        backgroundColor: 'rgba(59, 130, 246, 0.5)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html> 