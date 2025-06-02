-- =====================================================
-- EduHive Database Setup Script - COMPLETE VERSION
-- This script creates the complete database structure
-- Run this in your MySQL/phpMyAdmin
-- =====================================================

-- Create database
DROP DATABASE IF EXISTS eduhive_db;
CREATE DATABASE eduhive_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eduhive_db;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    role ENUM('user', 'admin', 'moderator') DEFAULT 'user',
    avatar VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Email tokens table (for verification and password reset)
CREATE TABLE email_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    type ENUM('verification', 'password_reset') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_type (user_id, type),
    INDEX idx_expires (expires_at)
);

-- Remember me tokens table
CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
);

-- =====================================================
-- ACADEMIC CONTENT TABLES
-- =====================================================

-- Courses table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#8B7355',
    semester VARCHAR(20) NULL,
    year YEAR NULL,
    credits INT DEFAULT 3,
    instructor VARCHAR(100) NULL,
    status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_course (user_id, name),
    INDEX idx_status (status),
    INDEX idx_year_semester (year, semester)
);

-- Tasks table
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('todo', 'progress', 'done') DEFAULT 'todo',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATE NULL,
    due_time TIME NULL,
    estimated_hours DECIMAL(4,2) NULL,
    actual_hours DECIMAL(4,2) NULL,
    completed_at TIMESTAMP NULL,
    tags JSON NULL,
    attachments JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_user_status (user_id, status),
    INDEX idx_due_date (due_date),
    INDEX idx_user_course (user_id, course_id),
    INDEX idx_priority (priority)
);

-- Events table (for calendar)
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    location VARCHAR(255) NULL,
    event_type ENUM('class', 'exam', 'assignment', 'meeting', 'deadline', 'personal', 'other') DEFAULT 'other',
    is_all_day BOOLEAN DEFAULT FALSE,
    recurring ENUM('none', 'daily', 'weekly', 'monthly') DEFAULT 'none',
    recurring_until DATE NULL,
    google_event_id VARCHAR(255) NULL,
    color VARCHAR(7) DEFAULT '#8B7355',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, start_datetime),
    INDEX idx_date_range (start_datetime, end_datetime),
    INDEX idx_event_type (event_type),
    INDEX idx_google_event (google_event_id)
);

-- Class schedules table
CREATE TABLE class_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NULL,
    class_code VARCHAR(20) NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(100) NULL,
    mode ENUM('online', 'physical', 'hybrid') DEFAULT 'physical',
    instructor VARCHAR(100) NULL,
    room VARCHAR(50) NULL,
    building VARCHAR(100) NULL,
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_user_day (user_id, day_of_week),
    INDEX idx_user_course (user_id, course_id),
    INDEX idx_time_range (start_time, end_time)
);

-- =====================================================
-- TIME TRACKING TABLES
-- =====================================================

-- Time tracking table
CREATE TABLE time_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NULL,
    course_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    category VARCHAR(50) NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NULL,
    duration INT NULL, -- in seconds
    date DATE NOT NULL,
    is_billable BOOLEAN DEFAULT FALSE,
    tags JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, date),
    INDEX idx_user_task (user_id, task_id),
    INDEX idx_date_range (date, start_time)
);

-- =====================================================
-- COLLABORATION TABLES
-- =====================================================

-- Team members table
CREATE TABLE team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(50) NULL,
    group_name VARCHAR(50) NOT NULL,
    avatar VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_active TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_group (user_id, group_name),
    INDEX idx_email (email),
    INDEX idx_status (status)
);

-- =====================================================
-- GAMIFICATION TABLES
-- =====================================================

-- User progress table (for rewards/badges)
CREATE TABLE user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_badges INT DEFAULT 0,
    total_points INT DEFAULT 0,
    current_level INT DEFAULT 1,
    experience_points INT DEFAULT 0,
    streak_days INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    tasks_completed INT DEFAULT 0,
    study_hours DECIMAL(8,2) DEFAULT 0.00,
    last_login DATE NULL,
    last_activity TIMESTAMP NULL,
    badges_earned JSON NULL,
    achievements JSON NULL,
    preferences JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_level (current_level),
    INDEX idx_points (total_points),
    INDEX idx_last_activity (last_activity)
);

