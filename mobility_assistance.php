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

// Create mobility_assistance_applications table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS mobility_assistance_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_type TEXT NOT NULL,
    required_device VARCHAR(255) NOT NULL,
    urgency_level ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
    medical_condition TEXT NOT NULL,
    previous_assistance ENUM('yes', 'no') DEFAULT 'no',
    medical_report_path VARCHAR(500),
    additional_notes TEXT,
    status ENUM('pending', 'approved', 'rejected', 'in_review') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
)";

if (!$conn->query($createTableSQL)) {
    error_log("Error creating mobility_assistance_applications table: " . $conn->error);
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
    
    // Get device types as array - ensure it's always an array
    $device_types = isset($_POST['device_type']) ? $_POST['device_type'] : [];
    
    // If only one device is selected, it comes as a string, so convert to array
    if (!is_array($device_types)) {
        $device_types = [$device_types];
    }
    
    $device_types_str = !empty($device_types) ? implode(', ', $device_types) : '';
    
    $required_device = trim($_POST['required_device'] ?? '');
    $urgency_level = $_POST['urgency_level'] ?? 'medium';
    $medical_condition = trim($_POST['medical_condition'] ?? '');
    $previous_assistance = $_POST['previous_assistance'] ?? 'no';
    $additional_notes = trim($_POST['additional_notes'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($device_types)) {
        $errors[] = 'Please select at least one device type';
    }
    
    if (empty($required_device)) {
        $errors[] = 'Specific device(s) required field is mandatory';
    }
    
    if (empty($medical_condition)) {
        $errors[] = 'Medical condition description is required';
    }
    
    if (empty($errors)) {
        // File upload handling
        $medical_report_path = '';
        if (isset($_FILES['medical_report']) && $_FILES['medical_report']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/medical_reports/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Validate file type
            $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($_FILES['medical_report']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                $file_name = time() . '_' . uniqid() . '_' . basename($_FILES['medical_report']['name']);
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['medical_report']['tmp_name'], $target_path)) {
                    $medical_report_path = $target_path;
                } else {
                    $errors[] = 'Failed to upload medical report file';
                }
            } else {
                $errors[] = 'Invalid file type. Allowed types: PDF, DOC, DOCX, JPG, JPEG, PNG';
            }
        }
        
        if (empty($errors)) {
            // Insert application into database
            $insert_sql = "INSERT INTO mobility_assistance_applications 
                          (user_id, device_type, required_device, urgency_level, medical_condition, 
                           previous_assistance, medical_report_path, additional_notes, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($insert_sql);
            if ($stmt) {
                $stmt->bind_param("isssssss", $user_id, $device_types_str, $required_device, $urgency_level, 
                                 $medical_condition, $previous_assistance, $medical_report_path, $additional_notes);
                
                if ($stmt->execute()) {
                    // Mark form as submitted to prevent duplicates
                    $_SESSION['form_submitted'] = true;
                    
                    // Store success message in session for display after redirect
                    $_SESSION['success_message'] = "Your mobility assistance application has been submitted successfully!";
                    
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
    <title>Mobility Assistance - PWD System</title>
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
            background: linear-gradient(120deg, #4e73df, #224abe);
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
        .device-option {
            border: 2px solid #e3e6f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .device-option:hover, .device-option.selected {
            border-color: #4e73df;
            background-color: #f8f9fc;
        }
        .device-option input[type="checkbox"] {
            position: absolute;
            top: 10px;
            right: 10px;
            transform: scale(1.3);
        }
        
        body {
            background: #f8f9fc;
            min-height: 100vh;
        }
        
        .container {
            padding: 2rem 0;
        }
        
        .alert-auto-dismiss {
            transition: opacity 0.5s ease-out;
        }
        
        .selected-count {
            background-color: #4e73df;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .selection-summary {
            background-color: #e9ecef;
            border-radius: 0.25rem;
            padding: 0.75rem;
            margin-top: 1rem;
        }
        
        .error-highlight {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
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
                            <i class="fas fa-wheelchair fa-4x"></i>
                        </div>
                        <div class="col-md-8">
                            <h1 class="mb-2">Mobility Assistance</h1>
                            <p class="mb-0">Apply for mobility devices and support services to enhance your independence and quality of life</p>
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

                    <form method="POST" enctype="multipart/form-data" id="mobilityForm">
                        <!-- Device Type Selection -->
                        <div class="form-section">
                            <h4 class="mb-3">
                                <i class="fas fa-wheelchair me-2"></i>Select Device Types
                                <span id="selectedCount" class="selected-count">0</span>
                            </h4>
                            <p class="text-muted">You can select multiple devices that you need assistance with</p>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="device-option">
                                        <input type="checkbox" name="device_type[]" value="wheelchair">
                                        <div class="text-center">
                                            <i class="fas fa-wheelchair fa-2x text-primary mb-2"></i>
                                            <h6>Wheelchair</h6>
                                            <small class="text-muted">Manual or electric wheelchairs</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="device-option">
                                        <input type="checkbox" name="device_type[]" value="walker">
                                        <div class="text-center">
                                            <i class="fas fa-walking fa-2x text-success mb-2"></i>
                                            <h6>Walker/Rollator</h6>
                                            <small class="text-muted">Walking frames and rollators</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="device-option">
                                        <input type="checkbox" name="device_type[]" value="crutches">
                                        <div class="text-center">
                                            <i class="fas fa-crutch fa-2x text-warning mb-2"></i>
                                            <h6>Crutches</h6>
                                            <small class="text-muted">Underarm and forearm crutches</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="device-option">
                                        <input type="checkbox" name="device_type[]" value="prosthetics">
                                        <div class="text-center">
                                            <i class="fas fa-band-aid fa-2x text-info mb-2"></i>
                                            <h6>Prosthetics</h6>
                                            <small class="text-muted">Artificial limbs and devices</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="device-option">
                                        <input type="checkbox" name="device_type[]" value="orthotics">
                                        <div class="text-center">
                                            <i class="fas fa-plus-circle fa-2x text-danger mb-2"></i>
                                            <h6>Orthotics</h6>
                                            <small class="text-muted">Braces and support devices</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="device-option">
                                        <input type="checkbox" name="device_type[]" value="other">
                                        <div class="text-center">
                                            <i class="fas fa-question-circle fa-2x text-secondary mb-2"></i>
                                            <h6>Other Device</h6>
                                            <small class="text-muted">Other mobility equipment</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div id="selectionSummary" class="selection-summary" style="display: none;">
                                <h6>Selected Devices:</h6>
                                <div id="selectedDevicesList"></div>
                            </div>
                        </div>

                        <!-- Device Details -->
                        <div class="form-section">
                            <h4 class="mb-3"><i class="fas fa-info-circle me-2"></i>Device Details</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Specific Device(s) Required *</label>
                                    <input type="text" name="required_device" class="form-control" required 
                                           placeholder="e.g., Electric wheelchair, Underarm crutches, etc."
                                           value="<?php echo isset($_POST['required_device']) ? htmlspecialchars($_POST['required_device']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Urgency Level *</label>
                                    <select name="urgency_level" class="form-select" required>
                                        <option value="low" <?php echo (isset($_POST['urgency_level']) && $_POST['urgency_level'] == 'low') ? 'selected' : ''; ?>>Low - Can wait several weeks</option>
                                        <option value="medium" <?php echo (isset($_POST['urgency_level']) && $_POST['urgency_level'] == 'medium' || !isset($_POST['urgency_level'])) ? 'selected' : ''; ?>>Medium - Needed within 2 weeks</option>
                                        <option value="high" <?php echo (isset($_POST['urgency_level']) && $_POST['urgency_level'] == 'high') ? 'selected' : ''; ?>>High - Needed immediately</option>
                                        <option value="emergency" <?php echo (isset($_POST['urgency_level']) && $_POST['urgency_level'] == 'emergency') ? 'selected' : ''; ?>>Emergency - Critical need</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Medical Information -->
                        <div class="form-section">
                            <h4 class="mb-3"><i class="fas fa-heartbeat me-2"></i>Medical Information</h4>
                            <div class="mb-3">
                                <label class="form-label">Medical Condition Requiring Device(s) *</label>
                                <textarea name="medical_condition" class="form-control" rows="3" required 
                                          placeholder="Describe your medical condition and how these devices will help you"><?php echo isset($_POST['medical_condition']) ? htmlspecialchars($_POST['medical_condition']) : ''; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Medical Report (if available)</label>
                                <input type="file" name="medical_report" class="form-control" 
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="text-muted">Upload medical documents supporting your application (PDF, Word, or images)</small>
                            </div>
                        </div>

                        <!-- Previous Assistance -->
                        <div class="form-section">
                            <h4 class="mb-3"><i class="fas fa-history me-2"></i>Previous Assistance</h4>
                            <div class="mb-3">
                                <label class="form-label">Have you received mobility assistance before? *</label>
                                <select name="previous_assistance" class="form-select" required>
                                    <option value="no" <?php echo (isset($_POST['previous_assistance']) && $_POST['previous_assistance'] == 'no' || !isset($_POST['previous_assistance'])) ? 'selected' : ''; ?>>No, this is my first time</option>
                                    <option value="yes" <?php echo (isset($_POST['previous_assistance']) && $_POST['previous_assistance'] == 'yes') ? 'selected' : ''; ?>>Yes, I have received assistance before</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Additional Notes</label>
                                <textarea name="additional_notes" class="form-control" rows="3" 
                                          placeholder="Any additional information you'd like to share"><?php echo isset($_POST['additional_notes']) ? htmlspecialchars($_POST['additional_notes']) : ''; ?></textarea>
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
                                <button type="submit" class="btn btn-primary btn-lg">
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
                    <h4 class="mb-3"><i class="fas fa-info-circle me-2"></i>About Mobility Assistance</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Eligibility Criteria</h6>
                            <ul class="text-muted">
                                <li>Registered person with disability</li>
                                <li>Medical certification of mobility impairment</li>
                                <li>Demonstrated need for mobility device</li>
                                <li>Financial need assessment</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>What to Expect</h6>
                            <ul class="text-muted">
                                <li>Application review within 3-5 business days</li>
                                <li>Possible assessment appointment</li>
                                <li>Device fitting and training if approved</li>
                                <li>Follow-up support services</li>
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
        
        // Function to handle device selection
        function setupDeviceSelection() {
            const deviceOptions = document.querySelectorAll('.device-option');
            const selectedCount = document.getElementById('selectedCount');
            const selectionSummary = document.getElementById('selectionSummary');
            const selectedDevicesList = document.getElementById('selectedDevicesList');
            
            deviceOptions.forEach(option => {
                const checkbox = option.querySelector('input[type="checkbox"]');
                
                // Toggle selected class when checkbox changes
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        option.classList.add('selected');
                    } else {
                        option.classList.remove('selected');
                    }
                    updateSelectionSummary();
                });
                
                // Allow clicking anywhere on the card to toggle selection
                option.addEventListener('click', function(e) {
                    if (e.target !== checkbox) {
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });
            });
            
            // Update selection summary
            function updateSelectionSummary() {
                const checkedBoxes = document.querySelectorAll('input[name="device_type[]"]:checked');
                const count = checkedBoxes.length;
                
                // Update count badge
                selectedCount.textContent = count;
                
                // Show/hide summary based on selection
                if (count > 0) {
                    selectionSummary.style.display = 'block';
                    
                    // Update selected devices list
                    let devicesHtml = '<ul class="mb-0">';
                    checkedBoxes.forEach(box => {
                        const deviceName = box.closest('.device-option').querySelector('h6').textContent;
                        devicesHtml += `<li>${deviceName}</li>`;
                    });
                    devicesHtml += '</ul>';
                    selectedDevicesList.innerHTML = devicesHtml;
                } else {
                    selectionSummary.style.display = 'none';
                }
            }
            
            // Form validation
            document.getElementById('mobilityForm').addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('input[name="device_type[]"]:checked');
                const termsCheckbox = document.getElementById('termsCheckbox');
                let isValid = true;
                
                // Clear previous error highlights
                document.querySelectorAll('.error-highlight').forEach(el => {
                    el.classList.remove('error-highlight');
                });
                
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one device type.');
                    isValid = false;
                }
                
                if (!termsCheckbox.checked) {
                    e.preventDefault();
                    alert('Please agree to the terms and conditions.');
                    isValid = false;
                }
                
                // Validate required fields
                const requiredFields = this.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('error-highlight');
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly.');
                }
                
                return isValid;
            });
            
            // Initialize selection summary
            updateSelectionSummary();
        }
        
        // Call functions when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            autoDismissAlert();
            setupDeviceSelection();
        });
    </script>
</body>
</html>