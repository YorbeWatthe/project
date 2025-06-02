<?php
session_start();
require_once 'includes/db.php';

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page instead of showing error message
    header("Location: login.php?redirect=cart.php");
    exit;
}

$user_id = (int)$_SESSION['user_id']; // Cast to integer for security

// Define pricing for each product
$pricing_rules = [
    'Jungle Book' => ['1_day' => 115, '2_days' => 200, '3_plus_discount' => 20],
    't Voetballerke' => ['1_day' => 120, '2_days' => 210, '3_plus_discount' => 20],
    'Aloha Hawaii' => ['1_day' => 120, '2_days' => 210, '3_plus_discount' => 20],
    'Stormbaan' => ['1_day' => 140, '2_days' => 250, '3_plus_discount' => 20]
];

// Function to calculate price based on days
function calculatePrice($product_name, $days, $pricing_rules) {
    if (!isset($pricing_rules[$product_name])) {
        return 0;
    }
    
    $rules = $pricing_rules[$product_name];
    
    if ($days == 1) {
        return $rules['1_day'];
    } elseif ($days == 2) {
        return $rules['2_days'];
    } else {
        // 3+ days: calculate based on daily rate with discount
        $daily_rate = $rules['1_day'];
        $total = $daily_rate * $days;
        $discount = $rules['3_plus_discount'];
        return $total * (1 - $discount / 100);
    }
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ongeldige aanvraag. Probeer het opnieuw.");
    }
    
    // Update rental dates
    if (isset($_POST['update_cart']) && isset($_POST['start_date']) && isset($_POST['end_date'])) {
        foreach ($_POST['start_date'] as $item_id => $start_date) {
            $item_id = (int)$item_id;
            $end_date = $_POST['end_date'][$item_id];
            
            // Validate dates
            if (strtotime($start_date) >= strtotime(date('Y-m-d')) && strtotime($end_date) >= strtotime($start_date)) {
                $rental_days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
                
                $stmt = $pdo->prepare("UPDATE cart_items SET start_date = ?, end_date = ?, rental_days = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$start_date, $end_date, $rental_days, $item_id, $user_id]);
            }
        }
        
        // Redirect to prevent form resubmission
        header("Location: cart.php?updated=1");
        exit;
    }
}

// Remove item from cart (using POST method instead of GET for security)
if (isset($_POST['remove']) && is_numeric($_POST['remove'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ongeldige aanvraag. Probeer het opnieuw.");
    }
    
    $remove_id = (int)$_POST['remove'];
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$remove_id, $user_id]);
    
    header("Location: cart.php?removed=1");
    exit;
}

