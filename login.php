<?php
session_start();
require_once 'config.php';

function get_dashboard_url($role) {
    switch ($role) {
        case 'admin':
            return 'admin-dashboard.php';
        case 'scout':
            return 'scout-dashboard.php';
        case 'coach':
            return 'coach-dashboard.php';
        case 'medical':
            return 'medical-dashboard.php';
        case 'club':
            return 'club-dashboard.php';
        default:
            return 'home.php';
    }
}



// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $license = $_FILES['license'] ?? null;
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        try {
            // Check if user exists with this email and role
            $stmt = $pdo->prepare("SELECT * FROM professional_users WHERE email = ? AND role = ?");
            $stmt->execute([$email, $role]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = "Your account is not active. Please contact admin.";
                } else {
                    
                    
                    
                    if (!isset($error)) {
                        // Update last login
                        $stmt = $pdo->prepare("UPDATE professional_users SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        // Set session variables
                        $_SESSION['professional_logged_in'] = true;
                        $_SESSION['professional_id'] = $user['id'];
                        $_SESSION['professional_role'] = $user['role'];
                        $_SESSION['professional_email'] = $user['email'];
                        $_SESSION['professional_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        
                        // Redirect to appropriate dashboard
                        header("Location: " . get_dashboard_url($user['role']));
                        exit;
                    }
                }
            } else {
                $error = "Invalid credentials for selected role";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "Database error occurred. Please try again.";
        }
    }
}

