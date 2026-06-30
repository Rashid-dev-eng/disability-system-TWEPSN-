<?php
require 'database.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $full_name = $conn->real_escape_string($_POST['full_name'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $date_of_birth = $conn->real_escape_string($_POST['date_of_birth'] ?? '');
    $gender = $conn->real_escape_string($_POST['gender'] ?? '');
    $region = $conn->real_escape_string($_POST['region'] ?? '');
    $district = $conn->real_escape_string($_POST['district'] ?? '');
    $disability_type = $conn->real_escape_string($_POST['disability_type'] ?? '');
    $disability_severity = $conn->real_escape_string($_POST['disability_severity'] ?? '');
    $communication_preference = $conn->real_escape_string($_POST['communication_preference'] ?? '');
    $terms = $_POST['terms'] ?? '';

    // Basic validation
    if (!$full_name || !$email || !$phone || !$password || !$confirm_password || !$disability_type) {
        $errors[] = "Please fill in all required fields.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "PIN codes do not match.";
    }
    if (strlen($password) !== 4 || !ctype_digit($password)) {
        $errors[] = "PIN must be a 4-digit number.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (!$terms) {
        $errors[] = "You must agree to the terms and conditions.";
    }

    // Check if phone already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? OR email = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("ss", $phone, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result && $check_result->num_rows > 0) {
            $errors[] = "Phone number or email already registered.";
        }
        $check_stmt->close();
    }

    if (empty($errors)) {
        // Hash the PIN
        $hashed_pin = password_hash($password, PASSWORD_DEFAULT);

        // Insert into users table only
        $sql = "INSERT INTO users (full_name, email, phone, pin, date_of_birth, gender, region, district, disability_type, disability_severity, communication_preference, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', NOW())";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssssssssss", 
                $full_name, 
                $email,
                $phone, 
                $hashed_pin, 
                $date_of_birth, 
                $gender, 
                $region, 
                $district, 
                $disability_type, 
                $disability_severity, 
                $communication_preference
            );
            
            if ($stmt->execute()) {
                // Get the newly created user ID
                $user_id = $stmt->insert_id;
                
                // Create user-specific tables if they don't exist (with error handling)
                $table_creation_result = create_user_tables($conn, $user_id);
                
                // Set session and redirect to dashboard
                session_start();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                $_SESSION['role'] = 'user';
                $_SESSION['login_time'] = time();
                
                // Log the registration
                log_user_action($conn, $user_id, $email, "User Registration", "New user account created: {$full_name}");
                
                header("Location: user_dashboard.php");
                exit;
            } else {
                $errors[] = "Registration failed. Please try again. Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}

/**
 * Create user-specific tables for data isolation with error handling
 */
function create_user_tables($conn, $user_id) {
    $results = [];
    
    try {
        // Create user_appointments table if not exists
        $appointments_table = "CREATE TABLE IF NOT EXISTS user_appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            purpose VARCHAR(255) NOT NULL,
            status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX user_id_idx (user_id)
        )";
        $results['appointments'] = $conn->query($appointments_table);
        
        // Create user_documents table if not exists
        $documents_table = "CREATE TABLE IF NOT EXISTS user_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            document_path VARCHAR(500) NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX user_id_idx (user_id)
        )";
        $results['documents'] = $conn->query($documents_table);
        
        // Create user_activity_log table if not exists
        $activity_table = "CREATE TABLE IF NOT EXISTS user_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX user_id_idx (user_id)
        )";
        $results['activity_log'] = $conn->query($activity_table);
        
    } catch (Exception $e) {
        // Log the error but don't stop registration
        error_log("Table creation error for user {$user_id}: " . $e->getMessage());
        return false;
    }
    
    return true;
}

/**
 * Log user actions for audit trail
 */
function log_user_action($conn, $user_id, $user_email, $action, $description) {
    try {
        $log_sql = "INSERT INTO user_activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        if ($log_stmt) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // Log error but don't break the flow
        error_log("Activity log error: " . $e->getMessage());
    }
}

// Close database connection at the end
$conn->close();
?>

