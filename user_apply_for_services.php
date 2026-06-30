<?php
session_start();
require 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$success_message = '';
$error_message = '';

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

// Disability Services Mapping
$disability_services = [
    'visual' => [
        'Screen Reader Software',
        'Braille Displays',
        'Magnification Software',
        'Voice Recognition',
        'Accessible Mobile Apps',
        'Digital Literacy Training',
        'Navigation Assistance Tools'
    ],
    'hearing' => [
        'Sign Language Interpretation',
        'Hearing Aids Support',
        'Captioning Services',
        'Visual Alert Systems',
        'Video Relay Services',
        'Communication Training',
        'Assistive Listening Devices'
    ],
    'physical' => [
        'Adaptive Keyboards',
        'Voice Control Systems',
        'Switch Access',
        'Ergonomic Workstations',
        'Mobility Equipment',
        'Environmental Controls',
        'Physical Therapy Tech'
    ],
    'intellectual' => [
        'Cognitive Support Software',
        'Simplified Interfaces',
        'Task Management Apps',
        'Learning Support Tools',
        'Memory Aids',
        'Social Skills Training',
        'Daily Living Assistants'
    ],
    'multiple' => [
        'Comprehensive Assistive Tech',
        'Multi-modal Communication',
        'Integrated Support Systems',
        'Customized Solutions',
        'Therapy Equipment',
        'Mobility & Communication Aids',
        'Specialized Training Programs'
    ],
    'psychosocial' => [
        'Mental Health Apps',
        'Stress Management Tools',
        'Social Integration Support',
        'Therapy Assistance',
        'Community Building',
        'Coping Strategy Training',
        'Peer Support Networks'
    ],
    'other' => [
        'Custom Assistive Technology',
        'Accessibility Consulting',
        'Training & Capacity Building',
        'Community Integration',
        'Employment Support',
        'Independent Living Aids',
        'Specialized Equipment'
    ]
];

// PROCESS FORM SUBMISSION with Post-Redirect-Get pattern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if form was already submitted to prevent duplicate submissions
    if (isset($_SESSION['form_submitted']) && $_SESSION['form_submitted'] === true) {
        // Form was already submitted, redirect to clear POST data
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Get form data
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $region = $_POST['region'] ?? '';
    $district = $_POST['district'] ?? '';
    $ward = $_POST['ward'] ?? '';
    $village = trim($_POST['village'] ?? '');
    $disabilityType = $_POST['disabilityType'] ?? '';
    $serviceType = $_POST['serviceType'] ?? '';
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($phone)) $errors[] = 'Phone number is required';
    if (empty($region)) $errors[] = 'Region is required';
    if (empty($district)) $errors[] = 'District is required';
    if (empty($ward)) $errors[] = 'Ward is required';
    if (empty($disabilityType)) $errors[] = 'Disability type is required';
    if (empty($serviceType)) $errors[] = 'Service type is required';
    if (empty($message)) $errors[] = 'Please describe your needs';
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($errors)) {
        try {
            // Check if services table exists, if not create it with all fields
            $tableCheck = $conn->query("SHOW TABLES LIKE 'service_applications'");
            if ($tableCheck->num_rows == 0) {
                // Create comprehensive service_applications table
                $createTableSQL = "CREATE TABLE service_applications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    email VARCHAR(150) NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    region VARCHAR(100) NOT NULL,
                    district VARCHAR(100) NOT NULL,
                    ward VARCHAR(100) NOT NULL,
                    village VARCHAR(100),
                    disability_type VARCHAR(50) NOT NULL,
                    service_type VARCHAR(200) NOT NULL,
                    message TEXT NOT NULL,
                    application_date DATE NOT NULL,
                    urgency_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
                    status ENUM('pending', 'under_review', 'approved', 'rejected', 'completed') DEFAULT 'pending',
                    assigned_staff_id INT NULL,
                    review_notes TEXT,
                    estimated_cost DECIMAL(10,2),
                    follow_up_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_status (status),
                    INDEX idx_disability_type (disability_type),
                    INDEX idx_region (region),
                    INDEX idx_created_at (created_at)
                )";
                
                if (!$conn->query($createTableSQL)) {
                    throw new Exception("Failed to create service_applications table: " . $conn->error);
                }
            } else {
                // Table exists, check if we need to add new columns
                $checkColumns = $conn->query("DESCRIBE service_applications");
                $existingColumns = [];
                while ($row = $checkColumns->fetch_assoc()) {
                    $existingColumns[] = $row['Field'];
                }
                
                // Add missing columns if they don't exist
                $missingColumns = [
                    'district' => "ALTER TABLE service_applications ADD COLUMN district VARCHAR(100) NOT NULL AFTER region",
                    'ward' => "ALTER TABLE service_applications ADD COLUMN ward VARCHAR(100) NOT NULL AFTER district", 
                    'village' => "ALTER TABLE service_applications ADD COLUMN village VARCHAR(100) AFTER ward",
                    'application_date' => "ALTER TABLE service_applications ADD COLUMN application_date DATE NOT NULL AFTER message",
                    'urgency_level' => "ALTER TABLE service_applications ADD COLUMN urgency_level ENUM('low', 'medium', 'high') DEFAULT 'medium' AFTER application_date"
                ];
                
                foreach ($missingColumns as $column => $alterSQL) {
                    if (!in_array($column, $existingColumns)) {
                        if (!$conn->query($alterSQL)) {
                            throw new Exception("Failed to add column $column: " . $conn->error);
                        }
                    }
                }
            }
            
            // Insert service application
            $application_date = date('Y-m-d');
            $sql = "INSERT INTO service_applications (
                    user_id, first_name, last_name, email, phone, region, district, ward, village,
                    disability_type, service_type, message, application_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare SQL statement: " . $conn->error);
            }
            
            $stmt->bind_param(
                "issssssssssss", 
                $user_id, $firstName, $lastName, $email, $phone, $region, $district, $ward, $village,
                $disabilityType, $serviceType, $message, $application_date
            );
            
            if ($stmt->execute()) {
                // Mark form as submitted to prevent duplicates
                $_SESSION['form_submitted'] = true;
                
                // Store success message in session for display after redirect
                $_SESSION['success_message'] = 'Application submitted successfully! We will contact you within 3 business days.';
                
                // Redirect to clear POST data (Post-Redirect-Get pattern)
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                throw new Exception("Failed to save application: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Service application error: " . $e->getMessage());
            $error_message = 'Application submission failed. Please try again. Error: ' . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Check for success message from session (after redirect)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
    // Also clear the form submitted flag
    unset($_SESSION['form_submitted']);
}