// Get role from query parameter if not submitting form
$role = $_GET['role'] ?? '';
$valid_roles = ['admin', 'scout', 'coach', 'medical', 'club'];
if (!in_array($role, $valid_roles)) {
    $role = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚽ Ethio Online Scouting System - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200..1000&family=Rubik:wght@300..900&family=Bebas+Neue&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        /* Header Styles */
.header {
  background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
  box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  height: 70px;
  padding: 0;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 999;
}

.header.scrolled {
  background: rgba(30, 60, 114, 0.95);
  backdrop-filter: blur(10px);
}

/* Logo Styles */
.logo {
  text-decoration: none;
  transition: transform 0.3s ease;
}

.logo:hover {
  transform: scale(1.02);
}

.logo img {
  transition: transform 0.3s ease, border-color 0.3s ease;
  border: 2px solid rgba(255, 255, 255, 0.2);
}

.logo:hover img {
  transform: rotate(5deg);
  border-color: #f8d458;
}

/* Navigation Styles */
.navbar ul {
  margin: 0;
  padding: 0;
  display: flex;
  list-style: none;
  align-items: center;
}

.navbar li {
  position: relative;
}

.navbar a {
  position: relative;
  padding: 10px 15px;
  font-family: 'Rubik', sans-serif;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-size: 0.9rem;
  color: white;
  font-weight: 600;
  transition: all 0.3s ease;
  text-decoration: none;
}

.navbar a::after {
  content: '';
  position: absolute;
  bottom: -5px;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 3px;
  background: #f8d458;
  transition: width 0.3s ease;
}

.navbar a:hover,
.navbar .active {
  color: #f8d458;
}

.navbar a:hover::after,
.navbar .active::after {
  width: 70%;
}

/* Mobile Menu Toggle */
.mobile-nav-toggle {
  color: white;
  font-size: 1.5rem;
  cursor: pointer;
  display: none;
  line-height: 0;
  padding: 10px;
  transition: all 0.3s ease;
}

.mobile-nav-toggle:hover {
  color: #f8d458;
}

/* Responsive Styles */
@media (max-width: 992px) {
  .mobile-nav-toggle {
    display: inline-block;
  }
  
  .navbar {
    position: fixed;
    top: 70px;
    right: -100%;
    width: 80%;
    max-width: 300px;
    height: calc(100vh - 70px);
    background: rgba(30, 60, 114, 0.95);
    backdrop-filter: blur(10px);
    flex-direction: column;
    padding: 20px 0;
    transition: 0.3s;
    z-index: 999;
    overflow-y: auto;
  }

  .navbar.active {
    right: 0;
  }

  .navbar ul {
    flex-direction: column;
    align-items: flex-start;
    padding: 0 20px;
    width: 100%;
  }

  .navbar li {
    width: 100%;
    margin: 5px 0;
  }

  .navbar a {
    padding: 12px 0;
    font-size: 1rem;
    width: 100%;
  }
  
  .navbar a::after {
    bottom: 0;
    left: 0;
    transform: none;
    width: 0;
    height: 2px;
  }
  
  .navbar a:hover::after,
  .navbar .active::after {
    width: 100%;
  }
}

/* Container Styles */
.container-fluid {
  padding: 0 15px;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 1200px;
  margin: 0 auto;
}

/* Additional Effects */
.header.scrolled .logo img {
  border-color: rgba(248, 212, 88, 0.5);
}

.header.scrolled .navbar a {
  color: rgba(255, 255, 255, 0.9);
}

.header.scrolled .navbar a:hover {
  color: #f8d458;
}
        .login-hero {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 100px 0 60px;
            text-align: center;
            margin-top: 70px;
        }
        
        .login-hero h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3rem;
            letter-spacing: 2px;
        }
        
        .login-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 0 15px;
        }
        
        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .login-header h2 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin: 0;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .role-indicator {
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 700;
            color: white;
        }
        
        .role-admin { background: #dc3545; }
        .role-scout { background: #8e44ad; }
        .role-coach { background: #27ae60; }
        .role-medical { background: #e74c3c; }
        .role-club { background: #3498db; }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            width: 100%;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #1e3c72;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: #2a5298;
            text-decoration: underline;
        }
        
        .license-upload {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    
<!-- Header -->
<header id="header" class="header fixed-top">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <a href="index.php" class="logo d-flex align-items-center scrollto me-auto me-lg-0">
            <img style="border-radius: 100%; width: 50px; height: 50px;" src="images/Football Award Vector.jpg" alt="Football Logo">
            <div style="color: white; font-weight: bold; margin-left: 10px; line-height: 1.2; font-size: 14px;">
                Ethiopian Online Football Scouting<br>Virtual Academy
            </div>
        </a>
        
        <!-- Mobile Menu Toggle -->
        <i class="mobile-nav-toggle fas fa-bars"></i>
        
        <!-- Navigation Bar -->
        <nav id="navbar" class="navbar">
            <ul>
                <li><a class="nav-link scrollto" href="index.php">back</a></li>
            </ul>
        </nav>
    </div>
</header>
    
<!-- Hero Section -->
<section class="login-hero">
    <div class="container">
    </div>
</section>

<!-- Login Form -->
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <h2>Access Your Account</h2>
        </div>
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" enctype="multipart/form-data">
                <!-- Hidden role field -->
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                
                <!-- Role Indicator -->
                <?php if (!empty($role)): ?>
                    <div class="role-indicator role-<?php echo htmlspecialchars($role); ?>">
                        <?php 
                            $role_names = [
                                'admin' => 'Administrator',
                                'scout' => 'Scout',
                                'coach' => 'Coach',
                                'medical' => 'Medical Staff',
                                'club' => 'Club Representative'
                            ];
                            echo $role_names[$role] ?? ucfirst($role);
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- Email Field -->
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required 
                           placeholder="Enter your professional email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <!-- Password Field -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <!-- License Upload (for Club role only) -->
             
                
                <!-- Submit Button -->
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                
                <a href="index.php#professional-login" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to role selection
                </a>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mobile navigation toggle
    const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
    const navbar = document.querySelector('.navbar');

    if (mobileNavToggle) {
        mobileNavToggle.addEventListener('click', () => {
            navbar.classList.toggle('active');
            mobileNavToggle.classList.toggle('fa-bars');
            mobileNavToggle.classList.toggle('fa-times');
        });
    }

    // Close mobile menu when clicking on a nav link
    document.querySelectorAll('.navbar a').forEach(navLink => {
        navLink.addEventListener('click', () => {
            if (navbar.classList.contains('active')) {
                navbar.classList.remove('active');
                mobileNavToggle.classList.add('fa-bars');
                mobileNavToggle.classList.remove('fa-times');
            }
        });
    });

    // Add header scroll class
    window.addEventListener('scroll', function() {
        if (window.scrollY > 100) {
            document.getElementById('header').classList.add('scrolled');
        } else {
            document.getElementById('header').classList.remove('scrolled');
        }
    });
</script>
</body>
</html>