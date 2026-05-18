<?php
$conn = new mysqli("localhost", "root", "", "jhyn");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create admin table
$conn->query("CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Delete existing admin
$conn->query("DELETE FROM admin WHERE username = '21459748'");

// Insert admin with correct password
$password = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admin (username, password, email, full_name) VALUES (?, ?, ?, ?)");
$username = '21459748';
$email = 'admin@uc.edu.ph';
$full_name = 'System Administrator';
$stmt->bind_param("ssss", $username, $password, $email, $full_name);

if ($stmt->execute()) {
    echo "<h2 style='color: green;'>✅ Admin account created successfully!</h2>";
    echo "<p><strong>Username:</strong> 21459748</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p><strong>Login ID:</strong> 21459748-admin</p>";
    echo "<br><a href='Login.php' style='background: #1a6fc4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
} else {
    echo "<h2 style='color: red;'>❌ Error: " . $conn->error . "</h2>";
}

$stmt->close();
$conn->close();
?>