-- Badges table
CREATE TABLE badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(255) NULL,
    color VARCHAR(7) DEFAULT '#FFD700',
    category ENUM('tasks', 'time', 'streak', 'social', 'achievement', 'special') DEFAULT 'achievement',
    requirement_type ENUM('tasks_completed', 'hours_studied', 'streak_days', 'points_earned', 'special') NOT NULL,
    requirement_value INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User badges table (many-to-many)
CREATE TABLE user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    INDEX idx_user_id (user_id),
    INDEX idx_badge_id (badge_id),
    INDEX idx_earned_at (earned_at)
);

-- =====================================================
-- COMMUNICATION TABLES
-- =====================================================

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('web', 'email', 'sms', 'push') DEFAULT 'web',
    category ENUM('system', 'task', 'deadline', 'achievement', 'social', 'reminder') DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    expires_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_priority (priority),
    INDEX idx_category (category)
);

-- =====================================================
-- AUDIT AND LOGGING TABLES
-- =====================================================

-- Activity logs table (for audit trail)
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NULL,
    resource_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    session_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created (created_at),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_ip_address (ip_address)
);

-- =====================================================
-- CONFIGURATION TABLES
-- =====================================================

-- Settings table (for application settings)
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description TEXT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_category (category)
);

-- =====================================================
-- FILE ATTACHMENTS TABLE (ADDITIONAL)
-- =====================================================

-- File attachments table for tasks and other entities
CREATE TABLE file_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    entity_type ENUM('task', 'event', 'course', 'team_member') NOT NULL,
    entity_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user_entity (user_id, entity_type),
    INDEX idx_created (created_at)
);

-- =====================================================
-- SESSION MANAGEMENT TABLE (ADDITIONAL)
-- =====================================================

-- User sessions table for better session tracking
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
);

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
('app_name', 'EduHive', 'string', 'general', 'Application name', TRUE),
('app_version', '1.0.0', 'string', 'general', 'Application version', TRUE),
('app_description', 'Academic task and time management platform', 'string', 'general', 'Application description', TRUE),
('maintenance_mode', '0', 'boolean', 'system', 'Maintenance mode status', FALSE),
('registration_enabled', '1', 'boolean', 'auth', 'User registration enabled', FALSE),
('email_verification_required', '0', 'boolean', 'auth', 'Email verification required for new users', FALSE),
('session_timeout', '3600', 'number', 'auth', 'Session timeout in seconds', FALSE),
('max_file_upload_size', '5242880', 'number', 'system', 'Maximum file upload size in bytes (5MB)', FALSE),
('default_theme', 'light', 'string', 'ui', 'Default application theme', TRUE),
('timezone', 'Asia/Kuala_Lumpur', 'string', 'general', 'Default timezone', TRUE),
('date_format', 'Y-m-d', 'string', 'ui', 'Default date format', TRUE),
('time_format', 'H:i', 'string', 'ui', 'Default time format', TRUE),
('items_per_page', '20', 'number', 'ui', 'Default pagination items per page', TRUE),
('backup_enabled', '1', 'boolean', 'system', 'Automatic backup enabled', FALSE),
('notifications_enabled', '1', 'boolean', 'features', 'Push notifications enabled', TRUE),
('gamification_enabled', '1', 'boolean', 'features', 'Gamification features enabled', TRUE),
('max_team_members', '50', 'number', 'limits', 'Maximum team members per user', FALSE),
('max_courses', '20', 'number', 'limits', 'Maximum courses per user', FALSE),
('max_tasks_per_course', '100', 'number', 'limits', 'Maximum tasks per course', FALSE);

