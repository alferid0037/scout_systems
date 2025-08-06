<?php
session_start();
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚽ Ethio Online Scouting System - Learn More</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200..1000&family=Rubik:wght@300..900&family=Bebas+Neue&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Enhanced Global Styles */
        body {
            font-family: 'Nunito', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* Header & Navigation */
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 70px;
            padding: 0;
        }

        .header.scrolled {
            background: rgba(30, 60, 114, 0.95);
            backdrop-filter: blur(10px);
        }

        .container-fluid {
            padding: 0 15px;
            height: 100%;
        }

        .logo img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border: 2px solid #f8d458;
            transition: all 0.3s ease;
        }

        .logo:hover img {
            transform: rotate(15deg);
        }

        .navbar {
            padding: 0;
        }

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
            bottom: 0;
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

        .btn-getstarted {
            background: #f8d458;
            color: #1e3c72;
            font-weight: 700;
            padding: 8px 20px;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(248, 212, 88, 0.3);
            font-size: 0.9rem;
            margin-left: 15px;
            text-decoration: none;
        }

        .btn-getstarted:hover {
            background: #f5c926;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(248, 212, 88, 0.4);
            color: #1e3c72;
        }

        .mobile-nav-toggle {
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: none;
            line-height: 0;
            padding: 10px;
        }

        /* Mobile Navigation */
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
            
            .btn-getstarted {
                margin: 15px 0 0 0;
                width: calc(100% - 40px);
                text-align: center;
                display: block;
                margin-left: 20px;
            }
        }

        /* Hero Section */
        #hero {
            background: linear-gradient(rgba(30, 60, 114, 0.8), rgba(30, 60, 114, 0.8)), 
                        url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            min-height: 600px;
            display: flex;
            align-items: center;
            text-align: center;
            color: white;
        }

        .hero-content h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 4.5rem;
            letter-spacing: 3px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.3);
            animation: fadeInDown 1s ease;
        }

        .hero-content p {
            font-size: 1.5rem;
            max-width: 700px;
            margin: 0 auto 30px;
            animation: fadeInUp 1s ease;
        }

        /* Section Headers */
        .section-header {
            text-align: center;
            padding: 60px 0 40px;
        }

        .section-header h2, 
        .section-header h3 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
        }

        .section-header h2 {
            font-size: 2.5rem;
            color: #1e3c72;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .section-header h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: #f8d458;
        }

        .section-header h3 {
            font-size: 1.5rem;
            color: #555;
            margin-bottom: 20px;
        }

        /* Age Categories */
        .services {
            background-color: #fff;
            padding: 60px 0;
        }

        .service-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            height: 100%;
        }

        .service-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .service-item .img {
            overflow: hidden;
            height: 200px;
        }

        .service-item .img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .service-item:hover .img img {
            transform: scale(1.1);
        }

        .service-item .details {
            padding: 25px;
        }

        .category-badge {
            display: inline-block;
            background: #1e3c72;
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .service-item h3 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .service-item p {
            color: #666;
        }

        /* Professional Login Section */
        .professional-login {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 80px 0;
        }

        .login-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .login-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-top: 5px solid transparent;
        }

        .login-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .login-card.admin {
            border-top-color: #dc3545;
        }

        .login-card.scout {
            border-top-color: #8e44ad;
        }

        .login-card.coach {
            border-top-color: #27ae60;
        }

        .login-card.medical {
            border-top-color: #e74c3c;
        }

        .login-card.club {
            border-top-color: #3498db;
        }

        .login-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }

        .login-card.admin .login-icon {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .login-card.scout .login-icon {
            background: linear-gradient(135deg, #8e44ad, #7d3c98);
        }

        .login-card.coach .login-icon {
            background: linear-gradient(135deg, #27ae60, #229954);
        }

        .login-card.medical .login-icon {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .login-card.club .login-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .login-card h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .login-card p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .login-btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .login-btn:hover {
            background: #2a5298;
            transform: translateY(-2px);
            color: white;
        }

        /* Footer */
        footer {
            background: #1a1a1a;
            color: white;
            padding: 60px 0 20px;
        }

        footer h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #f8d458;
        }

        footer p {
            color: #bbb;
            margin-bottom: 10px;
        }

        .social-links {
            margin-top: 20px;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            margin-right: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .social-links a:hover {
            background: #f8d458;
            color: #1e3c72;
            transform: translateY(-3px);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #999;
            font-size: 0.9rem;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .hero-content h1 {
                font-size: 3.5rem;
            }
        }

        @media (max-width: 992px) {
            .navbar {
                background: rgba(30, 60, 114, 0.95);
                backdrop-filter: blur(10px);
            }
            
            .hero-content h1 {
                font-size: 3rem;
            }
            
            .service-item {
                margin-bottom: 20px;
            }
        }

        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-content p {
                font-size: 1.2rem;
            }
            
            .section-header h2 {
                font-size: 2rem;
            }
            
            .section-header h3 {
                font-size: 1.2rem;
            }
            
            .login-cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 576px) {
            .hero-content h1 {
                font-size: 2rem;
                letter-spacing: 1px;
            }
            
            .hero-content p {
                font-size: 1rem;
            }
            
            .section-header {
                padding: 40px 0 20px;
            }
            
            .section-header h2 {
                font-size: 1.8rem;
            }
            
            .service-item .details {
                padding: 20px;
            }
            
            .login-card {
                padding: 25px;
            }
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
                <li><a class="nav-link scrollto" href="home.php">Main site</a></li>
            
            </ul>
        </nav>
    </div>
</header>
    
<!-- Hero Section -->
<section id="hero" class="registeration_heads">
    <div class="container">
        <div class="hero-content">
            <h1>Ethio Online Scouting System</h1>
            <p>Discovering the next generation of football talent through comprehensive training and professional development programs</p>
        </div>
    </div>
</section>

<!-- Age Categories -->
<section id="age" class="services">
    <div class="section-header">
        <h3>AGE CATEGORIES</h3>
    </div>
    <div class="container">
        <div class="row gy-5">
            <div class="col-xl-3 col-md-6">
                <div class="service-item">
                    <div class="img">
                        <img src="/placeholder.svg?height=200&width=300&text=U8+Training" class="img-fluid" alt="U8 Training">
                    </div>
                    <div class="details">
                        <div class="icon">
                            <i class="category-badge">U8</i>
                        </div>
                        <h3>6 to 8 Years</h3>
                        <p>Fundamental skills development, basic coordination, introduction to team play, and fostering love for the game through fun activities and structured learning.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="service-item">
                    <div class="img">
                        <img src="/placeholder.svg?height=200&width=300&text=U11+Training" class="img-fluid" alt="U11 Training">
                    </div>
                    <div class="details">
                        <div class="icon">
                            <i class="category-badge">U11</i>
                        </div>
                        <h3>9 to 11 Years</h3>
                        <p>Skill refinement, introduction to positions, small-sided games, and developing game understanding with emphasis on technical development.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="service-item">
                    <div class="img">
                        <img src="/placeholder.svg?height=200&width=300&text=U14+Training" class="img-fluid" alt="U14 Training">
                    </div>
                    <div class="details">
                        <div class="icon">
                            <i class="category-badge">U14</i>
                        </div>
                        <h3>12 to 14 Years</h3>
                        <p>Position-specific training, tactical development, physical conditioning, and competitive match play with advanced skill building.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="service-item">
                    <div class="img">
                        <img src="/placeholder.svg?height=200&width=300&text=U18+Training" class="img-fluid" alt="U18 Training">
                    </div>
                    <div class="details">
                        <div class="icon">
                            <i class="category-badge">U18</i>
                        </div>
                        <h3>15 to 18 Years</h3>
                        <p>Advanced tactical training, high-intensity conditioning, competitive league play, and pathway to professional development opportunities.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>



<!-- Footer -->
<footer>
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <h3>Address</h3>
                <p>Addis Ababa Stadium - ADDIS ABEBA</p>
                <p>2Q89+5G7, Addis Ababa</p>
            </div>
            <div class="col-lg-3 col-md-6">
                <h3>Contact</h3>
                <p>Phone: +251-11/515 6205</p>
                <p>Email: info@ethioscout.org</p>
            </div>
            <div class="col-lg-3 col-md-6">
                <h3>Social Media</h3>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-telegram"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <h3>Quick Links</h3>
                <p><a href="home.php">Home</a></p>
                <p><a href="signin.php">Player Login</a></p>
            </div>
        </div>
        <p class="copyright">&copy; 2024 Ethiopian Football Federation. All rights reserved.</p>
    </div>
</footer>

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

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
</script>
</body>
</html>
