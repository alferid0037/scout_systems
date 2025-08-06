<?php
require_once 'config.php';

// Calculate age function
function calculate_age($day, $month, $year) {
    $birth_date = new DateTime("$year-$month-$day");
    $today = new DateTime();
    return $today->diff($birth_date)->y;
}

// Get featured players for the carousel
$featured_query = "SELECT pr.*, u.email FROM player_registrations pr 
                   JOIN users u ON pr.user_id = u.id 
                   WHERE pr.registration_status = 'approved' 
                   ORDER BY pr.created_at DESC LIMIT 6";
$stmt = $pdo->prepare($featured_query);
$stmt->execute();
$featured_players = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚽ Ethio Online Scouting System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200..1000&family=Rubik:wght@300..900&family=Bebas+Neue&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
  --primary:#3562A6; /* Ethiopian yellow */
  --secondary: #f00808ff; /* Ethiopian red */
  --THERD:#0B0B0B;
  --FOURTH:#6594C0;
  --dark: #0C1E2E;
  --light: #F5F5F5;
  --accent: #078930; /* Ethiopian green */
  --text: #333333;
}


body {
    margin: 0;
    font-family: 'Montserrat', sans-serif;
    background-color: var(--light);
    color: var(--text);
    line-height: 1.6;
    font-size: 16px;
    overflow-x: hidden;
}

/* Header with Ethiopian flag colors */
nav {
    background: linear-gradient(135deg, var(--dark) 0%, #000 100%);
    color: white;
    padding: 15px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 4px solid var(--primary);
}

.logo-container {
    display: flex;
    align-items: center;
}

.logo-container img {
    height: 50px;
    border-radius: 50%;
    margin-right: 15px;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.logo-container::after {
    content: "ETHIO SCOUT";
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.8rem;
    color: var(--primary);
    letter-spacing: 1px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
}

.nav-links a {
    text-decoration: none;
    color: white;
    font-weight: 600;
    font-size: 1rem;
    padding: 8px 12px;
    border-radius: 4px;
    transition: all 0.3s ease;
    position: relative;
    min-height: 48px;
    min-width: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-links a:hover {
    color: var(--primary);
    transform: translateY(-2px);
}

.nav-links a::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: 0;
    left: 0;
    background-color: var(--primary);
    transition: width 0.3s ease;
}

.nav-links a:hover::after {
    width: 100%;
}

/* Mobile Menu Toggle */
.menu-toggle {
    display: none;
    cursor: pointer;
    padding: 10px;
}

.menu-toggle i {
    font-size: 1.5rem;
    color: white;
}

/* Search and settings */
.search-container, .dropdown {
  position: relative;
  display: flex;
  align-items: center;
}

#search-icon, #settings-icon {
  color: white;
  font-size: 1.2rem;
  cursor: pointer;
  transition: all 0.3s ease;
  padding: 8px;
  border-radius: 50%;
}

#search-icon:hover, #settings-icon:hover {
  background-color: rgba(255, 255, 255, 0.1);
  transform: rotate(15deg);
  color: var(--primary);
}

#search-input {
  position: absolute;
  right: 40px;
  width: 0;
  padding: 0;
  border: none;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.9);
  color: var(--dark);
  font-size: 0.9rem;
  transition: all 0.3s ease;
  opacity: 0;
  visibility: hidden;
}

#search-input.active {
  width: 200px;
  padding: 8px 15px;
  opacity: 1;
  visibility: visible;
}

.dark-mode {
  --primary: #FFCC00;
  --secondary: #DA1212;
  --dark: #121212;
  --light: #1E1E1E;
  --accent: #078930;
  --text: #E0E0E0;
  background-color: #121212;
  color: #E0E0E0;
}

.dark-mode nav {
  background: linear-gradient(135deg, #000000 0%, #1E1E1E 100%);
  border-bottom: 4px solid var(--secondary);
}

.dark-mode .item {
  background-color: #2D2D2D;
  color: #E0E0E0;
}

.dark-mode .item .occupation {
  color: #B0B0B0;
}

.dark-mode .video-item {
  background-color: #2D2D2D;
}

.dark-mode footer {
  background-color: #000000;
}

.dark-mode #player {
  background-color: #1E1E1E;
}

.dark-mode .about-text {
  color: rgba(255, 255, 255, 0.8);
}

