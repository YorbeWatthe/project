<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: user_dashboard.php?error=no_order");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$order_id = (int)$_GET['order_id'];

// Mollie API configuration
$mollie_api_key = 'test_wSuCC9DxhnJrBzVHmaKQC7JjByFwSS';

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, u.name, u.email, u.address 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: user_dashboard.php?error=order_not_found");
    exit;
}

// Get order items with rental details
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.image, p.category 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to check payment status with Mollie
function checkMolliePaymentStatus($payment_id, $mollie_api_key) {
    if (!$payment_id) return null;
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.mollie.com/v2/payments/' . $payment_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $mollie_api_key
        ]
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

// Check payment status
$payment_status = 'unknown';
$payment_details = null;

if ($order['payment_id']) {
    $payment_details = checkMolliePaymentStatus($order['payment_id'], $mollie_api_key);
    
    if ($payment_details) {
        $payment_status = $payment_details['status'];
        
        // Update order status in database if needed
        if ($payment_status === 'paid' && $order['payment_status'] !== 'paid') {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = 'confirmed' WHERE id = ?");
            $stmt->execute([$order_id]);
            $order['payment_status'] = 'paid';
            $order['status'] = 'confirmed';
        } elseif (in_array($payment_status, ['failed', 'canceled', 'expired']) && $order['payment_status'] !== $payment_status) {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $order_id]);
            $order['payment_status'] = $payment_status;
        }
    }
}

