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

// Fetch user data with prepared statement
$sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Calculate profile completion
$total_fields = 10;
$filled_fields = 0;
$fields = ['full_name', 'phone', 'date_of_birth', 'gender', 'region', 'district', 'disability_type', 'disability_severity', 'communication_preference', 'pin'];
foreach ($fields as $field) {
    if (!empty($user[$field])) $filled_fields++;
}
$profile_percent = intval(($filled_fields / $total_fields) * 100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - PWD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .profile-header {
            border-bottom: 1px solid #e3e6f0;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .info-group {
            margin-bottom: 1.5rem;
        }
        .info-label {
            font-weight: 600;
            color: #4e73df;
            margin-bottom: 0.25rem;
        }
        .info-value {
            color: #858796;
        }
        .profile-completion {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            width: 100%;
        }
        .progress-bar {
            height: 100%;
            background: #1cc88a;
            border-radius: 4px;
        }

        body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.container {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center; /* Centers vertically */
}

.row {
    justify-content: center; /* Centers horizontally */
}

.col-lg-9 {
    display: flex;
    flex-direction: column;
}

.profile-card {
    margin: 0 auto; /* Centers the card horizontally */
    max-width: 900px; /* Optional: limits maximum width */
    width: 100%;
}
    </style>
</head>
<body>

    <div class="container mt-4" >
        <div class="row">
            
            
            <div class="col-lg-9">
                <div class="profile-card">
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=4e73df&color=fff&size=100" 
                                     alt="Profile" class="rounded-circle" style="width: 100px; height: 100px;">
                            </div>
                            <div class="col-md-5">
                                <h2><?php echo htmlspecialchars($full_name); ?></h2>
                                <p class="text-muted mb-1">Registered Member</p>
                                <span class="badge bg-success">Verified</span>
                            </div>
                            <div class="col-md-5 text-end">
                                <a href="user_dashboard.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                                </a>
                                <a href="user_update_profile.php" class="btn btn-primary">
                                    <i class="fas fa-edit me-1"></i>Edit Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Completion -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5>Profile Completion</h5>
                            <div class="profile-completion mb-2">
                                <div class="progress-bar" style="width: <?php echo $profile_percent; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $profile_percent; ?>% complete</small>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Personal Information</h5>
                            
                            <div class="info-group">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?php echo !empty($user['full_name']) ? htmlspecialchars($user['full_name']) : '<span class="text-danger">Not provided</span>'; ?></div>
                            </div>

                            <div class="info-group">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span class="text-danger">Not provided</span>'; ?></div>
                            </div>

                            <div class="info-group">
                                <div class="info-label">Date of Birth</div>
                                <div class="info-value"><?php echo !empty($user['date_of_birth']) ? htmlspecialchars($user['date_of_birth']) : '<span class="text-danger">Not provided</span>'; ?></div>
                            </div>

                            <div class="info-group">
                                <div class="info-label">Gender</div>
                                <div class="info-value"><?php echo !empty($user['gender']) ? htmlspecialchars($user['gender']) : '<span class="text-danger">Not provided</span>'; ?></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="mb-3">Location & Disability Information</h5>
                            
                            <div class="info-group">
                                <div class="info-label">Region</div>
                                <div class="info-value"><?php echo !empty($user['region']) ? htmlspecialchars($user['region']) : '<span class="text-danger">Not provided</span>'; ?></div>
                            </div>

                            <div class="info-group">
                                <div class="info-label">District</div>
                                <div class="info-value"><?php echo !empty($user['district']) ? htmlspecialchars($user['district']) : '<span class="text-danger">Not provided</span>'; ?></div>
                            </div>

                            <div class="info-group">
                                <div class="info-label">Disability Type</div>
                                <div class="info-value"><?php echo !empty($user['disability_type']) ? htmlspecialchars($user['disability_type']) : '<span class="text-danger">Not provided</span>'; ?></div>
                            </div>

                            <div class="info-group">
                                <div class="info-label">Disability Severity</div>
                                <div class="info-value"><?php echo !empty($user['disability_severity']) ? htmlspecialchars($user['disability_severity']) : '<span class="text-danger">Not provided</span>'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5 class="mb-3">Account Information</h5>
                            <div class="info-group">
                                <div class="info-label">Communication Preference</div>
                                <div class="info-value"><?php echo !empty($user['communication_preference']) ? htmlspecialchars($user['communication_preference']) : '<span class="text-danger">Not set</span>'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>