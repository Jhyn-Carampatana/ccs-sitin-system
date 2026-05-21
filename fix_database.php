<?php
// fix_database.php - Run this once to fix the database structure

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Fixing database structure...<br>";

// Check if duration_minutes column exists
$check = $conn->query("SHOW COLUMNS FROM sit_in_sessions LIKE 'duration_minutes'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE sit_in_sessions ADD COLUMN duration_minutes INT DEFAULT 0 AFTER time_out");
    echo "✅ Added 'duration_minutes' column<br>";
} else {
    echo "⏭️ 'duration_minutes' column already exists<br>";
}

// Check if ended_by column exists
$check = $conn->query("SHOW COLUMNS FROM sit_in_sessions LIKE 'ended_by'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE sit_in_sessions ADD COLUMN ended_by VARCHAR(50) DEFAULT NULL AFTER status");
    echo "✅ Added 'ended_by' column<br>";
} else {
    echo "⏭️ 'ended_by' column already exists<br>";
}

// Check if cancelled status exists in ENUM
$check = $conn->query("SHOW COLUMNS FROM sit_in_sessions LIKE 'status'");
$row = $check->fetch_assoc();
$enum_values = $row['Type'];
if (strpos($enum_values, 'cancelled') === false) {
    $conn->query("ALTER TABLE sit_in_sessions MODIFY COLUMN status ENUM('active', 'completed', 'cancelled') DEFAULT 'active'");
    echo "✅ Updated 'status' column with 'cancelled' option<br>";
} else {
    echo "⏭️ 'cancelled' status already exists<br>";
}

// Create lab_capacity table if not exists
$conn->query("
CREATE TABLE IF NOT EXISTS lab_capacity (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    laboratory VARCHAR(20) NOT NULL UNIQUE,
    capacity INT DEFAULT 30,
    current_occupancy INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
echo "✅ lab_capacity table ready<br>";

// Initialize lab capacities if empty
$count = $conn->query("SELECT COUNT(*) as count FROM lab_capacity")->fetch_assoc()['count'];
if ($count == 0) {
    $labs = ['Lab 1', 'Lab 2', 'Lab 3', 'Lab 4', 'Lab 530'];
    foreach ($labs as $lab) {
        $conn->query("INSERT INTO lab_capacity (laboratory, capacity, current_occupancy) VALUES ('$lab', 30, 0)");
    }
    echo "✅ Lab capacities initialized<br>";
} else {
    echo "⏭️ Lab capacities already exist<br>";
}

echo "<hr>";
echo "<strong>Database fix completed!</strong><br>";
echo "<a href='sit_in_management.php'>Go to Sit-in Management</a>";
?>