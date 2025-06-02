<?php
session_start();
require_once 'includes/db.php';

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout.php");
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

// Function to check availability
function checkAvailability($pdo, $product_id, $start_date, $end_date) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as conflicts 
        FROM bookings 
        WHERE product_id = :product_id 
        AND (
            (start_date <= :start_date AND end_date >= :start_date) OR
            (start_date <= :end_date AND end_date >= :end_date) OR
            (start_date >= :start_date AND end_date <= :end_date)
        )
    ");
    $stmt->execute([
        ':product_id' => $product_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC)['conflicts'] == 0;
}

// Get user information
$stmt = $pdo->prepare("SELECT name, email, address FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Gebruiker niet gevonden.");
}

// Get cart items with rental information
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

// If cart is empty, redirect to cart page
if (empty($cart_items)) {
    header("Location: cart.php?error=empty");
    exit;
}

// Check availability and calculate prices
$unavailable_products = [];
$total_price = 0;
$total_days = 0;

foreach ($cart_items as &$item) {
    // Check if product is available
    if (!$item['available']) {
        $unavailable_products[] = $item['name'];
    }
    
    // Check if dates are available
    if ($item['start_date'] && $item['end_date']) {
        if (!checkAvailability($pdo, $item['product_id'], $item['start_date'], $item['end_date'])) {
            $unavailable_products[] = $item['name'] . ' (niet beschikbaar voor geselecteerde datums)';
        }
    }
    
    // Calculate price based on rental days
    $item_price = calculatePrice($item['name'], $item['rental_days'], $pricing_rules);
    $item['calculated_price'] = $item_price;
    $total_price += $item_price;
    $total_days += $item['rental_days'];
}

// Get valid discount codes
$stmt = $pdo->prepare("
    SELECT code, discount_percentage 
    FROM discount_codes 
    WHERE (valid_from IS NULL OR valid_from <= CURDATE()) 
    AND (valid_until IS NULL OR valid_until >= CURDATE())
");
$stmt->execute();
$discount_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Applied discount
$discount_amount = 0;
$discount_code = '';
$discount_message = '';
$terms_error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ongeldige aanvraag. Probeer het opnieuw.");
    }
    
    // Process discount code if provided
    if (isset($_POST['apply_discount']) && !empty($_POST['discount_code'])) {
        $entered_code = trim($_POST['discount_code']);
        
        // Check if code is valid
        $stmt = $pdo->prepare("
            SELECT discount_percentage 
            FROM discount_codes 
            WHERE code = ? 
            AND (valid_from IS NULL OR valid_from <= CURDATE()) 
            AND (valid_until IS NULL OR valid_until >= CURDATE())
        ");
        $stmt->execute([$entered_code]);
        $discount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($discount) {
            $discount_code = $entered_code;
            $discount_amount = ($discount['discount_percentage'] / 100) * $total_price;
            $discount_message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Kortingscode toegepast!</div>';
        } else {
            $discount_message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Ongeldige kortingscode.</div>';
        }
    }
    
    // Process order if place_order button is clicked
    if (isset($_POST['place_order']) && empty($unavailable_products)) {
        // Check if terms are accepted
        if (!isset($_POST['accept_terms']) || $_POST['accept_terms'] != '1') {
            $terms_error = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> U moet de algemene voorwaarden accepteren om door te gaan.</div>';
        } else {
            // Update user address if provided
            if (!empty($_POST['address'])) {
                $stmt = $pdo->prepare("UPDATE users SET address = ? WHERE id = ?");
                $stmt->execute([trim($_POST['address']), $user_id]);
            }
            
            // Calculate final price after discount
            $final_price = $total_price - $discount_amount;
            
            // Create order in database
            $pdo->beginTransaction();
            
            try {
                // Insert order
                $stmt = $pdo->prepare("
                    INSERT INTO orders (user_id, total_price, discount_code, discount_amount, notes, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $user_id, 
                    $final_price, 
                    $discount_code, 
                    $discount_amount, 
                    $_POST['notes'] ?? ''
                ]);
                $order_id = $pdo->lastInsertId();
                
                // Insert order items with rental information
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price, start_date, end_date, rental_days) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($cart_items as $item) {
                    $stmt->execute([
                        $order_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['calculated_price'],
                        $item['start_date'],
                        $item['end_date'],
                        $item['rental_days']
                    ]);
                }
                
                // Move cart items to bookings
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (user_id, product_id, quantity, start_date, end_date, rental_days) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($cart_items as $item) {
                    $stmt->execute([
                        $user_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['start_date'],
                        $item['end_date'],
                        $item['rental_days']
                    ]);
                }
                
                // Clear the user's cart
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $pdo->commit();
                
                // Redirect to payment page
                header("Location: pay.php?order_id=$order_id");
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Er is een fout opgetreden bij het verwerken van uw bestelling. Probeer het later opnieuw.";
            }
        }
    }
}

