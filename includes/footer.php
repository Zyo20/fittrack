<footer class="relative w-full bg-gray-800 text-white py-3 mt-8">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center">
            <p>&copy; <?php echo date('Y'); ?> OpFit - Gym Progress Tracking System</p>
            <div class="flex space-x-3">
                <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>
</footer>

<!-- Include the chatbot on all pages -->
<?php include_once 'chatbot.php'; ?>

<script>
    // Global script to ensure dropdowns are properly initialized
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dropdown toggles manually with Tailwind
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Find the dropdown menu
                const menu = this.nextElementSibling;
                if (menu && menu.classList.contains('dropdown-menu')) {
                    menu.classList.toggle('hidden');
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            const menus = document.querySelectorAll('.dropdown-menu');
            menus.forEach(menu => {
                if (!menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                }
            });
        });
    });
</script>