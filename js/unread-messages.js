/**
 * Handles polling for unread messages and updating the navbar
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find the messages link in the navbar
    const messagesLink = document.querySelector('.navbar .nav-link[href$="messages.php"]');
    if (!messagesLink) return;

    // Create or get badge element
    let badgeElement = messagesLink.querySelector('.badge');
    if (!badgeElement) {
        badgeElement = document.createElement('span');
        badgeElement.className = 'badge rounded-pill bg-danger ms-1';
        badgeElement.style.display = 'none';
        messagesLink.appendChild(badgeElement);
    }

    // Function to update the badge
    function updateUnreadBadge(count) {
        if (count > 0) {
            badgeElement.textContent = count;
            badgeElement.style.display = 'inline';
        } else {
            badgeElement.style.display = 'none';
        }
    }

    // Function to check for unread messages
    function checkUnreadMessages() {
        fetch('../includes/get_unread_count.php')
            .then(response => response.json())
            .then(data => {
                if (data.hasOwnProperty('unread_count')) {
                    updateUnreadBadge(data.unread_count);
                }
            })
            .catch(error => console.error('Error checking unread messages:', error));
    }

    // Initial check
    checkUnreadMessages();

    // Poll every 10 seconds
    setInterval(checkUnreadMessages, 10000);

    // Also check when the window gets focus
    window.addEventListener('focus', checkUnreadMessages);
}); 