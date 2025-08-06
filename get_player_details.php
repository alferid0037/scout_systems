<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['professional_logged_in'])) {
    die('Unauthorized access');
}

// Database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to calculate age
function calculateAge($day, $month, $year) {
    $birthDate = "$year-$month-$day";
    $today = date("Y-m-d");
    $diff = date_diff(date_create($birthDate), date_create($today));
    return $diff->format('%y');
}

function formatDocument($path) {
    if (!$path) return 'Not provided';
    
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        return '<img src="uploads/' . htmlspecialchars($path) . '" style="max-width: 100%; max-height: 200px;">';
    } elseif ($extension === 'pdf') {
        return '<a href="uploads/' . htmlspecialchars($path) . '" target="_blank" class="btn btn-primary">View PDF</a>';
    }
    
    return 'Unsupported format';
}

$player_id = (int)$_GET['id'];

// Get player details
$stmt = $pdo->prepare("
    SELECT pr.*, u.email, u.username, u.created_at as user_created 
    FROM player_registrations pr 
    JOIN professional_users u ON pr.user_id = u.id 
    WHERE pr.id = ?
");
$stmt->execute([$player_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    die('Player not found');
}

// Calculate age
$age = calculateAge($player['birth_day'], $player['birth_month'], $player['birth_year']);
?>

<style>
    .player-info {
        font-family: 'Montserrat', sans-serif;
        color: #333;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .player-info h3 {
        color: #1e3c72;
        font-size: 1.3rem;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid #f0a500;
    }

    .player-info h4 {
        color: #2a5298;
        font-size: 1.1rem;
        margin-bottom: 10px;
    }

    .player-info p {
        margin-bottom: 10px;
        line-height: 1.5;
    }

    .player-info strong {
        color: #1e3c72;
        font-weight: 600;
        min-width: 120px;
        display: inline-block;
    }

    .player-info .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .player-info .btn-success {
        background: #28a745;
        color: white;
    }

    .player-info .btn-danger {
        background: #dc3545;
        color: white;
    }

    .player-info .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .document-container {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        transition: all 0.3s ease;
        background: #f9f9f9;
    }

    .document-container:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        transform: translateY(-3px);
    }

    .document-preview {
        max-width: 100%;
        height: 200px;
        object-fit: contain;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .document-placeholder {
        width: 100%;
        height: 200px;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        border-radius: 4px;
        color: #777;
    }

    .document-placeholder i {
        font-size: 3rem;
    }

    .document-actions {
        margin-top: 10px;
    }

    .document-link {
        color: #1e3c72;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .document-link:hover {
        color: #f0a500;
        text-decoration: underline;
    }

    /* Status badges */
    .status-badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-approved {
        background: #d4edda;
        color: #155724;
    }

    .status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    /* Responsive layout */
    @media (max-width: 768px) {
        .player-info > div {
            flex-direction: column;
        }
        
        .player-info .document-grid {
            grid-template-columns: 1fr;
        }
        
        .player-info .photo-column {
            width: 100%;
            margin-top: 20px;
        }
    }

    /* Form elements */
    .rejection-form {
        margin-top: 20px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .rejection-form textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        min-height: 100px;
        margin-bottom: 10px;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
</style>

<div class="player-info">
    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <div style="flex: 1;">
            <h3 style="color: #1e3c72; margin-bottom: 15px;">Personal Information</h3>
            <p><strong>Name:</strong> <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></p>
            

            
            <p><strong>Date of Birth:</strong> <?= $player['birth_day'] . '/' . $player['birth_month'] . '/' . $player['birth_year'] ?></p>
            <p><strong>Age:</strong> <?= $age ?> years</p>
            <p><strong>Gender:</strong> <?= ucfirst($player['gender']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($player['phone']) ?></p>
            <p><strong>City:</strong> <?= htmlspecialchars($player['city']) ?></p>
            <p><strong>Weight:</strong> <?= htmlspecialchars($player['weight']) ?> kg</p>
            <p><strong>Passport:</strong> <?= htmlspecialchars($player['passport'] ?? 'Not provided') ?></p>
        </div>
        
        <div style="width: 200px; text-align: center;">
            <?php if ($player['photo_path']): ?>
                <img src="uploads/<?= htmlspecialchars($player['photo_path']) ?>" style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid #1e3c72;">
            <?php else: ?>
                <div style="width: 150px; height: 150px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <i class="fas fa-user" style="font-size: 3rem; color: #999;"></i>
                </div>
            <?php endif; ?>
            <p style="margin-top: 10px;"><strong>Status:</strong> 
                <span class="status-badge status-<?= $player['registration_status'] ?>">
                    <?= ucfirst($player['registration_status']) ?>
                </span>
            </p>
            <?php if ($player['registration_status'] === 'rejected' && $player['rejection_reason']): ?>
                <p><strong>Rejection Reason:</strong> <?= htmlspecialchars($player['rejection_reason']) ?></p>
            <?php endif; ?>
            <p><strong>Registered:</strong> <?= date('M j, Y', strtotime($player['user_created'])) ?></p>
        </div>
    </div>
    
    <h3 style="color: #1e3c72; margin-bottom: 15px;">Submitted Documents</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="document-container">
            <h4>Profile Photo</h4>
            <?= formatDocument($player['photo_path']) ?>
        </div>
        
        <div class="document-container">
            <h4>Birth Certificate</h4>
            <?= formatDocument($player['birth_certificate_path']) ?>
        </div>
        
        <div class="document-container">
            <h4>Education Certificate</h4>
            <?= formatDocument($player['education_certificate_path']) ?>
        </div>
        
        <div class="document-container">
            <h4>Passport Photo</h4>
            <?= formatDocument($player['passport_photo_path']) ?>
        </div>
    </div>
    
    <?php if ($player['registration_status'] === 'pending'): ?>
    <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
        <button class="btn btn-success" onclick="approvePlayer(<?= $player['id'] ?>, 'approved')">
            <i class="fas fa-check"></i> Approve Registration
        </button>
        <button class="btn btn-danger" onclick="showRejectionForm(<?= $player['id'] ?>)">
            <i class="fas fa-times"></i> Reject Registration
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
function approvePlayer(playerId, action) {
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=approve_player&player_id=${playerId}&approval_action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Player ${action} successfully`);
            window.location.reload();
        } else {
            alert(`Failed to ${action} player`);
        }
    });
}

function showRejectionForm(playerId) {
    const reason = prompt('Please enter the reason for rejection:');
    if (reason !== null) {
        fetch('admin-dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=reject_player&player_id=${playerId}&rejection_reason=${encodeURIComponent(reason)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Player rejected successfully');
                window.location.reload();
            } else {
                alert('Failed to reject player');
            }
        });
    }
}
</script>