/* Theme Toggle Button */
.theme-toggle {
  cursor: pointer;
  padding: 8px;
  border-radius: 50%;
  transition: all 0.3s ease;
}

.theme-toggle:hover {
  background-color: rgba(255, 255, 255, 0.1);
  transform: rotate(15deg);
}

#theme-icon {
  font-size: 1.2rem;
  color: var(--primary);
  transition: all 0.3s ease;
}

.dark-mode #theme-icon {
  color: var(--primary);
}

/* Profile Dropdown Styles */
.profile-dropdown {
    position: relative;
    display: flex;
    margin-left: 15px;
}

.profile-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.profile-icon i {
    font-size: 1.5rem;
    color: white;
}

.profile-icon:hover {
    background-color: var(--primary);
    transform: scale(1.1);
}

.profile-content {
    display: none;
    position: re;
    right: 0;
    background-color: white;
    min-width: 220px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    z-index: 1000;
    border-radius: 8px;
    overflow: hidden;
}

.profile-dropdown:hover .profile-content {
    display: block;
}

.profile-header {
    padding: 15px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-pic {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid white;
}

.profile-name {
    font-weight: 600;
    font-size: 0.9rem;
}

.profile-content a {
    color: var(--text);
    padding: 12px 16px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.profile-content a i {
    width: 20px;
    text-align: center;
}

.profile-content a:hover {
    background-color: #f5f5f5;
    color: var(--primary);
    padding-left: 20px;
}

.profile-content a:not(:last-child) {
    border-bottom: 1px solid #eee;
}

/* Dark mode styles for profile dropdown */
.dark-mode .profile-content {
    background-color: #2D2D2D;
    border: 1px solid #444;
}

.dark-mode .profile-content a {
    color: #E0E0E0;
}

.dark-mode .profile-content a:hover {
    background-color: #3D3D3D;
    color: var(--primary);
}

.dark-mode .profile-content a:not(:last-child) {
    border-bottom: 1px solid #444;
}
/* Hero Section */
#container {
    background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover;
    height: 90vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.sliding-header {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 4.5rem;
    letter-spacing: 3px;
    margin-bottom: 20px;
    color: var(--primary);
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    position: relative;
    overflow: hidden;
}

.sliding-header span {
    animation: slideIn 1.5s ease-out forwards;
    opacity: 0;
    transform: translateY(50px);
}

@keyframes slideIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.container p {
    font-size: 1.3rem;
    max-width: 800px;
    margin: 0 auto 40px;
    line-height: 1.6;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
}

.container button, .hero-btn {
    background-color: var(--primary);
    color: var(--light);
    border: none;
    padding: 15px 40px;
    font-size: 1.1rem;
    font-weight: 700;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(218, 18, 18, 0.4);
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    overflow: hidden;
    text-decoration: none;
}

.container button:hover, .hero-btn:hover {
    background-color: var(--secondary);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(218, 18, 18, 0.6);
}

.container button::before, .hero-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: 0.5s;
}

.container button:hover::before, .hero-btn:hover::before {
    left: 100%;
}

/* About Section */
        .about-us {
            display: flex;
            align-items: center;
            padding: 80px 10%;
            background: linear-gradient(to right, var(--dark) 50%, transparent 100%);
            position: relative;
        }

        .about-us::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: url('/placeholder.svg?height=400&width=600') no-repeat center center/cover;
            z-index: -1;
            border-radius: 10px 0 0 10px;
        }

        .text-content {
            flex: 1;
            padding-right: 40px;
            z-index: 1;
        }

        .section-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: white;
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 60px;
            height: 4px;
            background: #FFCC00;
            border-radius: 2px;
        }

        .welcome-message {
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .about-text {
            font-size: 1rem;
            line-height: 1.8;
            margin-bottom: 25px;
            color: rgba(255, 255, 255, 0.9);
        }

        .learn-more-button {
            background: transparent;
            color: white;
            border: 2px solid var(--accent);
            padding: 12px 25px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .learn-more-button:hover {
            background: #FFCC00;
            color: var(--dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(57, 255, 20, 0.4);
        }

        .image-content {
            flex: 1;
            position: relative;
        }

        .about-image {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .about-image:hover {
            transform: scale(1.02);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, transparent 0%, rgba(41, 47, 54, 0.7) 100%);
            border-radius: 10px;
        }


/* Featured Players Section */

/* Featured Players Section */
        .featured-players {
            padding: 80px 5%;
            background-color: #f9f9f9;
            position: relative;
        }

        .featured-players::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 10px;
            background: linear-gradient(90deg, var(--secondary), var(--primary), var(--accent));
        }

        .section-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3rem;
            color: var(--dark);
            text-align: center;
            margin-bottom: 50px;
            letter-spacing: 2px;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background-color: var(--primary);
        }

        .players-carousel {
            display: flex;
            gap: 30px;
            padding: 20px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 30px;
        }

        .player-card {
            scroll-snap-align: start;
            flex: 0 0 280px;
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            border-top: 5px solid var(--primary);
        }

        .player-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .player-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--light);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 0 auto 20px;
            display: block;
        }

        .player-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .player-details {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }

        .player-details b {
            color: var(--secondary);
        }

        .player-skills {
            color: var(--primary);
            font-size: 1.2rem;
            margin-top: 15px;
        }

        .register-cta {
            text-align: center;
            margin-top: 40px;
        }

        .register-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .register-btn:hover {
            background-color: var(--secondary);
            transform: translateY(-3px);
        }


