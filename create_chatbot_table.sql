-- Chatbot knowledge base table
CREATE TABLE IF NOT EXISTS chatbot_knowledge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (question(191))
);

-- Create chat logs table for tracking chatbot interactions
CREATE TABLE IF NOT EXISTS chat_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT 0,
    query TEXT NOT NULL,
    response TEXT NOT NULL,
    match_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert some initial knowledge for the chatbot
INSERT INTO chatbot_knowledge (question, answer, category) VALUES
("What is FitTrack?", "FitTrack is a fitness management system that connects customers with coaches to follow customized fitness programs and track progress.", "general"),
("How do I sign up?", "You can sign up by clicking the Register button and filling out the registration form with your details.", "account"),
("How do I choose a coach?", "As a customer, you can go to the \"Choose Coach\" page to view available coaches and select one that matches your fitness goals.", "customer"),
("How do I track my progress?", "You can track your progress on the Progress page where you can log your measurements and view your improvement over time.", "customer"),
("How do I create a program?", "As a coach, you can create new programs from your dashboard by going to the Programs section and clicking \"Create New Program\".", "coach"),
("How do I assign a program to a customer?", "As a coach, go to your Customers list, select a customer, and use the \"Assign Program\" option to assign a program to them.", "coach"),
("What types of programs are available?", "FitTrack offers various program types including Weight Loss, Muscle Building, Cardio Fitness, Strength Training, HIIT, and Flexibility & Mobility.", "programs"),
("How do I manage users?", "As an admin, you can manage all users from the Admin Dashboard by going to the Users section where you can edit, delete or add new users.", "admin");