// Send confirmation email if payment is successful (simplified version)
if ($payment_status === 'paid' && !isset($_SESSION['email_sent_' . $order_id])) {
    // Mark email as sent to prevent duplicate sends
    $_SESSION['email_sent_' . $order_id] = true;
    
    // Here you would integrate with your email system
    // For now, we'll just log it
    error_log("Confirmation email should be sent for order #$order_id to " . $order['email']);
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $payment_status === 'paid' ? 'Betaling Geslaagd' : 'Betaling Status'; ?> - L&G Fun</title>
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
        .success-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .success-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        /* Status indicators */
        .status-success {
            color: #22c55e;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #bbf7d0;
        }

        .status-failed {
            color: #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 2px solid #fecaca;
        }

        .status-pending {
            color: #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 2px solid #fed7aa;
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }

        .status-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .status-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Order details */
        .order-details {
            background: #f8fafc;
            border-radius: 16px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
            border: 1px solid #e5e7eb;
        }

        .order-details h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-details h3 i {
            color: #667eea;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.3rem;
        }

        .info-value {
            font-weight: 600;
            color: #334155;
        }

        /* Product list */
        .product-list {
            margin-top: 1.5rem;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
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

        .rental-info {
            font-size: 0.9rem;
            color: #64748b;
        }

        .product-price {
            font-weight: 700;
            color: #667eea;
            font-size: 1.1rem;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }

        /* Next steps */
        .next-steps {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }

        .next-steps h4 {
            color: #0369a1;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .next-steps h4 i {
            color: #0284c7;
        }

        .steps-list {
            list-style: none;
            padding: 0;
        }

        .steps-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
            color: #0369a1;
        }

        .steps-list li i {
            color: #0284c7;
            margin-top: 0.1rem;
            font-size: 0.9rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .success-container {
                padding: 1rem;
                margin: 1rem auto;
            }

            .success-card {
                padding: 2rem 1.5rem;
            }

            .status-title {
                font-size: 2rem;
            }

            .status-icon {
                font-size: 3rem;
            }

            .order-info {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .product-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .product-image {
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

        /* Animations */
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.8s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1000;
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
    <div class="success-container">
        <div class="success-card fade-in <?php 
            echo $payment_status === 'paid' ? 'status-success' : 
                ($payment_status === 'failed' || $payment_status === 'canceled' || $payment_status === 'expired' ? 'status-failed' : 'status-pending'); 
        ?>">
            
            <?php if ($payment_status === 'paid'): ?>
                <div class="status-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="status-title">Betaling Geslaagd!</h1>
                <p class="status-message">
                    Gefeliciteerd! Je betaling is succesvol verwerkt en je springkasteel reservering is bevestigd.<br>
                    Je ontvangt binnenkort een bevestigingsmail met alle details.
                </p>
                
            <?php elseif ($payment_status === 'pending' || $payment_status === 'open'): ?>
                <div class="status-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h1 class="status-title">Betaling Wordt Verwerkt</h1>
                <p class="status-message">
                    Je betaling wordt momenteel verwerkt. Dit kan enkele minuten duren.<br>
                    Je ontvangt een bevestiging zodra de betaling is voltooid.
                </p>
                
            <?php else: ?>
                <div class="status-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h1 class="status-title">Betaling Mislukt</h1>
                <p class="status-message">
                    Er is een probleem opgetreden met je betaling. Je reservering is nog niet bevestigd.<br>
                    Probeer het opnieuw of neem contact met ons op.
                </p>
            <?php endif; ?>

            <!-- Order Details -->
            <div class="order-details">
                <h3><i class="fas fa-receipt"></i> Besteldetails</h3>
                
                <div class="order-info">
                    <div class="info-item">
                        <div class="info-label">Bestelnummer</div>
                        <div class="info-value">#<?php echo $order['id']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Totaalbedrag</div>
                        <div class="info-value">€<?php echo number_format($order['total_price'], 2, ',', '.'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Betalingsstatus</div>
                        <div class="info-value">
                            <?php 
                            echo $payment_status === 'paid' ? 'Betaald' : 
                                ($payment_status === 'pending' ? 'In behandeling' : 
                                ($payment_status === 'failed' ? 'Mislukt' : 'Onbekend'));
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Besteldatum</div>
                        <div class="info-value"><?php echo date('d-m-Y H:i', strtotime($order['created_at'])); ?></div>
                    </div>
                </div>

                <div class="product-list">
                    <h4 style="margin-bottom: 1rem; color: #334155;">Gereserveerde springkastelen:</h4>
                    <?php foreach ($order_items as $item): ?>
                        <div class="product-item">
                            <?php if ($item['image']): ?>
                                <img src="images/<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                     class="product-image">
                            <?php endif; ?>
                            
                            <div class="product-details">
                                <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <span class="product-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                
                                <?php if ($item['start_date'] && $item['end_date']): ?>
                                    <div class="rental-info">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($item['start_date'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($item['end_date'])); ?>
                                        (<?php echo $item['rental_days']; ?> dagen)
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-price">
                                €<?php echo number_format($item['price'], 2, ',', '.'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($payment_status === 'paid'): ?>
                <!-- Next Steps for Successful Payment -->
                <div class="next-steps">
                    <h4><i class="fas fa-list-check"></i> Wat gebeurt er nu?</h4>
                    <ul class="steps-list">
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>Je ontvangt binnen 24 uur een bevestigingsmail met alle details</span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <span>We nemen contact met je op om de levering en opbouw af te spreken</span>
                        </li>
                        <li>
                            <i class="fas fa-truck"></i>
                            <span>Het springkasteel wordt op de afgesproken datum geleverd en opgebouwd</span>
                        </li>
                        <li>
                            <i class="fas fa-party-horn"></i>
                            <span>Geniet van je feest! Na afloop halen we alles weer op</span>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($payment_status === 'paid'): ?>
                    <a href="user_dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        Terug naar Dashboard
                    </a>
                    <a href="mailto:info@lgfun.be?subject=Bestelling #<?php echo $order['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-envelope"></i>
                        Contact Opnemen
                    </a>
                <?php elseif ($payment_status === 'pending' || $payment_status === 'open'): ?>
                    <button onclick="location.reload()" class="btn btn-primary">
                        <i class="fas fa-refresh"></i>
                        Status Vernieuwen
                    </button>
                    <a href="user_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i>
                        Terug naar Dashboard
                    </a>
                <?php else: ?>
                    <a href="pay.php?order_id=<?php echo $order_id; ?>" class="btn btn-success">
                        <i class="fas fa-redo"></i>
                        Opnieuw Proberen
                    </a>
                    <a href="cart.php" class="btn btn-secondary">
                        <i class="fas fa-shopping-cart"></i>
                        Terug naar Winkelwagen
                    </a>
                    <a href="mailto:info@lgfun.be?subject=Betalingsprobleem Bestelling #<?php echo $order['id']; ?>" class="btn btn-danger">
                        <i class="fas fa-headset"></i>
                        Hulp Nodig?
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php if ($payment_status === 'paid'): ?>
<!-- Confetti Animation for Success -->
<div class="confetti" id="confetti"></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate elements
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.2}s`;
    });
    
    <?php if ($payment_status === 'paid'): ?>
    // Confetti animation for successful payment
    function createConfetti() {
        const confettiContainer = document.getElementById('confetti');
        if (!confettiContainer) return;
        
        for (let i = 0; i < 100; i++) {
            const confettiPiece = document.createElement('div');
            confettiPiece.style.position = 'absolute';
            confettiPiece.style.width = '10px';
            confettiPiece.style.height = '10px';
            confettiPiece.style.backgroundColor = ['#667eea', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6'][Math.floor(Math.random() * 5)];
            confettiPiece.style.left = Math.random() * 100 + '%';
            confettiPiece.style.animationDuration = (Math.random() * 3 + 2) + 's';
            confettiPiece.style.animationName = 'confettiFall';
            confettiPiece.style.animationTimingFunction = 'ease-out';
            confettiPiece.style.animationFillMode = 'forwards';
            
            confettiContainer.appendChild(confettiPiece);
            
            setTimeout(() => {
                confettiPiece.remove();
            }, 5000);
        }
    }
    
    // Add confetti animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes confettiFall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Start confetti
    createConfetti();
    <?php endif; ?>
    
    <?php if ($payment_status === 'pending' || $payment_status === 'open'): ?>
    // Auto-refresh for pending payments
    setTimeout(function() {
        location.reload();
    }, 30000); // Refresh every 30 seconds
    
    // Show countdown
    let countdown = 30;
    const refreshButton = document.querySelector('.btn-primary');
    if (refreshButton) {
        const originalText = refreshButton.innerHTML;
        const timer = setInterval(function() {
            countdown--;
            refreshButton.innerHTML = `<i class="fas fa-refresh"></i> Automatisch vernieuwen in ${countdown}s`;
            
            if (countdown <= 0) {
                clearInterval(timer);
                refreshButton.innerHTML = originalText;
            }
        }, 1000);
    }
    <?php endif; ?>
});
</script>

</body>
</html>