// Clear form submitted flag when page is accessed via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    unset($_SESSION['form_submitted']);
}

// Fetch user data to pre-fill the form
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Application - ICT for Tanzanian PWDs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e3e8f0 100%);
            min-height: 100vh;
            padding: 20px;
            color: #2c3e50;
        }
        
        .container {
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            margin: 0 auto;
        }
        
        .header {
            background: #1a73e8;
            color: white;
            padding: 25px 30px;
            text-align: center;
            position: relative;
        }
        
        .tanzania-flag {
            position: absolute;
            top: 15px;
            right: 20px;
            display: flex;
        }
        
        .flag-strip {
            width: 20px;
            height: 40px;
        }
        
        .green { background-color: #1eb53a; }
        .yellow { background-color: #fcd116; }
        .black { background-color: #000000; }
        .blue { background-color: #1a73e8; }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .progress-bar {
            height: 6px;
            background: #1557b0;
            width: 30%;
            margin-top: 15px;
            border-radius: 3px;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .section-title {
            font-size: 20px;
            color: #1a73e8;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e6e6e6;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            background: #1a73e8;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
        }
        
        .row {
            display: flex;
            gap: 20px;
        }
        
        .col {
            flex: 1;
        }
        
        .required::after {
            content: " *";
            color: #e74c3c;
        }
        
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        button {
            padding: 14px 28px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-back {
            background: #ecf0f1;
            color: #7f8c8d;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-back:hover {
            background: #dde4e6;
        }
        
        .btn-submit {
            background: #2ecc71;
            color: white;
        }
        
        .btn-submit:hover {
            background: #27ae60;
        }
        
        .btn-submit:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .accessibility-options {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #1a73e8;
        }
        
        .accessibility-options h3 {
            margin-bottom: 10px;
            color: #1a73e8;
            display: flex;
            align-items: center;
        }
        
        .accessibility-options h3 i {
            margin-right: 10px;
        }
        
        .option-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }
        
        .option-item input {
            width: auto;
            margin-right: 5px;
        }
        
        /* Auto-hide alerts */
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            position: relative;
            animation: slideIn 0.3s ease-out;
        }
        
        .alert-auto-hide {
            animation: slideIn 0.3s ease-out, slideOut 0.3s ease-in 3s forwards;
        }
        
        .alert-error {
            background: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background: #eff8f0;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(-20px); opacity: 0; display: none; }
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .row {
                flex-direction: column;
                gap: 0;
            }
            
            .btn-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn-container button, .btn-container a {
                width: 100%;
                text-align: center;
            }
            
            .tanzania-flag {
                position: relative;
                justify-content: center;
                margin: 10px 0;
                top: 0;
                right: 0;
            }
        }
        
        .high-contrast {
            background: black;
            color: white;
        }
        
        .high-contrast .container {
            background: #222;
            color: white;
        }
        
        .high-contrast input, 
        .high-contrast select, 
        .high-contrast textarea {
            background: #333;
            color: white;
            border-color: #555;
        }

        /* New styles for enhanced functionality */
        .location-loading {
            display: none;
            color: #1a73e8;
            font-size: 0.875rem;
            margin-top: 5px;
        }
        
        .service-description {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 0.9rem;
            color: #555;
            border-left: 3px solid #1a73e8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="tanzania-flag">
                <div class="flag-strip green"></div>
                <div class="flag-strip yellow"></div>
                <div class="flag-strip black"></div>
                <div class="flag-strip blue"></div>
            </div>
            <h1>ICT Service Application for PWDs</h1>
            <p>Empowering Persons with Disabilities in Tanzania through Technology</p>
            <div class="progress-bar"></div>
        </div>
        
        <div class="form-container">
            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-auto-hide" id="successAlert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
                <script>
                    setTimeout(() => {
                        document.getElementById('successAlert').style.display = 'none';
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error alert-auto-hide" id="errorAlert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
                <script>
                    setTimeout(() => {
                        document.getElementById('errorAlert').style.display = 'none';
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <h2 class="section-title">
                <i class="fas fa-user"></i> Personal Information
            </h2>
            
            <form method="POST" action="" id="serviceForm">
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="firstName" class="required">First Name</label>
                            <input type="text" id="firstName" name="firstName" 
                                   value="<?php echo htmlspecialchars($firstName ?? ($user['full_name'] ?? '')); ?>" required>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="lastName" class="required">Last Name</label>
                            <input type="text" id="lastName" name="lastName" 
                                   value="<?php echo htmlspecialchars($lastName ?? ($user['full_name'] ?? '')); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($email ?? ($user['email'] ?? '')); ?>" required>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="phone" class="required">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($phone ?? ($user['phone'] ?? '')); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Location Information -->
                <h2 class="section-title">
                    <i class="fas fa-map-marker-alt"></i> Location Information
                </h2>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="region" class="required">Region</label>
                            <select id="region" name="region" class="form-select" required>
                                <option value="">Select Region</option>
                                <?php foreach ($tanzania_locations as $region_name => $data): ?>
                                    <option value="<?php echo htmlspecialchars($region_name); ?>" 
                                        <?php echo ($region ?? ($user['region'] ?? '')) == $region_name ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($region_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="location-loading" id="region-loading">
                                <i class="fas fa-spinner fa-spin me-1"></i> Loading districts...
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="district" class="required">District</label>
                            <select id="district" name="district" class="form-select" required>
                                <option value="">Select District</option>
                                <?php if (!empty($region) && isset($tanzania_locations[$region])): ?>
                                    <?php foreach ($tanzania_locations[$region]['districts'] as $district_name => $data): ?>
                                        <option value="<?php echo htmlspecialchars($district_name); ?>" 
                                            <?php echo ($district ?? ($user['district'] ?? '')) == $district_name ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($district_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="location-loading" id="district-loading">
                                <i class="fas fa-spinner fa-spin me-1"></i> Loading wards...
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="ward" class="required">Ward</label>
                            <select id="ward" name="ward" class="form-select" required>
                                <option value="">Select Ward</option>
                                <?php if (!empty($region) && !empty($district) && 
                                         isset($tanzania_locations[$region]['districts'][$district]['wards'])): ?>
                                    <?php foreach ($tanzania_locations[$region]['districts'][$district]['wards'] as $ward_name): ?>
                                        <option value="<?php echo htmlspecialchars($ward_name); ?>" 
                                            <?php echo ($ward ?? ($user['ward'] ?? '')) == $ward_name ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ward_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="village">Village/Street</label>
                            <input type="text" id="village" name="village" 
                                   value="<?php echo htmlspecialchars($village ?? ($user['village'] ?? '')); ?>"
                                   placeholder="Enter your village or street name">
                        </div>
                    </div>
                </div>

                <h2 class="section-title">
                    <i class="fas fa-concierge-bell"></i> Service Details
                </h2>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="disabilityType" class="required">Disability Type</label>
                            <select id="disabilityType" name="disabilityType" required>
                                <option value="">Select Disability Type</option>
                                <?php foreach ($disability_services as $disability => $services): ?>
                                    <option value="<?php echo htmlspecialchars($disability); ?>" 
                                        <?php echo ($disabilityType ?? ($user['disability_type'] ?? '')) == $disability ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(htmlspecialchars($disability)); ?> Disability
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label for="serviceType" class="required">Service Type</label>
                            <select id="serviceType" name="serviceType" required>
                                <option value="">Select a service</option>
                                <!-- Options will be populated by JavaScript based on disability type -->
                            </select>
                            <div id="serviceDescription" class="service-description" style="display: none;">
                                <!-- Service description will appear here -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="message" class="required">Please describe your needs</label>
                    <textarea id="message" name="message" rows="5" placeholder="Please describe your requirements in detail and how we can assist you" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                </div>
                
                <div class="accessibility-options">
                    <h3><i class="fas fa-universal-access"></i> Accessibility Options</h3>
                    <div class="option-row">
                        <div class="option-item">
                            <input type="checkbox" id="highContrast">
                            <label for="highContrast">High Contrast Mode</label>
                        </div>
                        <div class="option-item">
                            <input type="checkbox" id="textToSpeech">
                            <label for="textToSpeech">Enable Text-to-Speech</label>
                        </div>
                        <div class="option-item">
                            <input type="checkbox" id="largeText">
                            <label for="largeText">Large Text</label>
                        </div>
                    </div>
                </div>
                
                <div class="btn-container">
                    <a href="user_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tanzania location data
        const tanzaniaLocations = <?php echo json_encode($tanzania_locations); ?>;
        
        // Disability services data
        const disabilityServices = <?php echo json_encode($disability_services); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const regionSelect = document.getElementById('region');
            const districtSelect = document.getElementById('district');
            const wardSelect = document.getElementById('ward');
            const disabilityTypeSelect = document.getElementById('disabilityType');
            const serviceTypeSelect = document.getElementById('serviceType');
            const serviceDescription = document.getElementById('serviceDescription');
            const regionLoading = document.getElementById('region-loading');
            const districtLoading = document.getElementById('district-loading');

            // Auto-hide alerts after 3 seconds
            const alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 3000);
            });
            
            // Form validation
            document.getElementById('serviceForm').addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                const originalText = submitBtn.innerHTML;
                
                // Simple validation
                const phone = document.getElementById('phone').value;
                const phoneRegex = /^0[67]\d{8}$/;
                
                if (!phoneRegex.test(phone)) {
                    e.preventDefault();
                    alert('Please enter a valid Tanzanian phone number (e.g., 0755123456)');
                    return false;
                }
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<div class="loading"></div> Processing...';
                
                // Re-enable button after 5 seconds in case of error
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 5000);
                
                return true;
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

            // Disability type change handler
            disabilityTypeSelect.addEventListener('change', function() {
                const selectedDisability = this.value;
                
                // Reset service type
                serviceTypeSelect.innerHTML = '<option value="">Select a service</option>';
                serviceDescription.style.display = 'none';
                
                if (selectedDisability && disabilityServices[selectedDisability]) {
                    const services = disabilityServices[selectedDisability];
                    
                    services.forEach(service => {
                        const option = document.createElement('option');
                        option.value = service;
                        option.textContent = service;
                        serviceTypeSelect.appendChild(option);
                    });
                    
                    // Show service description
                    serviceDescription.textContent = `Available ${selectedDisability} disability services: ${services.length} options`;
                    serviceDescription.style.display = 'block';
                }
            });

            // Service type change handler
            serviceTypeSelect.addEventListener('change', function() {
                const selectedService = this.value;
                if (selectedService) {
                    serviceDescription.textContent = `Selected: ${selectedService}`;
                    serviceDescription.style.display = 'block';
                }
            });

            // Initialize form if values are already selected
            if (regionSelect.value) {
                regionSelect.dispatchEvent(new Event('change'));
                
                if (districtSelect.value) {
                    setTimeout(() => {
                        districtSelect.dispatchEvent(new Event('change'));
                    }, 600);
                }
            }
            
            if (disabilityTypeSelect.value) {
                disabilityTypeSelect.dispatchEvent(new Event('change'));
                
                if (serviceTypeSelect.value) {
                    setTimeout(() => {
                        serviceTypeSelect.dispatchEvent(new Event('change'));
                    }, 300);
                }
            }
        });
        
        // Accessibility features
        document.getElementById('highContrast').addEventListener('change', function() {
            document.body.classList.toggle('high-contrast', this.checked);
        });
        
        document.getElementById('textToSpeech').addEventListener('change', function() {
            if (this.checked) {
                alert('Text-to-speech enabled. This feature would read the content aloud when implemented.');
            }
        });
        
        document.getElementById('largeText').addEventListener('change', function() {
            document.body.style.fontSize = this.checked ? '18px' : '16px';
        });
        
        // Auto-fill form with user data
        document.addEventListener('DOMContentLoaded', function() {
            const userFullName = "<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>";
            if (userFullName && !document.getElementById('firstName').value) {
                const nameParts = userFullName.split(' ');
                if (nameParts.length > 0) {
                    document.getElementById('firstName').value = nameParts[0] || '';
                    if (nameParts.length > 1) {
                        document.getElementById('lastName').value = nameParts.slice(1).join(' ') || '';
                    }
                }
            }
        });
    </script>
</body>
</html>