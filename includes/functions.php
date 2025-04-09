<?php
// Functions for common tasks

// Sanitize user input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// Check if user exists
function user_exists($email) {
    global $conn;
    $email = sanitize_input($email);
    $query = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}

// Register a new user
function register_user($name, $email, $password, $user_type) {
    global $conn;
    
    // Sanitize inputs
    $name = sanitize_input($name);
    $email = sanitize_input($email);
    $user_type = sanitize_input($user_type);
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user into database
    $query = "INSERT INTO users (name, email, password, user_type) 
              VALUES ('$name', '$email', '$hashed_password', '$user_type')";
    
    if (mysqli_query($conn, $query)) {
        return mysqli_insert_id($conn);
    } else {
        return false;
    }
}

// Authenticate user
function login_user($email, $password) {
    global $conn;
    
    $email = sanitize_input($email);
    
    $query = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            return true;
        }
    }
    
    return false;
}

// Get user details by ID
function get_user_by_id($user_id) {
    global $conn;
    
    $user_id = (int)$user_id;
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return false;
}

// Get coach for customer
function get_coach_for_customer($customer_id) {
    global $conn;
    
    $customer_id = (int)$customer_id;
    $query = "SELECT c.id, u.name, u.email 
              FROM coach_customer cc
              JOIN users c ON cc.coach_id = c.id
              JOIN users u ON c.id = u.id
              WHERE cc.customer_id = $customer_id";
    
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return false;
}

// Get all programs
function get_all_programs() {
    global $conn;
    
    $query = "SELECT * FROM programs ORDER BY name";
    $result = mysqli_query($conn, $query);
    
    $programs = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $programs[] = $row;
    }
    
    return $programs;
}

// Get customer programs
function get_customer_programs($customer_id) {
    global $conn;
    
    $customer_id = (int)$customer_id;
    $query = "SELECT cp.*, p.name, p.description, cp.status
              FROM customer_programs cp
              JOIN programs p ON cp.program_id = p.id
              WHERE cp.customer_id = $customer_id
              ORDER BY cp.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    
    $programs = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $programs[] = $row;
    }
    
    return $programs;
}

// Get customers for coach
function get_coach_customers($coach_id) {
    global $conn;
    
    $coach_id = (int)$coach_id;
    $query = "SELECT cc.*, u.name, u.email
              FROM coach_customer cc
              JOIN users u ON cc.customer_id = u.id
              WHERE cc.coach_id = $coach_id";
    
    $result = mysqli_query($conn, $query);
    
    $customers = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
    
    return $customers;
}

// Track progress for customer
function add_progress_record($customer_id, $data) {
    global $conn;
    
    $customer_id = (int)$customer_id;
    $weight = (float)$data['weight'];
    $height = (float)$data['height'];
    $notes = sanitize_input($data['notes']);
    
    $query = "INSERT INTO progress (customer_id, weight, height, notes, record_date) 
              VALUES ($customer_id, $weight, $height, '$notes', NOW())";
    
    return mysqli_query($conn, $query);
}

// Get progress history for customer
function get_progress_history($customer_id) {
    global $conn;
    
    $customer_id = (int)$customer_id;
    $query = "SELECT * FROM progress 
              WHERE customer_id = $customer_id 
              ORDER BY record_date DESC";
    
    $result = mysqli_query($conn, $query);
    
    $history = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }
    
    return $history;
}

// Update program status
function update_program_status($program_id, $customer_id, $status) {
    global $conn;
    
    $program_id = (int)$program_id;
    $customer_id = (int)$customer_id;
    $status = sanitize_input($status);
    
    $query = "UPDATE customer_programs 
              SET status = '$status', updated_at = NOW() 
              WHERE program_id = $program_id AND customer_id = $customer_id";
    
    return mysqli_query($conn, $query);
}

// Get program steps
function get_program_steps($program_id) {
    global $conn;
    
    $program_id = (int)$program_id;
    $query = "SELECT * FROM program_steps 
              WHERE program_id = $program_id 
              ORDER BY step_number";
    $result = mysqli_query($conn, $query);
    
    $steps = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $steps[] = $row;
    }
    
    return $steps;
}

