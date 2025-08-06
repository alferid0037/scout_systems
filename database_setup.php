<?php
require_once 'config.php';

// Create tables for the course progress system
try {
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Courses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        age_group VARCHAR(20) NOT NULL,
        total_lessons INT DEFAULT 10,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Course progress table
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        lessons_completed INT DEFAULT 0,
        progress_percentage DECIMAL(5,2) DEFAULT 0.00,
        last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_course (user_id, course_id)
    )");

    // Lesson progress table
    $pdo->exec("CREATE TABLE IF NOT EXISTS lesson_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        lesson_number INT NOT NULL,
        completed BOOLEAN DEFAULT FALSE,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_lesson (user_id, course_id, lesson_number)
    )");

    // Insert sample data
    // Insert sample user
    $pdo->exec("INSERT IGNORE INTO users (id, username, email) VALUES (1, 'John Doe', 'john@example.com')");

    // Insert sample courses
    $courses = [
        ['Programming Basics for Kids', 'Learn the fundamentals of programming', '6-8', 8],
        ['Web Development Fundamentals', 'Introduction to HTML, CSS, and JavaScript', '9-11', 12],
        ['Advanced Programming Concepts', 'Object-oriented programming and algorithms', '12-14', 15],
        ['Full Stack Development', 'Complete web application development', '15-18', 20]
    ];

    foreach ($courses as $index => $course) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO courses (id, title, description, age_group, total_lessons) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$index + 1, $course[0], $course[1], $course[2], $course[3]]);
    }

    // Insert sample progress data
    $progress_data = [
        [1, 1, 6, 75.00, 'in_progress'],
        [1, 2, 8, 66.67, 'in_progress'],
        [1, 3, 15, 100.00, 'completed'],
        [1, 4, 3, 15.00, 'in_progress']
    ];

    foreach ($progress_data as $progress) {
        $stmt = $pdo->prepare("INSERT INTO course_progress (user_id, course_id, lessons_completed, progress_percentage, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE lessons_completed = VALUES(lessons_completed), progress_percentage = VALUES(progress_percentage), status = VALUES(status)");
        $stmt->execute($progress);
    }

    echo "Database setup completed successfully!";

} catch(PDOException $e) {
    die("Error setting up database: " . $e->getMessage());
}
?>