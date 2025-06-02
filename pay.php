<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=pay.php");
    exit;
}

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: cart.php?error=no_order");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$order_id = (int)$_GET['order_id'];

// Mollie API configuration
$mollie_api_key = 'test_wSuCC9DxhnJrBzVHmaKQC7JjByFwSS';

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, u.name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: cart.php?error=order_not_found");
    exit;
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.image 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to create Mollie payment
function createMolliePayment($order, $mollie_api_key) {
    $amount = number_format($order['total_price'], 2, '.', '');
    
    $payment_data = [
        'amount' => [
            'currency' => 'EUR',
            'value' => $amount
        ],
        'description' => 'Springkasteel verhuur - Bestelling #' . $order['id'],
        'redirectUrl' => 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/payment_success.php?order_id=' . $order['id'],
        'webhookUrl' => 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/payment_webhook.php',
        'metadata' => [
            'order_id' => $order['id'],
            'user_id' => $order['user_id']
        ],
        'method' => ['ideal', 'creditcard', 'bancontact', 'sofort', 'paypal']
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.mollie.com/v2/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payment_data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $mollie_api_key,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code === 201) {
        return json_decode($response, true);
    }
    
    return false;
}

// Handle payment creation
$payment_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment'])) {
    $payment = createMolliePayment($order, $mollie_api_key);
    
    if ($payment && isset($payment['_links']['checkout']['href'])) {
        // Save payment ID to order
        $stmt = $pdo->prepare("UPDATE orders SET payment_id = ?, payment_status = 'pending' WHERE id = ?");
        $stmt->execute([$payment['id'], $order_id]);
        
        // Redirect to Mollie payment page
        header("Location: " . $payment['_links']['checkout']['href']);
        exit;
    } else {
        $payment_error = 'Er is een fout opgetreden bij het verwerken van de betaling. Probeer het opnieuw.';
    }
}