// Get step progress for a customer
function get_step_progress($customer_id, $program_id) {
    global $conn;
    
    $customer_id = (int)$customer_id;
    $program_id = (int)$program_id;
    
    $query = "SELECT sp.*, ps.step_number, ps.title, ps.description, ps.duration 
              FROM step_progress sp
              JOIN program_steps ps ON sp.step_id = ps.id
              WHERE sp.customer_id = $customer_id 
              AND sp.program_id = $program_id
              ORDER BY ps.step_number";
    $result = mysqli_query($conn, $query);
    
    $progress = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $progress[$row['step_id']] = $row;
    }
    
    return $progress;
}

// Initialize step progress for a customer when they are assigned a new program
function initialize_step_progress($customer_id, $program_id) {
    global $conn;
    
    $customer_id = (int)$customer_id;
    $program_id = (int)$program_id;
    
    // Get program steps
    $steps = get_program_steps($program_id);
    
    foreach ($steps as $step) {
        $step_id = $step['id'];
        
        // Check if progress already exists for this step
        $check_query = "SELECT * FROM step_progress 
                        WHERE customer_id = $customer_id 
                        AND step_id = $step_id";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 0) {
            // Set first step to in_progress, others to pending
            $status = ($step['step_number'] == 1) ? 'in_progress' : 'pending';
            
            // Create new progress record
            $insert_query = "INSERT INTO step_progress (customer_id, program_id, step_id, status) 
                            VALUES ($customer_id, $program_id, $step_id, '$status')";
            mysqli_query($conn, $insert_query);
        }
    }
    
    return true;
}

// Update step progress
function update_step_progress($progress_id, $status, $notes = '') {
    global $conn;
    
    $progress_id = (int)$progress_id;
    $status = mysqli_real_escape_string($conn, $status);
    $notes = mysqli_real_escape_string($conn, $notes);
    
    // Check if this is a customer requesting completion
    if ($status == 'completed' && $_SESSION['user_type'] == 'customer') {
        // Change status to pending_approval instead of completed
        $status = 'pending_approval';
    }
    
    // Only allow completion if coach is approving or if it's already completed
    if ($status == 'completed' && $_SESSION['user_type'] != 'coach') {
        return false;
    }
    
    // Additional validation for customer starting a step
    if ($status == 'in_progress' && $_SESSION['user_type'] == 'customer') {
        // Get current step info
        $get_step = "SELECT sp.*, ps.step_number, ps.program_id 
                     FROM step_progress sp
                     JOIN program_steps ps ON sp.step_id = ps.id
                     WHERE sp.id = $progress_id";
        $step_result = mysqli_query($conn, $get_step);
        
        if (mysqli_num_rows($step_result) > 0) {
            $step_info = mysqli_fetch_assoc($step_result);
            
            // Skip validation for first step
            if ($step_info['step_number'] > 1) {
                $customer_id = $step_info['customer_id'];
                $program_id = $step_info['program_id'];
                $current_step = $step_info['step_number'];
                
                // Check previous step status
                $prev_query = "SELECT sp.status
                              FROM step_progress sp
                              JOIN program_steps ps ON sp.step_id = ps.id
                              WHERE sp.customer_id = $customer_id
                              AND ps.program_id = $program_id
                              AND ps.step_number = " . ($current_step - 1);
                $prev_result = mysqli_query($conn, $prev_query);
                
                if (mysqli_num_rows($prev_result) > 0) {
                    $prev_status = mysqli_fetch_assoc($prev_result)['status'];
                    
                    // Only allow if previous step is completed or pending_approval
                    if ($prev_status != 'completed' && $prev_status != 'pending_approval') {
                        return false;
                    }
                }
            }
        }
    }
    
    $completion_date = ($status == 'completed') ? ", completion_date = NOW()" : "";
    
    $query = "UPDATE step_progress 
              SET status = '$status', notes = '$notes' $completion_date
              WHERE id = $progress_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && $status == 'completed') {
        // Get current step info to find next step
        $get_current = "SELECT sp.*, ps.step_number, ps.program_id 
                        FROM step_progress sp
                        JOIN program_steps ps ON sp.step_id = ps.id
                        WHERE sp.id = $progress_id";
        $current_result = mysqli_query($conn, $get_current);
        
        if (mysqli_num_rows($current_result) > 0) {
            $current = mysqli_fetch_assoc($current_result);
            
            $customer_id = $current['customer_id'];
            $program_id = $current['program_id'];
            $current_step = $current['step_number'];
            
            // Find next step
            $next_query = "SELECT * FROM program_steps 
                          WHERE program_id = $program_id 
                          AND step_number = " . ($current_step + 1);
            $next_result = mysqli_query($conn, $next_query);
            
            if (mysqli_num_rows($next_result) > 0) {
                $next_step = mysqli_fetch_assoc($next_result);
                
                // Update next step to in_progress
                $update_next = "UPDATE step_progress 
                                SET status = 'in_progress' 
                                WHERE customer_id = $customer_id 
                                AND step_id = " . $next_step['id'];
                mysqli_query($conn, $update_next);
            } else {
                // No more steps, update program status to completed
                $update_program = "UPDATE customer_programs 
                                  SET status = 'completed' 
                                  WHERE customer_id = $customer_id 
                                  AND program_id = $program_id 
                                  AND status = 'approved'";
                mysqli_query($conn, $update_program);
            }
        }
    }
    
    return $result;
}

