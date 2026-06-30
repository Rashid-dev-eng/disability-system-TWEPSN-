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

// PROCESS FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data - only validate required fields, others are optional
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $region = $_POST['region'] ?? '';
    $district = $_POST['district'] ?? '';
    $ward = $_POST['ward'] ?? '';
    $village = $_POST['village'] ?? '';
    $street = $_POST['street'] ?? '';
    $disability_type = $_POST['disability_type'] ?? '';
    $disability_severity = $_POST['disability_severity'] ?? '';
    $communication_preference = $_POST['communication_preference'] ?? '';
    
    // Basic validation - only validate required fields
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^0[67]\d{8}$/', $phone)) {
        $errors[] = 'Please enter a valid Tanzanian phone number (e.g., 0755123456)';
    }
    
    if (empty($disability_type)) {
        $errors[] = 'Disability type is required';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($errors)) {
        // Check what columns exist in your users table
        $result = $conn->query("DESCRIBE users");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        // Build UPDATE query dynamically - only include fields that have values
        $update_fields = [];
        $params = [];
        $types = '';
        
        // Always include required fields
        $update_fields[] = "full_name = ?";
        $params[] = $full_name;
        $types .= "s";
        
        $update_fields[] = "phone = ?";
        $params[] = $phone;
        $types .= "s";
        
        $update_fields[] = "disability_type = ?";
        $params[] = $disability_type;
        $types .= "s";
        
        // Add optional fields only if they exist in the table AND have values
        if (in_array('email', $columns) && !empty($email)) {
            $update_fields[] = "email = ?";
            $params[] = $email;
            $types .= "s";
        }
        
        if (in_array('date_of_birth', $columns) && !empty($date_of_birth)) {
            $update_fields[] = "date_of_birth = ?";
            $params[] = $date_of_birth;
            $types .= "s";
        }
        
        if (in_array('gender', $columns) && !empty($gender)) {
            $update_fields[] = "gender = ?";
            $params[] = $gender;
            $types .= "s";
        }
        
        if (in_array('region', $columns) && !empty($region)) {
            $update_fields[] = "region = ?";
            $params[] = $region;
            $types .= "s";
        }
        
        if (in_array('district', $columns) && !empty($district)) {
            $update_fields[] = "district = ?";
            $params[] = $district;
            $types .= "s";
        }
        
        if (in_array('ward', $columns) && !empty($ward)) {
            $update_fields[] = "ward = ?";
            $params[] = $ward;
            $types .= "s";
        }
        
        if (in_array('village', $columns) && !empty($village)) {
            $update_fields[] = "village = ?";
            $params[] = $village;
            $types .= "s";
        }
        
        if (in_array('street', $columns) && !empty($street)) {
            $update_fields[] = "street = ?";
            $params[] = $street;
            $types .= "s";
        }
        
        if (in_array('disability_severity', $columns) && !empty($disability_severity)) {
            $update_fields[] = "disability_severity = ?";
            $params[] = $disability_severity;
            $types .= "s";
        }
        
        if (in_array('communication_preference', $columns) && !empty($communication_preference)) {
            $update_fields[] = "communication_preference = ?";
            $params[] = $communication_preference;
            $types .= "s";
        }
        
        // Add updated_at if it exists
        if (in_array('updated_at', $columns)) {
            $update_fields[] = "updated_at = NOW()";
        }
        
        // Only proceed with update if we have fields to update (besides required ones)
        if (count($update_fields) > 0) {
            // Add user_id for WHERE clause
            $types .= "i";
            $params[] = $user_id;
            
            // Build the final SQL query
            $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $success_msg = 'Profile updated successfully!';
                    
                    // Store success message in session to show after redirect
                    $_SESSION['success_message'] = $success_msg;
                    
                    // Redirect to avoid form resubmission
                    header("Location: user_update_profile.php");
                    exit;
                } else {
                    $error_msg = 'Failed to update profile: ' . $stmt->error;
                }
                
                $stmt->close();
            } else {
                $error_msg = 'Database error: ' . $conn->error;
            }
        } else {
            $error_msg = 'No changes detected to update.';
        }
    } else {
        $error_msg = implode('<br>', $errors);
    }
}

