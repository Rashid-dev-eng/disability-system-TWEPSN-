<?php
session_start();
require 'database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$success_msg = $error_msg = '';

// Create education_support_applications table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS education_support_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    support_type VARCHAR(50) NOT NULL,
    education_level VARCHAR(50) NOT NULL,
    institution_name VARCHAR(255),
    course_program VARCHAR(255),
    academic_year VARCHAR(50),
    primary_school VARCHAR(255),
    primary_years VARCHAR(50),
    secondary_school VARCHAR(255),
    secondary_type VARCHAR(50),
    secondary_years VARCHAR(50),
    other_education VARCHAR(255),
    financial_assistance ENUM('yes', 'no') DEFAULT 'no',
    learning_materials ENUM('yes', 'no') DEFAULT 'no',
    tutoring_support ENUM('yes', 'no') DEFAULT 'no',
    special_accommodations ENUM('yes', 'no') DEFAULT 'no',
    academic_documents_path VARCHAR(500),
    additional_notes TEXT,
    other_support_details TEXT,
    status ENUM('pending', 'approved', 'rejected', 'in_review') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_education_level (education_level),
    INDEX idx_created_at (created_at)
)";

if (!$conn->query($createTableSQL)) {
    error_log("Error creating education_support_applications table: " . $conn->error);
}

// Fetch user data
$user = null;
$sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if form was already submitted to prevent duplicate submissions
    if (isset($_SESSION['form_submitted']) && $_SESSION['form_submitted'] === true) {
        // Form was already submitted, redirect to clear POST data
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Validation
    $errors = [];
    
    $support_type = trim($_POST['support_type'] ?? '');
    $education_level = trim($_POST['education_level'] ?? '');
    
    // Validate required fields
    if (empty($support_type)) {
        $errors[] = 'Support type is required';
    }
    
    if (empty($education_level)) {
        $errors[] = 'Education level is required';
    }
    
    // Initialize variables for all possible fields
    $institution_name = '';
    $course_program = '';
    $academic_year = '';
    $primary_school = '';
    $primary_years = '';
    $secondary_school = '';
    $secondary_type = '';
    $secondary_years = '';
    $other_education = '';
    $other_support_details = '';
    $additional_notes = '';
    
    // Set values based on education level
    if ($education_level === 'primary') {
        $primary_school = trim($_POST['primary_school'] ?? '');
        $primary_years = trim($_POST['primary_years'] ?? '');
        
        if (empty($primary_school)) {
            $errors[] = 'Primary school name is required';
        }
    } else if ($education_level === 'secondary') {
        $secondary_school = trim($_POST['secondary_school'] ?? '');
        $secondary_type = trim($_POST['secondary_type'] ?? '');
        $secondary_years = trim($_POST['secondary_years'] ?? '');
        
        if (empty($secondary_school)) {
            $errors[] = 'Secondary school name is required';
        }
    } else if ($education_level === 'other') {
        $other_education = trim($_POST['other_education'] ?? '');
        
        if (empty($other_education)) {
            $errors[] = 'Please specify your education';
        }
    } else {
        $institution_name = trim($_POST['institution_name'] ?? '');
        $course_program = trim($_POST['course_program'] ?? '');
        $academic_year = trim($_POST['academic_year'] ?? '');
        
        if (empty($institution_name)) {
            $errors[] = 'Institution name is required';
        }
        if (empty($course_program)) {
            $errors[] = 'Course/program is required';
        }
        if (empty($academic_year)) {
            $errors[] = 'Academic year is required';
        }
    }
    
    // Handle other support details if "other" is selected
    if ($support_type === 'other') {
        $other_support_details = trim($_POST['other_support_details'] ?? '');
        
        if (empty($other_support_details)) {
            $errors[] = 'Please specify the type of support you need';
        }
    } else {
        $additional_notes = trim($_POST['additional_notes'] ?? '');
        
        if (empty($additional_notes)) {
            $errors[] = 'Please describe your specific support requirements';
        }
    }
    
    $financial_assistance = isset($_POST['financial_assistance']) ? 'yes' : 'no';
    $learning_materials = isset($_POST['learning_materials']) ? 'yes' : 'no';
    $tutoring_support = isset($_POST['tutoring_support']) ? 'yes' : 'no';
    $special_accommodations = isset($_POST['special_accommodations']) ? 'yes' : 'no';
    
    if (empty($errors)) {
        // File upload handling
        $academic_documents_path = '';
        if (isset($_FILES['academic_documents']) && $_FILES['academic_documents']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/education_documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Validate file type
            $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($_FILES['academic_documents']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                $file_name = time() . '_' . uniqid() . '_' . basename($_FILES['academic_documents']['name']);
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['academic_documents']['tmp_name'], $target_path)) {
                    $academic_documents_path = $target_path;
                } else {
                    $errors[] = 'Failed to upload academic documents';
                }
            } else {
                $errors[] = 'Invalid file type. Allowed types: PDF, DOC, DOCX, JPG, JPEG, PNG';
            }
        }
        
        if (empty($errors)) {
            // Insert application into database
            $insert_sql = "INSERT INTO education_support_applications 
                          (user_id, support_type, education_level, institution_name, course_program, 
                           academic_year, primary_school, primary_years, secondary_school, secondary_type,
                           secondary_years, other_education, financial_assistance, learning_materials, tutoring_support, 
                           special_accommodations, academic_documents_path, additional_notes, other_support_details, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($insert_sql);
            if ($stmt) {
                $stmt->bind_param("issssssssssssssssss", $user_id, $support_type, $education_level, $institution_name, 
                                 $course_program, $academic_year, $primary_school, $primary_years, $secondary_school,
                                 $secondary_type, $secondary_years, $other_education, $financial_assistance, $learning_materials, 
                                 $tutoring_support, $special_accommodations, $academic_documents_path, $additional_notes, $other_support_details);
                
                if ($stmt->execute()) {
                    // Mark form as submitted to prevent duplicates
                    $_SESSION['form_submitted'] = true;
                    
                    // Store success message in session for display after redirect
                    $_SESSION['success_message'] = "Your education support application has been submitted successfully!";
                    
                    // Redirect to clear POST data (Post-Redirect-Get pattern)
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $error_msg = "Error submitting application: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_msg = "Database error: " . $conn->error;
            }
        } else {
            $error_msg = implode('<br>', $errors);
        }
    } else {
        $error_msg = implode('<br>', $errors);
    }
}

