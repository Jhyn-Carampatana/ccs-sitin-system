<?php
$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if not exists
$conn->query("CREATE TABLE IF NOT EXISTS rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    points_cost INT NOT NULL,
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS points_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    points_change INT NOT NULL,
    reason VARCHAR(255),
    admin_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    reward_id INT NOT NULL,
    points_spent INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS reservations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11),
    id_number VARCHAR(20) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    course VARCHAR(20) NOT NULL,
    year_level VARCHAR(20) NOT NULL,
    purpose VARCHAR(50) NOT NULL,
    laboratory VARCHAR(20) NOT NULL,
    reservation_date DATE NOT NULL,
    time_in TIME NOT NULL,
    sessions_used INT(11) DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Insert default settings
$default_settings = [
    'easy_points' => '5', 'medium_points' => '10', 'medium_multiplier' => '1.5',
    'hard_points' => '15', 'hard_multiplier' => '2', 'morning_points' => '5',
    'afternoon_points' => '5', 'evening_points' => '8', 'bonus_hour' => '2'
];
foreach ($default_settings as $key => $value) {
    $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('$key', '$value') 
                  ON DUPLICATE KEY UPDATE setting_value = setting_value");
}
?>