-- Insert default badges
INSERT INTO badges (name, description, icon, color, category, requirement_type, requirement_value) VALUES
('Welcome to EduHive', 'Joined the EduHive community', '🎉', '#4CAF50', 'special', 'special', 0),
('First Task', 'Completed your first task', '✅', '#2196F3', 'tasks', 'tasks_completed', 1),
('Task Master', 'Completed 10 tasks', '🏆', '#FFD700', 'tasks', 'tasks_completed', 10),
('Productivity Pro', 'Completed 50 tasks', '⭐', '#FF9800', 'tasks', 'tasks_completed', 50),
('Task Champion', 'Completed 100 tasks', '👑', '#9C27B0', 'tasks', 'tasks_completed', 100),
('Study Streak', 'Maintained a 7-day study streak', '🔥', '#F44336', 'streak', 'streak_days', 7),
('Consistent Learner', 'Maintained a 30-day study streak', '📚', '#FF5722', 'streak', 'streak_days', 30),
('Dedicated Student', 'Studied for 10 hours', '📖', '#9C27B0', 'time', 'hours_studied', 10),
('Scholar', 'Studied for 100 hours', '🎓', '#673AB7', 'time', 'hours_studied', 100),
('Academic Master', 'Studied for 500 hours', '🏅', '#3F51B5', 'time', 'hours_studied', 500),
('Early Bird', 'Completed a task before 8 AM', '🐦', '#00BCD4', 'special', 'special', 0),
('Night Owl', 'Completed a task after 10 PM', '🦉', '#795548', 'special', 'special', 0),
('Perfectionist', 'Completed 5 tasks on time', '💯', '#E91E63', 'tasks', 'tasks_completed', 5),
('Team Player', 'Added 5 team members', '🤝', '#4CAF50', 'social', 'special', 0),
('Course Creator', 'Created 5 courses', '📚', '#FF9800', 'achievement', 'special', 0);