// Check if payment was already created
$stmt = $pdo->prepare("SELECT payment_id, payment_status FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$payment_info = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Betaling - L&G Fun Springkastelen</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            line-height: 1.6;
        }

        /* Header styling */
        header {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
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
        .payment-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .payment-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        /* Order summary */
        .order-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid #667eea;
        }

        .order-summary h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-summary h3 i {
            color: #667eea;
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
            gap: 1rem;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.3rem;
        }

        .item-period {
            font-size: 0.9rem;
            color: #64748b;
        }

        .item-price {
            font-weight: 700;
            color: #667eea;
            font-size: 1.1rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 0 0 0;
            margin-top: 1rem;
            border-top: 2px solid #667eea;
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        /* Payment methods */
        .payment-methods {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .payment-methods h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payment-methods h3 i {
            color: #667eea;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .payment-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: #667eea;
            background: #f0f9ff;
            transform: translateY(-2px);
        }

        .payment-option i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .payment-option span {
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
            text-align: center;
        }

        /* Security info */
        .security-info {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .security-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #166534;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .security-info h4 i {
            color: #22c55e;
        }

        .security-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .security-feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #166534;
        }

        .security-feature i {
            color: #22c55e;
            font-size: 0.9rem;
        }

        /* Payment button */
        .payment-button {
            width: 100%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 1.5rem 2rem;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.3);
            margin-bottom: 1rem;
        }

        .payment-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(34, 197, 94, 0.4);
        }

        .payment-button:active {
            transform: translateY(-1px);
        }

        .back-button {
            width: 100%;
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-align: center;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.3);
        }

        /* Error message */
        .error-message {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            border: 1px solid #fecaca;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-message i {
            font-size: 1.2rem;
        }

        /* Mollie branding */
        .mollie-info {
            text-align: center;
            margin-top: 2rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            color: #64748b;
            font-size: 0.9rem;
        }

        .mollie-logo {
            display: inline-block;
            background: #5a67d8;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            margin: 0 0.3rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .payment-container {
                padding: 1rem;
                margin: 1rem auto;
            }

            .payment-card {
                padding: 2rem 1.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .payment-options {
                grid-template-columns: repeat(2, 1fr);
            }

            .security-features {
                grid-template-columns: 1fr;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .item-image {
                width: 100%;
                height: 120px;
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

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
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
    <div class="payment-container">
        <div class="payment-card fade-in">
            <h1 class="page-title">
                <i class="fas fa-credit-card"></i>
                Veilig Betalen
            </h1>

            <?php if ($payment_error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($payment_error); ?>
                </div>
            <?php endif; ?>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3><i class="fas fa-receipt"></i> Jouw bestelling #<?php echo $order['id']; ?></h3>
                
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <?php if ($item['image']): ?>
                            <img src="images/<?php echo htmlspecialchars($item['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                 class="item-image">
                        <?php endif; ?>
                        
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <?php if ($item['start_date'] && $item['end_date']): ?>
                                <div class="item-period">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($item['start_date'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($item['end_date'])); ?>
                                    (<?php echo $item['rental_days']; ?> dagen)
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="item-price">
                            €<?php echo number_format($item['price'], 0, ',', '.'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="total-row">
                    <span>Totaal te betalen:</span>
                    <span>€<?php echo number_format($order['total_price'], 0, ',', '.'); ?></span>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="payment-methods">
                <h3><i class="fas fa-credit-card"></i> Beschikbare betaalmethoden</h3>
                
                <div class="payment-options">
                    <div class="payment-option">
                        <i class="fas fa-university"></i>
                        <span>iDEAL</span>
                    </div>
                    <div class="payment-option">
                        <i class="fas fa-credit-card"></i>
                        <span>Creditcard</span>
                    </div>
                    <div class="payment-option">
                        <i class="fab fa-paypal"></i>
                        <span>PayPal</span>
                    </div>
                    <div class="payment-option">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Bancontact</span>
                    </div>
                    <div class="payment-option">
                        <i class="fas fa-bolt"></i>
                        <span>SOFORT</span>
                    </div>
                </div>

                <form method="POST" action="">
                    <button type="submit" name="create_payment" class="payment-button pulse">
                        <i class="fas fa-shield-alt"></i>
                        Veilig betalen met <span class="mollie-logo">Mollie</span>
                    </button>
                </form>
            </div>

            <!-- Security Information -->
            <div class="security-info">
                <h4><i class="fas fa-shield-alt"></i> Veilig betalen</h4>
                <div class="security-features">
                    <div class="security-feature">
                        <i class="fas fa-lock"></i>
                        <span>256-bit SSL versleuteling</span>
                    </div>
                    <div class="security-feature">
                        <i class="fas fa-certificate"></i>
                        <span>PCI DSS gecertificeerd</span>
                    </div>
                    <div class="security-feature">
                        <i class="fas fa-undo"></i>
                        <span>Geld-terug-garantie</span>
                    </div>
                    <div class="security-feature">
                        <i class="fas fa-headset"></i>
                        <span>24/7 klantenservice</span>
                    </div>
                </div>
            </div>

            <a href="checkout.php?order_id=<?php echo $order_id; ?>" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Terug naar bestelling
            </a>

            <div class="mollie-info">
                Betalingen worden veilig verwerkt door <span class="mollie-logo">Mollie</span><br>
                <small>Je wordt doorgestuurd naar een beveiligde betalingspagina</small>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form submission handling
    const paymentForm = document.querySelector('form');
    const paymentButton = document.querySelector('.payment-button');
    
    if (paymentForm && paymentButton) {
        paymentForm.addEventListener('submit', function(e) {
            // Show loading state
            paymentButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Doorverwijzen naar betaling...';
            paymentButton.disabled = true;
            paymentButton.style.opacity = '0.7';
        });
    }
    
    // Animate elements
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.2}s`;
    });
    
    // Payment method hover effects
    const paymentOptions = document.querySelectorAll('.payment-option');
    paymentOptions.forEach(option => {
        option.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.05)';
        });
        
        option.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-2px) scale(1)';
        });
    });
    
    // Security features animation
    const securityFeatures = document.querySelectorAll('.security-feature');
    securityFeatures.forEach((feature, index) => {
        setTimeout(() => {
            feature.style.opacity = '1';
            feature.style.transform = 'translateX(0)';
        }, index * 200);
        
        feature.style.opacity = '0';
        feature.style.transform = 'translateX(-20px)';
        feature.style.transition = 'all 0.5s ease';
    });
});

// Add some visual feedback for successful payment creation
function showPaymentSuccess() {
    const button = document.querySelector('.payment-button');
    if (button) {
        button.innerHTML = '<i class="fas fa-check"></i> Doorverwijzen...';
        button.style.background = 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)';
    }
}
</script>

</body>
</html>