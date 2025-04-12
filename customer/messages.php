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

// Get customer's coach
$coach_query = "SELECT u.id, u.name FROM users u
               JOIN coach_customer cc ON u.id = cc.coach_id
               WHERE cc.customer_id = $user_id";
$coach_result = mysqli_query($conn, $coach_query);
$coach = mysqli_num_rows($coach_result) > 0 ? mysqli_fetch_assoc($coach_result) : null;

// Handle sending a new message
if (isset($_POST['send_message']) && isset($_POST['receiver_id']) && isset($_POST['message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $message = $_POST['message'];
    
    if (send_message($user_id, $receiver_id, $message)) {
        // Message sent successfully
        $_SESSION['success_message'] = "Message sent successfully";
    } else {
        // Error sending message
        $_SESSION['error_message'] = "Error sending message";
    }
    
    // Redirect to avoid form resubmission
    header("Location: messages.php");
    exit();
}

// Get conversation with coach if coach exists
$messages = [];
$last_message_id = 0;
if ($coach) {
    // Mark messages from coach as read
    mark_messages_as_read($coach['id'], $user_id);
    
    // Get messages
    $messages = get_conversation($user_id, $coach['id']);
    
    // Get the ID of the last message
    if (!empty($messages)) {
        $last_message_id = end($messages)['id'];
        reset($messages); // Reset the array pointer
    }
}

// Store success/error messages from session and then clear them
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear the session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FitTrack</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    .message-container {
        height: 60vh;
        max-height: 500px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 0 0 5px 5px;
        scroll-behavior: smooth;
        position: relative;
    }
    
    .scroll-bottom-btn {
        position: fixed;
        bottom: unset;
        right: unset;
        width: 40px;
        height: 40px;
        background-color: rgba(37, 99, 235, 0.9);
        color: white;
        border-radius: 50%;
        display: none;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
        z-index: 1000;
        border: none;
        transition: all 0.2s ease-in-out;
        margin: 0;
        padding: 0;
    }
    
    .scroll-bottom-btn:hover {
        background-color: rgba(29, 78, 216, 1);
        transform: scale(1.1);
    }
    
    .scroll-bottom-btn i {
        font-size: 1.2rem;
    }
    
    .message {
        max-width: 75%;
        margin-bottom: 10px;
        padding: 8px 12px;
        border-radius: 10px;
        position: relative;
        word-wrap: break-word;
    }
    
    .message.sent {
        align-self: flex-end;
        background-color: #dcf8c6;
        margin-left: 20%;
    }
    
    .message.received {
        align-self: flex-start;
        background-color: #f1f1f1;
        margin-right: 20%;
    }
    
    .message-time {
        font-size: 0.7rem;
        color: #777;
        text-align: right;
        margin-top: 4px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }
        
    .read-status {
        margin-left: 4px;
        font-size: 0.8rem;
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
                            <a class="text-gray-300 hover:text-white block py-2" href="dashboard.php">Dashboard</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">My Programs</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="progress.php">Progress Tracker</a>
                        </li>
                        <li>
                            <a class="text-white font-medium block py-2" href="messages.php">
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
            <div class="w-full">
                <h1 class="text-2xl font-bold mb-4">Messages</h1>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mb-6">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <?php if ($coach): ?>
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <div class="flex justify-between items-center">
                            <h5 class="font-medium">
                                <i class="fas fa-user-tie mr-2"></i>
                                Chat with <?php echo $coach['name']; ?>
                            </h5>
                        </div>
                    </div>
                    <div>
                        <div class="message-container" id="message-container">
                            <button id="scroll-to-bottom" class="scroll-bottom-btn" title="Scroll to bottom">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <?php if (count($messages) > 0): ?>
                                <?php foreach ($messages as $message): ?>
                                    <?php 
                                        $is_sent = $message['sender_id'] == $user_id;
                                        $message_class = $is_sent ? 'sent' : 'received';
                                        $time = date('h:i A, M d', strtotime($message['created_at']));
                                        $is_read = isset($message['is_read']) ? $message['is_read'] : false;
                                    ?>
                                    <div class="message <?php echo $message_class; ?>" data-message-id="<?php echo $message['id']; ?>">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        <div class="message-time">
                                            <?php echo $time; ?>
                                            <?php if ($is_sent): ?>
                                                <span class="read-status">
                                                    <i class="fas <?php echo $is_read ? 'fa-check-double text-blue-600' : 'fa-check'; ?>" 
                                                       title="<?php echo $is_read ? 'Read' : 'Sent'; ?>"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-gray-500 py-10">
                                    <i class="fas fa-comments text-5xl mb-4"></i>
                                    <p>Start a conversation with your coach!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="border-t border-gray-200 p-4 bg-gray-50">
                            <form id="message-form" action="javascript:void(0);" method="post">
                                <input type="hidden" name="receiver_id" id="receiver_id" value="<?php echo $coach ? $coach['id'] : ''; ?>">
                                <input type="hidden" name="last_message_id" id="last_message_id" value="<?php echo $last_message_id; ?>">
                                <div class="flex">
                                    <textarea class="flex-grow border border-gray-300 rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                              name="message" id="message-input" placeholder="Type your message..." rows="2" required></textarea>
                                    <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-r-lg px-4 py-2 flex items-center transition" 
                                            type="submit" id="send-button" onclick="if(typeof handleFormSubmit === 'function') handleFormSubmit(event);">
                                        <i class="fas fa-paper-plane mr-1"></i> Send
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-user-slash text-6xl text-gray-400 mb-4"></i>
                        <h5 class="text-xl font-medium text-gray-700 mb-2">No Coach Assigned</h5>
                        <p class="text-gray-500">You don't have a coach assigned yet. Once you're assigned a coach, you'll be able to chat with them here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const messageContainer = document.getElementById('message-container');
            const messageForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            const receiverId = document.getElementById('receiver_id');
            const lastMessageIdInput = document.getElementById('last_message_id');
            const scrollButton = document.getElementById('scroll-to-bottom');
            let lastMessageId = parseInt(lastMessageIdInput.value) || 0;
            let isPolling = false;
            
            console.log('DOM loaded, messageForm:', messageForm); // Debug - check if form is found
            
            // Attach form submit event immediately after getting the elements
            if (messageForm) {
                console.log('Attaching submit listener to form');
                messageForm.addEventListener('submit', handleFormSubmit);
            } else {
                console.error('Message form not found!');
            }
            
            // Position the scroll button at the correct position
            function positionScrollButton() {
                if (scrollButton && messageContainer) {
                    const rect = messageContainer.getBoundingClientRect();
                    // Calculate position (15px from bottom right corner of the message container)
                    // We subtract button size to keep it fully within the container
                    const buttonSize = 40; // Same as width/height in CSS
                    const padding = 15; // Padding from edge
                    
                    scrollButton.style.bottom = (window.innerHeight - rect.bottom + padding) + 'px';
                    scrollButton.style.right = (window.innerWidth - rect.right + padding) + 'px';
                    
                    // Make sure the button is visible when the container is in view
                    if (rect.bottom < 0 || rect.top > window.innerHeight || 
                        rect.right < 0 || rect.left > window.innerWidth) {
                        scrollButton.style.display = 'none';
                    }
                }
            }
            
            // Position the button initially
            positionScrollButton();
            
            // Update position on window resize
            window.addEventListener('resize', positionScrollButton);
            
            // Auto-scroll to bottom of messages on page load
            if (messageContainer) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            }
            
            // Function to format a message
            function formatMessage(message) {
                const messageClass = message.is_sent ? 'sent' : 'received';
                    const readStatus = message.is_sent ? 
                        `<span class="read-status">
                            <i class="fas ${message.is_read ? 'fa-check-double text-blue-600' : 'fa-check'}" 
                               title="${message.is_read ? 'Read' : 'Sent'}"></i>
                         </span>` : '';
                         
                return `
                        <div class="message ${messageClass}" data-message-id="${message.id}">
                            ${message.message.replace(/\n/g, '<br>')}
                            <div class="message-time">
                                ${message.time}
                                ${readStatus}
                            </div>
                    </div>
                `;
            }
            
            // Function to add a message to the chat
            function addMessageToChat(message) {
                if (messageContainer) {
                    const wasAtBottom = messageContainer.scrollHeight - messageContainer.clientHeight <= messageContainer.scrollTop + 50;
                    messageContainer.insertAdjacentHTML('beforeend', formatMessage(message));
                    
                    // Update last message ID
                    if (message.id > lastMessageId) {
                        lastMessageId = message.id;
                        lastMessageIdInput.value = lastMessageId;
                    }
                    
                    // Auto-scroll if user was at the bottom
                    if (wasAtBottom) {
                        messageContainer.scrollTop = messageContainer.scrollHeight;
                    }
                    
                    // Update scroll button position
                    positionScrollButton();
                }
            }
            
            // Add a function to check if scroll is at bottom
            function isScrolledToBottom() {
                if (!messageContainer) return false;
                const threshold = 100; // pixels from bottom to consider "at bottom"
                return messageContainer.scrollHeight - messageContainer.clientHeight - messageContainer.scrollTop <= threshold;
            }
            
            // Add scroll event listener to show/hide a "scroll to bottom" button if needed
            if (messageContainer) {
                messageContainer.addEventListener('scroll', function() {
                    if (scrollButton) {
                        scrollButton.style.display = isScrolledToBottom() ? 'none' : 'flex';
                        positionScrollButton(); // Update position on scroll
                    }
                });
            }
            
            // Add a function to scroll to bottom
            function scrollToBottom() {
                if (messageContainer) {
                    messageContainer.scrollTop = messageContainer.scrollHeight;
                }
            }
            
            // Set up scroll to bottom button
            if (scrollButton) {
                scrollButton.addEventListener('click', scrollToBottom);
                
                // Initially hide the button if already at bottom
                scrollButton.style.display = isScrolledToBottom() ? 'none' : 'flex';
            }
            
            // Function to handle form submission
            function handleFormSubmit(e) {
                e.preventDefault();
                console.log('Form submitted'); // Debug log
                
                const message = messageInput.value.trim();
                const receiver = receiverId.value;
                
                if (!message || !receiver) return;
                
                // Disable submit button during send
                const sendButton = document.getElementById('send-button');
                sendButton.disabled = true;
                
                // Send message via AJAX
                const formData = new FormData();
                formData.append('receiver_id', receiver);
                formData.append('message', message);
                
                fetch('../includes/send_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server responded with status: ${response.status}`);
                    }
                    // Check if response contains valid JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        // Log the actual text response if it's not JSON
                        return response.text().then(text => {
                            console.error('Server returned non-JSON response:', text);
                            throw new Error('Server returned non-JSON response');
                        });
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Add message to chat
                        addMessageToChat(data.message);
                        
                        // Clear input using multiple methods to ensure it works
                        console.log('Clearing input field'); // Debug log
                        messageInput.value = '';
                        document.getElementById('message-input').value = '';
                        messageForm.reset();
                        
                        // Update last message ID
                        lastMessageId = data.message.id;
                        lastMessageIdInput.value = lastMessageId;
                        
                        // Always scroll to bottom when sending a message
                        scrollToBottom();
                    } else {
                        console.error('Failed to send message:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                })
                .finally(() => {
                    sendButton.disabled = false;
                });
            }
            
            // Function to poll for new messages
            function pollForMessages() {
                if (!receiverId.value || isPolling) return;
                
                isPolling = true;
                
                fetch(`../includes/get_new_messages.php?other_user_id=${receiverId.value}&last_message_id=${lastMessageId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.messages && data.messages.length > 0) {
                            // Add new messages to chat
                            data.messages.forEach(message => {
                                addMessageToChat(message);
                            });
                            
                            // Show/hide scroll button as needed
                            if (scrollButton) {
                                scrollButton.style.display = isScrolledToBottom() ? 'none' : 'flex';
                                positionScrollButton(); // Update position when new messages arrive
                            }
                            
                            // Update unread count in navbar if needed
                            const unreadBadge = document.querySelector('.nav-link .badge');
                            if (unreadBadge && data.unread_count > 0) {
                                unreadBadge.textContent = data.unread_count;
                                unreadBadge.style.display = 'inline';
                            } else if (unreadBadge && data.unread_count === 0) {
                                unreadBadge.style.display = 'none';
                            }
                            
                            // Update read status for sent messages
                            if (data.read_status_updates && Object.keys(data.read_status_updates).length > 0) {
                                updateReadStatus(data.read_status_updates);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error polling for messages:', error);
                    })
                    .finally(() => {
                        isPolling = false;
                    });
            }
            
            // Function to update read status for sent messages
            function updateReadStatus(updates) {
                // Find all sent messages in the current conversation
                const messageElements = messageContainer.querySelectorAll('.message.sent');
                
                messageElements.forEach(messageEl => {
                    // Get the message ID from the data attribute
                    const messageId = messageEl.getAttribute('data-message-id');
                    
                    if (!messageId) {
                        return;
                    }
                    
                    // Check if this message is in our updates
                    if (updates[messageId]) {
                        // Update the read status icon
                        const readStatusIcon = messageEl.querySelector('.read-status i');
                        if (readStatusIcon) {
                            readStatusIcon.className = 'fas fa-check-double text-blue-600';
                            readStatusIcon.setAttribute('title', 'Read');
                        }
                    }
                });
            }
            
            // Set up event listeners
            if (messageForm) {
                console.log('Skipping duplicate event listener attachment');
            }
            
            // Start polling for new messages
            if (receiverId.value) {
                // Initial poll
                pollForMessages();
                
                // Poll every 3 seconds
                setInterval(pollForMessages, 3000);
                
                // Also poll when user focuses the window
                window.addEventListener('focus', pollForMessages);
            }
            
            // Function to update the navbar unread message badge
            function updateNavbarBadge() {
                fetch('../includes/get_unread_count.php')
                    .then(response => response.json())
                    .then(data => {
                        // Find the Messages link
                        const messagesLink = document.querySelector('a[href="messages.php"]');
                        if (!messagesLink) return;
                        
                        // Check if badge already exists
                        let badge = messagesLink.querySelector('.badge');
                        
                        if (data.unread_count > 0) {
                            if (!badge) {
                                // Create new badge
                                badge = document.createElement('span');
                                badge.className = 'ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-red-600';
                                messagesLink.appendChild(badge);
                            }
                            badge.textContent = data.unread_count;
                        } else if (badge) {
                            badge.remove();
                        }
                    })
                    .catch(error => {
                        console.error('Error updating navbar badge:', error);
                    });
            }

            // Send message when Enter key is pressed
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
            
            // Initial update
            updateNavbarBadge();
            
            // Update badge every 5 seconds
            setInterval(updateNavbarBadge, 5000);
            
            // Also ensure the button is correctly positioned when messages are loaded initially
            window.addEventListener('load', function() {
                positionScrollButton();
                
                // Reposition after a slight delay to account for any layout shifts
                setTimeout(positionScrollButton, 100);
            });
            
            // Mobile menu toggle
            const navbarToggle = document.getElementById('navbarToggle');
            if (navbarToggle) {
                navbarToggle.addEventListener('click', function() {
                    const menu = document.getElementById('navbarMenu');
                    menu.classList.toggle('hidden');
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
        });
    </script>
</body>
</html> 