<?php
// This component will add a floating chatbot to any page it's included on
?>

<!-- Chatbot UI -->
<div id="fittrack-chatbot" class="chatbot-container">
    <div class="chatbot-header">
        <h4>OpFit Assistant</h4>
        <button id="minimize-chatbot" class="btn-icon">
            <i class="fas fa-minus"></i>
        </button>
    </div>
    <div class="chatbot-body">
        <div id="chatbot-messages" class="messages">
            <!-- Welcome message -->
            <div class="message bot-message">
                <div class="message-content">
                    Hello! I'm your OpFit assistant. How can I help you today? You can ask me about how to use the system, program features, or tracking your progress.
                </div>
            </div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatbot-input-text" placeholder="Type your question here..." autocomplete="off">
            <button id="send-chatbot-message" class="btn-icon">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    <button id="chatbot-toggle" class="chatbot-toggle">
        <i class="fas fa-comment"></i>
    </button>
</div>

<style>
    /* Chatbot styling */
    .chatbot-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 350px;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        z-index: 9999;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        max-height: 450px; /* Ensure it fits in viewport */
    }
    
    .chatbot-container.minimized {
        height: 50px;
        max-height: 50px;
    }
    
    .chatbot-container.hidden {
        transform: translateY(100%);
        height: 450px;
    }
    
    .chatbot-header {
        background-color: #4a7aff;
        color: white;
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        width: 100%;
        box-sizing: border-box;
        flex-shrink: 0; /* Prevent header from shrinking */
        height: 50px; /* Fixed height for header */
    }
    
    .chatbot-header h4 {
        margin: 0;
        font-size: 16px;
        white-space: nowrap; /* Prevent text wrapping */
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .chatbot-body {
        height: 400px;
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow: hidden;
    }
    
    .messages {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        max-height: 340px; /* Ensure room for input area */
    }
    
    .message {
        margin-bottom: 10px;
        max-width: 80%;
        padding: 10px;
        border-radius: 10px;
    }
    
    .user-message {
        background-color: #e9f0ff;
        margin-left: auto;
    }
    
    .bot-message {
        background-color: #f0f0f0;
    }
    
    .chatbot-input {
        display: flex;
        padding: 10px;
        border-top: 1px solid #eee;
        flex-shrink: 0; /* Prevent input from shrinking */
        height: 60px; /* Fixed height for input area */
        box-sizing: border-box;
        align-items: center;
        width: 100%;
    }
    
    .chatbot-input input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
        height: 38px; /* Fixed height for input field */
    }
    
    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        color: #4a7aff;
        padding: 0 10px;
        font-size: 16px;
    }
    
    .chatbot-toggle {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: #4a7aff;
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        z-index: 9998;
    }
    
    /* Initially show the chatbot with just the header visible */
    .chatbot-container {
        height: 450px; 
        transform: translateY(calc(100% - 50px)); /* Only show the header (50px) */
    }
    
    /* Dark mode support */
    .dark-mode .chatbot-container {
        background-color: #2d2d2d;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
    }
    
    .dark-mode .chatbot-header {
        background-color: #3a5aaa;
    }
    
    .dark-mode .user-message {
        background-color: #3a5aaa;
        color: #fff;
    }
    
    .dark-mode .bot-message {
        background-color: #444;
        color: #fff;
    }
    
    .dark-mode .chatbot-input {
        border-top: 1px solid #444;
    }
    
    .dark-mode .chatbot-input input {
        background-color: #333;
        border: 1px solid #555;
        color: #fff;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elements
        const chatbotContainer = document.getElementById('fittrack-chatbot');
        const chatbotToggle = document.getElementById('chatbot-toggle');
        const minimizeButton = document.getElementById('minimize-chatbot');
        const chatbotInput = document.getElementById('chatbot-input-text');
        const sendButton = document.getElementById('send-chatbot-message');
        const messagesContainer = document.getElementById('chatbot-messages');
        const chatbotHeader = document.querySelector('.chatbot-header');
        
        // Initially hide the toggle button since we're showing the header
        chatbotToggle.style.display = 'none';
        
        // Toggle chatbot visibility
        chatbotHeader.addEventListener('click', function(e) {
            // Don't trigger if clicking on the minimize button
            if (e.target === minimizeButton || minimizeButton.contains(e.target)) {
                return;
            }
            
            chatbotContainer.classList.toggle('minimized');
            if (chatbotContainer.classList.contains('minimized')) {
                chatbotContainer.style.transform = 'translateY(calc(100% - 50px))';
            } else {
                chatbotContainer.style.transform = 'translateY(0)';
            }
        });
        
        // Toggle chatbot visibility with button
        chatbotToggle.addEventListener('click', function() {
            chatbotContainer.style.transform = 'translateY(0)';
            chatbotToggle.style.display = 'none';
        });
        
        // Minimize chatbot
        minimizeButton.addEventListener('click', function() {
            if (chatbotContainer.style.transform === 'translateY(0px)') {
                chatbotContainer.style.transform = 'translateY(calc(100% - 50px))';
            } else {
                chatbotContainer.style.transform = 'translateY(calc(100% - 50px))';
                chatbotToggle.style.display = 'flex';
            }
        });
        
        // Function to add a message to the chat
        function addMessage(content, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message');
            messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');
            
            const messageContent = document.createElement('div');
            messageContent.classList.add('message-content');
            messageContent.textContent = content;
            
            messageDiv.appendChild(messageContent);
            messagesContainer.appendChild(messageDiv);
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Function to send a message to the chatbot
        function sendMessage() {
            const message = chatbotInput.value.trim();
            if (message === '') return;
            
            // Add user message to chat
            addMessage(message, true);
            
            // Clear input
            chatbotInput.value = '';
            
            // Show loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.classList.add('message', 'bot-message');
            loadingDiv.innerHTML = '<div class="message-content">Thinking...</div>';
            messagesContainer.appendChild(loadingDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Send to backend with proper path relative to the current page
            fetch(window.location.pathname.includes('/admin/') || 
                  window.location.pathname.includes('/coach/') || 
                  window.location.pathname.includes('/customer/') ? 
                  '../includes/get_chatbot_response.php' : 'includes/get_chatbot_response.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'query=' + encodeURIComponent(message)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Remove loading indicator
                messagesContainer.removeChild(loadingDiv);
                
                // Add response to chat
                if (data.status === 'success') {
                    addMessage(data.message);
                    
                    // Log debug info to console if available
                    if (data.debug) {
                        console.log('Chatbot debug info:', data.debug);
                    }
                } else {
                    addMessage("Sorry, I'm having trouble understanding. Please try again.");
                    console.error('Chatbot error:', data);
                }
            })
            .catch(error => {
                // Remove loading indicator
                if (messagesContainer.contains(loadingDiv)) {
                    messagesContainer.removeChild(loadingDiv);
                }
                addMessage("Sorry, there was an error processing your request. Please make sure the chatbot database is set up.");
                console.error('Error:', error);
                
                // Add help message about setup
                const helpDiv = document.createElement('div');
                helpDiv.classList.add('message', 'bot-message');
                helpDiv.innerHTML = '<div class="message-content">If this is your first time using the chatbot, please run the <a href="../setup_chatbot.php" target="_blank">setup script</a> to initialize the knowledge base.</div>';
                messagesContainer.appendChild(helpDiv);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            });
        }
        
        // Send message when button is clicked
        sendButton.addEventListener('click', sendMessage);
        
        // Send message when Enter key is pressed
        chatbotInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // Expand the chatbot when clicking anywhere in the header except minimize button
        document.querySelector('.chatbot-header').addEventListener('click', function(e) {
            if (!e.target.closest('#minimize-chatbot')) {
                chatbotContainer.style.transform = 'translateY(0)';
            }
        });
    });
</script>