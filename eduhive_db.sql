-- =====================================================
-- EduHive Database Setup Script - SIMPLE VERSION
-- Perfect for beginners - only essential features
-- Run this in your MySQL/phpMyAdmin
-- =====================================================

-- Create database
DROP DATABASE IF EXISTS eduhive_db;
CREATE DATABASE eduhive_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eduhive_db;

-- =====================================================
-- CORE TABLES (Essential Only)
-- =====================================================

-- Users table (simplified)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Courses table (simplified)
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#8B7355',
    semester VARCHAR(20) NULL,
    year YEAR NULL,
    instructor VARCHAR(100) NULL,
    status ENUM('active', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tasks table (simplified)
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('todo', 'progress', 'done') DEFAULT 'todo',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATE NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Events table (simplified for calendar)
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    location VARCHAR(255) NULL,
    event_type ENUM('class', 'exam', 'assignment', 'meeting', 'other') DEFAULT 'other',
    color VARCHAR(7) DEFAULT '#8B7355',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Class schedules table (simplified)
CREATE TABLE class_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NULL,
    class_code VARCHAR(20) NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(100) NULL,
    mode ENUM('online', 'physical') DEFAULT 'physical',
    instructor VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Time tracking table (simplified)
CREATE TABLE time_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NULL,
    course_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NULL,
    duration INT NULL, -- in seconds
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Team members table (simplified)
CREATE TABLE team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(50) NULL,
    group_name VARCHAR(50) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Basic settings table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'number', 'boolean') DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Insert basic settings
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('app_name', 'EduHive', 'string'),
('app_version', '1.0.0', 'string'),
('registration_enabled', '1', 'boolean'),
('timezone', 'Asia/Kuala_Lumpur', 'string');

