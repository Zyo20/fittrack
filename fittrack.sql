-- Create database
CREATE DATABASE IF NOT EXISTS fittrack;
USE fittrack;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('customer', 'coach', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Programs table
CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    duration INT NOT NULL COMMENT 'Duration in weeks',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Program Steps table
CREATE TABLE IF NOT EXISTS program_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    step_number INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    duration VARCHAR(50) COMMENT 'Recommended duration/sets/reps',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_program_step (program_id, step_number)
);

-- Step Progress table
CREATE TABLE IF NOT EXISTS step_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    program_id INT NOT NULL,
    step_id INT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    completion_date TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES program_steps(id) ON DELETE CASCADE,
    UNIQUE KEY unique_step_progress (customer_id, step_id)
);

-- Coach-Customer relationship
CREATE TABLE IF NOT EXISTS coach_customer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_id INT NOT NULL,
    customer_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_coach_customer (coach_id, customer_id)
);

-- Customer Programs relationship
CREATE TABLE IF NOT EXISTS customer_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    program_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);

-- Progress tracking
CREATE TABLE IF NOT EXISTS progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    weight FLOAT,
    height FLOAT,
    notes TEXT,
    record_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Workout logs
CREATE TABLE IF NOT EXISTS workout_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    program_id INT NOT NULL,
    duration INT COMMENT 'Duration in minutes',
    notes TEXT,
    workout_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);

-- Insert sample programs
INSERT INTO programs (name, description, difficulty, duration) VALUES
('Weight Loss Program', 'A complete program designed to help you lose weight with cardio and strength training.', 'beginner', 8),
('Muscle Building', 'Focused on building muscle mass with progressive overload principles.', 'intermediate', 12),
('Cardio Fitness', 'Improve your cardiovascular health and endurance.', 'beginner', 6),
('Strength Training', 'Build overall body strength with compound exercises.', 'intermediate', 10),
('HIIT Challenge', 'High-intensity interval training to burn fat and improve fitness.', 'advanced', 4),
('Flexibility & Mobility', 'Improve your range of motion and prevent injuries.', 'beginner', 8);

-- Insert sample program steps
INSERT INTO program_steps (program_id, step_number, title, description, duration) VALUES
-- Weight Loss Program Steps
(1, 1, 'Initial Assessment', 'Record your starting weight, measurements, and fitness level', '1 session'),
(1, 2, 'Cardio Introduction', '30 minutes of low-intensity cardio to build endurance', '3 sessions per week, 2 weeks'),
(1, 3, 'Basic Strength Training', 'Introduction to bodyweight exercises and light weights', '2 sessions per week, 2 weeks'),
(1, 4, 'Interval Training', 'Alternating between high and low-intensity exercises', '2 sessions per week, 2 weeks'),
(1, 5, 'Nutrition Planning', 'Develop a balanced meal plan with calorie deficit', 'Ongoing'),
(1, 6, 'Advanced Cardio', 'Increase duration and intensity of cardio workouts', '3 sessions per week, 2 weeks'),

-- Muscle Building Steps
(2, 1, 'Foundation Assessment', 'Assess current strength levels and set initial weights', '1 session'),
(2, 2, 'Upper Body Basics', 'Focus on chest, back, and arms with proper form', '2 sessions per week, 3 weeks'),
(2, 3, 'Lower Body Strength', 'Develop leg and core strength with compound movements', '2 sessions per week, 3 weeks'),
(2, 4, 'Progressive Overload', 'Increase weights gradually while maintaining form', 'Ongoing'),
(2, 5, 'Nutrition for Muscle Growth', 'Protein-rich diet planning and meal timing', 'Ongoing'),
(2, 6, 'Advanced Techniques', 'Drop sets, supersets, and other intensity techniques', '2 sessions per week, 3 weeks'),
(2, 7, 'Recovery Strategies', 'Proper rest, stretching, and recovery techniques', 'Ongoing'),

-- Cardio Fitness Steps
(3, 1, 'Baseline Cardio Assessment', 'Determine starting endurance level', '1 session'),
(3, 2, 'Walking/Jogging Program', 'Begin with walking intervals, progressing to jogging', '3 sessions per week, 2 weeks'),
(3, 3, 'Steady State Cardio', 'Maintain consistent pace for longer durations', '3 sessions per week, 2 weeks'),
(3, 4, 'Cardio Variety', 'Incorporate different cardio machines and activities', '3 sessions per week, 2 weeks'),

-- Strength Training Steps
(4, 1, 'Form and Technique', 'Learn proper form for fundamental lifts', '2 sessions per week, 2 weeks'),
(4, 2, 'Compound Movements', 'Focus on squats, deadlifts, and presses', '3 sessions per week, 3 weeks'),
(4, 3, 'Strength Building Phase', 'Heavier weights with lower repetitions', '3 sessions per week, 3 weeks'),
(4, 4, 'Accessory Work', 'Target supporting muscle groups', '2 sessions per week, 2 weeks'),

