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

// Appointment Purposes
$appointment_purposes = [
    'consultation' => 'ICT Consultation - General technology advice and guidance',
    'training' => 'Training Program - Learn new digital skills',
    'assistive_tech' => 'Assistive Technology Demo - Try specialized equipment',
    'support' => 'Technical Support - Help with devices or software',
    'assessment' => 'Needs Assessment - Determine suitable technology solutions',
    'repair' => 'Device Repair - Fix broken assistive devices',
    'workshop' => 'Workshop Participation - Join training sessions',
    'counseling' => 'Career Counseling - ICT career guidance',
    'other' => 'Other - Specify in additional notes'
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
    $purpose = $_POST['purpose'] ?? '';
    $appointmentDate = $_POST['appointmentDate'] ?? '';
    $appointmentTime = $_POST['appointmentTime'] ?? '';
    $urgency = $_POST['urgency'] ?? 'medium';
    $preferred_contact = $_POST['preferred_contact'] ?? 'email';
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
    if (empty($purpose)) $errors[] = 'Purpose of visit is required';
    if (empty($appointmentDate)) $errors[] = 'Appointment date is required';
    if (empty($appointmentTime)) $errors[] = 'Appointment time is required';
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Validate appointment date (not in the past)
    if (!empty($appointmentDate)) {
        $selectedDate = DateTime::createFromFormat('Y-m-d', $appointmentDate);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($selectedDate < $today) {
            $errors[] = 'Appointment date cannot be in the past';
        }
    }
    
    if (empty($errors)) {
        try {
            // Check if appointments table exists, if not create it with all fields
            $tableCheck = $conn->query("SHOW TABLES LIKE 'appointments'");
            if ($tableCheck->num_rows == 0) {
                // Create comprehensive appointments table WITHOUT problematic foreign keys
                $createTableSQL = "CREATE TABLE appointments (
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
                    purpose VARCHAR(200) NOT NULL,
                    appointment_date DATE NOT NULL,
                    appointment_time VARCHAR(20) NOT NULL,
                    urgency_level VARCHAR(20) DEFAULT 'medium',
                    preferred_contact VARCHAR(20) DEFAULT 'email',
                    message TEXT,
                    status VARCHAR(20) DEFAULT 'pending',
                    admin_notes TEXT,
                    admin_action_date TIMESTAMP NULL,
                    admin_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )";
                
                if (!$conn->query($createTableSQL)) {
                    throw new Exception("Failed to create appointments table: " . $conn->error);
                }
            } else {
                // Table exists, check if we need to add new columns for admin communication
                $checkColumns = $conn->query("DESCRIBE appointments");
                $existingColumns = [];
                while ($row = $checkColumns->fetch_assoc()) {
                    $existingColumns[] = $row['Field'];
                }
                
                // Add missing columns if they don't exist
                $missingColumns = [
                    'district' => "ALTER TABLE appointments ADD COLUMN district VARCHAR(100) NOT NULL DEFAULT '' AFTER region",
                    'ward' => "ALTER TABLE appointments ADD COLUMN ward VARCHAR(100) NOT NULL DEFAULT '' AFTER district", 
                    'village' => "ALTER TABLE appointments ADD COLUMN village VARCHAR(100) DEFAULT '' AFTER ward",
                    'urgency_level' => "ALTER TABLE appointments ADD COLUMN urgency_level VARCHAR(20) DEFAULT 'medium' AFTER appointment_time",
                    'preferred_contact' => "ALTER TABLE appointments ADD COLUMN preferred_contact VARCHAR(20) DEFAULT 'email' AFTER urgency_level",
                    'admin_notes' => "ALTER TABLE appointments ADD COLUMN admin_notes TEXT AFTER status",
                    'admin_action_date' => "ALTER TABLE appointments ADD COLUMN admin_action_date TIMESTAMP NULL AFTER admin_notes",
                    'admin_id' => "ALTER TABLE appointments ADD COLUMN admin_id INT DEFAULT NULL AFTER admin_action_date"
                ];
                
                foreach ($missingColumns as $column => $alterSQL) {
                    if (!in_array($column, $existingColumns)) {
                        if (!$conn->query($alterSQL)) {
                            // Log error but don't stop execution
                            error_log("Failed to add column $column: " . $conn->error);
                        }
                    }
                }
            }
            
            // Insert appointment
            $sql = "INSERT INTO appointments (
                    user_id, first_name, last_name, email, phone, region, district, ward, village,
                    disability_type, purpose, appointment_date, appointment_time, urgency_level, preferred_contact, message
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare SQL statement: " . $conn->error);
            }
            
            $stmt->bind_param(
                "isssssssssssssss", 
                $user_id, $firstName, $lastName, $email, $phone, $region, $district, $ward, $village,
                $disabilityType, $purpose, $appointmentDate, $appointmentTime, $urgency, $preferred_contact, $message
            );
            
            if ($stmt->execute()) {
                $appointment_id = $stmt->insert_id;
                
                // Create notifications table if it doesn't exist
                $notificationsCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
                if ($notificationsCheck->num_rows == 0) {
                    $notificationsTableSQL = "CREATE TABLE notifications (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        appointment_id INT,
                        title VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        type VARCHAR(20) DEFAULT 'info',
                        is_read BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )";
                    
                    $conn->query($notificationsTableSQL);
                }
                
                // Create notification for the user
                $notification_sql = "INSERT INTO notifications (user_id, appointment_id, title, message, type) VALUES (?, ?, ?, ?, ?)";
                $notification_stmt = $conn->prepare($notification_sql);
                
                if ($notification_stmt) {
                    $notification_title = "Appointment Request Submitted";
                    $notification_message = "Your appointment for '{$purpose}' on {$appointmentDate} at {$appointmentTime} has been submitted and is pending admin approval.";
                    
                    $notification_stmt->bind_param("iisss", 
                        $user_id,
                        $appointment_id,
                        $notification_title,
                        $notification_message,
                        'info'
                    );
                    $notification_stmt->execute();
                    $notification_stmt->close();
                }
                
                // Mark form as submitted to prevent duplicates
                $_SESSION['form_submitted'] = true;
                
                // Store success message in session for display after redirect
                $_SESSION['success_message'] = 'Appointment booked successfully! You will receive a confirmation email shortly.';
                
                // Redirect to clear POST data (Post-Redirect-Get pattern)
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                throw new Exception("Failed to save appointment: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Appointment booking error: " . $e->getMessage());
            $error_message = 'Appointment booking failed. Please try again. Error: ' . $e->getMessage();
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
    <title>Book Appointment - ICT for Tanzanian PWDs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ALL YOUR EXISTING CSS STYLES REMAIN EXACTLY THE SAME - NO CHANGES */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e3e8f0 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #2c3e50;
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
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
        
        .appointment-container {
            display: flex;
            flex-wrap: wrap;
        }
        
        .left-panel {
            flex: 1;
            min-width: 300px;
            padding: 25px;
            background: #f8f9fa;
            border-right: 1px solid #e6e6e6;
        }
        
        .right-panel {
            flex: 1.5;
            min-width: 400px;
            padding: 25px;
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
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            position: relative;
            animation: slideIn 0.3s ease-out;
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
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .modal-icon {
            font-size: 60px;
            color: #2ecc71;
            margin-bottom: 20px;
        }
        
        .modal h2 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .modal p {
            margin-bottom: 25px;
            color: #7f8c8d;
            line-height: 1.5;
        }
        
        .appointment-details {
            text-align: left;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .appointment-details p {
            margin: 8px 0;
            color: #2c3e50;
        }
        
        .modal-close {
            background: #1a73e8;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .row {
                flex-direction: column;
                gap: 0;
            }
            
            .btn-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-back, .btn-submit {
                width: 100%;
            }
            
            .appointment-container {
                flex-direction: column;
            }
            
            .left-panel {
                border-right: none;
                border-bottom: 1px solid #e6e6e6;
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 24px;
            }
            
            .option-row {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        .user-welcome {
            background: #e8f4fe;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            border-left: 4px solid #1a73e8;
        }
        
        .user-welcome i {
            font-size: 24px;
            color: #1a73e8;
            margin-right: 15px;
        }
        
        .user-welcome h3 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .user-welcome p {
            color: #7f8c8d;
            margin: 0;
        }
        
        .location-hierarchy {
            background: #f0f7ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #d0e3ff;
        }
        
        .hierarchy-step {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .hierarchy-step:last-child {
            margin-bottom: 0;
        }
        
        .step-number {
            background: #1a73e8;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .step-text {
            color: #2c3e50;
            font-size: 14px;
        }
        
        .help-text {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
            font-style: italic;
        }
        
        .disability-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .disability-option {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .disability-option:hover {
            background: #f0f7ff;
            border-color: #1a73e8;
        }
        
        .disability-option.selected {
            background: #e8f4fe;
            border-color: #1a73e8;
        }
        
        .disability-option input {
            width: auto;
            margin-right: 8px;
        }
        
        .error-highlight {
            border-color: #e74c3c !important;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.2) !important;
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
            <h1><i class="fas fa-calendar-check"></i> Book Your Appointment</h1>
            <p>Schedule your visit to our ICT Resource Center for Persons with Disabilities</p>
            <div class="progress-bar"></div>
        </div>
        
        <!-- User Welcome Section -->
        <div class="user-welcome">
            <i class="fas fa-user-circle"></i>
            <div>
                <h3>Welcome, <?php echo htmlspecialchars($full_name); ?>!</h3>
                <p>Book your appointment and track its status here. You'll receive notifications when admin takes action on your request.</p>
            </div>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
            <script>
                // Auto-hide success message after 5 seconds
                setTimeout(() => {
                    const alert = document.getElementById('successAlert');
                    if (alert) {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }
                }, 5000);
            </script>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST" id="appointmentForm">
            <div class="appointment-container">
                <div class="left-panel">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i> Personal Information
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="firstName" class="required">First Name</label>
                                <input type="text" id="firstName" name="firstName" 
                                       value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : ($user ? htmlspecialchars($user['full_name']) : ''); ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="lastName" class="required">Last Name</label>
                                <input type="text" id="lastName" name="lastName" 
                                       value="<?php echo isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="required">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ($user ? htmlspecialchars($user['email']) : ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="required">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ($user ? htmlspecialchars($user['phone']) : ''); ?>" 
                               placeholder="+255 XXX XXX XXX" required>
                    </div>
                    
                    <div class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Location Details
                    </div>
                    
                    <div class="location-hierarchy">
                        <div class="hierarchy-step">
                            <div class="step-number">1</div>
                            <div class="step-text">Select your Region</div>
                        </div>
                        <div class="hierarchy-step">
                            <div class="step-number">2</div>
                            <div class="step-text">Choose your District</div>
                        </div>
                        <div class="hierarchy-step">
                            <div class="step-number">3</div>
                            <div class="step-text">Select your Ward</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="region" class="required">Region</label>
                        <select id="region" name="region" required>
                            <option value="">Select Region</option>
                            <?php foreach ($tanzania_locations as $region_name => $region_data): ?>
                                <option value="<?php echo $region_name; ?>" 
                                    <?php echo (isset($_POST['region']) && $_POST['region'] == $region_name) ? 'selected' : ''; ?>>
                                    <?php echo $region_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="district" class="required">District</label>
                        <select id="district" name="district" required>
                            <option value="">Select District</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ward" class="required">Ward</label>
                        <select id="ward" name="ward" required>
                            <option value="">Select Ward</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="village">Village/Street (Optional)</label>
                        <input type="text" id="village" name="village" 
                               value="<?php echo isset($_POST['village']) ? htmlspecialchars($_POST['village']) : ''; ?>">
                    </div>
                </div>
                
                <div class="right-panel">
                    <div class="section-title">
                        <i class="fas fa-wheelchair"></i> Disability Information
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Type of Disability</label>
                        <div class="disability-options">
                            <label class="disability-option">
                                <input type="radio" name="disabilityType" value="visual" 
                                       <?php echo (isset($_POST['disabilityType']) && $_POST['disabilityType'] == 'visual') ? 'checked' : ''; ?> required>
                                Visual Impairment
                            </label>
                            <label class="disability-option">
                                <input type="radio" name="disabilityType" value="hearing" 
                                       <?php echo (isset($_POST['disabilityType']) && $_POST['disabilityType'] == 'hearing') ? 'checked' : ''; ?>>
                                Hearing Impairment
                            </label>
                            <label class="disability-option">
                                <input type="radio" name="disabilityType" value="physical" 
                                       <?php echo (isset($_POST['disabilityType']) && $_POST['disabilityType'] == 'physical') ? 'checked' : ''; ?>>
                                Physical/Mobility
                            </label>
                            <label class="disability-option">
                                <input type="radio" name="disabilityType" value="intellectual" 
                                       <?php echo (isset($_POST['disabilityType']) && $_POST['disabilityType'] == 'intellectual') ? 'checked' : ''; ?>>
                                Intellectual
                            </label>
                            <label class="disability-option">
                                <input type="radio" name="disabilityType" value="multiple" 
                                       <?php echo (isset($_POST['disabilityType']) && $_POST['disabilityType'] == 'multiple') ? 'checked' : ''; ?>>
                                Multiple Disabilities
                            </label>
                            <label class="disability-option">
                                <input type="radio" name="disabilityType" value="other" 
                                       <?php echo (isset($_POST['disabilityType']) && $_POST['disabilityType'] == 'other') ? 'checked' : ''; ?>>
                                Other
                            </label>
                        </div>
                    </div>
                    
                    <div class="section-title">
                        <i class="fas fa-calendar-alt"></i> Appointment Details
                    </div>
                    
                    <div class="form-group">
                        <label for="purpose" class="required">Purpose of Visit</label>
                        <select id="purpose" name="purpose" required>
                            <option value="">Select Purpose</option>
                            <?php foreach ($appointment_purposes as $key => $description): ?>
                                <option value="<?php echo $key; ?>" 
                                    <?php echo (isset($_POST['purpose']) && $_POST['purpose'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo $description; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="appointmentDate" class="required">Preferred Date</label>
                                <input type="date" id="appointmentDate" name="appointmentDate" 
                                       value="<?php echo isset($_POST['appointmentDate']) ? htmlspecialchars($_POST['appointmentDate']) : ''; ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="appointmentTime" class="required">Preferred Time</label>
                                <select id="appointmentTime" name="appointmentTime" required>
                                    <option value="">Select Time</option>
                                    <option value="08:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '08:00') ? 'selected' : ''; ?>>08:00 AM</option>
                                    <option value="09:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '09:00') ? 'selected' : ''; ?>>09:00 AM</option>
                                    <option value="10:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '10:00') ? 'selected' : ''; ?>>10:00 AM</option>
                                    <option value="11:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '11:00') ? 'selected' : ''; ?>>11:00 AM</option>
                                    <option value="12:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '12:00') ? 'selected' : ''; ?>>12:00 PM</option>
                                    <option value="13:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '13:00') ? 'selected' : ''; ?>>01:00 PM</option>
                                    <option value="14:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '14:00') ? 'selected' : ''; ?>>02:00 PM</option>
                                    <option value="15:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '15:00') ? 'selected' : ''; ?>>03:00 PM</option>
                                    <option value="16:00" <?php echo (isset($_POST['appointmentTime']) && $_POST['appointmentTime'] == '16:00') ? 'selected' : ''; ?>>04:00 PM</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="urgency">Urgency Level</label>
                                <select id="urgency" name="urgency">
                                    <option value="low" <?php echo (isset($_POST['urgency']) && $_POST['urgency'] == 'low') ? 'selected' : ''; ?>>Low - Can wait 1-2 weeks</option>
                                    <option value="medium" <?php echo (isset($_POST['urgency']) && $_POST['urgency'] == 'medium' || !isset($_POST['urgency'])) ? 'selected' : ''; ?>>Medium - Within 1 week</option>
                                    <option value="high" <?php echo (isset($_POST['urgency']) && $_POST['urgency'] == 'high') ? 'selected' : ''; ?>>High - Need immediate assistance</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="preferred_contact">Preferred Contact Method</label>
                                <select id="preferred_contact" name="preferred_contact">
                                    <option value="email" <?php echo (isset($_POST['preferred_contact']) && $_POST['preferred_contact'] == 'email' || !isset($_POST['preferred_contact'])) ? 'selected' : ''; ?>>Email</option>
                                    <option value="phone" <?php echo (isset($_POST['preferred_contact']) && $_POST['preferred_contact'] == 'phone') ? 'selected' : ''; ?>>Phone Call</option>
                                    <option value="sms" <?php echo (isset($_POST['preferred_contact']) && $_POST['preferred_contact'] == 'sms') ? 'selected' : ''; ?>>SMS</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Additional Notes or Special Requirements</label>
                        <textarea id="message" name="message" rows="4" placeholder="Please let us know about any specific accommodations you might need..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        <div class="help-text">E.g., need sign language interpreter, wheelchair accessibility requirements, etc.</div>
                    </div>
                    
                    <div class="accessibility-options">
                        <h3><i class="fas fa-universal-access"></i> Accessibility Features Available</h3>
                        <div class="option-row">
                            <div class="option-item">
                                <i class="fas fa-wheelchair" style="color: #1a73e8;"></i>
                                <span>Wheelchair Access</span>
                            </div>
                            <div class="option-item">
                                <i class="fas fa-sign-language" style="color: #1a73e8;"></i>
                                <span>Sign Language Interpreters</span>
                            </div>
                            <div class="option-item">
                                <i class="fas fa-blind" style="color: #1a73e8;"></i>
                                <span>Braille Materials</span>
                            </div>
                            <div class="option-item">
                                <i class="fas fa-assistive-listening-systems" style="color: #1a73e8;"></i>
                                <span>Assistive Listening</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="btn-container">
                        <button type="button" class="btn-back" onclick="window.history.back()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-calendar-check"></i> Book Appointment
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // ALL YOUR EXISTING JAVASCRIPT REMAINS EXACTLY THE SAME
        // Tanzanian location data
        const tanzaniaLocations = <?php echo json_encode($tanzania_locations); ?>;
        
        // DOM elements
        const regionSelect = document.getElementById('region');
        const districtSelect = document.getElementById('district');
        const wardSelect = document.getElementById('ward');
        
        // Initialize location dropdowns
        function initializeLocationDropdowns() {
            // Region change event
            regionSelect.addEventListener('change', function() {
                const region = this.value;
                
                // Clear and disable dependent dropdowns
                districtSelect.innerHTML = '<option value="">Select District</option>';
                wardSelect.innerHTML = '<option value="">Select Ward</option>';
                
                if (region && tanzaniaLocations[region]) {
                    // Enable and populate district dropdown
                    const districts = tanzaniaLocations[region].districts;
                    
                    for (const districtName in districts) {
                        const option = document.createElement('option');
                        option.value = districtName;
                        option.textContent = districtName;
                        districtSelect.appendChild(option);
                    }
                    
                    // Restore previously selected district if applicable
                    <?php if (isset($_POST['district']) && isset($_POST['region'])): ?>
                        if (region === '<?php echo $_POST["region"]; ?>') {
                            districtSelect.value = '<?php echo $_POST["district"]; ?>';
                            triggerDistrictChange();
                        }
                    <?php endif; ?>
                }
            });
            
            // District change event
            districtSelect.addEventListener('change', function() {
                const region = regionSelect.value;
                const district = this.value;
                
                // Clear ward dropdown
                wardSelect.innerHTML = '<option value="">Select Ward</option>';
                
                if (region && district && tanzaniaLocations[region] && tanzaniaLocations[region].districts[district]) {
                    // Enable and populate ward dropdown
                    const wards = tanzaniaLocations[region].districts[district].wards;
                    
                    wards.forEach(ward => {
                        const option = document.createElement('option');
                        option.value = ward;
                        option.textContent = ward;
                        wardSelect.appendChild(option);
                    });
                    
                    // Restore previously selected ward if applicable
                    <?php if (isset($_POST['ward']) && isset($_POST['district'])): ?>
                        if (district === '<?php echo $_POST["district"]; ?>') {
                            wardSelect.value = '<?php echo $_POST["ward"]; ?>';
                        }
                    <?php endif; ?>
                }
            });
            
            // Trigger initial region change if region is already selected
            <?php if (isset($_POST['region'])): ?>
                regionSelect.value = '<?php echo $_POST["region"]; ?>';
                regionSelect.dispatchEvent(new Event('change'));
            <?php endif; ?>
        }
        
        function triggerDistrictChange() {
            districtSelect.dispatchEvent(new Event('change'));
        }
        
        // Form validation
        function validateForm() {
            const form = document.getElementById('appointmentForm');
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            // Clear previous error highlights
            form.querySelectorAll('.error-highlight').forEach(el => {
                el.classList.remove('error-highlight');
            });
            
            // Check required fields
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error-highlight');
                    isValid = false;
                }
            });
            
            // Validate email format
            const emailField = document.getElementById('email');
            if (emailField.value && !isValidEmail(emailField.value)) {
                emailField.classList.add('error-highlight');
                isValid = false;
            }
            
            // Validate date is not in the past
            const dateField = document.getElementById('appointmentDate');
            if (dateField.value) {
                const selectedDate = new Date(dateField.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    dateField.classList.add('error-highlight');
                    isValid = false;
                    alert('Appointment date cannot be in the past.');
                }
            }
            
            return isValid;
        }
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Disability option styling
        document.querySelectorAll('.disability-option input').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.disability-option').forEach(option => {
                    option.classList.remove('selected');
                });
                if (this.checked) {
                    this.closest('.disability-option').classList.add('selected');
                }
            });
            
            // Initialize selected state
            if (radio.checked) {
                radio.closest('.disability-option').classList.add('selected');
            }
        });
        
        // Form submission handler
        document.getElementById('appointmentForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
                return;
            }
        });
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeLocationDropdowns();
            
            <?php if ($success_message): ?>
                // If we have a success message, scroll to top to show it
                window.scrollTo(0, 0);
            <?php endif; ?>
        });
    </script>
</body>
</html>