// Check for success message from session (after redirect)
if (isset($_SESSION['success_message'])) {
    $success_msg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Fetch current user data
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

// Calculate profile completion
$total_fields = 10;
$filled_fields = 0;
$fields = ['full_name', 'phone', 'date_of_birth', 'gender', 'region', 'district', 'disability_type', 'disability_severity', 'communication_preference', 'email'];

if ($user) {
    foreach ($fields as $field) {
        if (!empty($user[$field])) $filled_fields++;
    }
}
$profile_percent = intval(($filled_fields / $total_fields) * 100);

// Format date for HTML input
function formatDateForInput($date) {
    if (empty($date) || $date == '0000-00-00') return '';
    return date('Y-m-d', strtotime($date));
}

// Tanzanian Location Data
$tanzania_locations = [
    'Arusha' => [
        'districts' => [
            'Arusha City' => ['wards' => ['Daraja Mbili', 'Elerai', 'Engutoto', 'Kaloleni', 'Kawekamo', 'Kimandolu', 'Kisongo', 'Lemara', 'Levolosi', 'Mbauda', 'Mianzini', 'Moshono', 'Monduli Juu', 'Ngaramtoni', 'Olorien', 'Sekei', 'Sombetini', 'Themi', 'Unga Limited']],
            'Arusha District' => ['wards' => ['Bwawani', 'Ilkiding\'a', 'Kisimani', 'Kisongo', 'Mlangarini', 'Muriwani', 'Musa', 'Mwandeti', 'Oldonyosambu', 'Olturoto', 'Sokon II', 'Terrat', 'Usa River']],
            'Karatu' => ['wards' => ['Buger', 'Daudi', 'Endabash', 'Endamarariek', 'Ganako', 'Kansay', 'Karatu', 'Karing', 'Mangola', 'Mbulumbulu', 'Qurus', 'Rhotia']],
            'Longido' => ['wards' => ['Gelai Lumbwa', 'Ilongero', 'Kamwanga', 'Ketumbeine', 'Kimokouwa', 'Lepurko', 'Longido', 'Matale', 'Mundarara', 'Namanga', 'Orbomba', 'Tingatinga']],
            'Meru' => ['wards' => ['Akheri', 'Kingori', 'Kikwe', 'Leguruki', 'Majengo', 'Makiba', 'Maroroni', 'Mbuguni', 'Maji ya Chai', 'Mwandeti', 'Nkoaranga', 'Nkoanrua', 'Poli', 'Seela', 'Shambarai', 'Usa River']],
            'Monduli' => ['wards' => ['Engaruka', 'Engutoto', 'Esilalei', 'Gelai', 'Kijungu', 'Kimokouwa', 'Lepurko', 'Mto wa Mbu', 'Monduli Juu', 'Monduli Mjini', 'Moita', 'Selela', 'Makuyuni']],
            'Ngorongoro' => ['wards' => ['Endulen', 'Kakesio', 'Nainokanoka', 'Ngorongoro', 'Olbalbal', 'Oldonyo Sambu', 'Piyaya', 'Samunge', 'Soitsambu']]
        ]
    ],
    'Dar es Salaam' => [
        'districts' => [
            'Ilala' => ['wards' => ['Buguruni', 'Gerezani', 'Ilala', 'Kariakoo', 'Kipawa', 'Kitunda', 'Mchafukoge', 'Msongola', 'Mzimuni', 'Pugu', 'Tabata', 'Ukonga', 'Vingunguti']],
            'Kinondoni' => ['wards' => ['Hananasif', 'Kawe', 'Kibamba', 'Kijitonyama', 'Kinondoni', 'Kunduchi', 'Makongo', 'Makuburi', 'Mbezi', 'Mbweni', 'Mikocheni', 'Mwananyamala', 'Mwenge', 'Tandale', 'Ubungo']],
            'Temeke' => ['wards' => ['Azimio', 'Chamazi', 'Charambe', 'Keko', 'Kiburugwa', 'Kijichi', 'Kitunda', 'Mbagala', 'Mbagala Kuu', 'Mianzini', 'Mji Mwema', 'Mtoni', 'Sandali', 'Temeke', 'Toangoma', 'Tandika', 'Yombo Vituka']],
            'Ubungo' => ['wards' => ['Kibamba', 'Kimara', 'Kinshasa', 'Kwa Mrombo', 'Mburahati', 'Saranga', 'Ubungo', 'Urafiki', 'Uwanja wa Ndege']],
            'Kigamboni' => ['wards' => ['Kigamboni', 'Kisarawe II', 'Mbagala', 'Mjimwema', 'Pemba Mnazi', 'Somangila', 'Tungi']]
        ]
    ],
    'Dodoma' => [
        'districts' => [
            'Bahi' => ['wards' => ['Bahi', 'Chali', 'Chipanga', 'Ibihwa', 'Ilindi', 'Kidoka', 'Mpalanga', 'Mpamantwa', 'Mundemu']],
            'Chamwino' => ['wards' => ['Chamwino', 'Chibelela', 'Chikola', 'Chipanga', 'Chilonwa', 'Farkwa', 'Handali', 'Iduo', 'Kibakwe', 'Mabwegere', 'Mambali', 'Manda', 'Manchali', 'Mandau', 'Mpwayungu', 'Msamalo', 'Mvumi', 'Mwitikira', 'Newala', 'Segala']],
            'Chemba' => ['wards' => ['Babayu', 'Chemba', 'Churuku', 'Farkwa', 'Goima', 'Jangalo', 'Kinyamsindo', 'Kwa Mtoro', 'Lokisale', 'Mjange', 'Mondo', 'Ovada', 'Paranga', 'Ssawando']],
            'Dodoma Municipal' => ['wards' => ['Hazina', 'Ipagala', 'Kikuyu', 'Kikuyu North', 'Kikuyu South', 'Kwa Mrombo', 'Majengo', 'Makole', 'Mbabala', 'Mkonze', 'Msalato', 'Mtumba', 'Nala', 'Ng\'hong\'hona', 'Nzuguni', 'Tambukareli']],
            'Kondoa' => ['wards' => ['Busi', 'Bereko', 'Bolisa', 'Chemka', 'Haubi', 'Kikore', 'Kolo', 'Kondoa Mjini', 'Kwadino', 'Masange', 'Mnenia', 'Mondo', 'Pahi', 'Soera', 'Thawi']],
            'Mpwapwa' => ['wards' => ['Berega', 'Chitemo', 'Dihombo', 'Godegode', 'Igandu', 'Igundu', 'Ilonga', 'Kibakwe', 'Luhombero', 'Mabama', 'Maliwa', 'Manda', 'Mpwapwa', 'Rudi', 'Ving\'hawe']]
        ]
    ],
    'Mwanza' => [
        'districts' => [
            'Ilemela' => ['wards' => ['Bugarika', 'Buhongwa', 'Buswelu', 'Butimba', 'Ilemela', 'Igoma', 'Kitangiri', 'Mkuyuni', 'Nyakato', 'Pasiansi', 'Sangabuye']],
            'Nyamagana' => ['wards' => ['Bismarck', 'Igogo', 'Isamilo', 'Khorongo', 'Kirumba', 'Kitangiri', 'Luhala', 'Mbugani', 'Mirongo', 'Mkuyuni', 'Nyamagana', 'Nyakato', 'Pamba']],
            'Magu' => ['wards' => ['Bujashi', 'Igombe', 'Kabita', 'Kihumulo', 'Kizasi', 'Kongolo', 'Magugu', 'Magu Mjini', 'Malili', 'Mbakani', 'Mhunga', 'Milambo', 'Mwamabanza', 'Mwamanga', 'Ngasamo', 'Nkungugu', 'Nyigogo', 'Shigala', 'Sukuma']],
            'Misungwi' => ['wards' => ['Bubiki', 'Bulemeji', 'Bupamwa', 'Busongo', 'Butundwe', 'Igokelo', 'Igoma', 'Ilujamate', 'Isesa', 'Kanyelele', 'Kijima', 'Mbarika', 'Misungwi', 'Mwaniko', 'Ng\'wagiswa', 'Shilalo', 'Sissa', 'Sukuma']],
            'Kwimba' => ['wards' => ['Bugando', 'Buhongwa', 'Bukwimba', 'Busisi', 'Butimba', 'Igoma', 'Ilemela', 'Isamilo', 'Itilima', 'Kasharazi', 'Kharumwa', 'Mkolani', 'Ng\'homango', 'Ngudu', 'Nkungugu', 'Nyambiti', 'Sumve']],
            'Sengerema' => ['wards' => ['Bugarama', 'Bugoro', 'Bukomelo', 'Bukongo', 'Busisi', 'Buzilasoga', 'Igalukilo', 'Igulumuki', 'Kagongolo', 'Kahunda', 'Kakubilo', 'Kasengere', 'Katwe', 'Kayenze', 'Kigongo', 'Kiziku', 'Magu', 'Mhunze', 'Mwalushu', 'Ng\'weli', 'Nkome', 'Nyamatongo', 'Nyampulukano', 'Sengerema']]
        ]
    ],
    'Mbeya' => [
        'districts' => [
            'Mbeya City' => ['wards' => ['Iganzo', 'Iganzo', 'Ilembo', 'Isanga', 'Itagano', 'Itiji', 'Iyela', 'Iyunga', 'Kalobe', 'Kawetele', 'Lupata', 'Lupatingatinga', 'Lwangwa', 'Mbalizi', 'Mbeya Mjini', 'Mlowo', 'Msalala', 'Msangano', 'Mshewe', 'Msia', 'Mtimbo', 'Mwakibete', 'Mwambani', 'Mwankulwe', 'Nkana', 'Nsalaga', 'Nsalala', 'Nyimbili', 'Saza', 'Simbwa', 'Sokoni', 'Songwe', 'Suma', 'Uyole']],
            'Mbeya Rural' => ['wards' => ['Igale', 'Igurusi', 'Ikuti', 'Ilogi', 'Isangijo', 'Isansa', 'Itamboleo', 'Itawa', 'Itete', 'Itiji', 'Itimbo', 'Iyogelo', 'Iyunga', 'Kafule', 'Kajunjumele', 'Kalobe', 'Kambasegela', 'Kashishi', 'Kawetele', 'Kibebe', 'Kigonsera', 'Kikondo', 'Kilyamatundu', 'Kisinga', 'Kisondela', 'Kitola', 'Lupata', 'Lupatingatinga', 'Lwangwa', 'Mbalizi', 'Mbozi', 'Mlowo', 'Msalala', 'Msangano', 'Mshewe', 'Msia', 'Mtimbo', 'Mwakibete', 'Mwambani', 'Mwankulwe', 'Nkana', 'Nsalaga', 'Nsalala', 'Nyimbili', 'Saza', 'Simbwa', 'Sokoni', 'Songwe', 'Suma', 'Uyole']],
            'Chunya' => ['wards' => ['Chunya', 'Iboya', 'Ifumbo', 'Igamba', 'Igurusi', 'Ihango', 'Ikombo', 'Ilembo', 'Ilomba', 'Ipinda', 'Isangijo', 'Isansa', 'Itaka', 'Itale', 'Itawa', 'Itete', 'Itiji', 'Itimbo', 'Iwindi', 'Iyogelo', 'Iyunga', 'Kafule', 'Kajunjumele', 'Kambasegela', 'Kashishi', 'Kawetele', 'Kibebe', 'Kigonsera', 'Kikondo', 'Kilyamatundu', 'Kisinga', 'Kisondela', 'Kitola', 'Lupata', 'Lupatingatinga', 'Lwangwa', 'Mbalizi', 'Mbozi', 'Mlowo', 'Msalala', 'Msangano', 'Mshewe', 'Msia', 'Mtimbo', 'Mwakibete', 'Mwambani', 'Mwankulwe', 'Nkana', 'Nsalaga', 'Nsalala', 'Nyimbili', 'Saza', 'Simbwa', 'Sokoni', 'Songwe', 'Suma', 'Uyole']],
            'Mbarali' => ['wards' => ['Igurusi', 'Ihango', 'Ikombo', 'Ilembo', 'Ilomba', 'Ipinda', 'Isangijo', 'Isansa', 'Itaka', 'Itale', 'Itawa', 'Itete', 'Itiji', 'Itimbo', 'Iwindi', 'Iyogelo', 'Iyunga', 'Kafule', 'Kajunjumele', 'Kambasegela', 'Kashishi', 'Kawetele', 'Kibebe', 'Kigonsera', 'Kikondo', 'Kilyamatundu', 'Kisinga', 'Kisondela', 'Kitola', 'Lupata', 'Lupatingatinga', 'Lwangwa', 'Mbalizi', 'Mbozi', 'Mlowo', 'Msalala', 'Msangano', 'Mshewe', 'Msia', 'Mtimbo', 'Mwakibete', 'Mwambani', 'Mwankulwe', 'Nkana', 'Nsalaga', 'Nsalala', 'Nyimbili', 'Saza', 'Simbwa', 'Sokoni', 'Songwe', 'Suma', 'Uyole']],
            'Rungwe' => ['wards' => ['Bulyaga', 'Bumila', 'Busale', 'Busokelo', 'Butandiga', 'Ibuyu', 'Ihango', 'Ikombo', 'Ilembo', 'Ilomba', 'Ipinda', 'Isangijo', 'Isansa', 'Itaka', 'Itale', 'Itawa', 'Itete', 'Itiji', 'Itimbo', 'Iwindi', 'Iyogelo', 'Iyunga', 'Kafule', 'Kajunjumele', 'Kambasegela', 'Kashishi', 'Kawetele', 'Kibebe', 'Kigonsera', 'Kikondo', 'Kilyamatundu', 'Kisinga', 'Kisondela', 'Kitola', 'Lupata', 'Lupatingatinga', 'Lwangwa', 'Mbalizi', 'Mbozi', 'Mlowo', 'Msalala', 'Msangano', 'Mshewe', 'Msia', 'Mtimbo', 'Mwakibete', 'Mwambani', 'Mwankulwe', 'Nkana', 'Nsalaga', 'Nsalala', 'Nyimbili', 'Saza', 'Simbwa', 'Sokoni', 'Songwe', 'Suma', 'Uyole']]
        ]
    ]
];

// Add more regions as needed...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - PWD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-bg: #f8f9fc;
        }
        
        .profile-container {
            background: #f8f9fc;
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .profile-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .profile-header {
            background: linear-gradient(120deg, var(--primary-color), #224abe);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
        }
        
        .form-section {
            padding: 1.5rem;
        }
        
        .section-title {
            color: var(--primary-color);
            border-bottom: 2px solid var(--light-bg);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-auto-hide {
            animation: fadeOut 5s forwards;
            animation-delay: 2s;
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; display: none; }
        }
        
        .btn-update {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-update:hover {
            background: #224abe;
        }
        
        .location-loading {
            display: none;
            color: var(--info-color);
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="container">
            <!-- Back to Dashboard Button -->
            <div class="row mb-4">
                <div class="col-12">
                    <a href="user_dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Left Column - Profile Summary -->
                <div class="col-lg-4">
                    <div class="profile-card">
                        <div class="profile-header text-center">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name'] ?? 'User'); ?>&background=fff&color=4e73df&size=100" 
                                 alt="Profile picture" class="profile-avatar mb-3">
                            <h4><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h4>
                            <p class="mb-2">Member Since: <?php echo date('M j, Y', strtotime($user['created_at'] ?? 'now')); ?></p>
                            <span class="badge bg-success">Verified Account</span>
                        </div>
                        
                        <div class="profile-stats text-center" style="background: #f8f9fa; padding: 1rem; margin: 1rem; border-radius: 0.5rem;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #4e73df;"><?php echo $profile_percent; ?>%</div>
                            <div style="font-size: 0.85rem; color: #858796;">Profile Completion</div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $profile_percent; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="p-3">
                            <div class="info-item" style="padding: 1rem; border-bottom: 1px solid #e3e6f0;">
                                <div class="info-label" style="font-weight: 600; color: #858796; font-size: 0.9rem;">Last Updated</div>
                                <div class="info-value" style="color: #5a5c69; font-weight: 500;">
                                    <?php 
                                    if (!empty($user['updated_at']) && $user['updated_at'] != '0000-00-00 00:00:00') {
                                        echo date('M j, Y', strtotime($user['updated_at']));
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="info-item" style="padding: 1rem; border-bottom: 1px solid #e3e6f0;">
                                <div class="info-label" style="font-weight: 600; color: #858796; font-size: 0.9rem;">Account Status</div>
                                <div class="info-value"><span class="badge bg-success">Active</span></div>
                            </div>
                            <div class="info-item" style="padding: 1rem;">
                                <div class="info-label" style="font-weight: 600; color: #858796; font-size: 0.9rem;">Phone Number</div>
                                <div class="info-value" style="color: #5a5c69; font-weight: 500;"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Update Form -->
                <div class="col-lg-8">
                    <div class="profile-card">
                        <div class="profile-header">
                            <h4 class="mb-0">
                                <i class="fas fa-user-edit me-2"></i>Update Your Profile
                            </h4>
                            <p class="mb-0">Keep your information current to receive the best support</p>
                        </div>

                        <div class="form-section">
                            <!-- Auto-hiding Messages -->
                            <?php if (!empty($success_msg)): ?>
                                <div class="alert alert-success alert-dismissible fade show alert-auto-hide" role="alert" id="successAlert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $success_msg; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($error_msg)): ?>
                                <div class="alert alert-danger alert-dismissible fade show alert-auto-hide" role="alert" id="errorAlert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $error_msg; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <!-- Personal Information -->
                                <h5 class="section-title">
                                    <i class="fas fa-user me-2"></i>Personal Information
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="background-color: #f8f9fc; border-right: none;">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" name="full_name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="background-color: #f8f9fc; border-right: none;">
                                                <i class="fas fa-calendar"></i>
                                            </span>
                                            <input type="date" name="date_of_birth" class="form-control" 
                                                   value="<?php echo formatDateForInput($user['date_of_birth'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number *</label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="background-color: #f8f9fc; border-right: none;">
                                                <i class="fas fa-phone"></i>
                                            </span>
                                            <input type="tel" name="phone_number" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                                        </div>
                                        <small class="text-muted">Format: 0755123456</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="background-color: #f8f9fc; border-right: none;">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            <input type="email" name="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($user['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($user['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($user['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Location Information -->
                                <h5 class="section-title mt-4">
                                    <i class="fas fa-map-marker-alt me-2"></i>Location Information
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Region *</label>
                                        <select name="region" id="region" class="form-select" required>
                                            <option value="">Select Region</option>
                                            <?php foreach ($tanzania_locations as $region => $data): ?>
                                                <option value="<?php echo htmlspecialchars($region); ?>" 
                                                    <?php echo ($user['region'] ?? '') == $region ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($region); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="location-loading" id="region-loading">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Loading districts...
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">District *</label>
                                        <select name="district" id="district" class="form-select" required>
                                            <option value="">Select District</option>
                                            <?php if (!empty($user['region']) && isset($tanzania_locations[$user['region']])): ?>
                                                <?php foreach ($tanzania_locations[$user['region']]['districts'] as $district => $data): ?>
                                                    <option value="<?php echo htmlspecialchars($district); ?>" 
                                                        <?php echo ($user['district'] ?? '') == $district ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($district); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="location-loading" id="district-loading">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Loading wards...
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ward *</label>
                                        <select name="ward" id="ward" class="form-select" required>
                                            <option value="">Select Ward</option>
                                            <?php if (!empty($user['region']) && !empty($user['district']) && 
                                                     isset($tanzania_locations[$user['region']]['districts'][$user['district']]['wards'])): ?>
                                                <?php foreach ($tanzania_locations[$user['region']]['districts'][$user['district']]['wards'] as $ward): ?>
                                                    <option value="<?php echo htmlspecialchars($ward); ?>" 
                                                        <?php echo ($user['ward'] ?? '') == $ward ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($ward); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="location-loading" id="ward-loading">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Loading villages...
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Village/Street</label>
                                        <input type="text" name="village" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['village'] ?? ''); ?>"
                                               placeholder="Enter your village or street">
                                    </div>
                                </div>

                                <!-- Disability Information -->
                                <h5 class="section-title mt-4">
                                    <i class="fas fa-wheelchair me-2"></i>Disability Information
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Disability Type *</label>
                                        <select name="disability_type" class="form-select" required>
                                            <option value="">Select Type</option>
                                            <option value="physical" <?php echo ($user['disability_type'] ?? '') == 'physical' ? 'selected' : ''; ?>>Physical Disability</option>
                                            <option value="visual" <?php echo ($user['disability_type'] ?? '') == 'visual' ? 'selected' : ''; ?>>Visual Impairment</option>
                                            <option value="hearing" <?php echo ($user['disability_type'] ?? '') == 'hearing' ? 'selected' : ''; ?>>Hearing Impairment</option>
                                            <option value="intellectual" <?php echo ($user['disability_type'] ?? '') == 'intellectual' ? 'selected' : ''; ?>>Intellectual Disability</option>
                                            <option value="multiple" <?php echo ($user['disability_type'] ?? '') == 'multiple' ? 'selected' : ''; ?>>Multiple Disabilities</option>
                                            <option value="psychosocial" <?php echo ($user['disability_type'] ?? '') == 'psychosocial' ? 'selected' : ''; ?>>Psychosocial Disability</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Severity Level</label>
                                        <select name="disability_severity" class="form-select">
                                            <option value="">Select Level</option>
                                            <option value="mild" <?php echo ($user['disability_severity'] ?? '') == 'mild' ? 'selected' : ''; ?>>Mild</option>
                                            <option value="moderate" <?php echo ($user['disability_severity'] ?? '') == 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                            <option value="severe" <?php echo ($user['disability_severity'] ?? '') == 'severe' ? 'selected' : ''; ?>>Severe</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Communication Preferences -->
                                <h5 class="section-title mt-4">
                                    <i class="fas fa-comments me-2"></i>Communication Preferences
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Communication Method</label>
                                        <select name="communication_preference" class="form-select">
                                            <option value="text" <?php echo ($user['communication_preference'] ?? '') == 'text' ? 'selected' : ''; ?>>Text Message</option>
                                            <option value="call" <?php echo ($user['communication_preference'] ?? '') == 'call' ? 'selected' : ''; ?>>Phone Call</option>
                                            <option value="in_person" <?php echo ($user['communication_preference'] ?? '') == 'in_person' ? 'selected' : ''; ?>>In Person</option>
                                            <option value="email" <?php echo ($user['communication_preference'] ?? '') == 'email' ? 'selected' : ''; ?>>Email</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="user_dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-update">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Tanzania location data
    const tanzaniaLocations = <?php echo json_encode($tanzania_locations); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const regionSelect = document.getElementById('region');
        const districtSelect = document.getElementById('district');
        const wardSelect = document.getElementById('ward');
        const regionLoading = document.getElementById('region-loading');
        const districtLoading = document.getElementById('district-loading');
        const wardLoading = document.getElementById('ward-loading');

        // Auto-hide alerts after 3 seconds
        const alerts = document.querySelectorAll('.alert-auto-hide');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 3000);
        });
        
        // Phone number validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phoneInput = document.querySelector('input[name="phone_number"]');
            const phoneRegex = /^0[67]\d{8}$/;
            
            if (!phoneRegex.test(phoneInput.value)) {
                e.preventDefault();
                alert('Please enter a valid Tanzanian phone number (e.g., 0755123456)');
                phoneInput.focus();
            }
        });

        // Region change handler
        regionSelect.addEventListener('change', function() {
            const selectedRegion = this.value;
            
            // Reset dependent fields
            districtSelect.innerHTML = '<option value="">Select District</option>';
            wardSelect.innerHTML = '<option value="">Select Ward</option>';
            
            if (selectedRegion && tanzaniaLocations[selectedRegion]) {
                regionLoading.style.display = 'block';
                
                // Simulate loading delay for better UX
                setTimeout(() => {
                    const districts = tanzaniaLocations[selectedRegion].districts;
                    
                    Object.keys(districts).forEach(district => {
                        const option = document.createElement('option');
                        option.value = district;
                        option.textContent = district;
                        districtSelect.appendChild(option);
                    });
                    
                    regionLoading.style.display = 'none';
                }, 500);
            }
        });

        // District change handler
        districtSelect.addEventListener('change', function() {
            const selectedRegion = regionSelect.value;
            const selectedDistrict = this.value;
            
            // Reset dependent field
            wardSelect.innerHTML = '<option value="">Select Ward</option>';
            
            if (selectedRegion && selectedDistrict && 
                tanzaniaLocations[selectedRegion] && 
                tanzaniaLocations[selectedRegion].districts[selectedDistrict]) {
                
                districtLoading.style.display = 'block';
                
                // Simulate loading delay for better UX
                setTimeout(() => {
                    const wards = tanzaniaLocations[selectedRegion].districts[selectedDistrict].wards;
                    
                    wards.forEach(ward => {
                        const option = document.createElement('option');
                        option.value = ward;
                        option.textContent = ward;
                        wardSelect.appendChild(option);
                    });
                    
                    districtLoading.style.display = 'none';
                }, 500);
            }
        });

        // Ward change handler
        wardSelect.addEventListener('change', function() {
            const selectedWard = this.value;
            
            if (selectedWard) {
                wardLoading.style.display = 'block';
                
                // Simulate loading for villages (in real implementation, you might fetch villages from database)
                setTimeout(() => {
                    wardLoading.style.display = 'none';
                }, 300);
            }
        });

        // Initialize form if region is already selected
        if (regionSelect.value) {
            regionSelect.dispatchEvent(new Event('change'));
            
            // If district is also selected, trigger district change
            if (districtSelect.value) {
                setTimeout(() => {
                    districtSelect.dispatchEvent(new Event('change'));
                }, 600);
            }
        }
    });
    </script>
</body>
</html>