/* Course Section */
#course {
    padding: 80px 5%;
    background: #f7f9fc;
}

#course h1 {
    text-align: center;
    margin-bottom: 20px;
    color: #004080;
    font-family: 'Bebas Neue', sans-serif;
    font-size: 3rem;
}

.tabs {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tab {
    padding: 10px 18px;
    margin: 5px;
    background: #007bff;
    color: white;
    border-radius: 5px;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.3s ease;
}

.tab.active,
.tab:hover {
    background: #0056b3;
}

.age-select {
    display: flex;
    justify-content: center;
    margin-bottom: 30px;
    align-items: center;
}

.age-select select {
    font-size: 16px;
    padding: 8px 12px;
    border-radius: 5px;
    border: 1px solid #ccc;
    min-width: 150px;
}

.content {
    max-width: 700px;
    margin: 0 auto;
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    line-height: 1.5;
    font-size: 16px;
}

.module-title {
    color: #004080;
    margin-bottom: 15px;
    font-size: 22px;
    font-weight: 700;
    text-align: center;
}

.content ul {
    color: #004080;
    margin-left: 20px;
    margin-bottom: 20px;
}

/* Footer */
footer {
    background-color: var(--dark);
    color: white;
    padding: 60px 5% 30px;
    position: relative;
}

footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 10px;
    background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
}

.footer-section h3 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.8rem;
    color: var(--primary);
    margin-bottom: 20px;
    letter-spacing: 1px;
    position: relative;
}

.footer-section h3::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 50px;
    height: 3px;
    background-color: var(--secondary);
}

