# FitTrack - Gym Progress Tracking System

FitTrack is a comprehensive web application built with PHP, MySQL, and Tailwind CSS that allows gyms to manage customer-coach relationships, program approvals, progress tracking, and messaging.

## Features

- **Customer Management**: Track customer information and assign coaches
- **Coach-Customer Relationship**: Each customer is assigned to a single coach
- **Program Approval System**: Customers select programs, coaches approve or reject
- **Progress Tracking**: Monitor customer's weight, height, and other metrics
- **Workout Logging**: Record and track workout sessions with duration and notes
- **Messaging System**: Communication between coaches and customers with read receipts
- **Unread Message Notifications**: Visual indicators for unread messages in the navigation bar
- **Mobile-Responsive Design**: Modern interface that works on all devices
- **Role-based Access Control**: Different interfaces for customers, coaches, and admins
- **Program Steps & Progress**: Detailed program steps with progress tracking
- **Detailed Reporting**: Track customer progress over time

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)

## Installation

1. Clone the repository to your web server's document root:

2. Create a MySQL database named `fittrack`

3. Import the database schema and sample data:
   ```
   mysql -u username -p fittrack < fittrack.sql
   ```

4. Update the database connection settings in `includes/db_connect.php` with your credentials:
   ```php
   $host = 'localhost';
   $username = 'your_db_username';
   $password = 'your_db_password';
   $database = 'fittrack';
   ```

5. Set up the messaging tables:
   ```
   php create_chat_table.php
   ```

6. Ensure the web server has write permissions to the necessary directories:
   ```
   chmod -R 755 img/
   ```

## Default Users

The system comes with pre-configured users for testing:

### Admin
- Email: admin@fittrack.com
- Password: admin123

### Coaches
- Email: john@fittrack.com
- Password: coach123

- Email: jane@fittrack.com
- Password: coach123

### Customers
- Email: mike@example.com
- Password: customer123

- Email: sarah@example.com
- Password: customer123

- Email: david@example.com
- Password: customer123

- Email: lisa@example.com
- Password: customer123

## Usage

1. **Customer Flow**:
   - Register as a customer
   - Login to the customer dashboard
   - View assigned coach
   - Request programs
   - Track progress by adding weight, height measurements
   - Log workouts and track program step completion
   - Message your coach with questions or updates

2. **Coach Flow**:
   - Login as a coach
   - View assigned customers
   - Approve/reject program requests
   - Monitor customer progress
   - Communicate with customers through the messaging system
   - Assign programs to customers

3. **Admin Flow**:
   - Login as admin
   - Manage users (coaches and customers)
   - Assign coaches to customers
   - Create and manage programs
   - Generate reports

## Messaging System

The FitTrack messaging system provides:

- **Real-time Updates**: Messages appear without page refresh using AJAX
- **Read Receipts**: Visual indicators show when messages have been read
- **Unread Message Notifications**: Counters in the navigation bar show number of unread messages
- **Scrollable Chat Interface**: Chat history is contained in a scrollable container

## File Structure

```
fittrack/
├── admin/
│   ├── dashboard.php
│   ├── users.php
│   ├── user_details.php
│   ├── programs.php
│   ├── edit_program.php
│   ├── assignments.php
│   ├── reports.php
│   └── requests.php
├── coach/
│   ├── dashboard.php
│   ├── customers.php
│   ├── customer_details.php
│   ├── messages.php
│   ├── programs.php
│   └── assign_program.php
├── customer/
│   ├── dashboard.php
│   ├── programs.php
│   ├── progress.php
│   ├── messages.php
│   ├── select_coach.php
│   └── program_progress.php
├── css/
│   └── style.css
├── includes/
│   ├── db_connect.php
│   ├── functions.php
│   ├── send_message.php
│   ├── get_new_messages.php
│   ├── get_chat_contacts.php
│   └── get_unread_count.php
├── js/
│   ├── script.js
│   ├── tailwind-utilities.js
│   └── unread-messages.js
├── img/
├── index.php
├── login.php
├── logout.php
├── register.php
├── profile.php
├── .htaccess
├── fittrack.sql
├── chat_table.sql
├── create_chat_table.php
└── README.md
```

## Recent Updates

- Added Tailwind CSS for modern, responsive UI
- Enhanced user profile management
- Improved program assignment workflow
- Added detailed program progress tracking
- Enhanced reporting capabilities for admins

## License

This project is licensed under the MIT License. 