// Calculate overall program progress percentage
function calculate_program_progress($customer_id, $program_id) {
    global $conn;
    
    $customer_id = (int)$customer_id;
    $program_id = (int)$program_id;
    
    // Get total number of steps
    $total_steps_query = "SELECT COUNT(*) as total FROM program_steps WHERE program_id = $program_id";
    $total_steps_result = mysqli_query($conn, $total_steps_query);
    $total_steps = mysqli_fetch_assoc($total_steps_result)['total'];
    
    if ($total_steps == 0) {
        return 0; // No steps in program
    }
    
    // Get completed steps
    $completed_steps_query = "SELECT COUNT(*) as completed 
                             FROM step_progress sp
                             JOIN program_steps ps ON sp.step_id = ps.id
                             WHERE sp.customer_id = $customer_id 
                             AND ps.program_id = $program_id 
                             AND sp.status = 'completed'";
    $completed_steps_result = mysqli_query($conn, $completed_steps_query);
    $completed_steps = mysqli_fetch_assoc($completed_steps_result)['completed'];
    
    // Calculate percentage
    $percentage = ($completed_steps / $total_steps) * 100;
    return round($percentage);
}

// Count pending approval steps
function count_pending_approval_steps($customer_id, $program_id) {
    global $conn;
    
    $customer_id = (int)$customer_id;
    $program_id = (int)$program_id;
    
    $query = "SELECT COUNT(*) as pending_count 
              FROM step_progress sp
              JOIN program_steps ps ON sp.step_id = ps.id
              WHERE sp.customer_id = $customer_id 
              AND ps.program_id = $program_id 
              AND sp.status = 'pending_approval'";
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    return (int)$row['pending_count'];
}

// The following function injects JavaScript to fix modal display issues
function modal_fix_script() {
    $script = '
    <script>
    // Fix modal issue by preventing immediate closing
    document.addEventListener("DOMContentLoaded", function() {
        // Prevent modals from auto-closing
        const modals = document.querySelectorAll(".modal");
        
        modals.forEach(modal => {
            // Store modal instance
            const modalInstance = new bootstrap.Modal(modal);
            
            // Prevent modal from closing automatically
            modal.addEventListener("shown.bs.modal", function(event) {
                event.stopPropagation();
                
                // Make sure the backdrop is properly applied
                document.body.classList.add("modal-open");
                if (!document.querySelector(".modal-backdrop")) {
                    const backdrop = document.createElement("div");
                    backdrop.classList.add("modal-backdrop", "fade", "show");
                    document.body.appendChild(backdrop);
                }
            });
            
            // Handle proper modal closing
            const closeButtons = modal.querySelectorAll(".btn-close, .modal-footer .btn-secondary");
            closeButtons.forEach(button => {
                button.addEventListener("click", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    modalInstance.hide();
                    
                    // Clean up backdrop and body class
                    setTimeout(function() {
                        if (document.querySelector(".modal-backdrop")) {
                            document.querySelector(".modal-backdrop").remove();
                        }
                        
                        if (!document.querySelector(".modal.show")) {
                            document.body.classList.remove("modal-open");
                        }
                    }, 200);
                });
            });
        });
    });
    </script>
    ';
    
    return $script;
}

