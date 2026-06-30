<?php
// employment_help.php
session_start();
require_once 'database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Employment Help Class (MySQLi version)
class EmploymentHelp {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getCategories() {
        $query = "SELECT * FROM employment_categories WHERE is_active = 1 ORDER BY category_name";
        $result = $this->db->query($query);
        $categories = [];
        while($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        return $categories;
    }
    
    public function getServicesByCategory($category_id) {
        $query = "SELECT es.*, ec.category_name, ec.icon 
                 FROM employment_services es 
                 JOIN employment_categories ec ON es.category_id = ec.id 
                 WHERE es.category_id = ? AND es.is_active = 1 
                 ORDER BY es.service_name";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $services = [];
        while($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        return $services;
    }
    
    public function getAllDisabilityServices() {
        $query = "SELECT es.*, ec.category_name, ec.icon 
                 FROM employment_services es 
                 JOIN employment_categories ec ON es.category_id = ec.id 
                 WHERE es.is_active = 1 AND es.is_for_disability = 1 
                 ORDER BY ec.category_name, es.service_name";
        $result = $this->db->query($query);
        $services = [];
        while($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        return $services;
    }
    
    public function searchServicesByDisability($disability_type) {
        $query = "SELECT es.*, ec.category_name, ec.icon 
                 FROM employment_services es 
                 JOIN employment_categories ec ON es.category_id = ec.id 
                 WHERE (es.disability_type LIKE ? OR es.disability_type = 'All disabilities') 
                 AND es.is_active = 1 
                 ORDER BY ec.category_name";
        $stmt = $this->db->prepare($query);
        $search_term = "%" . $disability_type . "%";
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        $services = [];
        while($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        return $services;
    }
}

// Create instance using your MySQLi connection
$employmentHelp = new EmploymentHelp($conn);

// Get parameters
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$disability_type = isset($_GET['disability_type']) ? $_GET['disability_type'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employment Help - Disability System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 12000px;
            margin: 0 auto;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            color:linear-gradient(135deg, #667eea 0%, #764ba2 100%) ;
            padding: 12px 24px;
            border-radius: 50px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: fixed;
            top: 20px;
            right: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .employment-help-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.2em;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .filter-group {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            font-size: 16px;
            min-width: 200px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .category-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .category-icon {
            font-size: 3em;
            color: #667eea;
            margin-bottom: 15px;
        }

        .services-section {
            margin-top: 40px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .service-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            border-left: 5px solid #667eea;
        }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .service-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 15px;
        }

        .service-title {
            color: #333;
            font-size: 1.3em;
            margin-bottom: 10px;
        }

        .disability-badge {
            background: #e7f3ff;
            color: #0066cc;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-left: auto;
        }

        .service-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .service-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }

        .detail-item {
            margin-bottom: 8px;
            display: flex;
        }

        .detail-label {
            font-weight: bold;
            color: #333;
            min-width: 120px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .no-services {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 1.1em;
        }

        .section-title {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8em;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            display: inline-block;
        }

        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                min-width: auto;
            }
            
            .employment-help-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        

        <div class="employment-help-card">
            <!-- Back Button -->
        <a href="user_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-briefcase"></i> Employment Assistance</h1>
                <p>Find specialized employment support and opportunities tailored for people with disabilities</p>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-group">
                    <div>
                        <label for="disabilityTypeFilter" style="font-weight: bold; margin-bottom: 5px; display: block;">Filter by Disability:</label>
                        <select id="disabilityTypeFilter" class="filter-select" onchange="filterByDisability()">
                            <option value="">All Disability Types</option>
                            <option value="Physical" <?= $disability_type == 'Physical' ? 'selected' : '' ?>>Physical Disabilities</option>
                            <option value="Visual" <?= $disability_type == 'Visual' ? 'selected' : '' ?>>Visual Impairment</option>
                            <option value="Hearing" <?= $disability_type == 'Hearing' ? 'selected' : '' ?>>Hearing Impairment</option>
                            <option value="Learning" <?= $disability_type == 'Learning' ? 'selected' : '' ?>>Learning Disabilities</option>
                            <option value="Mental" <?= $disability_type == 'Mental' ? 'selected' : '' ?>>Mental Health</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Categories -->
            <h2 class="section-title">Service Categories</h2>
            <div class="categories-grid">
                <div class="category-card" onclick="loadCategory(0)">
                    <div class="category-icon">
                        <i class="fas fa-th"></i>
                    </div>
                    <h3>All Services</h3>
                    <p>Browse all available employment support</p>
                </div>
                
                <?php
                $categories = $employmentHelp->getCategories();
                foreach($categories as $category) {
                    echo '
                    <div class="category-card" onclick="loadCategory('.$category['id'].')">
                        <div class="category-icon">
                            <i class="fas '.htmlspecialchars($category['icon']).'"></i>
                        </div>
                        <h3>'.htmlspecialchars($category['category_name']).'</h3>
                        <p>'.htmlspecialchars($category['description']).'</p>
                    </div>';
                }
                ?>
            </div>

            <!-- Services Display -->
            <div class="services-section">
                <h2 class="section-title">
                    <?php
                    if($category_id > 0) {
                        $category_services = $employmentHelp->getServicesByCategory($category_id);
                        if(!empty($category_services)) {
                            echo htmlspecialchars($category_services[0]['category_name']) . ' Services';
                        } else {
                            echo 'Available Services';
                        }
                    } else if(!empty($disability_type)) {
                        echo 'Services for ' . htmlspecialchars($disability_type) . ' Disabilities';
                    } else {
                        echo 'All Employment Services';
                    }
                    ?>
                </h2>
                
                <div class="services-grid" id="servicesContainer">
                    <?php
                    // Display functions
                    function displayServices($employmentHelp, $category_id, $disability_type) {
                        if($category_id > 0) {
                            $services = $employmentHelp->getServicesByCategory($category_id);
                        } else if(!empty($disability_type)) {
                            $services = $employmentHelp->searchServicesByDisability($disability_type);
                        } else {
                            $services = $employmentHelp->getAllDisabilityServices();
                        }
                        
                        if(empty($services)) {
                            echo '<div class="no-services">
                                    <i class="fas fa-search" style="font-size: 3em; margin-bottom: 20px; color: #ccc;"></i>
                                    <h3>No services found</h3>
                                    <p>Try selecting a different category or disability type</p>
                                  </div>';
                            return;
                        }
                        
                        foreach($services as $service) {
                            echo '
                            <div class="service-card">
                                <div class="service-header">
                                    <div>
                                        <h3 class="service-title">'.htmlspecialchars($service['service_name']).'</h3>
                                        <div style="color: #667eea; font-weight: 500;">'.htmlspecialchars($service['category_name']).'</div>
                                    </div>
                                    <span class="disability-badge">'.htmlspecialchars($service['disability_type']).'</span>
                                </div>
                                
                                <p class="service-description">'.htmlspecialchars($service['description']).'</p>
                                
                                <div class="service-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Eligibility:</span>
                                        <span>'.htmlspecialchars($service['eligibility_criteria']).'</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Benefits:</span>
                                        <span>'.htmlspecialchars($service['benefits']).'</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Process:</span>
                                        <span>'.htmlspecialchars($service['application_process']).'</span>
                                    </div>
                                </div>';
                            
                            if(!empty($service['contact_info']) || !empty($service['website_url'])) {
                                echo '<div class="action-buttons">';
                                if(!empty($service['contact_info'])) {
                                    echo '<button class="btn btn-secondary" onclick="contactService(\''.htmlspecialchars($service['contact_info']).'\')">
                                            <i class="fas fa-phone"></i> Contact
                                          </button>';
                                }
                                if(!empty($service['website_url'])) {
                                    echo '<a href="'.htmlspecialchars($service['website_url']).'" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-external-link-alt"></i> Visit Website
                                          </a>';
                                }
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }
                    }
                    
                    displayServices($employmentHelp, $category_id, $disability_type);
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function loadCategory(categoryId) {
        const disabilityType = document.getElementById('disabilityTypeFilter').value;
        let url = 'employment_help.php?category_id=' + categoryId;
        if(disabilityType) {
            url += '&disability_type=' + disabilityType;
        }
        window.location.href = url;
    }

    function filterByDisability() {
        const disabilityType = document.getElementById('disabilityTypeFilter').value;
        const urlParams = new URLSearchParams(window.location.search);
        const categoryId = urlParams.get('category_id') || 0;
        
        let url = 'employment_help.php?disability_type=' + disabilityType;
        if(categoryId > 0) {
            url += '&category_id=' + categoryId;
        }
        window.location.href = url;
    }

    function contactService(contactInfo) {
        alert('Contact Information:\n' + contactInfo + '\n\nWe recommend saving this information for future reference.');
    }

    function applyForService(serviceId, serviceName) {
        if(confirm('Would you like to apply for: ' + serviceName + '?')) {
            // In a real implementation, this would redirect to an application form
            alert('Application process for ' + serviceName + ' would start here.\n\nThis feature can be integrated with your application system.');
        }
    }
    </script>
</body>
</html>