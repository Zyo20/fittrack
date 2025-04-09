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

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Process approve/reject request
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $request_id = (int)$_GET['id'];
    
    // Get request details to find customer and program
    $check_query = "SELECT customer_id, program_id FROM customer_programs WHERE id = $request_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $request = mysqli_fetch_assoc($check_result);
        $customer_id = $request['customer_id'];
        $program_id = $request['program_id'];
        
        if ($action == 'approve') {
            $status = 'approved';
            $success_msg = "Request approved successfully";
        } elseif ($action == 'reject') {
            $status = 'rejected';
            $success_msg = "Request rejected successfully";
        } elseif ($action == 'complete') {
            $status = 'completed';
            $success_msg = "Program marked as completed";
        } else {
            header("Location: requests.php");
            exit();
        }
        
        // Update status
        if (update_program_status($program_id, $customer_id, $status)) {
            $success = $success_msg;
        } else {
            $error = "Failed to update request status";
        }
    } else {
        $error = "Invalid request ID";
    }
}

// Process filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT cp.*, u.name as customer_name, u.email as customer_email, p.name as program_name, p.difficulty
          FROM customer_programs cp
          JOIN users u ON cp.customer_id = u.id
          JOIN programs p ON cp.program_id = p.id";

$where_clauses = [];
if (!empty($status_filter)) {
    $where_clauses[] = "cp.status = '$status_filter'";
}

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR p.name LIKE '%$search%')";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(' AND ', $where_clauses);
}

$query .= " ORDER BY cp.created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Requests - OpFit Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">OpFit Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="programs.php">Programs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assignments.php">Coach Assignments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo $user_name; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h1>Program Requests</h1>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="requests.php" method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo ($status_filter == 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Search by customer or program" value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Requests Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Program</th>
                                <th>Difficulty</th>
                                <th>Status</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($request = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $request['id']; ?></td>
                                        <td>
                                            <div><?php echo $request['customer_name']; ?></div>
                                            <small class="text-muted"><?php echo $request['customer_email']; ?></small>
                                        </td>
                                        <td><?php echo $request['program_name']; ?></td>
                                        <td>
                                            <?php if ($request['difficulty'] == 'beginner'): ?>
                                                <span class="badge bg-success">Beginner</span>
                                            <?php elseif ($request['difficulty'] == 'intermediate'): ?>
                                                <span class="badge bg-warning">Intermediate</span>
                                            <?php elseif ($request['difficulty'] == 'advanced'): ?>
                                                <span class="badge bg-danger">Advanced</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($request['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif ($request['status'] == 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif ($request['status'] == 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php elseif ($request['status'] == 'completed'): ?>
                                                <span class="badge bg-info">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <?php if ($request['status'] == 'pending'): ?>
                                                <a href="requests.php?action=approve&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <a href="requests.php?action=reject&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            <?php elseif ($request['status'] == 'approved'): ?>
                                                <a href="requests.php?action=complete&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-check-double"></i> Mark Completed
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>No Actions</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No requests found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 