// Chat Functions

// Send a message from sender to receiver
function send_message($sender_id, $receiver_id, $message) {
    global $conn;
    
    $sender_id = (int)$sender_id;
    $receiver_id = (int)$receiver_id;
    $message = sanitize_input($message);
    
    $query = "INSERT INTO chat_messages (sender_id, receiver_id, message) 
              VALUES ($sender_id, $receiver_id, '$message')";
    
    return mysqli_query($conn, $query);
}

// Get conversation between two users
function get_conversation($user1_id, $user2_id) {
    global $conn;
    
    $user1_id = (int)$user1_id;
    $user2_id = (int)$user2_id;
    
    $query = "SELECT * FROM chat_messages 
              WHERE (sender_id = $user1_id AND receiver_id = $user2_id) 
              OR (sender_id = $user2_id AND receiver_id = $user1_id) 
              ORDER BY created_at ASC";
    
    $result = mysqli_query($conn, $query);
    
    $messages = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }
    
    return $messages;
}

// Get all contacts (people the user has messaged with)
function get_chat_contacts($user_id) {
    global $conn;
    
    $user_id = (int)$user_id;
    
    $query = "SELECT DISTINCT 
                CASE 
                    WHEN sender_id = $user_id THEN receiver_id 
                    ELSE sender_id 
                END as contact_id,
                u.name,
                u.user_type,
                (SELECT created_at 
                 FROM chat_messages 
                 WHERE (sender_id = $user_id AND receiver_id = contact_id) 
                    OR (sender_id = contact_id AND receiver_id = $user_id) 
                 ORDER BY created_at DESC 
                 LIMIT 1) as last_message_time,
                (SELECT COUNT(*) 
                 FROM chat_messages 
                 WHERE sender_id = contact_id 
                    AND receiver_id = $user_id 
                    AND is_read = 0) as unread_count
              FROM chat_messages cm
              JOIN users u ON u.id = CASE 
                                WHEN cm.sender_id = $user_id THEN cm.receiver_id 
                                ELSE cm.sender_id 
                             END
              WHERE sender_id = $user_id OR receiver_id = $user_id
              ORDER BY last_message_time DESC";
    
    $result = mysqli_query($conn, $query);
    
    $contacts = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row;
    }
    
    return $contacts;
}

// Mark all messages from a sender to a receiver as read
function mark_messages_as_read($sender_id, $receiver_id) {
    global $conn;
    
    $sender_id = (int)$sender_id;
    $receiver_id = (int)$receiver_id;
    
    $query = "UPDATE chat_messages 
              SET is_read = 1 
              WHERE sender_id = $sender_id AND receiver_id = $receiver_id AND is_read = 0";
    
    return mysqli_query($conn, $query);
}

// Count unread messages for a user
function count_unread_messages($user_id) {
    global $conn;
    
    $user_id = (int)$user_id;
    
    $query = "SELECT COUNT(*) as unread_count 
              FROM chat_messages 
              WHERE receiver_id = $user_id AND is_read = 0";
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    return (int)$row['unread_count'];
}

// Get messages newer than a specific message ID
function get_new_messages($user1_id, $user2_id, $last_message_id = 0) {
    global $conn;
    
    $user1_id = (int)$user1_id;
    $user2_id = (int)$user2_id;
    $last_message_id = (int)$last_message_id;
    
    $query = "SELECT * FROM chat_messages 
              WHERE ((sender_id = $user1_id AND receiver_id = $user2_id) 
              OR (sender_id = $user2_id AND receiver_id = $user1_id))
              AND id > $last_message_id
              ORDER BY created_at ASC";
    
    $result = mysqli_query($conn, $query);
    
    $messages = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }
    
    return $messages;
}
?> 