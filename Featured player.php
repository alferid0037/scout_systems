<?php
require_once 'config.php';

// Database connection class
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Calculate age function
function calculate_age($day, $month, $year) {
    $birth_date = new DateTime("$year-$month-$day");
    $today = new DateTime();
    return $today->diff($birth_date)->y;
}

// Sanitize input function
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

$database = new Database();
$db = $database->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$position_filter = isset($_GET['position']) ? sanitize_input($_GET['position']) : '';
$age_filter = isset($_GET['age']) ? sanitize_input($_GET['age']) : '';
$country_filter = isset($_GET['country']) ? sanitize_input($_GET['country']) : '';

// Build query
$where_conditions = ["pr.registration_status = 'approved'"];
$params = [];

if ($search) {
    $where_conditions[] = "(pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.city LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($country_filter) {
    $where_conditions[] = "pr.country = ?";
    $params[] = $country_filter;
}

if ($position_filter) {
    $where_conditions[] = "pr.position = ?";
    $params[] = $position_filter;
}

// Age filter logic
if ($age_filter) {
    switch ($age_filter) {
        case '5-10':
            $where_conditions[] = "(YEAR(CURDATE()) - pr.birth_year) BETWEEN 5 AND 10";
            break;
        case '11-15':
            $where_conditions[] = "(YEAR(CURDATE()) - pr.birth_year) BETWEEN 11 AND 15";
            break;
        case '16-18':
            $where_conditions[] = "(YEAR(CURDATE()) - pr.birth_year) BETWEEN 16 AND 18";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM player_registrations pr 
                JOIN users u ON pr.user_id = u.id 
                WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$total_players = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_players / $per_page);

// Get players
$players_query = "SELECT pr.*, u.email FROM player_registrations pr 
                  JOIN users u ON pr.user_id = u.id 
                  WHERE $where_clause 
                  ORDER BY pr.created_at DESC 
                  LIMIT $per_page OFFSET $offset";
$players_stmt = $db->prepare($players_query);
$players_stmt->execute($params);
$players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique countries for filter
$countries_query = "SELECT DISTINCT country FROM player_registrations WHERE registration_status = 'approved' AND country IS NOT NULL AND country != '' ORDER BY country";
$countries_stmt = $db->prepare($countries_query);
$countries = $countries_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Featured Players - Ethiopian Football Scouting</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1E3A8A;
            --secondary: #DC2626;
            --accent: #F59E0B;
            --success: #10B981;
            --light: #F8FAFC;
            --dark: #1E293B;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            color: var(--dark);
            opacity: 0.7;
        }
        
        .filters-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .filter-control {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .filter-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .player-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .player-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        }
        
        .player-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .player-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--light);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 0 auto 20px;
            display: block;
        }
        
        .player-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .player-details {
            color: var(--dark);
            opacity: 0.7;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .player-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--dark);
            opacity: 0.6;
        }
        
        .player-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .pagination a, .pagination span {
            padding: 10px 15px;
            background: white;
            border-radius: 10px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .pagination .current {
            background: var(--primary);
            color: white;
        }
        
        .no-players {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .no-players i {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            color: var(--primary);
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .stats-summary {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stats-summary h3 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .players-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .player-actions {
                flex-direction: column;
            }
            
            .back-btn {
                position: relative;
                top: auto;
                left: auto;
                margin-bottom: 20px;
                display: inline-block;
            }
        }
    </style>
</head>
<body>
    <a href="home.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>
    
    <div class="container">
        <div class="header">
            <h1>Featured Players</h1>
            <p>Discover talented football players from Ethiopia</p>
        </div>
        
        <?php if ($total_players > 0): ?>
        <div class="stats-summary">
            <h3>Total Players Found: <?php echo $total_players; ?></h3>
            <p>Showing page <?php echo $page; ?> of <?php echo $total_pages; ?></p>
        </div>
        <?php endif; ?>
        
        <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label for="search">Search Players</label>
                    <input type="text" id="search" name="search" class="filter-control" 
                           placeholder="Search by name or city..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="city">city</label>
                    <select id="city" name="city" class="filter-control">
                        <option value="">All city</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>" 
                                    <?php echo $city_filter == $city ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($city); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
               
                <div class="filter-group">
                    <label for="age">Age Range</label>
                    <select id="age" name="age" class="filter-control">
                        <option value="">All Ages</option>
                        <option value="5-10" <?php echo $age_filter == '5-10' ? 'selected' : ''; ?>>6-8 years</option>
                        <option value="11-15" <?php echo $age_filter == '11-15' ? 'selected' : ''; ?>>9-11 years</option>
                        <option value="16-18" <?php echo $age_filter == '16-18' ? 'selected' : ''; ?>>12-15 years</option>
                        <option value="16-18" <?php echo $age_filter == '16-18' ? 'selected' : ''; ?>>16-18 years</option>

                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="position">Position</label>
                    <select id="position" name="position" class="filter-control">
                        <option value="">All Positions</option>
                        <option value="striker" <?php echo $position_filter == 'striker' ? 'selected' : ''; ?>>Striker</option>
                        <option value="midfielder" <?php echo $position_filter == 'midfielder' ? 'selected' : ''; ?>>Midfielder</option>
                        <option value="defender" <?php echo $position_filter == 'defender' ? 'selected' : ''; ?>>Defender</option>
                        <option value="goalkeeper" <?php echo $position_filter == 'goalkeeper' ? 'selected' : ''; ?>>Goalkeeper</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
            </form>
        </div>
        
        <?php if (!empty($players)): ?>
            <div class="players-grid">
                <?php foreach ($players as $player): ?>
                    <?php $age = calculate_age($player['birth_day'], $player['birth_month'], $player['birth_year']); ?>
                    <div class="player-card">
                        <img src="<?php echo $player['photo_path'] ? 'uploads/' . htmlspecialchars($player['photo_path']) : '/placeholder.svg?height=120&width=120'; ?>" 
                             alt="<?php echo htmlspecialchars($player['first_name']); ?>" 
                             class="player-photo"
                             onerror="this.src='/placeholder.svg?height=120&width=120'">
                        
                        <h3 class="player-name"><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></h3>
                        
                        <div class="player-details">
                            <p><strong>Age:</strong> <?php echo $age; ?> years</p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($player['city'] . ', ' . $player['country']); ?></p>
                            <?php if (isset($player['weight']) && $player['weight']): ?>
                                <p><strong>Weight:</strong> <?php echo htmlspecialchars($player['weight']); ?>kg</p>
                            <?php endif; ?>
                            <?php if (isset($player['position']) && $player['position']): ?>
                                <p><strong>Position:</strong> <?php echo htmlspecialchars(ucfirst($player['position'])); ?></p>
                            <?php endif; ?>
                            <p><strong>Registration:</strong> <?php echo ucfirst(htmlspecialchars($player['registration_type'] ?? 'Standard')); ?></p>
                        </div>
                        
                        <div class="player-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $age; ?></div>
                                <div class="stat-label">Age</div>
                            </div>
                            <?php if (isset($player['weight']) && $player['weight']): ?>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo htmlspecialchars($player['weight']); ?>kg</div>
                                <div class="stat-label">Weight</div>
                            </div>
                            <?php endif; ?>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo rand(7, 10); ?>.<?php echo rand(0, 9); ?></div>
                                <div class="stat-label">Rating</div>
                            </div>
                        </div>
                        
                     
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&country=<?php echo urlencode($country_filter); ?>&age=<?php echo urlencode($age_filter); ?>&position=<?php echo urlencode($position_filter); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&country=<?php echo urlencode($country_filter); ?>&age=<?php echo urlencode($age_filter); ?>&position=<?php echo urlencode($position_filter); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&country=<?php echo urlencode($country_filter); ?>&age=<?php echo urlencode($age_filter); ?>&position=<?php echo urlencode($position_filter); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-players">
                <i class="fas fa-search"></i>
                <h3>No Players Found</h3>
                <p>Try adjusting your search criteria or filters.</p>
                
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add loading state to filter button
        document.querySelector('form').addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Filtering...';
            button.disabled = true;
        });

        // Auto-submit form when filters change (optional)
        document.querySelectorAll('.filter-control').forEach(function(element) {
            if (element.type === 'select-one') {
                element.addEventListener('change', function() {
                    // Uncomment the line below if you want auto-submit on filter change
                    // this.form.submit();
                });
            }
        });

        // Clear all filters function
        function clearFilters() {
            window.location.href = 'featured-players.php';
        }

        // Add clear filters button if any filter is active
        <?php if ($search || $country_filter || $age_filter || $position_filter): ?>
        const filtersSection = document.querySelector('.filters-section');
        const clearBtn = document.createElement('div');
        clearBtn.innerHTML = '<button type="button" class="btn btn-secondary" onclick="clearFilters()" style="margin-top: 15px;"><i class="fas fa-times"></i> Clear All Filters</button>';
        filtersSection.appendChild(clearBtn);
        <?php endif; ?>
    </script>
</body>
</html>