-- Create a default admin user (password: admin123)
-- Note: Change this password immediately after setup!
INSERT INTO users (name, email, password, role, email_verified, status) VALUES
('Administrator', 'admin@eduhive.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, 'active');

-- Get the admin user ID for sample data
SET @admin_id = LAST_INSERT_ID();

-- Insert sample user progress for admin
INSERT INTO user_progress (user_id, total_badges, total_points, current_level, tasks_completed, last_login) VALUES
(@admin_id, 3, 150, 2, 8, CURDATE());

-- Award welcome badge to admin
INSERT INTO user_badges (user_id, badge_id) VALUES (@admin_id, 1);

-- Add some sample courses
INSERT INTO courses (user_id, name, code, description, color, semester, year, credits, instructor, status) VALUES
(@admin_id, 'Final Year Project', 'FYP2024', 'Final year project development and research', '#8B7355', 'Semester 2', 2025, 6, 'Dr. John Smith', 'active'),
(@admin_id, 'Web Programming', 'TP2543', 'Advanced web development with PHP and JavaScript', '#6c757d', 'Semester 2', 2025, 3, 'Prof. Jane Doe', 'active'),
(@admin_id, 'Property Development', 'HARTA301', 'Real estate and property development course', '#fd7e14', 'Semester 2', 2025, 3, 'Mr. Ahmad Rahman', 'active'),
(@admin_id, 'Database Systems', 'CS3421', 'Advanced database design and management', '#28a745', 'Semester 1', 2025, 3, 'Dr. Sarah Lee', 'completed');

-- Get course IDs for sample tasks
SET @fyp_course_id = (SELECT id FROM courses WHERE code = 'FYP2024' LIMIT 1);
SET @web_course_id = (SELECT id FROM courses WHERE code = 'TP2543' LIMIT 1);
SET @harta_course_id = (SELECT id FROM courses WHERE code = 'HARTA301' LIMIT 1);

-- Add sample tasks
INSERT INTO tasks (user_id, course_id, title, description, status, priority, due_date, estimated_hours) VALUES
(@admin_id, @fyp_course_id, 'Complete Literature Review', 'Research and compile literature review for FYP', 'progress', 'high', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 20.0),
(@admin_id, @fyp_course_id, 'Develop System Architecture', 'Design and document system architecture', 'todo', 'high', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 15.0),
(@admin_id, @fyp_course_id, 'Implement User Authentication', 'Build secure login and registration system', 'todo', 'medium', DATE_ADD(CURDATE(), INTERVAL 10 DAY), 8.0),
(@admin_id, @web_course_id, 'Build Login System', 'Implement secure user authentication', 'done', 'medium', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 6.0),
(@admin_id, @web_course_id, 'Create Database Schema', 'Design and implement database structure', 'done', 'high', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 4.0),
(@admin_id, @harta_course_id, 'Market Analysis Report', 'Analyze current property market trends', 'todo', 'medium', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 12.0),
(@admin_id, @harta_course_id, 'Site Visit Documentation', 'Document findings from property site visits', 'progress', 'low', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 8.0),
(@admin_id, NULL, 'Prepare for Job Interviews', 'Practice coding challenges and behavioral questions', 'todo', 'high', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 25.0);

-- Add sample class schedules
INSERT INTO class_schedules (user_id, course_id, class_code, day_of_week, start_time, end_time, location, mode, instructor, room) VALUES
(@admin_id, @fyp_course_id, 'FYP2024', 'monday', '14:00:00', '16:00:00', 'Supervision Room A', 'physical', 'Dr. John Smith', 'A-101'),
(@admin_id, @web_course_id, 'TP2543L', 'tuesday', '08:00:00', '10:00:00', 'Computer Lab 1', 'physical', 'Prof. Jane Doe', 'B-201'),
(@admin_id, @web_course_id, 'TP2543T', 'thursday', '10:00:00', '12:00:00', 'Tutorial Room 3', 'physical', 'Prof. Jane Doe', 'C-301'),
(@admin_id, @harta_course_id, 'HARTA301', 'wednesday', '14:00:00', '17:00:00', 'Lecture Hall 1', 'physical', 'Mr. Ahmad Rahman', 'LH-1'),
(@admin_id, @web_course_id, 'TP2543', 'friday', '09:00:00', '11:00:00', 'Online Platform', 'online', 'Prof. Jane Doe', 'Zoom');

-- Add some sample events
INSERT INTO events (user_id, course_id, title, description, start_datetime, end_datetime, location, event_type, color) VALUES
(@admin_id, @fyp_course_id, 'FYP Presentation', 'Final year project presentation to panel', DATE_ADD(CURDATE(), INTERVAL 20 DAY) + INTERVAL 14 HOUR, DATE_ADD(CURDATE(), INTERVAL 20 DAY) + INTERVAL 16 HOUR, 'Auditorium A', 'exam', '#8B7355'),
(@admin_id, @web_course_id, 'Web Programming Final Exam', 'Final examination for TP2543', DATE_ADD(CURDATE(), INTERVAL 15 DAY) + INTERVAL 9 HOUR, DATE_ADD(CURDATE(), INTERVAL 15 DAY) + INTERVAL 11 HOUR, 'Exam Hall 2', 'exam', '#6c757d'),
(@admin_id, @harta_course_id, 'Property Site Visit', 'Visit to development site in Kuala Lumpur', DATE_ADD(CURDATE(), INTERVAL 8 DAY) + INTERVAL 8 HOUR, DATE_ADD(CURDATE(), INTERVAL 8 DAY) + INTERVAL 17 HOUR, 'KL City Center', 'class', '#fd7e14'),
(@admin_id, NULL, 'Job Interview - Tech Company', 'Technical interview for software developer position', DATE_ADD(CURDATE(), INTERVAL 25 DAY) + INTERVAL 10 HOUR, DATE_ADD(CURDATE(), INTERVAL 25 DAY) + INTERVAL 12 HOUR, 'KLCC Tower 1', 'meeting', '#28a745');

-- Add some sample team members
INSERT INTO team_members (user_id, name, email, role, group_name, status) VALUES
(@admin_id, 'Alice Wong', 'alice.wong@student.edu.my', 'Team Leader', 'FYP Group', 'active'),
(@admin_id, 'Bob Chen', 'bob.chen@student.edu.my', 'Developer', 'FYP Group', 'active'),
(@admin_id, 'Carol Lim', 'carol.lim@student.edu.my', 'Designer', 'FYP Group', 'active'),
(@admin_id, 'David Kumar', 'david.kumar@student.edu.my', 'Researcher', 'FYP Group', 'active'),
(@admin_id, 'Emma Tan', 'emma.tan@student.edu.my', 'Frontend Developer', 'Programming Group', 'active'),
(@admin_id, 'Frank Lee', 'frank.lee@student.edu.my', 'Backend Developer', 'Programming Group', 'active');

-- Add some sample time entries
INSERT INTO time_entries (user_id, task_id, course_id, title, description, category, start_time, end_time, duration, date) VALUES
(@admin_id, (SELECT id FROM tasks WHERE title = 'Build Login System' LIMIT 1), @web_course_id, 'Login System Development', 'Worked on user authentication features', 'Development', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 4 HOUR, 14400, DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(@admin_id, (SELECT id FROM tasks WHERE title = 'Complete Literature Review' LIMIT 1), @fyp_course_id, 'Literature Research', 'Reading academic papers for literature review', 'Research', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 3 HOUR, 10800, DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(@admin_id, (SELECT id FROM tasks WHERE title = 'Market Analysis Report' LIMIT 1), @harta_course_id, 'Market Research', 'Analyzing property market data and trends', 'Analysis', NOW() - INTERVAL 2 HOUR, NOW(), 7200, CURDATE());

-- Add some sample notifications
INSERT INTO notifications (user_id, type, category, title, message, priority, created_at) VALUES
(@admin_id, 'web', 'deadline', 'Task Due Tomorrow', 'Your task "Complete Literature Review" is due tomorrow!', 'high', NOW() - INTERVAL 1 HOUR),
(@admin_id, 'web', 'achievement', 'Badge Earned!', 'Congratulations! You earned the "First Task" badge.', 'normal', NOW() - INTERVAL 2 HOUR),
(@admin_id, 'web', 'system', 'Welcome to EduHive', 'Welcome to EduHive! Start by creating your first task or course.', 'normal', NOW() - INTERVAL 1 DAY);

-- =====================================================
-- CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional indexes for better performance
CREATE INDEX idx_tasks_user_due_status ON tasks(user_id, due_date, status);
CREATE INDEX idx_events_user_date_range ON events(user_id, start_datetime, end_datetime);
CREATE INDEX idx_time_entries_user_date_range ON time_entries(user_id, date, start_time);
CREATE INDEX idx_notifications_user_unread_created ON notifications(user_id, is_read, created_at);
CREATE INDEX idx_activity_logs_user_created ON activity_logs(user_id, created_at);
CREATE INDEX idx_file_attachments_entity ON file_attachments(entity_type, entity_id);
CREATE INDEX idx_user_sessions_activity ON user_sessions(last_activity);

-- =====================================================
-- CREATE TRIGGERS FOR AUTOMATION
-- =====================================================

DELIMITER //

-- Update user progress when task is completed
CREATE TRIGGER update_progress_on_task_complete
    AFTER UPDATE ON tasks
    FOR EACH ROW
BEGIN
    IF NEW.status = 'done' AND OLD.status != 'done' THEN
        INSERT INTO user_progress (user_id, total_points, tasks_completed) 
        VALUES (NEW.user_id, 10, 1)
        ON DUPLICATE KEY UPDATE 
        total_points = total_points + 10,
        tasks_completed = tasks_completed + 1,
        updated_at = CURRENT_TIMESTAMP;
        
        -- Create achievement notification
        INSERT INTO notifications (user_id, type, category, title, message, priority)
        VALUES (NEW.user_id, 'web', 'achievement', 'Task Completed!', 
                CONCAT('Great job! You completed "', NEW.title, '"'), 'normal');
    END IF;
END//

-- Update time entry duration when end_time is set
CREATE TRIGGER calculate_time_entry_duration
    BEFORE UPDATE ON time_entries
    FOR EACH ROW
BEGIN
    IF NEW.end_time IS NOT NULL AND OLD.end_time IS NULL THEN
        SET NEW.duration = TIMESTAMPDIFF(SECOND, NEW.start_time, NEW.end_time);
    END IF;
END//

-- Auto-populate duration when inserting complete time entry
CREATE TRIGGER auto_calculate_duration_on_insert
    BEFORE INSERT ON time_entries
    FOR EACH ROW
BEGIN
    IF NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL AND NEW.duration IS NULL THEN
        SET NEW.duration = TIMESTAMPDIFF(SECOND, NEW.start_time, NEW.end_time);
    END IF;
END//

-- Log user login activity
CREATE TRIGGER log_user_login
    AFTER UPDATE ON users
    FOR EACH ROW
BEGIN
    IF NEW.last_login != OLD.last_login OR (OLD.last_login IS NULL AND NEW.last_login IS NOT NULL) THEN
        INSERT INTO activity_logs (user_id, action, details, created_at)
        VALUES (NEW.id, 'user_login', CONCAT('User logged in: ', NEW.email), NOW());
    END IF;
END//

-- Mark notification as read when read_at is set
CREATE TRIGGER mark_notification_read
    BEFORE UPDATE ON notifications
    FOR EACH ROW
BEGIN
    IF NEW.read_at IS NOT NULL AND OLD.read_at IS NULL THEN
        SET NEW.is_read = TRUE;
    END IF;
END//

-- Update course task count (virtual column simulation)
CREATE TRIGGER update_course_stats
    AFTER INSERT ON tasks
    FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, details)
    VALUES (NEW.user_id, 'task_created', 'task', NEW.id, CONCAT('Task created: ', NEW.title));
END//

-- Clean up related data when user is deleted
CREATE TRIGGER cleanup_user_data
    BEFORE DELETE ON users
    FOR EACH ROW
BEGIN
    -- Clean up file attachments
    DELETE FROM file_attachments WHERE user_id = OLD.id;
    
    -- Log user deletion
    INSERT INTO activity_logs (user_id, action, details, created_at)
    VALUES (NULL, 'user_deleted', CONCAT('User deleted: ', OLD.email), NOW());
END//

DELIMITER ;

-- =====================================================
-- CREATE VIEWS FOR COMMON QUERIES
-- =====================================================

-- User dashboard statistics view
CREATE VIEW user_dashboard_stats AS
SELECT 
    u.id as user_id,
    u.name,
    u.email,
    COUNT(DISTINCT t.id) as total_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'done' THEN t.id END) as completed_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'todo' THEN t.id END) as pending_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'progress' THEN t.id END) as in_progress_tasks,
    COUNT(DISTINCT CASE WHEN t.due_date = CURDATE() AND t.status != 'done' THEN t.id END) as due_today,
    COUNT(DISTINCT CASE WHEN t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND t.status != 'done' THEN t.id END) as due_this_week,
    COUNT(DISTINCT c.id) as total_courses,
    COALESCE(up.total_badges, 0) as total_badges,
    COALESCE(up.total_points, 0) as total_points,
    COALESCE(up.current_level, 1) as current_level,
    COALESCE(up.streak_days, 0) as streak_days
