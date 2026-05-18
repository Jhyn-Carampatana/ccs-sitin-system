<?php
$conn = new mysqli("localhost", "root", "", "jhyn");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Verifying Admin Account</h2>";

$result = $conn->query("SELECT * FROM admin WHERE username = '21459748'");

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    
    echo "✅ Admin found!<br>";
    echo "Username: " . $admin['username'] . "<br>";
    echo "Password Hash: " . $admin['password'] . "<br><br>";
    
    $test_password = 'admin123';
    
    if (password_verify($test_password, $admin['password'])) {
        echo "<span style='color: green; font-size: 20px; font-weight: bold;'>✅ SUCCESS! Password 'admin123' is CORRECT!</span><br><br>";
        echo "You can now login with:<br>";
        echo "<strong>ID Number:</strong> 21459748-admin<br>";
        echo "<strong>Password:</strong> admin123<br><br>";
        echo "<a href='Login.php' style='background: #1a6fc4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
    } else {
        echo "<span style='color: red; font-weight: bold;'>❌ Password still doesn't match!</span><br>";
        
        // Generate new hash
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        echo "New hash: " . $new_hash . "<br>";
        echo "Run this SQL:<br>";
        echo "<code>UPDATE admin SET password = '$new_hash' WHERE username = '21459748';</code>";
    }
} else {
    echo "❌ Admin not found!<br>";
    $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
    echo "Run this SQL:<br>";
    echo "<code>INSERT INTO admin (username, password, email, full_name) VALUES ('21459748', '$new_hash', 'admin@uc.edu.ph', 'System Administrator');</code>";
}

$conn->close();
?>