// Get cart items from database with rental information
$stmt = $pdo->prepare("
    SELECT ci.id as cart_id, p.id as product_id, p.name, p.description, p.price, ci.quantity, p.available, p.category, p.image, 
           ci.start_date, ci.end_date, ci.rental_days
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.user_id = ?
    ORDER BY ci.created_at DESC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_price = 0;
$total_days = 0;

// Flash messages
$messages = [];
if (isset($_GET['updated'])) {
    $messages[] = ['type' => 'success', 'text' => 'Winkelwagen bijgewerkt!'];
}
if (isset($_GET['removed'])) {
    $messages[] = ['type' => 'success', 'text' => 'Product verwijderd!'];
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Winkelwagen - L&G Fun Springkastelen</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: #333;
            background-color: #f8fafc;
            line-height: 1.6;
        }

        /* Header styling */
        header {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .logo img {
            height: 60px;
            width: auto;
            filter: brightness(1.1);
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
            padding: 0;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        nav ul li a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Main container */
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .page-title i {
            color: #667eea;
        }

        /* Alert messages */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Empty cart state */
        .empty-cart {
            text-align: center;
            background: white;
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .empty-cart i {
            font-size: 4rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }

        .empty-cart h2 {
            font-size: 1.5rem;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .empty-cart p {
            color: #64748b;
            margin-bottom: 2rem;
        }

        /* Cart items */
        .cart-items {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .cart-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            align-items: start;
            transition: all 0.3s ease;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item:hover {
            background: #f8fafc;
        }

        .item-image {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .item-image:hover img {
            transform: scale(1.05);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .item-category {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .item-description {
            color: #64748b;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .rental-info {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .rental-info h4 {
            color: #334155;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rental-info h4 i {
            color: #667eea;
        }

        .date-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .date-group {
            display: flex;
            flex-direction: column;
        }

        .date-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.3rem;
        }

        .date-group input {
            padding: 0.6rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .date-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .rental-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }

        .rental-days {
            font-weight: 600;
            color: #334155;
        }

        .item-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
        }

        .item-controls {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            align-items: flex-end;
        }

        .price-display {
            text-align: right;
        }

        .original-price {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.3rem;
        }

        .final-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #22c55e;
        }

        .discount-badge {
            background: #22c55e;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 0.3rem;
        }

        .remove-button {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .remove-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .stock-warning {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            padding: 0.8rem;
            border-radius: 8px;
            border: 1px solid #fecaca;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .stock-warning i {
            font-size: 1.1rem;
        }

        /* Cart actions */
        .cart-actions {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .update-cart {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .update-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }

        .continue-shopping {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .continue-shopping:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.3);
        }

        /* Cart summary */
        .cart-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #667eea;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.15);
        }

        .cart-summary h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-summary h3 i {
            color: #667eea;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-row:last-child {
            border-bottom: none;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #667eea;
        }

        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .summary-label {
            color: #64748b;
            font-weight: 500;
        }

        .summary-value {
            color: #334155;
            font-weight: 600;
        }

        .total-price {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .checkout-button {
            width: 100%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 1.2rem 2rem;
            text-decoration: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.2);
        }

        .checkout-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.4);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .cart-container {
                padding: 1rem;
            }

            .cart-item {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .item-image {
                width: 100%;
                height: 200px;
                justify-self: center;
            }

            .item-controls {
                align-items: stretch;
            }

            .date-inputs {
                grid-template-columns: 1fr;
            }

            .cart-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .navbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            nav ul {
                gap: 1rem;
            }

            nav ul li a {
                padding: 0.6rem 1rem;
                font-size: 1rem;
            }
        }

        /* Animation */
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.6s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
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
                <li><a href="user_dashboard.php">Home</a></li>
                <li><a href="filter_products.php">Producten</a></li>
                <li><a href="cart.php">Winkelwagen</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="loguit.php">Uitloggen</a></li>
            </ul>
        </nav>
    </div>
</header>

<main>
    <div class="cart-container">
        <h1 class="page-title">
            <i class="fas fa-shopping-cart"></i>
            Jouw Springkasteel Reserveringen
        </h1>
        
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?php echo $message['type']; ?> fade-in">
                <i class="fas fa-<?php echo $message['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message['text']); ?>
            </div>
        <?php endforeach; ?>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart fade-in">
                <i class="fas fa-shopping-cart"></i>
                <h2>Je winkelwagen is leeg</h2>
                <p>Voeg wat geweldige springkastelen toe aan je winkelwagen om te beginnen!</p>
                <a href="filter_products.php" class="checkout-button" style="max-width: 300px; margin: 0 auto;">
                    <i class="fas fa-search"></i>
                    Bekijk onze springkastelen
                </a>
            </div>
        <?php else: ?>
            <form method="post" action="cart.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="cart-items fade-in">
                    <?php foreach ($cart_items as $item): 
                        // Calculate price based on rental days and product
                        $item_price = calculatePrice($item['name'], $item['rental_days'], $pricing_rules);
                        $total_price += $item_price;
                        $total_days += $item['rental_days'];
                        
                        // Check if product is available
                        $availability_warning = !$item['available'];
                        
                        // Calculate if there's a discount
                        $has_discount = $item['rental_days'] >= 3;
                        $original_price = $pricing_rules[$item['name']]['1_day'] * $item['rental_days'];
                        $discount_percentage = $has_discount ? $pricing_rules[$item['name']]['3_plus_discount'] : 0;
                    ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php if ($item['image']): ?>
                                    <img src="images/<?php echo htmlspecialchars($item['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                        <i class="fas fa-image" style="font-size: 2rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <span class="item-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                
                                <?php if ($item['description']): ?>
                                    <p class="item-description">
                                        <?php echo htmlspecialchars(substr($item['description'], 0, 120)) . '...'; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="rental-info">
                                    <h4><i class="fas fa-calendar-alt"></i> Verhuurdagen</h4>
                                    <div class="date-inputs">
                                        <div class="date-group">
                                            <label>Startdatum</label>
                                            <input type="date" 
                                                   name="start_date[<?php echo $item['cart_id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($item['start_date']); ?>" 
                                                   min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="date-group">
                                            <label>Einddatum</label>
                                            <input type="date" 
                                                   name="end_date[<?php echo $item['cart_id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($item['end_date']); ?>" 
                                                   min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="rental-summary">
                                        <span class="rental-days">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $item['rental_days']; ?> dag<?php echo $item['rental_days'] > 1 ? 'en' : ''; ?>
                                        </span>
                                        <span class="item-price">
                                            Periode: <?php echo date('d/m', strtotime($item['start_date'])); ?> - <?php echo date('d/m', strtotime($item['end_date'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($availability_warning): ?>
                                    <div class="stock-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Product momenteel niet beschikbaar!
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-controls">
                                <div class="price-display">
                                    <?php if ($has_discount): ?>
                                        <div class="original-price">
                                            <s>€<?php echo number_format($original_price, 0, ',', '.'); ?></s>
                                        </div>
                                        <div class="discount-badge">
                                            <?php echo $discount_percentage; ?>% korting
                                        </div>
                                    <?php endif; ?>
                                    <div class="final-price">
                                        €<?php echo number_format($item_price, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                
                                <button type="submit" name="remove" value="<?php echo $item['cart_id']; ?>" class="remove-button">
                                    <i class="fas fa-trash"></i>
                                    Verwijderen
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-actions fade-in">
                    <button type="submit" name="update_cart" value="1" class="update-cart">
                        <i class="fas fa-sync-alt"></i>
                        Winkelwagen bijwerken
                    </button>
                    <a href="filter_products.php" class="continue-shopping">
                        <i class="fas fa-arrow-left"></i>
                        Verder winkelen
                    </a>
                </div>
                
                <div class="cart-summary fade-in">
                    <h3><i class="fas fa-calculator"></i> Overzicht</h3>
                    
                    <div class="summary-row">
                        <span class="summary-label">Aantal springkastelen:</span>
                        <span class="summary-value"><?php echo count($cart_items); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Totaal aantal huurdagen:</span>
                        <span class="summary-value"><?php echo $total_days; ?> dagen</span>
                    </div>
                    
                    <div class="summary-row total">
                        <span class="summary-label">Totaalprijs:</span>
                        <span class="total-price">€<?php echo number_format($total_price, 0, ',', '.'); ?></span>
                    </div>
                    
                    <a href="checkout.php" class="checkout-button">
                        <i class="fas fa-credit-card"></i>
                        Ga naar afrekenen
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle date input validation and automatic rental days calculation
    const startDateInputs = document.querySelectorAll('input[name^="start_date"]');
    const endDateInputs = document.querySelectorAll('input[name^="end_date"]');
    
    function updateRentalDays(startInput, endInput) {
        const startDate = new Date(startInput.value);
        const endDate = new Date(endInput.value);
        
        if (startDate && endDate && endDate >= startDate) {
            const timeDiff = endDate.getTime() - startDate.getTime();
            const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
            
            // Find the rental summary for this item
            const cartItem = startInput.closest('.cart-item');
            const rentalDaysSpan = cartItem.querySelector('.rental-days');
            const periodSpan = cartItem.querySelector('.item-price');
            
            if (rentalDaysSpan) {
                rentalDaysSpan.innerHTML = `<i class="fas fa-clock"></i> ${daysDiff} dag${daysDiff > 1 ? 'en' : ''}`;
            }
            
            if (periodSpan) {
                const startFormatted = startDate.toLocaleDateString('nl-NL', { day: '2-digit', month: '2-digit' });
                const endFormatted = endDate.toLocaleDateString('nl-NL', { day: '2-digit', month: '2-digit' });
                periodSpan.textContent = `Periode: ${startFormatted} - ${endFormatted}`;
            }
            
            // Update price if pricing rules are available
            updateItemPrice(cartItem, daysDiff);
        }
    }
    
    function updateItemPrice(cartItem, days) {
        // Get product name from the item
        const productName = cartItem.querySelector('.item-name').textContent.trim();
        
        // Pricing rules (same as PHP)
        const pricingRules = {
            'Jungle Book': { '1_day': 115, '2_days': 200, '3_plus_discount': 20 },
            't Voetballerke': { '1_day': 120, '2_days': 210, '3_plus_discount': 20 },
            'Aloha Hawaii': { '1_day': 120, '2_days': 210, '3_plus_discount': 20 },
            'Stormbaan': { '1_day': 140, '2_days': 250, '3_plus_discount': 20 }
        };
        
        if (pricingRules[productName]) {
            const rules = pricingRules[productName];
            let price = 0;
            let originalPrice = 0;
            let hasDiscount = false;
            
            if (days === 1) {
                price = rules['1_day'];
            } else if (days === 2) {
                price = rules['2_days'];
            } else if (days >= 3) {
                const dailyRate = rules['1_day'];
                originalPrice = dailyRate * days;
                const discount = rules['3_plus_discount'];
                price = originalPrice * (1 - discount / 100);
                hasDiscount = true;
            }
            
            // Update price display
            const priceDisplay = cartItem.querySelector('.price-display');
            const originalPriceEl = priceDisplay.querySelector('.original-price');
            const discountBadge = priceDisplay.querySelector('.discount-badge');
            const finalPrice = priceDisplay.querySelector('.final-price');
            
            if (hasDiscount) {
                if (originalPriceEl) {
                    originalPriceEl.innerHTML = `<s>€${originalPrice.toLocaleString('nl-NL')}</s>`;
                    originalPriceEl.style.display = 'block';
                } else {
                    const newOriginalPrice = document.createElement('div');
                    newOriginalPrice.className = 'original-price';
                    newOriginalPrice.innerHTML = `<s>€${originalPrice.toLocaleString('nl-NL')}</s>`;
                    priceDisplay.insertBefore(newOriginalPrice, finalPrice);
                }
                
                if (discountBadge) {
                    discountBadge.textContent = `${rules['3_plus_discount']}% korting`;
                    discountBadge.style.display = 'block';
                } else {
                    const newDiscountBadge = document.createElement('div');
                    newDiscountBadge.className = 'discount-badge';
                    newDiscountBadge.textContent = `${rules['3_plus_discount']}% korting`;
                    priceDisplay.insertBefore(newDiscountBadge, finalPrice);
                }
            } else {
                if (originalPriceEl) originalPriceEl.style.display = 'none';
                if (discountBadge) discountBadge.style.display = 'none';
            }
            
            if (finalPrice) {
                finalPrice.textContent = `€${Math.round(price).toLocaleString('nl-NL')}`;
            }
        }
    }
    
    // Add event listeners to all date inputs
    startDateInputs.forEach((startInput, index) => {
        const cartItemId = startInput.name.match(/\[(\d+)\]/)[1];
        const endInput = document.querySelector(`input[name="end_date[${cartItemId}]"]`);
        
        startInput.addEventListener('change', function() {
            // Set minimum end date to start date
            if (endInput) {
                endInput.min = this.value;
                if (endInput.value && endInput.value < this.value) {
                    endInput.value = this.value;
                }
                updateRentalDays(this, endInput);
            }
        });
        
        if (endInput) {
            endInput.addEventListener('change', function() {
                // Ensure end date is not before start date
                if (this.value < startInput.value) {
                    this.value = startInput.value;
                }
                updateRentalDays(startInput, this);
            });
        }
    });
    
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const action = e.submitter ? e.submitter.name : '';
        
        if (action === 'update_cart') {
            let hasValidDates = true;
            const today = new Date().toISOString().split('T')[0];
            
            startDateInputs.forEach((startInput, index) => {
                const cartItemId = startInput.name.match(/\[(\d+)\]/)[1];
                const endInput = document.querySelector(`input[name="end_date[${cartItemId}]"]`);
                
                if (!startInput.value || !endInput.value) {
                    hasValidDates = false;
                    return;
                }
                
                if (startInput.value < today) {
                    hasValidDates = false;
                    return;
                }
                
                if (endInput.value < startInput.value) {
                    hasValidDates = false;
                    return;
                }
            });
            
            if (!hasValidDates) {
                e.preventDefault();
                alert('Controleer alle datums. Startdatum mag niet in het verleden liggen en einddatum moet na startdatum zijn.');
                return;
            }
            
            // Show loading state
            const button = e.submitter;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bijwerken...';
            button.disabled = true;
        }
    });
    
    // Smooth scrolling for better UX
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Initialize rental days calculation for existing items
    startDateInputs.forEach((startInput, index) => {
        const cartItemId = startInput.name.match(/\[(\d+)\]/)[1];
        const endInput = document.querySelector(`input[name="end_date[${cartItemId}]"]`);
        
        if (startInput.value && endInput && endInput.value) {
            updateRentalDays(startInput, endInput);
        }
    });
});

// Add confirmation for remove actions
document.querySelectorAll('.remove-button').forEach(button => {
    button.addEventListener('click', function(e) {
        if (!confirm('Weet je zeker dat je dit springkasteel uit je winkelwagen wilt verwijderen?')) {
            e.preventDefault();
        }
    });
});
</script>

</body>
</html>