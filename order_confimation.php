<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=order_confirmation.php");
    exit;
}

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: index.php");
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = (int)$_SESSION['user_id'];

// Get order details
$stmt = $pdo->prepare("
    SELECT o.* 
    FROM orders o
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// If no order found or order doesn't belong to user
if (!$order) {
    header("Location: index.php");
    exit;
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.image, p.category
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user details
$stmt = $pdo->prepare("SELECT name, email, address FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestelling bevestigd - L&G Fun Springkastelen</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .confirmation-card {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-details {
            margin-bottom: 30px;
        }
        .order-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .order-details-table th {
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #ddd;
        }
        .order-details-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .order-items {
            margin-bottom: 30px;
        }
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #ddd;
        }
        .order-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            margin-right: 15px;
        }
        .order-item-details {
            flex-grow: 1;
        }
        .order-item-price {
            font-weight: bold;
            min-width: 100px;
            text-align: right;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 1.2em;
            padding: 15px 0;
            border-top: 2px solid #ddd;
        }
        .thank-you-message {
            text-align: center;
            margin: 30px 0;
        }
        .continue-shopping {
            display: block;
            text-align: center;
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px auto;
            width: fit-content;
        }
        .continue-shopping:hover {
            background: #45a049;
        }
        .contact-info {
            text-align: center;
            margin-top: 30px;
            font-size: 0.9em;
            color: #666;
        }
        .order-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            background-color: #ffc107;
            color: #212121;
        }
    </style>
</head>
<body>
<header>
    <div class="navbar">
        <div class="logo">
            <img src="images/Logo L&G Fun-Photoroom.png" alt="Logo">
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php">Over ons</a></li>
                <li><a href="cart.php">Winkelwagen</a></li>
                <li><a href="checkout.php">Afrekenen</a></li>
            </ul>
        </nav>
    </div>
</header>

<main>
    <div class="confirmation-container">
        <div class="confirmation-header">
            <h1>Bedankt voor je bestelling!</h1>
            <p>Je reservering is succesvol geplaatst.</p>
        </div>
        
        <div class="confirmation-card">
            <div class="order-details">
                <h2>Bestelling #<?php echo $order_id; ?></h2>
                <p>Geplaatst op: <?php echo date('d-m-Y H:i', strtotime($order['created_at'])); ?></p>
                <p>Status: <span class="order-status"><?php 
                    switch($order['status']) {
                        case 'pending': echo 'In behandeling'; break;
                        case 'confirmed': echo 'Bevestigd'; break;
                        case 'shipped': echo 'Onderweg'; break;
                        case 'completed': echo 'Afgerond'; break;
                        case 'cancelled': echo 'Geannuleerd'; break;
                        default: echo ucfirst($order['status']); break;
                    }
                ?></span></p>
                
                <h3>Leveringsadres</h3>
                <p><?php echo nl2br(htmlspecialchars($user['address'])); ?></p>
            </div>
            
            <div class="order-items">
                <h3>Bestelde springkastelen</h3>
                
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <?php if ($item['image']): ?>
                            <img src="images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="order-item-image">
                        <?php endif; ?>
                        <div class="order-item-details">
                            <div><strong><?php echo htmlspecialchars($item['name']); ?></strong></div>
                            <div><?php echo htmlspecialchars($item['category']); ?></div>
                            <div><?php echo htmlspecialchars($item['quantity']); ?> dag(en) × €<?php echo number_format($item['price'], 2, ',', '.'); ?></div>
                        </div>
                        <div class="order-item-price">
                            €<?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="total-row">
                    <span>Totaalbedrag</span>
                    <span>€<?php echo number_format($order['total_price'], 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="thank-you-message">
            <h2>Wat gebeurt er nu?</h2>
            <p>We nemen binnen 24 uur contact met je op om de levering van je springkasteel te bevestigen.</p>
            <p>Je ontvangt ook een bevestigingsmail met de details van je bestelling.</p>
        </div>
        
        <a href="index.php" class="continue-shopping">Terug naar homepage</a>
        
        <div class="contact-info">
            <p>Heb je nog vragen? Neem contact met ons op:</p>
            <p>Email: info@lgfun.com | Telefoon: +32 123 456 789</p>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>