.footer-section p, .footer-section a {
    color: #ddd;
    margin-bottom: 10px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.footer-section a:hover {
    color: var(--primary);
    padding-left: 5px;
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.social-links a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: rgba(255,255,255,0.1);
    border-radius: 50%;
    color: white;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.social-links a:hover {
    background-color: var(--primary);
    color: var(--dark);
    transform: translateY(-3px);
}

.map-embed iframe {
    width: 100%;
    height: 200px;
    border: none;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.copyright {
    grid-column: 1 / -1;
    text-align: center;
    margin-top: 50px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    color: #aaa;
    font-size: 0.9rem;
}

/* Dark Mode */
.dark-mode {
    --primary: #FFCC00;
    --secondary: #DA1212;
    --dark: #121212;
    --light: #1E1E1E;
    --accent: #078930;
    --text: #E0E0E0;
    background-color: #121212;
    color: #E0E0E0;
}

.dark-mode nav {
    background: linear-gradient(135deg, #000000 0%, #1E1E1E 100%);
    border-bottom: 4px solid var(--secondary);
}

.dark-mode .player-card {
    background-color: #2D2D2D;
    color: #E0E0E0;
}

.dark-mode .player-details {
    color: #B0B0B0;
}

.dark-mode footer {
    background-color: #000000;
}

.dark-mode #course {
    background-color: #1E1E1E;
}

.dark-mode .about-text {
    color: rgba(255, 255, 255, 0.8);
}

.dark-mode .content {
    background-color: #2D2D2D;
    color: #E0E0E0;
}

.dark-mode .module-title,
.dark-mode .content ul {
    color: #FFCC00;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .sliding-header {
        font-size: 3.5rem;
    }
    
    .container p {
        font-size: 1.1rem;
        max-width: 700px;
    }
}

@media (max-width: 768px) {
    nav {
        flex-direction: row;
        flex-wrap: wrap;
        padding: 10px 5%;
    }
    
    .logo-container {
        order: 1;
        flex: 1;
    }
    
    .menu-toggle {
        display: block;
        order: 2;
    }
    
    .nav-links {
        display: none;
        order: 3;
        width: 100%;
        flex-direction: column;
        gap: 10px;
        padding: 15px 0;
    }
    
    .nav-links.active {
        display: flex;
    }
    
    .nav-links a {
        width: 100%;
        text-align: center;
        padding: 12px;
        font-size: 1.1rem;
    }
    
    .sliding-header {
        font-size: 2.5rem;
    }
    
    .container p {
        font-size: 1rem;
        padding: 0 20px;
    }
    
    .about-us {
        flex-direction: column;
    }
    
    .about-us::before {
        width: 100%;
        opacity: 0.3;
    }
    
    .text-content {
        padding-right: 0;
        margin-bottom: 30px;
    }
    
    #course h1 {
        font-size: 2.2rem;
    }
    
    .player-card {
        flex: 0 0 240px;
    }
    
    .tabs {
        flex-direction: column;
        align-items: center;
    }
    
    .tab {
        width: 90%;
        text-align: center;
    }
    
    .content {
        padding: 15px;
        font-size: 14px;
    }
}

@media (max-width: 480px) {
    .sliding-header {
        font-size: 2rem;
    }
    
    .logo-container::after {
        font-size: 1.5rem;
    }
    
    .container button, .hero-btn {
        padding: 12px 30px;
        font-size: 1rem;
    }
    
    .footer-container {
        grid-template-columns: 1fr;
    }
    
    .footer-section {
        text-align: center;
    }
    
    .footer-section h3::after {
        left: 50%;
        transform: translateX(-50%);
    }
    
    .social-links {
        justify-content: center;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .welcome-message {
        font-size: 1.2rem;
    }
}
    </style>
</head>

<body>
    <!-- Header -->
    <nav>
        <div class="logo-container">
            <img src="images/Football Award Vector.jpg" alt="Ethio Scout Logo">
        </div>
        
        
        <div class="menu-toggle">
            <i class="fas fa-bars"></i>
        </div>
        
        <div class="nav-links">
            <a href="#container"><i class="fas fa-home"></i> Home</a>
<a href="#about-us"><i class="fas fa-info-circle"></i> About</a>
            <a href="#players"><i class="fas fa-users"></i> Players</a>
<a href="#course"><i class="fas fa-book"></i> Online Course List</a>
            <a href="#contact"><i class="fas fa-envelope"></i> Contact</a>
            
            <a href="signin.php" class="hero-btn" style="padding: 8px 16px; font-size: 0.9rem;">
                LOGIN
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <div id="container">
        <div class="container">
            <div class="sliding-header">
                <span>ETHIO ONLINE SCOUTING SYSTEM</span>
            </div>
            <p>"Empower youth through Ethiopia's Online Scouting System! Build skills, foster leadership, and unite for a brighter, impactful future together!"</p>
            <div style="display: flex; justify-content: center;">
                <a href="signup.php" class="hero-btn">REGISTER NOW</a>
            </div>
        </div>
    </div>

    <!-- About Section -->
       <div id="about-us">
        <section class="about-us">
            <div class="text-content">
                <h2 class="section-title" style="color:yellow;">About Us</h2>
                <p class="welcome-message">Welcome to Our Platform</p>
                <p class="about-text">The Ethiopian Football Scouting System represents a comprehensive platform designed to revolutionize player assessment, match analysis, and talent identification processes within the Ethiopian football landscape. This system aims to address the evolving needs of players, scouts, coaches, medical staff, and external football clubs, providing them with powerful tools and insights to drive the development of football talent across the nation.</p>
                <button class="learn-more-button" onclick="window.location.href='learnmore.php'">
                    Know More <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="image-content">
                <img src="images/photo.jpg" alt="Football Scouting" class="about-image">
                <div class="image-overlay"></div>
            </div>
        </section>
    </div>


    <!-- Featured Players Section -->
    <section id="players" class="featured-players">
        <h2 class="section-title">Featured Players</h2>
        
        <div class="players-carousel">
            <?php if (!empty($featured_players)): ?>
                <?php foreach ($featured_players as $player): ?>
                    <div class="player-card">
                        <img src="<?php echo $player['photo_path'] ? 'uploads/' . htmlspecialchars($player['photo_path']) : '/placeholder.svg?height=150&width=150'; ?>" 
                             alt="<?php echo htmlspecialchars($player['first_name']); ?>" 
                             class="player-photo">
                        <h3 class="player-name"><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></h3>
                        <p class="player-details">
                            <b>Age:</b> <?php echo calculate_age($player['birth_day'], $player['birth_month'], $player['birth_year']); ?> years<br>
                            <b>Location:</b> <?php echo htmlspecialchars($player['city']); ?><br>
                            <b>weight:</b> <?php echo htmlspecialchars($player['weight']); ?>

                        </p>
                        
                    </div>
                <?php endforeach; ?>
                    <?php else: ?>
                        <div class="item">
                            <img src="/placeholder.svg?height=120&width=120" alt="No Players">
                            <div class="name">No Featured Players</div>
                            <div class="occupation">No players available yet. Be the first to register!</div>
                        </div>
                    <?php endif; ?>
        </div>
        <div class="register-cta">
            <p style= "colour:pink"; >Want to showcase your talent?</p>
            <a href="Featured player.php" class="register-btn">More Feature Player</a>
        </div>

    </section>

    <!-- Course Section -->
    <div id="course">
        <h1>Basic Football Learning Course</h1>

        <div class="tabs" id="moduleTabs">
            <div class="tab active" data-module="1">Module 1</div>
            <div class="tab" data-module="2">Module 2</div>
            <div class="tab" data-module="3">Module 3</div>
            <div class="tab" data-module="4">Module 4</div>
            <div class="tab" data-module="5">Module 5</div>
        </div>

        <div class="age-select">
            <label for="ageGroup" style="margin-right: 10px; font-weight: bold;">Select Age Group:</label>
            <select id="ageGroup">
                <option value="6-8">6-8 years</option>
                <option value="9-11">9-11 years</option>
                <option value="12-14">12-14 years</option>
                <option value="15-18">15-18 years</option>
            </select>
        </div>

        <div class="content" id="contentArea">
            <!-- Dynamic content here -->
        </div>
    </div>

    <!-- Footer -->
    <footer id="contact">
        <div class="footer-container">
            <div class="footer-section">
                <h3>Address</h3>
                <p>Addis Ababa Stadium - ADDIS ABEBA</p>
                <p>2Q89+5G7, Addis Ababa</p>
                <p><a href="#">View on Map</a></p>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>Phone: +251-11/515 6205</p>
                <p>Email: info@ethioscout.org</p>
                <p>Website: www.ethioscout.org</p>
            </div>
            <div class="footer-section">
                <h3>Social Media</h3>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-telegram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Map</h3>
                <div class="map-embed">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3940.869244261849!2d38.76331531536616!3d9.012722893547026!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x164b85f1a4d1f8b5%3A0x7fddde27ad21a9aa!2sEthiopian%20Football%20Federation!5e0!3m2!1sen!2set!4v1633081234567!5m2!1sen!2set" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
            <p class="copyright">© 2024 Ethiopian Football Federation. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Course data
        const courseData = {
            "1": {
                title: "Module 1: Introduction to Football & Basic Rules",
                "6-8": [
                    "Understand what football is",
                    "Learn the basic rules (no hands, out of bounds, goals)",
                    "Simple games to practice ball control",
                    "Quiz"
                ],
                "9-11": [
                    "Detailed rules overview",
                    "Importance of teamwork",
                    "Basic positions on the field",
                    "Quiz"
                ],
                "12-14": [
                    "Understanding offside rule",
                    "Fouls and penalties explained",
                    "Referee signals and game flow",
                    "Quiz"
                ],
                "15-18": [
                    "In-depth study of game strategies",
                    "Role of different positions tactically",
                    "Analysis of professional matches for rules",
                    "Quiz"
                ]
            },
            "2": {
                title: "Module 2: Ball Control & Dribbling",
                "6-8": [
                    "Basic dribbling with feet",
                    "Simple ball stops and starts",
                    "Fun obstacle dribbling drills",
                    "Quiz"
                ],
                "9-11": [
                    "Using different parts of the foot",
                    "Changing direction while dribbling",
                    "Shielding the ball from opponents",
                    "Quiz"
                ],
                "12-14": [
                    "Advanced dribbling moves (step-overs, feints)",
                    "Dribbling under pressure",
                    "One-on-one dribbling drills",
                    "Quiz"
                ],
                "15-18": [
                    "Creative dribbling techniques",
                    "Dribbling in tight spaces",
                    "Integrating dribbling into team play",
                    "Quiz"
                ]
            },
            "3": {
                title: "Module 3: Passing & Receiving",
                "6-8": [
                    "Simple short passes with inside foot",
                    "Basic receiving and controlling the ball",
                    "Passing games in pairs",
                    "Quiz"
                ],
                "9-11": [
                    "Passing accuracy drills",
                    "Receiving with different body parts (chest, thigh)",
                    "Introduction to long passes",
                    "Quiz"
                ],
                "12-14": [
                    "Passing under pressure",
                    "One-touch passing drills",
                    "Communication during passing",
                    "Quiz"
                ],
                "15-18": [
                    "Tactical passing (through balls, switches)",
                    "Quick combination plays",
                    "Analyzing passing in real matches",
                    "Quiz"
                ]
            },
            "4": {
                title: "Module 4: Shooting & Scoring",
                "6-8": [
                    "Basic shooting techniques with inside foot",
                    "Target practice (shooting at goals)",
                    "Fun shooting games",
                    "Quiz"
                ],
                "9-11": [
                    "Shooting with laces for power",
                    "Accuracy and placement drills",
                    "Shooting on the move",
                    "Quiz"
                ],
                "12-14": [
                    "Shooting under pressure",
                    "Volley and half-volley shooting",
                    "Penalty kick basics",
                    "Quiz"
                ],
                "15-18": [
                    "Finishing techniques in different scenarios",
                    "Shooting with both feet",
                    "Advanced penalty and free kick techniques",
                    "Quiz"
                ]
            },
            "5": {
                title: "Module 5: Fitness & Team Play",
                "6-8": [
                    "Basic warm-ups and stretches",
                    "Fun fitness games to improve stamina",
                    "Introduction to playing as a team",
                    "Final quiz"
                ],
                "9-11": [
                    "Endurance and speed drills",
                    "Understanding positions in team play",
                    "Basic tactical awareness",
                    "Final quiz"
                ],
                "12-14": [
                    "Position-specific fitness",
                    "Team formations and roles",
                    "Communication on the field",
                    "Final quiz"
                ],
                "15-18": [
                    "Advanced conditioning and recovery",
                    "In-depth tactical formations",
                    "Leadership and decision making",
                    "Final quiz"
                ]
            }
        };

        const tabs = document.querySelectorAll('.tab');
        const ageSelect = document.getElementById('ageGroup');
        const contentArea = document.getElementById('contentArea');

        let selectedModule = "1";
        let selectedAge = "6-8";

        function renderContent() {
            const module = courseData[selectedModule];
            const ageItems = module[selectedAge];
            let html = `<div class="module-title">${module.title}</div><ul>`;
            ageItems.forEach(item => {
                html += `<li>${item}</li>`;
            });
            html += "</ul>";
            contentArea.innerHTML = html;
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                selectedModule = tab.dataset.module;
                renderContent();
            });
        });

        ageSelect.addEventListener('change', () => {
            selectedAge = ageSelect.value;
            renderContent();
        });

        // Mobile menu toggle
        const menuToggle = document.querySelector('.menu-toggle');
        const navLinks = document.querySelector('.nav-links');
        
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            menuToggle.querySelector('i').classList.toggle('fa-bars');
            menuToggle.querySelector('i').classList.toggle('fa-times');
        });

        // Close menu when clicking on a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
                menuToggle.querySelector('i').classList.add('fa-bars');
                menuToggle.querySelector('i').classList.remove('fa-times');
            });
        });

        // Animate sliding header letters
        const slidingHeader = document.querySelector('.sliding-header span');
        if (slidingHeader) {
            const letters = slidingHeader.textContent.split('');
            slidingHeader.textContent = '';
            
            letters.forEach((letter, i) => {
                const span = document.createElement('span');
                span.textContent = letter;
                span.style.animationDelay = `${i * 0.1}s`;
                slidingHeader.appendChild(span);
            });
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('nav a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Initial render
        renderContent();
    </script>
</body>
</html>