<?php
// Feature announcements array
$feature_announcements = [
    [
        'title' => 'FitTrack Assistant Chatbot',
        'description' => 'Our new AI assistant helps you find answers about using FitTrack. Click on the chat icon at the bottom right of any page to ask questions about programs, tracking progress, or system features.',
        'link' => '#',
        'link_text' => 'Try It Now',
        'icon' => 'fa-solid fa-robot',
        'admin_only_link' => false // Everyone can see this link
    ],
    [
        'title' => 'Real-time Messaging',
        'description' => 'Our messaging system provides real-time updates, read receipts, and unread message notifications. Communicate seamlessly with your coach or customers through our modern, scrollable chat interface.',
        'link' => 'messages.php',
        'link_text' => 'Check Messages',
        'icon' => 'fa-solid fa-message',
        'admin_only_link' => false // Everyone can see this link
    ],
    // Add more announcements here as needed
];

// Function to display feature announcements
function display_feature_announcements() {
    global $feature_announcements;
    
    foreach ($feature_announcements as $announcement) {
        echo '<div class="w-full px-2 mb-3">
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                <div class="flex">
                    <div class="mr-3">
                        <i class="fas ' . htmlspecialchars($announcement['icon']) . ' text-2xl"></i>
                    </div>
                    <div>
                        <h5 class="font-bold">New Feature: ' . htmlspecialchars($announcement['title']) . '</h5>
                        <p>' . htmlspecialchars($announcement['description']) . '</p>';
        
        // Check if the link should be displayed
        if (!empty($announcement['link'])) {
            $show_link = true;
            
            // Check if link is admin-only and user is not an admin
            if (isset($announcement['admin_only_link']) && 
                $announcement['admin_only_link'] === true && 
                (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin')) {
                $show_link = false;
            }
            
            // Only display the link if allowed
            if ($show_link) {
                echo '<a href="' . htmlspecialchars($announcement['link']) . '" class="inline-block mt-2 bg-white hover:bg-gray-100 text-blue-700 font-medium py-1 px-3 border border-blue-500 rounded text-sm">' . 
                     htmlspecialchars($announcement['link_text']) . '</a>';
            }
        }
        
        echo '</div>
                </div>
            </div>
        </div>';
    }
}
?>