// Check for success message from session (after redirect)
if (isset($_SESSION['success_message'])) {
    $success_msg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
    // Also clear the form submitted flag
    unset($_SESSION['form_submitted']);
}

// Clear form submitted flag when page is accessed via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    unset($_SESSION['form_submitted']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Education Support - PWD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .assistance-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .service-header {
            background: linear-gradient(120deg, #1cc88a, #13855c);
            color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .form-section {
            border-bottom: 1px solid #e3e6f0;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .form-section:last-child {
            border-bottom: none;
        }
        .support-option {
            border: 2px solid #e3e6f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .support-option:hover, .support-option.selected {
            border-color: #1cc88a;
            background-color: #f8f9fc;
        }
        .support-option input[type="radio"] {
            display: none;
        }
        
        body {
            background: #f8f9fc;
            min-height: 100vh;
        }
        
        .container {
            padding: 2rem 0;
        }
        
        .checkbox-group {
            background: #f8f9fc;
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e3e6f0;
        }
        
        .conditional-field {
            display: none;
        }
        
        .fade-in {
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .alert-auto-dismiss {
            transition: opacity 0.5s ease-out;
        }
        
        #otherSupportField {
            margin-top: 1rem;
            padding: 1rem;
            background-color: #f8f9fc;
            border-radius: 0.5rem;
            border-left: 4px solid #1cc88a;
        }
        
        .error-highlight {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
        
        .hidden-section {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Service Header -->
                <div class="service-header">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <i class="fas fa-graduation-cap fa-4x"></i>
                        </div>
                        <div class="col-md-8">
                            <h1 class="mb-2">Education Support</h1>
                            <p class="mb-0">Access educational resources, financial assistance, and support services to achieve your academic goals</p>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="user_dashboard.php" class="btn btn-light">
                                <i class="fas fa-arrow-left me-1"></i>Back
                            </a>
                        </div>
                    </div>
                </div>

                <div class="assistance-card">
                    <?php if ($success_msg): ?>
                        <div class="alert alert-success alert-auto-dismiss" id="successAlert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                            <br><small>Your application is now under review. We'll contact you within 3-5 business days.</small>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="educationForm">
                        <!-- Support Type Selection -->
                        <div class="form-section">
                            <h4 class="mb-3"><i class="fas fa-book-open me-2"></i>Type of Support Needed</h4>
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="support-option">
                                        <input type="radio" name="support_type" value="financial" required 
                                               <?php echo (isset($_POST['support_type']) && $_POST['support_type'] == 'financial') ? 'checked' : ''; ?>>
                                        <div class="text-center">
                                            <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                                            <h6>Financial Assistance</h6>
                                            <small class="text-muted">Tuition fees, books, supplies</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="support-option">
                                        <input type="radio" name="support_type" value="materials"
                                               <?php echo (isset($_POST['support_type']) && $_POST['support_type'] == 'materials') ? 'checked' : ''; ?>>
                                        <div class="text-center">
                                            <i class="fas fa-book fa-2x text-primary mb-2"></i>
                                            <h6>Learning Materials</h6>
                                            <small class="text-muted">Books, equipment, resources</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="support-option">
                                        <input type="radio" name="support_type" value="tutoring"
                                               <?php echo (isset($_POST['support_type']) && $_POST['support_type'] == 'tutoring') ? 'checked' : ''; ?>>
                                        <div class="text-center">
                                            <i class="fas fa-chalkboard-teacher fa-2x text-warning mb-2"></i>
                                            <h6>Tutoring Support</h6>
                                            <small class="text-muted">One-on-one academic help</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="support-option">
                                        <input type="radio" name="support_type" value="accommodation"
                                               <?php echo (isset($_POST['support_type']) && $_POST['support_type'] == 'accommodation') ? 'checked' : ''; ?>>
                                        <div class="text-center">
                                            <i class="fas fa-universal-access fa-2x text-info mb-2"></i>
                                            <h6>Special Accommodations</h6>
                                            <small class="text-muted">Classroom adjustments</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="support-option">
                                        <input type="radio" name="support_type" value="technology"
                                               <?php echo (isset($_POST['support_type']) && $_POST['support_type'] == 'technology') ? 'checked' : ''; ?>>
                                        <div class="text-center">
                                            <i class="fas fa-laptop fa-2x text-danger mb-2"></i>
                                            <h6>Technology Support</h6>
                                            <small class="text-muted">Computers, software, devices</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="support-option">
                                        <input type="radio" name="support_type" value="other"
                                               <?php echo (isset($_POST['support_type']) && $_POST['support_type'] == 'other') ? 'checked' : ''; ?>>
                                        <div class="text-center">
                                            <i class="fas fa-question-circle fa-2x text-secondary mb-2"></i>
                                            <h6>Other Support</h6>
                                            <small class="text-muted">Other educational needs</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Other Support Details Field -->
                            <div id="otherSupportField" class="conditional-field">
                                <div class="mb-3">
                                    <label class="form-label">Please specify the type of support you need *</label>
                                    <textarea name="other_support_details" class="form-control" rows="3" 
                                              placeholder="Describe in detail the type of support you require"><?php echo isset($_POST['other_support_details']) ? htmlspecialchars($_POST['other_support_details']) : ''; ?></textarea>
                                    <small class="text-muted">Please provide specific details about the support you need that isn't covered by the options above.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Educational Information -->
                        <div class="form-section" id="educationInfoSection">
                            <h4 class="mb-3"><i class="fas fa-user-graduate me-2"></i>Educational Information</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Education Level *</label>
                                    <select name="education_level" id="educationLevel" class="form-select" required>
                                        <option value="">Select Education Level</option>
                                        <option value="primary" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == 'primary') ? 'selected' : ''; ?>>Primary School</option>
                                        <option value="secondary" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == 'secondary') ? 'selected' : ''; ?>>Secondary School</option>
                                        <option value="vocational" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == 'vocational') ? 'selected' : ''; ?>>Vocational Training</option>
                                        <option value="diploma" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == 'diploma') ? 'selected' : ''; ?>>Diploma/Certificate</option>
                                        <option value="undergraduate" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == 'undergraduate') ? 'selected' : ''; ?>>Undergraduate Degree</option>
                                        <option value="postgraduate" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == 'postgraduate') ? 'selected' : ''; ?>>Postgraduate Degree</option>
                                        <option value="other" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <!-- Primary Education Fields -->
                                <div id="primaryFields" class="conditional-field">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Name *</label>
                                        <input type="text" name="primary_school" class="form-control" 
                                               placeholder="Enter primary school name"
                                               value="<?php echo isset($_POST['primary_school']) ? htmlspecialchars($_POST['primary_school']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Years Attended</label>
                                        <input type="text" name="primary_years" class="form-control" 
                                               placeholder="e.g., 2005-2011"
                                               value="<?php echo isset($_POST['primary_years']) ? htmlspecialchars($_POST['primary_years']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <!-- Secondary Education Fields -->
                                <div id="secondaryFields" class="conditional-field">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Name *</label>
                                        <input type="text" name="secondary_school" class="form-control" 
                                               placeholder="Enter secondary school name"
                                               value="<?php echo isset($_POST['secondary_school']) ? htmlspecialchars($_POST['secondary_school']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Type</label>
                                        <select name="secondary_type" class="form-select">
                                            <option value="">-- Select School Type --</option>
                                            <option value="public" <?php echo (isset($_POST['secondary_type']) && $_POST['secondary_type'] == 'public') ? 'selected' : ''; ?>>Public School</option>
                                            <option value="private" <?php echo (isset($_POST['secondary_type']) && $_POST['secondary_type'] == 'private') ? 'selected' : ''; ?>>Private School</option>
                                            <option value="international" <?php echo (isset($_POST['secondary_type']) && $_POST['secondary_type'] == 'international') ? 'selected' : ''; ?>>International School</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Years Attended</label>
                                        <input type="text" name="secondary_years" class="form-control" 
                                               placeholder="e.g., 2011-2017"
                                               value="<?php echo isset($_POST['secondary_years']) ? htmlspecialchars($_POST['secondary_years']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <!-- Higher Education Fields -->
                                <div id="higherEdFields" class="conditional-field">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Institution Name *</label>
                                        <input type="text" name="institution_name" class="form-control" 
                                               placeholder="e.g., University of Dar es Salaam"
                                               value="<?php echo isset($_POST['institution_name']) ? htmlspecialchars($_POST['institution_name']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Course/Program *</label>
                                        <input type="text" name="course_program" class="form-control" 
                                               placeholder="e.g., Bachelor of Education"
                                               value="<?php echo isset($_POST['course_program']) ? htmlspecialchars($_POST['course_program']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Academic Year *</label>
                                        <input type="text" name="academic_year" class="form-control" 
                                               placeholder="e.g., 2024/2025"
                                               value="<?php echo isset($_POST['academic_year']) ? htmlspecialchars($_POST['academic_year']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <!-- Other Education Fields -->
                                <div id="otherFields" class="conditional-field">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Please specify your education *</label>
                                        <input type="text" name="other_education" class="form-control" 
                                               placeholder="Describe your education"
                                               value="<?php echo isset($_POST['other_education']) ? htmlspecialchars($_POST['other_education']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Support Details -->
                        <div class="form-section" id="supportDetailsSection">
                            <h4 class="mb-3"><i class="fas fa-hands-helping me-2"></i>Support Details</h4>
                            <div class="checkbox-group">
                                <label class="form-label fw-bold mb-3">What type of support do you need? (Select all that apply)</label>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="financial_assistance" value="yes"
                                                   <?php echo (isset($_POST['financial_assistance']) && $_POST['financial_assistance'] == 'yes') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                Financial Assistance (Tuition fees, books, supplies)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="learning_materials" value="yes"
                                                   <?php echo (isset($_POST['learning_materials']) && $_POST['learning_materials'] == 'yes') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                Learning Materials (Books, equipment, resources)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tutoring_support" value="yes"
                                                   <?php echo (isset($_POST['tutoring_support']) && $_POST['tutoring_support'] == 'yes') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                Tutoring/Academic Support
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="special_accommodations" value="yes"
                                                   <?php echo (isset($_POST['special_accommodations']) && $_POST['special_accommodations'] == 'yes') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                Special Accommodations
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3 mt-3">
                                <label class="form-label">Specific Support Requirements *</label>
                                <textarea name="additional_notes" class="form-control" rows="3" 
                                          placeholder="Please describe in detail what support you need and how it will help your education"><?php echo isset($_POST['additional_notes']) ? htmlspecialchars($_POST['additional_notes']) : ''; ?></textarea>
                            </div>
                        </div>

                        <!-- Documents Upload -->
                        <div class="form-section" id="documentsSection">
                            <h4 class="mb-3"><i class="fas fa-file-alt me-2"></i>Supporting Documents</h4>
                            <div class="mb-3">
                                <label class="form-label">Academic Documents (if available)</label>
                                <input type="file" name="academic_documents" class="form-control" 
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="text-muted">Upload admission letters, fee structures, or other relevant documents (PDF, Word, or images)</small>
                            </div>
                        </div>

                        <!-- Terms and Submit -->
                        <div class="form-section">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" required id="termsCheckbox">
                                <label class="form-check-label">
                                    I declare that the information provided is true and accurate to the best of my knowledge.
                                    I understand that providing false information may result in application rejection.
                                </label>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                                <a href="user_dashboard.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Additional Information -->
                <div class="assistance-card">
                    <h4 class="mb-3"><i class="fas fa-info-circle me-2"></i>About Education Support</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Eligibility Criteria</h6>
                            <ul class="text-muted">
                                <li>Registered person with disability</li>
                                <li>Enrolled or accepted in an educational institution</li>
                                <li>Demonstrated financial need</li>
                                <li>Good academic standing (if currently enrolled)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>What We Offer</h6>
                            <ul class="text-muted">
                                <li>Tuition fee assistance</li>
                                <li>Learning materials and equipment</li>
                                <li>Tutoring and academic support</li>
                                <li>Special accommodations in classrooms</li>
                                <li>Technology and assistive devices</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to auto-dismiss success alert after 5 seconds
        function autoDismissAlert() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(() => {
                    // Fade out the alert
                    successAlert.style.transition = 'opacity 0.5s ease';
                    successAlert.style.opacity = '0';
                    
                    // Remove the alert from DOM after fade out completes
                    setTimeout(() => {
                        successAlert.remove();
                    }, 500);
                }, 5000); // 5000ms = 5 seconds
            }
        }

        // Function to handle support type selection
        function setupSupportSelection() {
            const supportOptions = document.querySelectorAll('.support-option');
            const otherSupportField = document.getElementById('otherSupportField');
            const educationInfoSection = document.getElementById('educationInfoSection');
            const supportDetailsSection = document.getElementById('supportDetailsSection');
            const documentsSection = document.getElementById('documentsSection');
            
            supportOptions.forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                
                // Set initial selected state
                if (radio.checked) {
                    option.classList.add('selected');
                    handleSupportTypeChange(radio.value);
                }
                
                option.addEventListener('click', function() {
                    document.querySelectorAll('.support-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    radio.checked = true;
                    
                    handleSupportTypeChange(radio.value);
                });
            });
            
            function handleSupportTypeChange(supportType) {
                if (supportType === 'other') {
                    // Show other support details field
                    otherSupportField.style.display = 'block';
                    otherSupportField.classList.add('fade-in');
                    
                    // Hide other sections
                    educationInfoSection.style.display = 'none';
                    supportDetailsSection.style.display = 'none';
                    documentsSection.style.display = 'none';
                    
                    // Remove required attribute from hidden fields
                    document.querySelectorAll('#educationInfoSection input, #educationInfoSection select').forEach(field => {
                        field.removeAttribute('required');
                    });
                    document.querySelector('#supportDetailsSection textarea').removeAttribute('required');
                } else {
                    // Hide other support details field
                    otherSupportField.style.display = 'none';
                    
                    // Show other sections
                    educationInfoSection.style.display = 'block';
                    supportDetailsSection.style.display = 'block';
                    documentsSection.style.display = 'block';
                    
                    // Add required attribute back
                    document.querySelector('#educationLevel').setAttribute('required', 'required');
                    document.querySelector('#supportDetailsSection textarea').setAttribute('required', 'required');
                }
            }
        }

        // Dynamic education level fields
        function setupEducationLevelFields() {
            const educationLevel = document.getElementById('educationLevel');
            const primaryFields = document.getElementById('primaryFields');
            const secondaryFields = document.getElementById('secondaryFields');
            const higherEdFields = document.getElementById('higherEdFields');
            const otherFields = document.getElementById('otherFields');
            
            function hideAllFields() {
                primaryFields.style.display = 'none';
                secondaryFields.style.display = 'none';
                higherEdFields.style.display = 'none';
                otherFields.style.display = 'none';
                primaryFields.classList.remove('fade-in');
                secondaryFields.classList.remove('fade-in');
                higherEdFields.classList.remove('fade-in');
                otherFields.classList.remove('fade-in');
                
                // Remove required attributes from all conditional fields
                document.querySelectorAll('#primaryFields input, #secondaryFields input, #secondaryFields select, #higherEdFields input, #otherFields input').forEach(field => {
                    field.removeAttribute('required');
                });
            }
            
            // Hide all conditional fields initially
            hideAllFields();
            
            // Show appropriate fields based on initial selection
            if (educationLevel.value) {
                showEducationFields(educationLevel.value);
            }
            
            // Add event listener to the education level dropdown
            educationLevel.addEventListener('change', function() {
                hideAllFields();
                showEducationFields(this.value);
            });
            
            function showEducationFields(level) {
                switch(level) {
                    case 'primary':
                        primaryFields.style.display = 'block';
                        primaryFields.classList.add('fade-in');
                        document.querySelector('#primaryFields input[name="primary_school"]').setAttribute('required', 'required');
                        break;
                    case 'secondary':
                        secondaryFields.style.display = 'block';
                        secondaryFields.classList.add('fade-in');
                        document.querySelector('#secondaryFields input[name="secondary_school"]').setAttribute('required', 'required');
                        break;
                    case 'vocational':
                    case 'diploma':
                    case 'undergraduate':
                    case 'postgraduate':
                        higherEdFields.style.display = 'block';
                        higherEdFields.classList.add('fade-in');
                        document.querySelectorAll('#higherEdFields input').forEach(field => {
                            field.setAttribute('required', 'required');
                        });
                        break;
                    case 'other':
                        otherFields.style.display = 'block';
                        otherFields.classList.add('fade-in');
                        document.querySelector('#otherFields input[name="other_education"]').setAttribute('required', 'required');
                        break;
                    default:
                        // No fields shown for default selection
                        hideAllFields();
                }
            }
        }

        // Form validation
        function setupFormValidation() {
            document.getElementById('educationForm').addEventListener('submit', function(e) {
                const termsCheckbox = document.getElementById('termsCheckbox');
                let isValid = true;
                
                // Clear previous error highlights
                document.querySelectorAll('.error-highlight').forEach(el => {
                    el.classList.remove('error-highlight');
                });
                
                // Check support type
                const selectedSupport = document.querySelector('input[name="support_type"]:checked');
                if (!selectedSupport) {
                    e.preventDefault();
                    alert('Please select a support type.');
                    isValid = false;
                }
                
                // Check education level
                const educationLevel = document.getElementById('educationLevel');
                if (!educationLevel.value) {
                    educationLevel.classList.add('error-highlight');
                    isValid = false;
                }
                
                // Check terms agreement
                if (!termsCheckbox.checked) {
                    e.preventDefault();
                    alert('Please agree to the terms and conditions.');
                    isValid = false;
                }
                
                // Validate required fields based on support type
                if (selectedSupport && selectedSupport.value === 'other') {
                    const otherSupportDetails = document.querySelector('textarea[name="other_support_details"]');
                    if (!otherSupportDetails.value.trim()) {
                        otherSupportDetails.classList.add('error-highlight');
                        isValid = false;
                    }
                } else {
                    const additionalNotes = document.querySelector('textarea[name="additional_notes"]');
                    if (!additionalNotes.value.trim()) {
                        additionalNotes.classList.add('error-highlight');
                        isValid = false;
                    }
                }
                
                // Validate education level specific fields
                const educationLevelValue = educationLevel.value;
                if (educationLevelValue === 'primary') {
                    const primarySchool = document.querySelector('input[name="primary_school"]');
                    if (!primarySchool.value.trim()) {
                        primarySchool.classList.add('error-highlight');
                        isValid = false;
                    }
                } else if (educationLevelValue === 'secondary') {
                    const secondarySchool = document.querySelector('input[name="secondary_school"]');
                    if (!secondarySchool.value.trim()) {
                        secondarySchool.classList.add('error-highlight');
                        isValid = false;
                    }
                } else if (['vocational', 'diploma', 'undergraduate', 'postgraduate'].includes(educationLevelValue)) {
                    const institutionName = document.querySelector('input[name="institution_name"]');
                    const courseProgram = document.querySelector('input[name="course_program"]');
                    const academicYear = document.querySelector('input[name="academic_year"]');
                    
                    if (!institutionName.value.trim()) {
                        institutionName.classList.add('error-highlight');
                        isValid = false;
                    }
                    if (!courseProgram.value.trim()) {
                        courseProgram.classList.add('error-highlight');
                        isValid = false;
                    }
                    if (!academicYear.value.trim()) {
                        academicYear.classList.add('error-highlight');
                        isValid = false;
                    }
                } else if (educationLevelValue === 'other') {
                    const otherEducation = document.querySelector('input[name="other_education"]');
                    if (!otherEducation.value.trim()) {
                        otherEducation.classList.add('error-highlight');
                        isValid = false;
                    }
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly.');
                }
                
                return isValid;
            });
        }
        
        // Call functions when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            autoDismissAlert();
            setupSupportSelection();
            setupEducationLevelFields();
            setupFormValidation();
            
            <?php if ($success_msg): ?>
                // If we have a success message, scroll to top to show it
                window.scrollTo(0, 0);
            <?php endif; ?>
        });
    </script>
</body>
</html>