FROM users u
LEFT JOIN tasks t ON u.id = t.user_id
LEFT JOIN courses c ON u.id = c.user_id AND c.status = 'active'
LEFT JOIN user_progress up ON u.id = up.user_id
WHERE u.status = 'active'
GROUP BY u.id, u.name, u.email, up.total_badges, up.total_points, up.current_level, up.streak_days;

-- Course overview view
CREATE VIEW course_overview AS
SELECT 
    c.*,
    COUNT(DISTINCT t.id) as total_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'done' THEN t.id END) as completed_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'todo' THEN t.id END) as pending_tasks,
    COUNT(DISTINCT cs.id) as class_sessions,
    COALESCE(SUM(te.duration), 0) / 3600 as total_hours_logged
FROM courses c
LEFT JOIN tasks t ON c.id = t.course_id
LEFT JOIN class_schedules cs ON c.id = cs.course_id AND cs.is_active = TRUE
LEFT JOIN time_entries te ON c.id = te.course_id
GROUP BY c.id;

-- Weekly schedule view
CREATE VIEW weekly_schedule AS
SELECT 
    cs.*,
    c.name as course_name,
    c.color as course_color,
    u.name as user_name
FROM class_schedules cs
JOIN courses c ON cs.course_id = c.id
JOIN users u ON cs.user_id = u.id
WHERE cs.is_active = TRUE
ORDER BY 
    FIELD(cs.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
    cs.start_time;

-- Recent activity view
CREATE VIEW recent_activity AS
SELECT 
    al.*,
    u.name as user_name,
    u.email as user_email
FROM activity_logs al
LEFT JOIN users u ON al.user_id = u.id
ORDER BY al.created_at DESC
LIMIT 100;

-- Task summary view with course information
CREATE VIEW task_summary AS
SELECT 
    t.*,
    c.name as course_name,
    c.code as course_code,
    c.color as course_color,
    u.name as user_name,
    CASE 
        WHEN t.due_date = CURDATE() THEN 'due_today'
        WHEN t.due_date < CURDATE() THEN 'overdue'
        WHEN t.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'due_soon'
        ELSE 'future'
    END as due_status
FROM tasks t
JOIN users u ON t.user_id = u.id
LEFT JOIN courses c ON t.course_id = c.id
WHERE u.status = 'active'
ORDER BY t.due_date ASC, t.priority DESC;

-- =====================================================
-- CREATE STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure to award badge to user
CREATE PROCEDURE AwardBadge(
    IN p_user_id INT,
    IN p_badge_id INT
)
BEGIN
    DECLARE badge_exists INT DEFAULT 0;
    DECLARE badge_name VARCHAR(100);
    
    -- Check if user already has this badge
    SELECT COUNT(*) INTO badge_exists 
    FROM user_badges 
    WHERE user_id = p_user_id AND badge_id = p_badge_id;
    
    -- Award badge if not already earned
    IF badge_exists = 0 THEN
        -- Get badge name
        SELECT name INTO badge_name FROM badges WHERE id = p_badge_id;
        
        INSERT INTO user_badges (user_id, badge_id) VALUES (p_user_id, p_badge_id);
        
        -- Update user progress
        UPDATE user_progress 
        SET total_badges = total_badges + 1, updated_at = NOW()
        WHERE user_id = p_user_id;
        
        -- Create notification
        INSERT INTO notifications (user_id, type, category, title, message, priority)
        VALUES (p_user_id, 'web', 'achievement', 
               CONCAT('Badge Earned: ', badge_name),
               CONCAT('Congratulations! You earned the "', badge_name, '" badge.'),
               'normal');
    END IF;
END//

-- Procedure to check and award badges based on progress
CREATE PROCEDURE CheckAndAwardBadges(IN p_user_id INT)
BEGIN
    DECLARE v_tasks_completed INT;
    DECLARE v_study_hours DECIMAL(8,2);
    DECLARE v_streak_days INT;
    
    -- Get user progress
    SELECT tasks_completed, study_hours, streak_days 
    INTO v_tasks_completed, v_study_hours, v_streak_days
    FROM user_progress 
    WHERE user_id = p_user_id;
    
    -- Check for task-based badges
    IF v_tasks_completed >= 1 THEN
        CALL AwardBadge(p_user_id, 2); -- First Task
    END IF;
    
    IF v_tasks_completed >= 10 THEN
        CALL AwardBadge(p_user_id, 3); -- Task Master
    END IF;
    
    IF v_tasks_completed >= 50 THEN
        CALL AwardBadge(p_user_id, 4); -- Productivity Pro
    END IF;
    
    IF v_tasks_completed >= 100 THEN
        CALL AwardBadge(p_user_id, 5); -- Task Champion
    END IF;
    
    -- Check for streak badges
    IF v_streak_days >= 7 THEN
        CALL AwardBadge(p_user_id, 6); -- Study Streak
    END IF;
    
    IF v_streak_days >= 30 THEN
        CALL AwardBadge(p_user_id, 7); -- Consistent Learner
    END IF;
    
    -- Check for time-based badges
    IF v_study_hours >= 10 THEN
        CALL AwardBadge(p_user_id, 8); -- Dedicated Student
    END IF;
    
    IF v_study_hours >= 100 THEN
        CALL AwardBadge(p_user_id, 9); -- Scholar
    END IF;
    
    IF v_study_hours >= 500 THEN
        CALL AwardBadge(p_user_id, 10); -- Academic Master
    END IF;
END//

-- Procedure to update user study hours from time entries
CREATE PROCEDURE UpdateStudyHours(IN p_user_id INT)
BEGIN
    DECLARE v_total_hours DECIMAL(8,2);
    
    -- Calculate total study hours from time entries
    SELECT COALESCE(SUM(duration), 0) / 3600 INTO v_total_hours
    FROM time_entries 
    WHERE user_id = p_user_id;
    
    -- Update user progress
    INSERT INTO user_progress (user_id, study_hours)
    VALUES (p_user_id, v_total_hours)
    ON DUPLICATE KEY UPDATE
    study_hours = v_total_hours,
    updated_at = NOW();
    
    -- Check for badges
    CALL CheckAndAwardBadges(p_user_id);
END//

-- Procedure to clean up old data
CREATE PROCEDURE CleanupOldData()
BEGIN
    -- Clean up old read notifications (older than 30 days)
    DELETE FROM notifications 
    WHERE is_read = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Clean up expired tokens
    DELETE FROM email_tokens 
    WHERE expires_at < NOW();
    
    DELETE FROM remember_tokens 
    WHERE expires_at < NOW();
    
    -- Clean up old activity logs (older than 90 days)
    DELETE FROM activity_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Clean up old sessions (older than 7 days)
    DELETE FROM user_sessions 
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Archive completed time entries older than 1 year
    DELETE FROM time_entries 
    WHERE date < DATE_SUB(CURDATE(), INTERVAL 1 YEAR);
END//

-- Procedure to generate user activity report
CREATE PROCEDURE GenerateUserReport(
    IN p_user_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT 
        'Tasks Completed' as metric,
        COUNT(*) as value
    FROM tasks 
    WHERE user_id = p_user_id 
    AND status = 'done' 
    AND DATE(completed_at) BETWEEN p_start_date AND p_end_date
    
    UNION ALL
    
    SELECT 
        'Study Hours' as metric,
        ROUND(SUM(duration) / 3600, 2) as value
    FROM time_entries 
    WHERE user_id = p_user_id 
    AND date BETWEEN p_start_date AND p_end_date
    
    UNION ALL
    
    SELECT 
        'Badges Earned' as metric,
        COUNT(*) as value
    FROM user_badges 
    WHERE user_id = p_user_id 
    AND DATE(earned_at) BETWEEN p_start_date AND p_end_date;
END//

DELIMITER ;

-- =====================================================
-- FINAL SETUP COMMANDS
-- =====================================================

-- Create a scheduled event to run cleanup daily (requires EVENT_SCHEDULER to be ON)
-- SET GLOBAL event_scheduler = ON;
-- CREATE EVENT daily_cleanup
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO CALL CleanupOldData();

-- Grant appropriate permissions (adjust as needed)
-- CREATE USER 'eduhive_user'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON eduhive_db.* TO 'eduhive_user'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- SAMPLE QUERIES FOR TESTING
-- =====================================================

-- Test the dashboard view
-- SELECT * FROM user_dashboard_stats WHERE user_id = 1;

-- Test course overview
-- SELECT * FROM course_overview WHERE user_id = 1;

-- Test weekly schedule
-- SELECT * FROM weekly_schedule WHERE user_id = 1;

-- Test task summary
-- SELECT * FROM task_summary WHERE user_id = 1 LIMIT 10;

-- Test badge awarding
-- CALL AwardBadge(1, 1);
-- CALL CheckAndAwardBadges(1);

-- Test user report generation
-- CALL GenerateUserReport(1, '2025-01-01', '2025-12-31');

-- =====================================================
-- DATABASE SETUP COMPLETE
-- =====================================================

SELECT 'EduHive database setup completed successfully!' as status;
SELECT 'Default admin user: admin@eduhive.com / admin123' as admin_info;
SELECT 'Remember to change the admin password!' as security_note;
SELECT 'Database includes enhanced features and optimizations' as features_note;