-- HIIT Challenge Steps
(5, 1, 'HIIT Introduction', 'Learn proper technique and timing for intervals', '2 sessions per week, 1 week'),
(5, 2, 'Bodyweight HIIT', 'High-intensity bodyweight circuit training', '3 sessions per week, 1 week'),
(5, 3, 'HIIT with Equipment', 'Incorporate kettlebells, battle ropes, and more', '3 sessions per week, 1 week'),
(5, 4, 'HIIT Finisher Challenge', 'Complete the full advanced HIIT program', '3 sessions per week, 1 week'),

-- Flexibility & Mobility Steps
(6, 1, 'Mobility Assessment', 'Identify tight areas and range of motion limitations', '1 session'),
(6, 2, 'Basic Stretching Routine', 'Daily stretching program for major muscle groups', 'Daily, 2 weeks'),
(6, 3, 'Dynamic Mobility', 'Active movements to improve joint mobility', '3 sessions per week, 2 weeks'),
(6, 4, 'Yoga Fundamentals', 'Basic yoga poses for flexibility and balance', '2 sessions per week, 2 weeks'),
(6, 5, 'Advanced Flexibility', 'Deeper stretches and longer hold times', '3 sessions per week, 2 weeks');

-- Insert admin user (password: admin123)
INSERT INTO users (name, email, password, user_type) VALUES
('Admin', 'admin@fittrack.com', '$2y$10$LO8sB5.JfxA.3.s41UmKdOsZfDPE8qC0JH/1NmpCJNFQNxqbXmYY.', 'admin');

-- Insert sample coaches (password: coach123)
INSERT INTO users (name, email, password, user_type) VALUES
('John Smith', 'john@fittrack.com', '$2y$10$LO8sB5.JfxA.3.s41UmKdOsZfDPE8qC0JH/1NmpCJNFQNxqbXmYY.', 'coach'),
('Jane Doe', 'jane@fittrack.com', '$2y$10$LO8sB5.JfxA.3.s41UmKdOsZfDPE8qC0JH/1NmpCJNFQNxqbXmYY.', 'coach');

-- Insert sample customers (password: customer123)
INSERT INTO users (name, email, password, user_type) VALUES
('Mike Johnson', 'mike@example.com', '$2y$10$LO8sB5.JfxA.3.s41UmKdOsZfDPE8qC0JH/1NmpCJNFQNxqbXmYY.', 'customer'),
('Sarah Williams', 'sarah@example.com', '$2y$10$LO8sB5.JfxA.3.s41UmKdOsZfDPE8qC0JH/1NmpCJNFQNxqbXmYY.', 'customer'),
('David Brown', 'david@example.com', '$2y$10$LO8sB5.JfxA.3.s41UmKdOsZfDPE8qC0JH/1NmpCJNFQNxqbXmYY.', 'customer'),
('Lisa Taylor', 'lisa@example.com', '$2y$10$LO8sB5.JfxA.3.s41UmKdOsZfDPE8qC0JH/1NmpCJNFQNxqbXmYY.', 'customer');

-- Assign coaches to customers
INSERT INTO coach_customer (coach_id, customer_id) VALUES
(2, 4), -- John is coach to Mike
(2, 5), -- John is coach to Sarah
(3, 6), -- Jane is coach to David
(3, 7); -- Jane is coach to Lisa

-- Assign programs to customers
INSERT INTO customer_programs (customer_id, program_id, status) VALUES
(4, 1, 'approved'), -- Mike has Weight Loss Program
(5, 3, 'approved'), -- Sarah has Cardio Fitness
(6, 2, 'pending'),  -- David wants Muscle Building (pending approval)
(7, 5, 'approved'); -- Lisa has HIIT Challenge

-- Insert some progress data
INSERT INTO progress (customer_id, weight, height, notes, record_date) VALUES
(4, 85.5, 178, 'Initial measurement', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 83.2, 178, 'Making progress', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(4, 82.0, 178, 'Continue with the program', NOW()),
(5, 65.0, 165, 'Initial measurement', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(5, 64.5, 165, 'Slight progress', NOW());

-- Insert some workout logs
INSERT INTO workout_logs (customer_id, program_id, duration, notes, workout_date) VALUES
(4, 1, 45, 'Completed cardio session', DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(4, 1, 60, 'Strength training', DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(4, 1, 30, 'Short HIIT session', CURDATE()),
(5, 3, 40, 'Jogging and stretching', DATE_SUB(CURDATE(), INTERVAL 4 DAY)),
(5, 3, 45, 'Interval training', DATE_SUB(CURDATE(), INTERVAL 2 DAY)); 