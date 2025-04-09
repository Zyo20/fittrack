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

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'coach') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get all customers for this coach
$customers = get_coach_customers($user_id);

// If a specific customer is selected, show conversation
$current_customer = null;
$messages = [];
$last_message_id = 0;

if (isset($_GET['customer_id'])) {
    $customer_id = (int)$_GET['customer_id'];
    
    // Verify this customer is assigned to the coach
    foreach ($customers as $customer) {
        if ($customer['customer_id'] == $customer_id) {
            $current_customer = $customer;
            break;
        }
    }
    
    if ($current_customer) {
        // Mark messages from this customer as read
        mark_messages_as_read($customer_id, $user_id);
        
        // Get conversation with customer
        $messages = get_conversation($user_id, $customer_id);
        
        // Get the ID of the last message
        if (!empty($messages)) {
            $last_message_id = end($messages)['id'];
            reset($messages); // Reset the array pointer
        }
    }
}

// Get all contacts (for sidebar)
$contacts = get_chat_contacts($user_id);

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
    header("Location: messages.php?customer_id=" . $receiver_id);
    exit();
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
    <title>Messages - OpFit Coach</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .message-container {
            height: 400px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            max-width: 75%;
            margin-bottom: 10px;
            padding: 8px 12px;
            border-radius: 10px;
            position: relative;
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
                            <a class="text-gray-300 hover:text-white block py-2" href="customers.php">My Customers</a>
                        </li>
                        <li>
                            <a class="text-gray-300 hover:text-white block py-2" href="programs.php">Programs</a>
                        </li>
                        <li>
                            <a class="text-white font-medium block py-2" href="messages.php">Messages</a>
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
                <h1 class="text-2xl font-bold mb-4">Messages</h1>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mt-4" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="flex flex-col md:flex-row gap-6">
            <div class="w-full md:w-1/3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-600 text-white px-4 py-3">
                        <h5 class="font-medium">My Customers</h5>
                    </div>
                    <div>
                        <?php if (count($customers) > 0): ?>
                            <div class="contacts-list h-[500px] overflow-y-auto">
                                <?php foreach ($customers as $customer): ?>
                                    <?php 
                                        $is_active = $current_customer && $current_customer['customer_id'] == $customer['customer_id']; 
                                        $unread_count = 0;
                                        
                                        // Check for unread messages
                                        foreach ($contacts as $contact) {
                                            if ($contact['contact_id'] == $customer['customer_id']) {
                                                $unread_count = $contact['unread_count'];
                                                break;
                                            }
                                        }
                                    ?>
                                    <a href="messages.php?customer_id=<?php echo $customer['customer_id']; ?>" 
                                       class="contact-item block px-4 py-3 border-b border-gray-200 hover:bg-gray-50 transition-all <?php echo $is_active ? 'bg-gray-100 border-l-4 border-blue-500' : ''; ?>">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <i class="fas fa-user mr-2 text-gray-600"></i>
                                                <span class="font-medium text-gray-800"><?php echo $customer['name']; ?></span>
                                            </div>
                                            <?php if ($unread_count > 0): ?>
                                                <span class="unread-badge inline-block bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                                                    <?php echo $unread_count; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-gray-500"><?php echo $customer['email']; ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-6 text-center">
                                <p class="text-gray-500">No customers assigned to you yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="w-full md:w-2/3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <?php if ($current_customer): ?>
                        <div class="bg-blue-600 text-white px-4 py-3">
                            <div class="flex justify-between items-center">
                                <h5 class="font-medium">
                                    <i class="fas fa-user mr-2"></i>
                                    Chat with <?php echo $current_customer['name']; ?>
                                </h5>
                            </div>
                        </div>
                        <div>
                            <div class="message-container p-4" id="message-container">
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
                                        <p>Start a conversation with <?php echo $current_customer['name']; ?>!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="border-t border-gray-200 p-4 bg-gray-50">
                                <form id="message-form" action="javascript:void(0);" method="post">
                                    <input type="hidden" name="receiver_id" id="receiver_id" value="<?php echo $current_customer ? $current_customer['customer_id'] : ''; ?>">
                                    <input type="hidden" name="last_message_id" id="last_message_id" value="<?php echo $last_message_id; ?>">
                                    <div class="flex">
                                        <textarea class="flex-grow border border-gray-300 rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                  name="message" id="message-input" placeholder="Type your message..." rows="2" required></textarea>
                                        <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-r-lg px-4 py-2 flex items-center transition" 
                                                type="submit" id="send-button">
                                            <i class="fas fa-paper-plane mr-1"></i> Send
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-12 text-center">
                            <i class="fas fa-comments text-6xl text-gray-400 mb-4"></i>
                            <h5 class="text-xl font-medium text-gray-700 mb-2">Select a Customer</h5>
                            <p class="text-gray-500">Select a customer from the list to start a conversation.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle dropdown
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
            
            const messageContainer = document.getElementById('message-container');
            const messageForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            const receiverId = document.getElementById('receiver_id');
            const lastMessageIdInput = document.getElementById('last_message_id');
            let lastMessageId = parseInt(lastMessageIdInput.value) || 0;
            let isPolling = false;
            
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
                }
            }
            
            // Function to handle form submission
            function handleFormSubmit(e) {
                e.preventDefault();
                
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
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Add message to chat
                        addMessageToChat(data.message);
                        
                        // Clear input
                        messageInput.value = '';
                        
                        // Update last message ID
                        lastMessageId = data.message.id;
                        lastMessageIdInput.value = lastMessageId;
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
            
            // Function to update sidebar unread counts
            function updateSidebarUnreadCounts(customerId, newUnreadCount) {
                // Find the contact in the sidebar
                const contactItems = document.querySelectorAll('.contact-item');
                contactItems.forEach(item => {
                    const href = item.getAttribute('href');
                    if (href && href.includes(`customer_id=${customerId}`)) {
                        // Find badge if it exists
                        let badge = item.querySelector('.unread-badge');
                        
                        if (newUnreadCount > 0) {
                            if (badge) {
                                badge.textContent = newUnreadCount;
                            } else {
                                // Create new badge
                                const badgeContainer = item.querySelector('.flex');
                                if (badgeContainer) {
                                    const newBadge = document.createElement('span');
                                    newBadge.className = 'unread-badge inline-block bg-red-500 text-white text-xs px-2 py-1 rounded-full';
                                    newBadge.textContent = newUnreadCount;
                                    badgeContainer.appendChild(newBadge);
                                }
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    }
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
                            
                            // Update unread count in navbar if needed
                            const unreadBadge = document.querySelector('.nav-link .badge');
                            if (unreadBadge && data.unread_count > 0) {
                                unreadBadge.textContent = data.unread_count;
                                unreadBadge.style.display = 'inline';
                            } else if (unreadBadge && data.unread_count === 0) {
                                unreadBadge.style.display = 'none';
                            }
                            
                            // Update sidebar unread counts
                            if (receiverId.value) {
                                updateSidebarUnreadCounts(receiverId.value, 0); // Clear unread for current conversation
                            }
                        }
                        
                        // Update read status for sent messages
                        if (data.read_status_updates && Object.keys(data.read_status_updates).length > 0) {
                            updateReadStatus(data.read_status_updates);
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
                    // Try to find the message ID in the data attribute
                    const messageId = messageEl.getAttribute('data-message-id');
                    
                    // If we can't find it by data attribute, we'll need to search by content
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
                messageForm.addEventListener('submit', handleFormSubmit);
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
            
            // Poll for updates to all conversations for sidebar
            function pollForAllConversations() {
                // Only if we're on the messages page and have customers
                if (document.querySelector('.contacts-list')) {
                    fetch('../includes/get_chat_contacts.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.contacts) {
                                // Update sidebar unread counts
                                data.contacts.forEach(contact => {
                                    // Skip current conversation, it's handled by the regular polling
                                    if (receiverId.value && contact.contact_id == receiverId.value) return;
                                    
                                    updateSidebarUnreadCounts(contact.contact_id, contact.unread_count);
                                });
                                
                                // Update navbar count
                                updateNavbarBadge(data.total_unread);
                            }
                        })
                        .catch(error => {
                            console.error('Error polling for all conversations:', error);
                        });
                }
            }
            
            // Function to update the navbar unread message badge
            function updateNavbarBadge(unreadCount) {
                // Find the Messages nav link
                const messagesNavLink = document.querySelector('a[href="messages.php"]:not(.block)');
                if (!messagesNavLink) return;
                
                // Check if badge already exists
                let badge = messagesNavLink.querySelector('.badge');
                
                if (unreadCount > 0) {
                    if (!badge) {
                        // Create new badge
                        badge = document.createElement('span');
                        badge.className = 'badge inline-block bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-1';
                        messagesNavLink.appendChild(badge);
                    }
                    badge.textContent = unreadCount;
                } else if (badge) {
                    badge.remove();
                }
            }
            
            // Direct check for unread messages (useful when page first loads)
            function checkUnreadMessages() {
                fetch('../includes/get_unread_count.php')
                    .then(response => response.json())
                    .then(data => {
                        updateNavbarBadge(data.unread_count);
                    })
                    .catch(error => {
                        console.error('Error checking unread messages:', error);
                    });
            }
            
            // Initial check
            checkUnreadMessages();
            
            // Poll for all conversations every 10 seconds to update sidebar
            setInterval(pollForAllConversations, 10000);
            
            // Also check for unread messages every 5 seconds for more responsive UI
            setInterval(checkUnreadMessages, 5000);
        });
    </script>
</body>
</html> 