-- Create default admin user (password: admin123)
-- IMPORTANT: Change this password after setup!
INSERT INTO users (name, email, password, role, status) VALUES
('Administrator', 'admin@eduhive.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Get the admin user ID for sample data
SET @admin_id = LAST_INSERT_ID();

-- Add sample courses
INSERT INTO courses (user_id, name, code, description, color, semester, year, instructor, status) VALUES
(@admin_id, 'Final Year Project', 'FYP2025', 'Final year project development', '#8B7355', 'Semester 2', 2025, 'Dr. John Smith', 'active'),
(@admin_id, 'Web Programming', 'TP2543', 'Web development with PHP and JavaScript', '#6c757d', 'Semester 2', 2025, 'Prof. Jane Doe', 'active'),
(@admin_id, 'Property Development', 'HARTA301', 'Real estate development course', '#fd7e14', 'Semester 2', 2025, 'Mr. Ahmad Rahman', 'active');

-- Get course IDs for sample tasks
SET @fyp_course_id = (SELECT id FROM courses WHERE code = 'FYP2025' LIMIT 1);
SET @web_course_id = (SELECT id FROM courses WHERE code = 'TP2543' LIMIT 1);
SET @harta_course_id = (SELECT id FROM courses WHERE code = 'HARTA301' LIMIT 1);

-- Add sample tasks
INSERT INTO tasks (user_id, course_id, title, description, status, priority, due_date) VALUES
(@admin_id, @fyp_course_id, 'Complete Literature Review', 'Research and compile literature review for FYP', 'progress', 'high', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
(@admin_id, @fyp_course_id, 'Develop System Design', 'Design system architecture', 'todo', 'high', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
(@admin_id, @web_course_id, 'Build Login System', 'Create user authentication', 'done', 'medium', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(@admin_id, @web_course_id, 'Create Database Schema', 'Design database structure', 'done', 'high', DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(@admin_id, @harta_course_id, 'Market Analysis Report', 'Analyze property market trends', 'todo', 'medium', DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
(@admin_id, NULL, 'Prepare for Job Interview', 'Practice coding challenges', 'todo', 'high', DATE_ADD(CURDATE(), INTERVAL 30 DAY));

-- Add sample class schedules
INSERT INTO class_schedules (user_id, course_id, class_code, day_of_week, start_time, end_time, location, mode, instructor) VALUES
(@admin_id, @fyp_course_id, 'FYP2025', 'monday', '14:00:00', '16:00:00', 'Supervision Room A', 'physical', 'Dr. John Smith'),
(@admin_id, @web_course_id, 'TP2543L', 'tuesday', '08:00:00', '10:00:00', 'Computer Lab 1', 'physical', 'Prof. Jane Doe'),
(@admin_id, @web_course_id, 'TP2543T', 'thursday', '10:00:00', '12:00:00', 'Tutorial Room 3', 'physical', 'Prof. Jane Doe'),
(@admin_id, @harta_course_id, 'HARTA301', 'wednesday', '14:00:00', '17:00:00', 'Lecture Hall 1', 'physical', 'Mr. Ahmad Rahman'),
(@admin_id, @web_course_id, 'TP2543', 'friday', '09:00:00', '11:00:00', 'Online Platform', 'online', 'Prof. Jane Doe');

-- Add sample events
INSERT INTO events (user_id, course_id, title, description, start_datetime, end_datetime, location, event_type, color) VALUES
(@admin_id, @fyp_course_id, 'FYP Presentation', 'Final year project presentation', DATE_ADD(CURDATE(), INTERVAL 20 DAY) + INTERVAL 14 HOUR, DATE_ADD(CURDATE(), INTERVAL 20 DAY) + INTERVAL 16 HOUR, 'Auditorium A', 'exam', '#8B7355'),
(@admin_id, @web_course_id, 'Web Programming Final Exam', 'Final examination for TP2543', DATE_ADD(CURDATE(), INTERVAL 15 DAY) + INTERVAL 9 HOUR, DATE_ADD(CURDATE(), INTERVAL 15 DAY) + INTERVAL 11 HOUR, 'Exam Hall 2', 'exam', '#6c757d'),
(@admin_id, @harta_course_id, 'Property Site Visit', 'Visit development site in KL', DATE_ADD(CURDATE(), INTERVAL 8 DAY) + INTERVAL 8 HOUR, DATE_ADD(CURDATE(), INTERVAL 8 DAY) + INTERVAL 17 HOUR, 'KL City Center', 'class', '#fd7e14');

-- Add sample team members
INSERT INTO team_members (user_id, name, email, role, group_name, status) VALUES
(@admin_id, 'Alice Wong', 'alice.wong@student.edu.my', 'Team Leader', 'FYP Group', 'active'),
(@admin_id, 'Bob Chen', 'bob.chen@student.edu.my', 'Developer', 'FYP Group', 'active'),
(@admin_id, 'Carol Lim', 'carol.lim@student.edu.my', 'Designer', 'FYP Group', 'active'),
(@admin_id, 'Emma Tan', 'emma.tan@student.edu.my', 'Frontend Developer', 'Programming Group', 'active'),
(@admin_id, 'Frank Lee', 'frank.lee@student.edu.my', 'Backend Developer', 'Programming Group', 'active');

-- Add sample time entries
INSERT INTO time_entries (user_id, task_id, course_id, title, description, start_time, end_time, duration, date) VALUES
(@admin_id, (SELECT id FROM tasks WHERE title = 'Build Login System' LIMIT 1), @web_course_id, 'Login System Development', 'Working on user authentication', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 4 HOUR, 14400, DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(@admin_id, (SELECT id FROM tasks WHERE title = 'Complete Literature Review' LIMIT 1), @fyp_course_id, 'Literature Research', 'Reading academic papers', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 3 HOUR, 10800, DATE_SUB(CURDATE(), INTERVAL 1 DAY));

-- =====================================================
-- SIMPLE TRIGGERS (Essential Only)
-- =====================================================

DELIMITER //

-- Auto-set completion time when task is marked as done
CREATE TRIGGER set_task_completion_time
    BEFORE UPDATE ON tasks
    FOR EACH ROW
BEGIN
    IF NEW.status = 'done' AND OLD.status != 'done' THEN
        SET NEW.completed_at = NOW();
    ELSEIF NEW.status != 'done' AND OLD.status = 'done' THEN
        SET NEW.completed_at = NULL;
    END IF;
END//

-- Auto-calculate duration for time entries
CREATE TRIGGER calculate_duration
    BEFORE UPDATE ON time_entries
    FOR EACH ROW
BEGIN
    IF NEW.end_time IS NOT NULL AND OLD.end_time IS NULL THEN
        SET NEW.duration = TIMESTAMPDIFF(SECOND, NEW.start_time, NEW.end_time);
    END IF;
END//

-- Auto-calculate duration when inserting complete time entry
CREATE TRIGGER calculate_duration_insert
    BEFORE INSERT ON time_entries
    FOR EACH ROW
BEGIN
    IF NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL AND NEW.duration IS NULL THEN
        SET NEW.duration = TIMESTAMPDIFF(SECOND, NEW.start_time, NEW.end_time);
    END IF;
END//

DELIMITER ;

-- =====================================================
-- SIMPLE VIEWS (Essential Only)
-- =====================================================

-- User dashboard view
CREATE VIEW user_dashboard AS
SELECT 
    u.id as user_id,
    u.name,
    u.email,
    COUNT(DISTINCT t.id) as total_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'done' THEN t.id END) as completed_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'todo' THEN t.id END) as pending_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'progress' THEN t.id END) as in_progress_tasks,
    COUNT(DISTINCT CASE WHEN t.due_date = CURDATE() AND t.status != 'done' THEN t.id END) as due_today,
    COUNT(DISTINCT c.id) as total_courses
FROM users u
LEFT JOIN tasks t ON u.id = t.user_id
LEFT JOIN courses c ON u.id = c.user_id AND c.status = 'active'
WHERE u.status = 'active'
GROUP BY u.id, u.name, u.email;

-- Course overview view
CREATE VIEW course_overview AS
SELECT 
    c.*,
    COUNT(DISTINCT t.id) as total_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'done' THEN t.id END) as completed_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'todo' THEN t.id END) as pending_tasks,
    COUNT(DISTINCT cs.id) as class_sessions
FROM courses c
LEFT JOIN tasks t ON c.id = t.course_id
LEFT JOIN class_schedules cs ON c.id = cs.course_id
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
ORDER BY 
    FIELD(cs.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
    cs.start_time;

-- =====================================================
-- BASIC INDEXES FOR PERFORMANCE
-- =====================================================

-- Essential indexes only
CREATE INDEX idx_tasks_user_status ON tasks(user_id, status);
CREATE INDEX idx_tasks_due_date ON tasks(due_date);
CREATE INDEX idx_courses_user ON courses(user_id);
CREATE INDEX idx_events_user_date ON events(user_id, start_datetime);
CREATE INDEX idx_schedules_user_day ON class_schedules(user_id, day_of_week);
CREATE INDEX idx_time_entries_user_date ON time_entries(user_id, date);
CREATE INDEX idx_team_members_user_group ON team_members(user_id, group_name);

-- =====================================================
-- DATABASE SETUP COMPLETE
-- =====================================================

SELECT 'EduHive Simple Database Setup Completed!' as status;
SELECT 'Default admin login: admin@eduhive.com / admin123' as admin_info;
SELECT 'Perfect for beginners - simple and clean!' as note;