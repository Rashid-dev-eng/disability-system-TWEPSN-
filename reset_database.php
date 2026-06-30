<?php
// reset_database.php
echo "<h1>Resetting Database...</h1>";

$servername = "localhost";
$username = "root";
$password = "";
$database = "disability";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop and recreate database
$conn->query("DROP DATABASE IF EXISTS disability");
$conn->query("CREATE DATABASE disability");
$conn->select_db($database);

echo "<p>Database created successfully</p>";

// Create tables with ALL required columns
$tables = [
    "CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        full_name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL UNIQUE,
        email VARCHAR(255),
        pin VARCHAR(255) NOT NULL,
        date_of_birth DATE,
        gender ENUM('male', 'female', 'other'),
        disability_type VARCHAR(100),
        region VARCHAR(100),
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE admins (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        department VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE appointments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        purpose VARCHAR(255) NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        disability_type VARCHAR(100) NOT NULL,
        message TEXT,
        status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE services (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        category VARCHAR(100),
        is_active BOOLEAN DEFAULT 1,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE service_applications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        service_type VARCHAR(255) NOT NULL,
        disability_type VARCHAR(100) NOT NULL,
        region VARCHAR(100),
        message TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        system_name VARCHAR(255) NOT NULL DEFAULT 'Disability Support System',
        admin_email VARCHAR(255) NOT NULL DEFAULT 'admin@disability-system.com',
        contact_phone VARCHAR(50) DEFAULT '+255 789 123 456',
        system_url VARCHAR(255) DEFAULT 'http://localhost/disability_system',
        email_notifications TINYINT DEFAULT 1,
        sms_notifications TINYINT DEFAULT 0,
        appointment_reminders TINYINT DEFAULT 1,
        application_alerts TINYINT DEFAULT 1,
        require_strong_passwords TINYINT DEFAULT 1,
        enable_2fa TINYINT DEFAULT 0,
        session_timeout INT DEFAULT 60,
        max_login_attempts INT DEFAULT 5,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        admin_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
        is_read BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE audit_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        user_email VARCHAR(100),
        action VARCHAR(255) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $sql) {
    if ($conn->query($sql)) {
        echo "<p>✓ Table created successfully</p>";
    } else {
        echo "<p>✗ Error creating table: " . $conn->error . "</p>";
    }
}

// Create default admin
$hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
$admin_sql = "INSERT INTO users (full_name, phone, pin, role, email) VALUES (?, ?, ?, 'admin', ?)";
$stmt = $conn->prepare($admin_sql);
$admin_name = 'System Administrator';
$admin_phone = '+255000000000';
$admin_email = 'admin@disability-system.com';

if ($stmt) {
    $stmt->bind_param("ssss", $admin_name, $admin_phone, $hashed_password, $admin_email);
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        echo "<p>✓ Admin user created (ID: $user_id)</p>";
        
        // Create admin record
        $admin_record_sql = "INSERT INTO admins (user_id, username, email, department) VALUES (?, ?, ?, ?)";
        $stmt2 = $conn->prepare($admin_record_sql);
        $username = 'admin';
        $department = 'Administration';
        
        if ($stmt2) {
            $stmt2->bind_param("isss", $user_id, $username, $admin_email, $department);
            $stmt2->execute();
            echo "<p>✓ Admin record created</p>";
            $stmt2->close();
        }
    }
    $stmt->close();
}

// Insert default settings
$conn->query("INSERT INTO system_settings (id) VALUES (1)");
echo "<p>✓ Default settings created</p>";

echo "<h2>Database reset complete! <a href='admin/admin_login.php'>Go to Admin Login</a></h2>";
$conn->close();
?>