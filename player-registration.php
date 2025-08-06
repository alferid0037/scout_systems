<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('signin.php');
}

$error = '';
$success = '';

// Add this function if not in config.php
if (!function_exists('calculateAge')) {
    function calculateAge($day, $month, $year) {
        $birth_date = new DateTime("$year-$month-$day");
        $today = new DateTime();
        $age = $today->diff($birth_date);
        return $age->y;
    }
}

// Rest of your code...
// Function to get player registration
function getPlayerRegistration($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM player_registrations WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to create notification
function createNotification($user_id, $title, $message, $type = 'info') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
    return $stmt->execute([$user_id, $title, $message, $type]);
}

// Function to validate file upload
function validateFileUpload($file, $allowed_types, $max_size) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    return true;
}

// Check if player already has registration
$registration = getPlayerRegistration($_SESSION['user_id']);
if ($registration) {
    header('Location: dashboard.php');
    exit();
}

if ($_POST) {
    $first_name = trim($_POST['firstName']);
    $last_name = trim($_POST['lastName']);
    $day = $_POST['day'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $gender = $_POST['gender'];
    $phone = trim($_POST['phone']);
    $city = $_POST['city'];
    $weight = $_POST['weight'];
    $passport = trim($_POST['passport']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($day) || empty($month) || empty($year) || 
        empty($gender) || empty($phone) || empty($city) || empty($weight)) {
        $error = 'Please fill in all required fields.';
    } 
    // Validate phone number format
    elseif (!preg_match('/^\+2519\d{8}$/', $phone)) {
        $error = 'Phone number must be in format +2519XXXXXXXX';
    }
    // Validate age (must be between 6 and 18) and born in 2007 or later
    elseif ($year < 2007 || calculateAge($day, $month, $year) < 6 || calculateAge($day, $month, $year) > 18) {
        $error = 'Player must be between 6 and 18 years old and born in 2007 or later.';
    }
    else {
        // Handle file uploads
        $photo_path = '';
        $birth_cert_path = '';
        $education_cert_path = '';
        $passport_photo_path = '';
        
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $upload_success = true;
        $upload_errors = [];
        
        // Upload profile photo (required)
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            if (validateFileUpload($_FILES['photo'], ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024)) { // 2MB
                $photo_name = 'photo_' . $_SESSION['user_id'] . '_' . time() . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo_name)) {
                    $photo_path = $photo_name;
                } else {
                    $upload_errors[] = 'Failed to upload profile photo.';
                    $upload_success = false;
                }
            } else {
                $upload_errors[] = 'Profile photo must be JPG/PNG and under 2MB.';
                $upload_success = false;
            }
        } else {
            $upload_errors[] = 'Profile photo is required.';
            $upload_success = false;
        }
        
        // Upload birth certificate (required)
        if (isset($_FILES['birthCertificate']) && $_FILES['birthCertificate']['error'] === UPLOAD_ERR_OK) {
            if (validateFileUpload($_FILES['birthCertificate'], ['pdf', 'jpg', 'jpeg', 'png'], 5 * 1024 * 1024)) { // 5MB
                $birth_cert_name = 'birth_cert_' . $_SESSION['user_id'] . '_' . time() . '.' . pathinfo($_FILES['birthCertificate']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['birthCertificate']['tmp_name'], $upload_dir . $birth_cert_name)) {
                    $birth_cert_path = $birth_cert_name;
                } else {
                    $upload_errors[] = 'Failed to upload birth certificate.';
                    $upload_success = false;
                }
            } else {
                $upload_errors[] = 'Birth certificate must be PDF/JPG/PNG and under 5MB.';
                $upload_success = false;
            }
        } else {
            $upload_errors[] = 'Birth certificate is required.';
            $upload_success = false;
        }
        
        // Upload education certificate (required)
        if (isset($_FILES['education']) && $_FILES['education']['error'] === UPLOAD_ERR_OK) {
            if (validateFileUpload($_FILES['education'], ['pdf'], 5 * 1024 * 1024)) { // 5MB
                $education_cert_name = 'education_cert_' . $_SESSION['user_id'] . '_' . time() . '.' . pathinfo($_FILES['education']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['education']['tmp_name'], $upload_dir . $education_cert_name)) {
                    $education_cert_path = $education_cert_name;
                } else {
                    $upload_errors[] = 'Failed to upload education certificate.';
                    $upload_success = false;
                }
            } else {
                $upload_errors[] = 'Education certificate must be PDF and under 5MB.';
                $upload_success = false;
            }
        } else {
            $upload_errors[] = 'Education certificate is required.';
            $upload_success = false;
        }
        
        // Upload passport photo (optional)
        if (isset($_FILES['passportPhoto']) && $_FILES['passportPhoto']['error'] === UPLOAD_ERR_OK) {
            if (validateFileUpload($_FILES['passportPhoto'], ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024)) { // 2MB
                $passport_photo_name = 'passport_photo_' . $_SESSION['user_id'] . '_' . time() . '.' . pathinfo($_FILES['passportPhoto']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['passportPhoto']['tmp_name'], $upload_dir . $passport_photo_name)) {
                    $passport_photo_path = $passport_photo_name;
                } else {
                    $upload_errors[] = 'Failed to upload passport photo.';
                }
            } else {
                $upload_errors[] = 'Passport photo must be JPG/PNG and under 2MB.';
            }
        }
        
        if (!$upload_success) {
            $error = implode(' ', $upload_errors);
        } else {
            // Insert registration data
            try {
                $query = "INSERT INTO player_registrations (user_id, first_name, last_name, birth_day, birth_month, birth_year, 
                          gender, phone, city, weight, passport, photo_path, birth_certificate_path, education_certificate_path, 
                          passport_photo_path, registration_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                
                $stmt = $pdo->prepare($query);
                
                if ($stmt->execute([$_SESSION['user_id'], $first_name, $last_name, $day, $month, $year, 
                                   $gender, $phone, $city, $weight, $passport, $photo_path, $birth_cert_path, 
                                   $education_cert_path, $passport_photo_path])) {
                    
                    // Calculate age and determine age group
                    $age = calculateAge($day, $month, $year);
                    $age_group = '';
                    if ($age >= 6 && $age <= 8) $age_group = '6-8';
                    elseif ($age >= 9 && $age <= 11) $age_group = '9-11';
                    elseif ($age >= 12 && $age <= 14) $age_group = '12-14';
                    elseif ($age >= 15 && $age <= 18) $age_group = '15-18';
                    
                    // Automatically assign courses based on age group
                    if (!empty($age_group)) {
                        $query = "SELECT id, title FROM course_modules WHERE age_group = ?";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$age_group]);
                        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Create progress records for all modules in the age group
                        foreach ($modules as $module) {
                            $query = "INSERT INTO player_progress (user_id, module_id, progress_percentage, is_completed, created_at) 
                                      VALUES (?, ?, 0, FALSE, NOW())";
                            $stmt = $pdo->prepare($query);
                            $stmt->execute([$_SESSION['user_id'], $module['id']]);
                        }
                        
                        // Create notification about assigned courses
                        createNotification($_SESSION['user_id'], 'Courses Assigned', 
                                         "You have been automatically assigned " . count($modules) . " training modules for age group {$age_group}. Complete your registration approval to access them.", 'info');
                    }
                    
                    // Create notification for admin
                    $stmt = $pdo->prepare("SELECT id FROM professional_users WHERE role = 'admin' LIMIT 1");
                    $stmt->execute();
                    $admin = $stmt->fetch();
                    if ($admin) {
                        createNotification($admin['id'], 'New Player Registration', 
                                         "New player registration from {$first_name} {$last_name} (Age: {$age}) is pending approval.", 'info');
                    }
                    
                    $success = 'Registration completed successfully! Please wait for admin approval.';
                    
                    // Redirect after 2 seconds
                    header("refresh:2;url=dashboard.php");
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Registration Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
           :root {
            --primary-color: #3562A6;
            --secondary-color: #0E1EB5;
            --accent-color: #078930;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --error-color: #dc3545;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--dark-color);
        }

        .container {
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary-color);
        }

        h2 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1rem;
        }

        label.required::after {
            content: " *";
            color: var(--error-color);
        }

        input, select, textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5ee;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: var(--light-color);
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(53, 98, 166, 0.2);
            background-color: white;
        }

        .dob-fields {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 15px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .row-3 {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
        }

        input[type="file"] {
            padding: 12px;
            background-color: white;
            border: 2px dashed #ddd;
        }

        input[type="file"]:hover {
            border-color: var(--primary-color);
        }

        .file-requirements {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        .upload-notes {
            background: linear-gradient(135deg, rgba(53, 98, 166, 0.1), rgba(14, 30, 181, 0.05));
            padding: 20px;
            border-radius: 12px;
            margin: 25px 0;
            border-left: 4px solid var(--primary-color);
        }

        .upload-notes h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .upload-notes ul {
            padding-left: 20px;
            color: #555;
        }

        .upload-notes li {
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            gap: 20px;
        }

        button, .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        button:hover, .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .back-btn {
            background-color: #6c757d;
            color: white;
        }

        .back-btn:hover {
            background-color: #5a6268;
        }

        .register-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            flex: 1;
        }

        .register-btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        }

        .error-message {
            color: var(--error-color);
            margin-bottom: 20px;
            text-align: center;
            padding: 15px;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .success-message {
            color: var(--success-color);
            margin-bottom: 20px;
            text-align: center;
            padding: 15px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            
            .dob-fields, .row, .row-3 {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }

            h2 {
                font-size: 2rem;
            }
        } </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><i class="fas fa-user-edit"></i> Complete Your Registration</h2>
            <p class="subtitle">Please provide your personal information to complete your player profile</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- Personal Information -->
            <div class="row">
                <div class="form-group">
                    <label for="firstName" class="required">First Name</label>
                    <input type="text" id="firstName" name="firstName" required placeholder="Enter your first name" 
                           value="<?= isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="lastName" class="required">Last Name</label>
                    <input type="text" id="lastName" name="lastName" required placeholder="Enter your last name"
                           value="<?= isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : '' ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="required">Date of Birth</label>
                <div class="dob-fields">
                    <select id="day" name="day" required>
                        <option value="">Day</option>
                        <?php for($i = 1; $i <= 31; $i++): ?>
                            <option value="<?= $i ?>" <?= (isset($_POST['day']) && $_POST['day'] == $i) ? 'selected' : '' ?>>
                                <?= sprintf('%02d', $i) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select id="month" name="month" required>
                        <option value="">Month</option>
                        <?php 
                        $months = ['January', 'February', 'March', 'April', 'May', 'June',
                                  'July', 'August', 'September', 'October', 'November', 'December'];
                        foreach($months as $index => $month): ?>
                            <option value="<?= $index + 1 ?>" <?= (isset($_POST['month']) && $_POST['month'] == ($index + 1)) ? 'selected' : '' ?>>
                                <?= $month ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="year" name="year" required>
                        <option value="">Year</option>
                        <?php for($i = date('Y'); $i >= 2007; $i--): ?>
                            <option value="<?= $i ?>" <?= (isset($_POST['year']) && $_POST['year'] == $i) ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="gender" class="required">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select your gender</option>
                        <option value="male" <?= (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="phone" class="required">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required 
                        placeholder="+251912345678"
                        pattern="^\+2519\d{8}$" title="Phone number must start with +2519 followed by 8 digits"
                        value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                </div>
            </div>

            <div class="row-3">
                <div class="form-group">
                    <label for="country" class="required">Country</label>
                    <select id="country" name="country" required>
                        <option value="Ethiopia">Ethiopia</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="city" class="required">City</label>
                    <select id="city" name="city" required>
                        <option value="">Select city</option>
                        <?php 
                        $cities = ['Addis Ababa', 'Dire Dawa', 'Mekelle', 'Adama', 'Bahir Dar', 'Hawassa', 'Jimma', 'Gondar', 'Dessie', 'Shashemene'];
                        foreach($cities as $city): ?>
                            <option value="<?= $city ?>" <?= (isset($_POST['city']) && $_POST['city'] == $city) ? 'selected' : '' ?>>
                                <?= $city ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="weight" class="required">Weight (kg)</label>
                    <input type="number" id="weight" name="weight" step="0.1" min="30" max="200" required placeholder="Weight"
                           value="<?= isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : '' ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="passport">Passport Number (Optional)</label>
                <input type="text" id="passport" name="passport" placeholder="Enter passport number if available"
                       value="<?= isset($_POST['passport']) ? htmlspecialchars($_POST['passport']) : '' ?>">
            </div>

            <!-- File Uploads -->
            <div class="upload-notes">
                <h4><i class="fas fa-info-circle"></i> Upload Requirements:</h4>
                <ul>
                    <li>Profile photo must be a clear headshot and According to sample photo (JPG/PNG only, max 2MB)</li>
                    <li>Birth certificate can be scanned document (PDF) or clear photo (JPG/PNG, max 5MB)</li>
                    <li>Education certificate must be PDF format only (max 5MB)</li>
                    <li>Passport photo (if provided) must be JPG/PNG (max 2MB)</li>
                    <li>All documents must be clear and readable</li>
                </ul>
            </div>

            <div class="form-group">
                <label for="photo" class="required">Profile Photo</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" required>
                <img src="images/sample photo.jpg" alt="sample photo" style="width: 50%; max-width: 100px; margin-top: 10px;
                ">
                <div class="file-requirements">JPG, PNG only. Max size: 2MB</div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="birthCertificate" class="required">Birth Certificate</label>
                    <input type="file" id="birthCertificate" name="birthCertificate" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="file-requirements">PDF, JPG, PNG. Max size: 5MB</div>
                </div>
                <div class="form-group">
                    <label for="education" class="required">Educational Certificate</label>
                    <input type="file" id="education" name="education" accept=".pdf" required>
                    <div class="file-requirements">PDF only. Max size: 5MB</div>
                </div>
            </div>

            <div class="form-group">
                <label for="passportPhoto">Passport Photo (Optional)</label>
                <input type="file" id="passportPhoto" name="passportPhoto" accept="image/jpeg,image/png">
                <div class="file-requirements">JPG, PNG only. Max size: 2MB</div>
            </div>

            <div class="button-group">
                <a href="dashboard.php" class="btn back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button type="submit" class="register-btn">
                    <i class="fas fa-user-check"></i> Complete Registration
                </button>
            </div>
        </form>
    </div>

    <script>
        // Add client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const phonePattern = /^\+2519\d{8}$/;
            
            if (!phonePattern.test(phone)) {
                e.preventDefault();
                alert('Phone number must be in format +2519XXXXXXXX');
                return false;
            }
            
            // Check file sizes
            const files = ['photo', 'birthCertificate', 'education', 'passportPhoto'];
            const maxSizes = {
                'photo': 2 * 1024 * 1024, // 2MB
                'birthCertificate': 5 * 1024 * 1024, // 5MB
                'education': 5 * 1024 * 1024, // 5MB
                'passportPhoto': 2 * 1024 * 1024 // 2MB
            };
            
            for (let fileId of files) {
                const fileInput = document.getElementById(fileId);
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    if (file.size > maxSizes[fileId]) {
                        e.preventDefault();
                        alert(`${fileId} file size exceeds the maximum allowed size.`);
                        return false;
                    }
                }
            }
        });
    </script>
</body>
</html>