<!-- REST OF YOUR HTML CODE REMAINS EXACTLY THE SAME -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PWD Registration System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .register-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            background: rgba(255, 255, 255, 0.95);
        }
        .register-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .register-logo i {
            font-size: 3.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }
        .progress-bar-container {
            margin-bottom: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #6c757d;
        }
        .step.active {
            background-color: #0d6efd;
            color: white;
        }
        .step.completed {
            background-color: #198754;
            color: white;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="card register-card">
                <div class="card-body p-4 p-md-5">
                    
                    <!-- Logo and Header -->
                    <div class="register-logo">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="card-title text-center mb-4">Create Your Account</h3>
                    <p class="text-center text-muted mb-4">Register to access the PWD Registration System</p>

                    <!-- Progress Indicator -->
                    <div class="progress-bar-container">
                        <div class="step-indicator">
                            <div class="step active" id="step1">1</div>
                            <div class="step" id="step2">2</div>
                            <div class="step" id="step3">3</div>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" id="registrationProgress" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- PHP Error Display -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Registration Form -->
                    <form id="registrationForm" method="POST" action="">
                        
                        <!-- Step 1: Account Information -->
                        <div class="form-section" id="step1-section">
                            <h5 class="mb-3"><i class="fas fa-user-circle me-2"></i>Account Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fullName" class="form-label">Full Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="fullName" name="full_name" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="your@email.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phoneNumber" class="form-label">Phone Number *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="phoneNumber" name="phone" placeholder="0755123456" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Create PIN *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" maxlength="4" placeholder="4-digit PIN" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="confirmPassword" class="form-label">Confirm PIN *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password" maxlength="4" placeholder="Re-enter your PIN" required>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary next-step" data-next="2">Next <i class="fas fa-arrow-right ms-2"></i></button>
                            </div>
                        </div>

                        <!-- Step 2: Personal Details -->
                        <div class="form-section d-none" id="step2-section">
                            <h5 class="mb-3"><i class="fas fa-id-card me-2"></i>Personal Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Region *</label>
                                    <select class="form-select" name="region" id="region" required>
                                        <option value="">Select Region</option>
                                        <option value="Arusha" <?php echo ($_POST['region'] ?? '') === 'Arusha' ? 'selected' : ''; ?>>Arusha</option>
                                        <option value="Dar es Salaam" <?php echo ($_POST['region'] ?? '') === 'Dar es Salaam' ? 'selected' : ''; ?>>Dar es Salaam</option>
                                        <option value="Dodoma" <?php echo ($_POST['region'] ?? '') === 'Dodoma' ? 'selected' : ''; ?>>Dodoma</option>
                                        <option value="Geita" <?php echo ($_POST['region'] ?? '') === 'Geita' ? 'selected' : ''; ?>>Geita</option>
                                        <option value="Iringa" <?php echo ($_POST['region'] ?? '') === 'Iringa' ? 'selected' : ''; ?>>Iringa</option>
                                        <option value="Kagera" <?php echo ($_POST['region'] ?? '') === 'Kagera' ? 'selected' : ''; ?>>Kagera</option>
                                        <option value="Katavi" <?php echo ($_POST['region'] ?? '') === 'Katavi' ? 'selected' : ''; ?>>Katavi</option>
                                        <option value="Kigoma" <?php echo ($_POST['region'] ?? '') === 'Kigoma' ? 'selected' : ''; ?>>Kigoma</option>
                                        <option value="Kilimanjaro" <?php echo ($_POST['region'] ?? '') === 'Kilimanjaro' ? 'selected' : ''; ?>>Kilimanjaro</option>
                                        <option value="Lindi" <?php echo ($_POST['region'] ?? '') === 'Lindi' ? 'selected' : ''; ?>>Lindi</option>
                                        <option value="Manyara" <?php echo ($_POST['region'] ?? '') === 'Manyara' ? 'selected' : ''; ?>>Manyara</option>
                                        <option value="Mara" <?php echo ($_POST['region'] ?? '') === 'Mara' ? 'selected' : ''; ?>>Mara</option>
                                        <option value="Mbeya" <?php echo ($_POST['region'] ?? '') === 'Mbeya' ? 'selected' : ''; ?>>Mbeya</option>
                                        <option value="Mjini Magharibi" <?php echo ($_POST['region'] ?? '') === 'Mjini Magharibi' ? 'selected' : ''; ?>>Mjini Magharibi</option>
                                        <option value="Morogoro" <?php echo ($_POST['region'] ?? '') === 'Morogoro' ? 'selected' : ''; ?>>Morogoro</option>
                                        <option value="Mtwara" <?php echo ($_POST['region'] ?? '') === 'Mtwara' ? 'selected' : ''; ?>>Mtwara</option>
                                        <option value="Mwanza" <?php echo ($_POST['region'] ?? '') === 'Mwanza' ? 'selected' : ''; ?>>Mwanza</option>
                                        <option value="Njombe" <?php echo ($_POST['region'] ?? '') === 'Njombe' ? 'selected' : ''; ?>>Njombe</option>
                                        <option value="Pemba North" <?php echo ($_POST['region'] ?? '') === 'Pemba North' ? 'selected' : ''; ?>>Pemba North</option>
                                        <option value="Pemba South" <?php echo ($_POST['region'] ?? '') === 'Pemba South' ? 'selected' : ''; ?>>Pemba South</option>
                                        <option value="Pwani" <?php echo ($_POST['region'] ?? '') === 'Pwani' ? 'selected' : ''; ?>>Pwani</option>
                                        <option value="Rukwa" <?php echo ($_POST['region'] ?? '') === 'Rukwa' ? 'selected' : ''; ?>>Rukwa</option>
                                        <option value="Ruvuma" <?php echo ($_POST['region'] ?? '') === 'Ruvuma' ? 'selected' : ''; ?>>Ruvuma</option>
                                        <option value="Shinyanga" <?php echo ($_POST['region'] ?? '') === 'Shinyanga' ? 'selected' : ''; ?>>Shinyanga</option>
                                        <option value="Simiyu" <?php echo ($_POST['region'] ?? '') === 'Simiyu' ? 'selected' : ''; ?>>Simiyu</option>
                                        <option value="Singida" <?php echo ($_POST['region'] ?? '') === 'Singida' ? 'selected' : ''; ?>>Singida</option>
                                        <option value="Songwe" <?php echo ($_POST['region'] ?? '') === 'Songwe' ? 'selected' : ''; ?>>Songwe</option>
                                        <option value="Tabora" <?php echo ($_POST['region'] ?? '') === 'Tabora' ? 'selected' : ''; ?>>Tabora</option>
                                        <option value="Tanga" <?php echo ($_POST['region'] ?? '') === 'Tanga' ? 'selected' : ''; ?>>Tanga</option>
                                        <option value="Zanzibar North" <?php echo ($_POST['region'] ?? '') === 'Zanzibar North' ? 'selected' : ''; ?>>Zanzibar North</option>
                                        <option value="Zanzibar South" <?php echo ($_POST['region'] ?? '') === 'Zanzibar South' ? 'selected' : ''; ?>>Zanzibar South</option>
                                        <option value="Zanzibar West" <?php echo ($_POST['region'] ?? '') === 'Zanzibar West' ? 'selected' : ''; ?>>Zanzibar West</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">District *</label>
                                    <select class="form-select" name="district" id="district" required>
                                        <option value="">Select District</option>
                                        <!-- Districts will be loaded based on region selection -->
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary prev-step" data-prev="1">
                                    <i class="fas fa-arrow-left me-2"></i> Back
                                </button>
                                <button type="button" class="btn btn-primary next-step" data-next="3">Next <i class="fas fa-arrow-right ms-2"></i></button>
                            </div>
                        </div>

                        <!-- Step 3: Disability Information -->
                        <div class="form-section d-none" id="step3-section">
                            <h5 class="mb-3"><i class="fas fa-wheelchair me-2"></i>Disability Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Type of Disability *</label>
                                    <select class="form-select" name="disability_type" required>
                                        <option value="">Select Type</option>
                                        <option value="physical" <?php echo ($_POST['disability_type'] ?? '') === 'physical' ? 'selected' : ''; ?>>Physical</option>
                                        <option value="visual" <?php echo ($_POST['disability_type'] ?? '') === 'visual' ? 'selected' : ''; ?>>Visual Impairment</option>
                                        <option value="hearing" <?php echo ($_POST['disability_type'] ?? '') === 'hearing' ? 'selected' : ''; ?>>Hearing Impairment</option>
                                        <option value="intellectual" <?php echo ($_POST['disability_type'] ?? '') === 'intellectual' ? 'selected' : ''; ?>>Intellectual</option>
                                        <option value="other" <?php echo ($_POST['disability_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Severity</label>
                                    <select class="form-select" name="disability_severity">
                                        <option value="mild" <?php echo ($_POST['disability_severity'] ?? '') === 'mild' ? 'selected' : ''; ?>>Mild</option>
                                        <option value="moderate" <?php echo ($_POST['disability_severity'] ?? '') === 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                        <option value="severe" <?php echo ($_POST['disability_severity'] ?? '') === 'severe' ? 'selected' : ''; ?>>Severe</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Communication Preference</label>
                                <select class="form-select" name="communication_preference">
                                    <option value="text" <?php echo ($_POST['communication_preference'] ?? '') === 'text' ? 'selected' : ''; ?>>Text Message</option>
                                    <option value="call" <?php echo ($_POST['communication_preference'] ?? '') === 'call' ? 'selected' : ''; ?>>Phone Call</option>
                                    <option value="in_person" <?php echo ($_POST['communication_preference'] ?? '') === 'in_person' ? 'selected' : ''; ?>>In Person</option>
                                </select>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                                </label>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary prev-step" data-prev="2">
                                    <i class="fas fa-arrow-left me-2"></i> Back
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-plus me-2"></i> Complete Registration
                                </button>
                            </div>
                        </div>

                    </form>

                    <!-- Alert Message Area -->
                    <div id="registerAlert" class="alert alert-danger d-none mt-3" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><span id="alertMessage"></span>
                    </div>

                    <hr class="my-4">

                    <p class="text-center text-muted mb-0">Already have an account? <a href="login.php">Login here</a>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// JavaScript code remains exactly the same as in your previous version
$(document).ready(function() {
    let currentStep = 1;
    const totalSteps = 3;

    // Tanzania districts by region (same as before)
    const districtsByRegion = {
        "Arusha": ["Arusha City", "Arusha Rural", "Karatu", "Longido", "Meru", "Monduli", "Ngorongoro"],
        "Dar es Salaam": ["Ilala", "Kinondoni", "Temeke", "Ubungo", "Kigamboni"],
        "Dodoma": ["Bahi", "Chamwino", "Chemba", "Dodoma Municipal", "Kondoa", "Kongwa", "Mpwapwa"],
        "Geita": ["Bukombe", "Chato", "Geita", "Mbogwe", "Nyang'hwale"],
        "Iringa": ["Iringa Municipal", "Iringa Rural", "Kilolo", "Mafinga Town", "Mufindi"],
        "Kagera": ["Biharamulo", "Bukoba Rural", "Bukoba Urban", "Karagwe", "Kyerwa", "Missenyi", "Muleba", "Ngara"],
        "Katavi": ["Mlele", "Mpanda Town", "Mpanda Rural"],
        "Kigoma": ["Buhigwe", "Kakonko", "Kasulu Rural", "Kasulu Urban", "Kibondo", "Kigoma Rural", "Kigoma Urban", "Uvinza"],
        "Kilimanjaro": ["Hai", "Moshi Rural", "Moshi Urban", "Mwanga", "Rombo", "Same", "Siha"],
        "Lindi": ["Kilwa", "Lindi Municipal", "Lindi Rural", "Liwale", "Nachingwea", "Ruangwa"],
        "Manyara": ["Babati Rural", "Babati Urban", "Hanang", "Kiteto", "Mbulu", "Simanjiro"],
        "Mara": ["Bunda", "Butiama", "Musoma Rural", "Musoma Urban", "Rorya", "Serengeti", "Tarime"],
        "Mbeya": ["Busokelo", "Chunya", "Kyela", "Mbarali", "Mbeya City", "Mbeya Rural", "Rungwe"],
        "Mjini Magharibi": ["Magharibi", "Mjini"],
        "Morogoro": ["Gairo", "Kilombero", "Kilosa", "Morogoro Rural", "Morogoro Urban", "Mvomero", "Ulanga"],
        "Mtwara": ["Masasi", "Masasi Town", "Mtwara Rural", "Mtwara Urban", "Nanyumbu", "Newala", "Tandahimba"],
        "Mwanza": ["Ilemela", "Kwimba", "Magu", "Misungwi", "Nyamagana", "Sengerema", "Ukerewe"],
        "Njombe": ["Ludewa", "Makambako Town", "Makete", "Njombe Rural", "Njombe Urban", "Wanging'ombe"],
        "Pemba North": ["Micheweni", "Wete"],
        "Pemba South": ["Chake Chake", "Mkoani"],
        "Pwani": ["Bagamoyo", "Kibaha", "Kibaha Town", "Kisarawe", "Mafia", "Mkuranga", "Rufiji"],
        "Rukwa": ["Kalambo", "Nkasi", "Sumbawanga Rural", "Sumbawanga Urban"],
        "Ruvuma": ["Mbinga", "Songea Rural", "Songea Urban", "Tunduru", "Namtumbo"],
        "Shinyanga": ["Kahama", "Kahama Town", "Kishapu", "Shinyanga Rural", "Shinyanga Urban"],
        "Simiyu": ["Bariadi", "Busega", "Itilima", "Maswa", "Meatu"],
        "Singida": ["Iramba", "Manyoni", "Mkalama", "Singida Rural", "Singida Urban"],
        "Songwe": ["Ileje", "Mbozi", "Momba", "Songwe"],
        "Tabora": ["Igunga", "Kaliua", "Nzega", "Sikonge", "Tabora Urban", "Urambo", "Uyui"],
        "Tanga": ["Handeni", "Handeni Town", "Kilindi", "Korogwe", "Korogwe Town", "Lushoto", "Mkinga", "Muheza", "Pangani", "Tanga City"],
        "Zanzibar North": ["Kaskazini 'A'", "Kaskazini 'B'"],
        "Zanzibar South": ["Kusini", "Kati"],
        "Zanzibar West": ["Magharibi", "Mji Mkongwe"]
    };

    // Update progress bar
    function updateProgress() {
        const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
        $('#registrationProgress').css('width', progress + '%').attr('aria-valuenow', progress);
        
        // Update step indicators
        $('.step').removeClass('active completed');
        for (let i = 1; i <= totalSteps; i++) {
            if (i < currentStep) {
                $('#step' + i).addClass('completed');
            } else if (i === currentStep) {
                $('#step' + i).addClass('active');
            }
        }
    }

    // Show alert message
    function showAlert(message, type = 'danger') {
        $('#alertMessage').text(message);
        $('#registerAlert').removeClass('d-none alert-success alert-danger').addClass('alert-' + type);
    }

    // Hide alert message
    function hideAlert() {
        $('#registerAlert').addClass('d-none');
    }

    // Next step button handler
    $('.next-step').on('click', function() {
        const nextStep = $(this).data('next');
        
        // Validate current step before proceeding
        if (currentStep === 1) {
            const email = $('#email').val().trim();
            const phone = $('#phoneNumber').val().trim();
            const password = $('#password').val();
            const confirmPassword = $('#confirmPassword').val();
            
            if (email === '' || phone === '' || password === '' || $('#fullName').val().trim() === '') {
                showAlert('Please fill in all required fields.');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showAlert('Please enter a valid email address.');
                return;
            }
            
            if (password !== confirmPassword) {
                showAlert('PIN codes do not match.');
                return;
            }
            
            if (password.length !== 4 || !$.isNumeric(password)) {
                showAlert('PIN must be a 4-digit number.');
                return;
            }
        }

        hideAlert();
        $('#step' + currentStep + '-section').addClass('d-none');
        $('#step' + nextStep + '-section').removeClass('d-none');
        currentStep = nextStep;
        updateProgress();
    });

    // Previous step button handler
    $('.prev-step').on('click', function() {
        const prevStep = $(this).data('prev');
        hideAlert();
        $('#step' + currentStep + '-section').addClass('d-none');
        $('#step' + prevStep + '-section').removeClass('d-none');
        currentStep = prevStep;
        updateProgress();
    });

    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        const passwordInput = $(this).closest('.input-group').find('input');
        const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
        passwordInput.attr('type', type);
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    // Region change handler
    $('#region').on('change', function() {
        const region = $(this).val();
        const districtSelect = $('#district');
        
        if (region && districtsByRegion[region]) {
            districtSelect.html('<option value="">Select District</option>');
            districtsByRegion[region].forEach(function(district) {
                districtSelect.append('<option value="' + district + '">' + district + '</option>');
            });
        } else {
            districtSelect.html('<option value="">Select District</option>');
        }
    });

    // Initialize progress
    updateProgress();
});
</script>

</body>
</html>