// Calculate final total after discount
$final_total = $total_price - $discount_amount;
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afrekenen - L&G Fun Springkastelen</title>
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
        .checkout-container {
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

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Unavailable warning */
        .unavailable-warning {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            border: 1px solid #f5c6cb;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .unavailable-warning h3 {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .unavailable-warning ul {
            margin: 1rem 0 1rem 2rem;
        }

        /* Checkout form layout */
        .checkout-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }

        .customer-details {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .form-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .form-section h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section h2 i {
            color: #667eea;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control[readonly] {
            background: #f8fafc;
            color: #64748b;
        }

        /* Discount form */
        .discount-form {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .discount-form input {
            flex: 1;
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .discount-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .discount-form button {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .discount-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }

        /* Order summary */
        .order-summary {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .order-summary h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-summary h2 i {
            color: #667eea;
        }

        /* Product list */
        .product-list {
            margin-bottom: 2rem;
        }

        .product-item {
            display: flex;
            align-items: flex-start;
            padding: 1.5rem 0;
            border-bottom: 1px solid #f1f5f9;
            gap: 1rem;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .product-details {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.3rem;
        }

        .product-category {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .rental-period {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .rental-period h4 {
            font-size: 0.9rem;
            color: #374151;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .rental-period h4 i {
            color: #667eea;
        }

        .date-range {
            font-size: 0.85rem;
            color: #64748b;
        }

        .rental-days {
            font-size: 0.85rem;
            color: #334155;
            font-weight: 500;
        }

        .discount-info {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.3rem;
        }

        .product-price {
            text-align: right;
            flex-shrink: 0;
        }

        .original-price {
            font-size: 0.8rem;
            color: #64748b;
            text-decoration: line-through;
        }

        .final-price {
            font-weight: 700;
            color: #22c55e;
            font-size: 1.1rem;
        }

        /* Pricing summary */
        .pricing-summary {
            border-top: 2px solid #f1f5f9;
            padding-top: 1.5rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 1rem;
        }

        .price-row.total-row {
            font-size: 1.3rem;
            font-weight: 700;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #667eea;
            color: #667eea;
        }

        .discount-row {
            color: #22c55e;
            font-weight: 600;
        }

        /* Terms checkbox */
        .terms-checkbox {
            margin: 1.5rem 0;
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
        }

        .terms-checkbox input[type="checkbox"] {
            margin-top: 0.2rem;
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .terms-checkbox label {
            flex: 1;
            line-height: 1.5;
            font-size: 0.95rem;
            color: #374151;
        }

        .terms-link {
            color: #667eea;
            text-decoration: underline;
            font-weight: 600;
        }

        .terms-link:hover {
            color: #5a67d8;
        }

        /* Buttons */
        .checkout-button {
            width: 100%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 1.2rem 2rem;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.2);
        }

        .checkout-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.4);
        }

        .checkout-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .back-button {
            display: inline-block;
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.3);
        }

        /* Footer note */
        .footer-note {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            font-size: 0.9rem;
            color: #64748b;
            text-align: center;
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .checkout-form {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .order-summary {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .checkout-container {
                padding: 1rem;
            }

            .product-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .product-image {
                width: 100%;
                height: 150px;
            }

            .discount-form {
                flex-direction: column;
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
    <div class="checkout-container">
        <h1 class="page-title fade-in">
            <i class="fas fa-credit-card"></i>
            Afrekenen
        </h1>
        
        <?php if (!empty($unavailable_products)): ?>
            <div class="unavailable-warning fade-in">
                <h3><i class="fas fa-exclamation-triangle"></i> Let op</h3>
                <p>De volgende springkastelen zijn momenteel niet beschikbaar:</p>
                <ul>
                    <?php foreach ($unavailable_products as $product): ?>
                        <li><?php echo htmlspecialchars($product); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Verwijder deze items uit je reservering om door te gaan.</p>
                <a href="cart.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Terug naar winkelwagen
                </a>
            </div>
        <?php else: ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="checkout.php" class="fade-in">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <!-- Customer Details Section - at the top -->
                <div class="form-section fade-in" style="margin-bottom: 2rem;">
                    <h2><i class="fas fa-user"></i> Jouw gegevens</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label for="name">Naam</label>
                            <input type="text" id="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="email">E-mailadres</label>
                            <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Adres (voor levering springkasteel)</label>
                        <textarea id="address" name="address" class="form-control" rows="3" required placeholder="Straat en huisnummer, postcode en plaats"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="checkout-form">
                    <div class="customer-details">
                        <div class="form-section">
                            <h2><i class="fas fa-comment"></i> Opmerkingen</h2>
                            <div class="form-group">
                                <label for="notes">Eventuele opmerkingen of speciale wensen</label>
                                <textarea id="notes" name="notes" class="form-control" rows="4" placeholder="Bijv. specifieke opbouw wensen, toegankelijkheid, contacttijden..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h2><i class="fas fa-tag"></i> Kortingscode</h2>
                            <?php echo $discount_message; ?>
                            <div class="discount-form">
                                <input type="text" name="discount_code" placeholder="Voer kortingscode in" value="<?php echo htmlspecialchars($discount_code); ?>">
                                <button type="submit" name="apply_discount">
                                    <i class="fas fa-check"></i>
                                    Toepassen
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-summary">
                        <h2><i class="fas fa-list-check"></i> Jouw reservering</h2>
                        
                        <div class="product-list">
                            <?php foreach ($cart_items as $item): 
                                $item_price = $item['calculated_price'];
                                $has_discount = $item['rental_days'] >= 3;
                                $original_price = $has_discount ? $pricing_rules[$item['name']]['1_day'] * $item['rental_days'] : 0;
                                $discount_percentage = $has_discount ? $pricing_rules[$item['name']]['3_plus_discount'] : 0;
                            ?>
                                <div class="product-item">
                                    <?php if ($item['image']): ?>
                                        <img src="images/<?php echo htmlspecialchars($item['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="product-image">
                                    <?php endif; ?>
                                    
                                    <div class="product-details">
                                        <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <span class="product-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                        
                                        <div class="rental-period">
                                            <h4><i class="fas fa-calendar-alt"></i> Verhuurperiode</h4>
                                            <div class="date-range">
                                                <?php echo date('d/m/Y', strtotime($item['start_date'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($item['end_date'])); ?>
                                            </div>
                                            <div class="rental-days">
                                                <?php echo $item['rental_days']; ?> dag<?php echo $item['rental_days'] > 1 ? 'en' : ''; ?>
                                            </div>
                                            
                                            <?php if ($has_discount): ?>
                                                <div class="discount-info">
                                                    <i class="fas fa-percent"></i>
                                                    <?php echo $discount_percentage; ?>% korting vanaf 3 dagen
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="product-price">
                                        <?php if ($has_discount): ?>
                                            <div class="original-price">
                                                €<?php echo number_format($original_price, 0, ',', '.'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="final-price">
                                            €<?php echo number_format($item_price, 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="pricing-summary">
                            <div class="price-row">
                                <span><i class="fas fa-calculator"></i> Subtotaal</span>
                                <span>€<?php echo number_format($total_price, 0, ',', '.'); ?></span>
                            </div>
                            
                            <div class="price-row">
                                <span><i class="fas fa-clock"></i> Totaal dagen</span>
                                <span><?php echo $total_days; ?> dagen</span>
                            </div>
                            
                            <?php if ($discount_amount > 0): ?>
                                <div class="price-row discount-row">
                                    <span><i class="fas fa-tag"></i> Korting (<?php echo htmlspecialchars($discount_code); ?>)</span>
                                    <span>-€<?php echo number_format($discount_amount, 0, ',', '.'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="price-row total-row">
                                <span><i class="fas fa-euro-sign"></i> Totaal</span>
                                <span>€<?php echo number_format($final_total, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        
                        <?php echo $terms_error; ?>
                        
                        <div class="terms-checkbox">
                            <input type="checkbox" id="accept_terms" name="accept_terms" value="1">
                            <label for="accept_terms">
                                Ik heb de <a href="Algemene voorwaarden L&G Fun.pdf" class="terms-link" target="_blank">algemene voorwaarden</a> gelezen en ga hiermee akkoord
                            </label>
                        </div>
                        
                        <button type="submit" name="place_order" class="checkout-button" <?php echo !empty($unavailable_products) ? 'disabled' : ''; ?>>
                            <i class="fas fa-credit-card"></i>
                            Ga naar betaling
                        </button>
                        
                        <div class="footer-note">
                            <i class="fas fa-info-circle"></i>
                            Kleine onderneming onderworpen aan de vrijstellingsregeling, btw niet van toepassing.
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const checkoutForm = document.querySelector('form');
    const addressField = document.getElementById('address');
    const termsCheckbox = document.getElementById('accept_terms');
    const placeOrderButton = document.querySelector('[name="place_order"]');
    
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            // Only validate for place_order, not for apply_discount
            if (e.submitter && e.submitter.name === 'place_order') {
                let hasErrors = false;
                
                // Validate address
                if (!addressField || !addressField.value || addressField.value.trim() === '') {
                    e.preventDefault();
                    hasErrors = true;
                    alert('Voer een geldig adres in voor de levering van het springkasteel.');
                    if (addressField) addressField.focus();
                    return;
                }
                
                // Validate address length (minimum required info)
                if (addressField.value.trim().length < 10) {
                    e.preventDefault();
                    hasErrors = true;
                    alert('Voer een volledig adres in (straat, huisnummer, postcode en plaats).');
                    addressField.focus();
                    return;
                }
                
                // Validate terms
                if (!termsCheckbox || !termsCheckbox.checked) {
                    e.preventDefault();
                    alert('U moet de algemene voorwaarden accepteren om door te gaan.');
                    if (termsCheckbox) termsCheckbox.focus();
                    return;
                }
                
                // Show loading state
                if (!hasErrors && placeOrderButton) {
                    placeOrderButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bestelling verwerken...';
                    placeOrderButton.disabled = true;
                }
            }
        });
    }
    
    // Terms checkbox interaction
    if (termsCheckbox && placeOrderButton) {
        termsCheckbox.addEventListener('change', function() {
            if (this.checked) {
                placeOrderButton.style.opacity = '1';
                placeOrderButton.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    placeOrderButton.style.transform = 'scale(1)';
                }, 200);
            } else {
                placeOrderButton.style.opacity = '0.7';
            }
        });
        
        // Initial state
        if (!termsCheckbox.checked) {
            placeOrderButton.style.opacity = '0.7';
        }
    }
    
    // Address field auto-formatting and validation
    if (addressField) {
        addressField.addEventListener('blur', function() {
            // Basic formatting: capitalize first letter of each word
            const words = this.value.toLowerCase().split(' ');
            const formatted = words.map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
            this.value = formatted;
        });
        
        // Real-time validation feedback
        addressField.addEventListener('input', function() {
            const value = this.value.trim();
            const isValid = value.length >= 10;
            
            if (value.length > 0) {
                this.style.borderColor = isValid ? '#22c55e' : '#ef4444';
                this.style.boxShadow = isValid ? 
                    '0 0 0 3px rgba(34, 197, 94, 0.1)' : 
                    '0 0 0 3px rgba(239, 68, 68, 0.1)';
            } else {
                this.style.borderColor = '#e5e7eb';
                this.style.boxShadow = 'none';
            }
        });
    }
    
    // Discount code interaction
    const discountInput = document.querySelector('input[name="discount_code"]');
    const discountButton = document.querySelector('button[name="apply_discount"]');
    
    if (discountInput && discountButton) {
        discountInput.addEventListener('input', function() {
            const hasCode = this.value.trim().length > 0;
            discountButton.style.opacity = hasCode ? '1' : '0.6';
            discountButton.disabled = !hasCode;
        });
        
        // Initial state
        const hasCode = discountInput.value.trim().length > 0;
        discountButton.style.opacity = hasCode ? '1' : '0.6';
        discountButton.disabled = !hasCode;
        
        // Loading state for discount application
        discountButton.addEventListener('click', function() {
            if (!this.disabled) {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Controleren...';
                this.disabled = true;
            }
        });
    }
    
    // Smooth animations
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Auto-scroll to errors
    const errorAlert = document.querySelector('.alert-danger');
    if (errorAlert) {
        errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Calculate and display savings
    const discountRows = document.querySelectorAll('.discount-info');
    discountRows.forEach(row => {
        row.style.animation = 'pulse 2s infinite';
    });
});

// Add pulse animation for discount highlights
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>