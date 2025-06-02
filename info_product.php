<?php 
session_start();
include 'includes/db.php';

if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    die("Geen product geselecteerd.");
}

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = $_SESSION['is_admin'] ?? false;
$product_id = (int) $_GET['product_id'];

if ($product_id <= 0) {
    die("Ongeldige product ID.");
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :product_id");
$stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product niet gevonden.");
}

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_date']) && isset($_POST['end_date'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $discount_code = $_POST['discount_code'] ?? '';
    
    // Validate dates
    if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error_message = "Startdatum kan niet in het verleden liggen.";
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "Einddatum moet na startdatum liggen.";
    } else {
        // Calculate number of days
        $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
        
        // Check availability
        if (!checkAvailability($pdo, $product_id, $start_date, $end_date)) {
            $error_message = "Het springkasteel is niet beschikbaar voor de geselecteerde periode.";
        } else {
            // Calculate price
            $base_price = calculatePrice($product['name'], $days, $pricing_rules);
            
            // Apply discount code if provided
            $discount_percentage = 0;
            if ($discount_code) {
                $stmt = $pdo->prepare("SELECT * FROM discount_codes WHERE code = ? AND expiration_date >= CURDATE() AND usage_count < 1");
                $stmt->execute([$discount_code]);
                $discount = $stmt->fetch(PDO::FETCH_ASSOC);
                $discount_percentage = $discount ? $discount['discount_percentage'] : 0;
            }
            
            $final_price = $base_price * (1 - $discount_percentage / 100);
            
            // Add to cart_items
            $stmt = $pdo->prepare("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ? AND start_date = ? AND end_date = ?");
            $stmt->execute([$user_id, $product_id, $start_date, $end_date]);
            $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cart_item) {
                $error_message = "Deze reservering staat al in uw winkelwagen.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity, start_date, end_date, rental_days) VALUES (?, ?, 1, ?, ?, ?)");
                $stmt->execute([$user_id, $product_id, $start_date, $end_date, $days]);
                
                header("Location: cart.php");
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - L&G Fun</title>
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

        /* Header styling - identical to filter page */
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
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Breadcrumb Navigation */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: #64748b;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #5a67d8;
        }

        .breadcrumb i {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* Product Layout */
        .product-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        /* Product Image Gallery Section */
        .product-image-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .image-gallery {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .main-image-container {
            position: relative;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            cursor: pointer;
            background: #f8fafc;
        }

        .main-image-container img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .main-image-container:hover img {
            transform: scale(1.05);
        }

        .image-zoom-overlay {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .main-image-container:hover .image-zoom-overlay {
            opacity: 1;
        }

        .thumbnail-gallery {
            display: flex;
            gap: 0.8rem;
            overflow-x: auto;
            padding: 0.5rem 0;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        .thumbnail-gallery::-webkit-scrollbar {
            height: 4px;
        }

        .thumbnail-gallery::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 2px;
        }

        .thumbnail-gallery::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        .thumbnail {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .thumbnail:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .thumbnail.active {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.3);
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .thumbnail:hover img {
            transform: scale(1.1);
        }

        .image-counter {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #64748b;
            background: #f8fafc;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin: 1rem auto 0;
        }

        /* Lightbox styles */
        .lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            padding: 2rem;
            box-sizing: border-box;
        }

        .lightbox.active {
            display: flex;
        }

        .lightbox-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            overflow: hidden;
        }

        .lightbox img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .lightbox-close {
            position: absolute;
            top: -3rem;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .lightbox-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .lightbox-nav:hover {
            background: rgba(0, 0, 0, 0.8);
            transform: translateY(-50%) scale(1.1);
        }

        .lightbox-prev {
            left: -4rem;
        }

        .lightbox-next {
            right: -4rem;
        }

        .lightbox-counter {
            position: absolute;
            bottom: -3rem;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            background: rgba(0, 0, 0, 0.5);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        /* Product Info Section */
        .product-info-section {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .product-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .product-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .product-description {
            font-size: 1.1rem;
            color: #475569;
            line-height: 1.7;
        }

        /* Pricing Table */
        .pricing-table {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .pricing-table h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pricing-table h3 i {
            color: #667eea;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .price-option {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .price-option:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
        }

        .price-option.best-value {
            border-color: #22c55e;
            background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);
            position: relative;
        }

        .price-option.best-value::before {
            content: 'Beste waarde';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #22c55e;
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .price-option h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .price-option .price {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .price-option .discount {
            color: #22c55e;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Product Details Cards */
        .product-details {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .detail-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-card h3 i {
            color: #667eea;
            font-size: 1.1rem;
        }

        .features-list {
            list-style: none;
            padding: 0;
        }

        .features-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .features-list li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #22c55e;
            font-size: 0.9rem;
        }

        /* Date Selection Form */
        .booking-form-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 3rem;
        }

        .booking-form-section h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .booking-form-section h3 i {
            color: #667eea;
        }

        .booking-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group label i {
            color: #667eea;
            font-size: 0.9rem;
        }

        .form-group input {
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .price-display {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #667eea;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin: 1rem 0;
        }

        .price-display h4 {
            font-size: 1.2rem;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .price-display .total-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .price-display .price-breakdown {
            font-size: 0.9rem;
            color: #64748b;
        }

        .add-to-cart-btn {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .add-to-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .add-to-cart-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Error/Success Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        .alert i {
            font-size: 1.1rem;
        }

        /* Review Section */
        .review-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .review-section h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .review-section h3 i {
            color: #667eea;
        }

        .review-form {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .review-form textarea {
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .review-form textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .submit-review-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            justify-self: start;
        }

        .submit-review-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.3);
        }

        /* Reviews Display */
        .reviews-container {
            margin-top: 2rem;
        }

        .review {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
        }

        .review-author {
            font-weight: 600;
            color: #334155;
        }

        .review-date {
            font-size: 0.9rem;
            color: #64748b;
        }

        .review-content {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }

        .review-actions {
            margin-top: 1rem;
        }

        .delete-review-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .delete-review-btn:hover {
            background: #dc2626;
        }

        .no-reviews {
            text-align: center;
            color: #64748b;
            font-style: italic;
            padding: 2rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .product-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .main-container {
                padding: 1rem;
            }

            .thumbnail-gallery {
                gap: 0.5rem;
            }

            .thumbnail {
                width: 60px;
                height: 60px;
            }

            .main-image-container img {
                height: 300px;
            }

            .lightbox-nav {
                display: none;
            }

            .lightbox-close {
                top: -2rem;
                font-size: 1.5rem;
            }

            .pricing-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
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

            .product-title {
                font-size: 2rem;
            }

            .booking-form {
                grid-template-columns: 1fr;
            }

            .breadcrumb {
                font-size: 0.8rem;
            }
        }

        /* Animation for page load */
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
                <li><a href="contact.php">Contact</a></li>
                <li><a href="loguit.php">Uitloggen</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="main-container">
    <!-- Breadcrumb Navigation -->
    <nav class="breadcrumb">
        <a href="user_dashboard.php">
            <i class="fas fa-home"></i> Home
        </a>
        <i class="fas fa-chevron-right"></i>
        <a href="filter_products.php">Producten</a>
        <i class="fas fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </nav>

    <!-- Error/Success Messages -->
    <?php if (isset($error_message)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <!-- Product Main Layout -->
    <div class="product-layout fade-in">
        <!-- Product Image Gallery -->
        <div class="product-image-section">
            <?php
            // Prepare main image and additional images
            $main_image = $product['image'];
            $additional_images = [];
            
            if (!empty($product['additional_images'])) {
                $additional_images = array_filter(explode(',', $product['additional_images']));
            }
            
            // Combine all images (main image first)
            $all_images = array_merge([$main_image], $additional_images);
            ?>
            
            <div class="image-gallery">
                <!-- Main Image Display -->
                <div class="main-image-container">
                    <img id="mainImage" 
                         src="images/<?php echo htmlspecialchars($all_images[0]); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <div class="image-zoom-overlay">
                        <i class="fas fa-search-plus"></i>
                        <span>Klik om te vergroten</span>
                    </div>
                </div>
                
                <!-- Thumbnail Gallery -->
                <?php if (count($all_images) > 1): ?>
                <div class="thumbnail-gallery">
                    <?php foreach ($all_images as $index => $image): ?>
                        <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                             onclick="changeMainImage('images/<?php echo htmlspecialchars($image); ?>', this)">
                            <img src="images/<?php echo htmlspecialchars($image); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?> - Afbeelding <?php echo $index + 1; ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Image Counter -->
            <?php if (count($all_images) > 1): ?>
            <div class="image-counter">
                <span id="currentImageIndex">1</span> / <?php echo count($all_images); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Product Info -->
        <div class="product-info-section">
            <div class="product-header">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <?php if (!empty($product['description'])): ?>
                    <p class="product-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                <?php endif; ?>
            </div>

            <!-- Product Details -->
            <div class="product-details">
                <?php if (!empty($product['features'])): ?>
                    <div class="detail-card">
                        <h3><i class="fas fa-star"></i> Kenmerken</h3>
                        <ul class="features-list">
                            <?php foreach (explode("\n", $product['features']) as $feature): ?>
                                <li><?php echo htmlspecialchars(trim($feature)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($product['dimensions'])): ?>
                    <div class="detail-card">
                        <h3><i class="fas fa-ruler-combined"></i> Afmetingen</h3>
                        <p><?php echo htmlspecialchars($product['dimensions']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($product['detailed_description'])): ?>
                    <div class="detail-card">
                        <h3><i class="fas fa-info-circle"></i> Gedetailleerde beschrijving</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['detailed_description'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pricing Table -->
    <?php if (isset($pricing_rules[$product['name']])): ?>
    <div class="pricing-table fade-in">
        <h3><i class="fas fa-euro-sign"></i> Prijzen</h3>
        <div class="pricing-grid">
            <div class="price-option">
                <h4>1 Dag</h4>
                <div class="price">€<?php echo number_format($pricing_rules[$product['name']]['1_day'], 0, ',', '.'); ?></div>
            </div>
            <div class="price-option">
                <h4>2 Dagen</h4>
                <div class="price">€<?php echo number_format($pricing_rules[$product['name']]['2_days'], 0, ',', '.'); ?></div>
            </div>
            <div class="price-option best-value">
                <h4>3+ Dagen</h4>
                <div class="price">Vanaf €<?php echo number_format(calculatePrice($product['name'], 3, $pricing_rules), 0, ',', '.'); ?></div>
                <div class="discount"><?php echo $pricing_rules[$product['name']]['3_plus_discount']; ?>% korting</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Booking Form -->
    <div class="booking-form-section fade-in">
        <h3><i class="fas fa-calendar-alt"></i> Reserveer je springkasteel</h3>
        <form method="POST" class="booking-form" id="bookingForm">
            <div class="form-group">
                <label><i class="fas fa-calendar-day"></i> Startdatum</label>
                <input type="date" name="start_date" id="startDate" 
                       min="<?php echo date('Y-m-d'); ?>" 
                       value="<?php echo $_POST['start_date'] ?? ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-calendar-day"></i> Einddatum</label>
                <input type="date" name="end_date" id="endDate" 
                       min="<?php echo date('Y-m-d'); ?>" 
                       value="<?php echo $_POST['end_date'] ?? ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Kortingscode (optioneel)</label>
                <input type="text" name="discount_code" id="discountCode" 
                       placeholder="Voer kortingscode in"
                       value="<?php echo $_POST['discount_code'] ?? ''; ?>">
            </div>

            <div class="price-display" id="priceDisplay" style="display: none;">
                <h4>Totaalprijs</h4>
                <div class="total-price" id="totalPrice">€0</div>
                <div class="price-breakdown" id="priceBreakdown"></div>
            </div>

            <button type="submit" class="add-to-cart-btn" id="submitBtn" disabled>
                <i class="fas fa-shopping-cart"></i>
                Reservering toevoegen aan winkelwagen
            </button>
        </form>
    </div>

    <!-- Review Section -->
    <div class="review-section fade-in">
        <h3><i class="fas fa-comment-dots"></i> Laat een review achter</h3>
        <form method="POST" action="submit_review.php" class="review-form">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> Naam</label>
                <input type="text" name="customer_name" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-edit"></i> Review</label>
                <textarea name="review" placeholder="Deel je ervaring met dit product..." required></textarea>
            </div>
            
            <button type="submit" class="submit-review-btn">
                <i class="fas fa-paper-plane"></i>
                Verstuur review
            </button>
        </form>

        <h3><i class="fas fa-star"></i> Beoordelingen</h3>
        <div class="reviews-container">
            <?php
            $stmt = $pdo->prepare("SELECT * FROM product_reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC");
            $stmt->execute([$product_id]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($reviews) > 0):
                foreach ($reviews as $review):
            ?>
                <div class="review">
                    <div class="review-header">
                        <span class="review-author">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($review['customer_name']); ?>
                        </span>
                        <span class="review-date">
                            <i class="fas fa-calendar"></i>
                            <?php echo date("d-m-Y H:i", strtotime($review['created_at'])); ?>
                        </span>
                    </div>
                    <div class="review-content">
                        <?php echo nl2br(htmlspecialchars($review['review'])); ?>
                    </div>
                    <?php if ($is_admin): ?>
                        <div class="review-actions">
                            <a href="moderate_review.php?review_id=<?php echo $review['id']; ?>&action=delete" 
                               class="delete-review-btn"
                               onclick="return confirm('Weet je zeker dat je deze beoordeling wilt verwijderen?')">
                                <i class="fas fa-trash"></i> Verwijderen
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; else: ?>
                <div class="no-reviews">
                    <i class="fas fa-comment-slash" style="font-size: 2rem; margin-bottom: 1rem; color: #94a3b8;"></i>
                    <p>Er zijn nog geen beoordelingen voor dit product.</p>
                    <p>Wees de eerste om een review achter te laten!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lightbox for image zoom -->
<div class="lightbox" id="imageLightbox">
    <div class="lightbox-content">
        <button class="lightbox-close" onclick="closeLightbox()">
            <i class="fas fa-times"></i>
        </button>
        <button class="lightbox-nav lightbox-prev" onclick="previousImage()">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="lightbox-nav lightbox-next" onclick="nextImage()">
            <i class="fas fa-chevron-right"></i>
        </button>
        <img id="lightboxImage" src="" alt="">
        <div class="lightbox-counter">
            <span id="lightboxCounter"></span>
        </div>
    </div>
</div>

<script>
// Image gallery functionality
let currentImageIndex = 0;
let allImages = <?php echo json_encode(array_map(function($img) { return 'images/' . $img; }, $all_images)); ?>;

function changeMainImage(imageSrc, thumbnailElement) {
    // Update main image
    const mainImage = document.getElementById('mainImage');
    mainImage.src = imageSrc;
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
    thumbnailElement.classList.add('active');
    
    // Update counter
    const thumbnails = Array.from(document.querySelectorAll('.thumbnail'));
    currentImageIndex = thumbnails.indexOf(thumbnailElement);
    updateImageCounter();
}

function updateImageCounter() {
    const counter = document.getElementById('currentImageIndex');
    if (counter) {
        counter.textContent = currentImageIndex + 1;
    }
}

// Lightbox functionality
function openLightbox(imageIndex = null) {
    const lightbox = document.getElementById('imageLightbox');
    const lightboxImage = document.getElementById('lightboxImage');
    
    if (imageIndex !== null) {
        currentImageIndex = imageIndex;
    }
    
    lightboxImage.src = allImages[currentImageIndex];
    updateLightboxCounter();
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('imageLightbox');
    lightbox.classList.remove('active');
    document.body.style.overflow = 'auto';
}

function nextImage() {
    currentImageIndex = (currentImageIndex + 1) % allImages.length;
    const lightboxImage = document.getElementById('lightboxImage');
    lightboxImage.src = allImages[currentImageIndex];
    updateLightboxCounter();
    updateMainImageFromLightbox();
}

function previousImage() {
    currentImageIndex = (currentImageIndex - 1 + allImages.length) % allImages.length;
    const lightboxImage = document.getElementById('lightboxImage');
    lightboxImage.src = allImages[currentImageIndex];
    updateLightboxCounter();
    updateMainImageFromLightbox();
}

function updateLightboxCounter() {
    const counter = document.getElementById('lightboxCounter');
    counter.textContent = `${currentImageIndex + 1} / ${allImages.length}`;
}

function updateMainImageFromLightbox() {
    const mainImage = document.getElementById('mainImage');
    mainImage.src = allImages[currentImageIndex];
    
    // Update active thumbnail
    const thumbnails = document.querySelectorAll('.thumbnail');
    thumbnails.forEach((thumb, index) => {
        thumb.classList.toggle('active', index === currentImageIndex);
    });
    
    updateImageCounter();
}

// Pricing functionality
const pricingRules = <?php echo json_encode($pricing_rules); ?>;
const productName = <?php echo json_encode($product['name']); ?>;

function calculateDays(startDate, endDate) {
    if (!startDate || !endDate) return 0;
    const start = new Date(startDate);
    const end = new Date(endDate);
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    return diffDays;
}

function calculatePrice(days) {
    if (!pricingRules[productName] || days <= 0) return 0;
    
    const rules = pricingRules[productName];
    
    if (days === 1) {
        return rules['1_day'];
    } else if (days === 2) {
        return rules['2_days'];
    } else {
        // 3+ days: calculate with discount
        const dailyRate = rules['1_day'];
        const total = dailyRate * days;
        const discount = rules['3_plus_discount'];
        return total * (1 - discount / 100);
    }
}

function updatePrice() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const submitBtn = document.getElementById('submitBtn');
    const priceDisplay = document.getElementById('priceDisplay');
    const totalPrice = document.getElementById('totalPrice');
    const priceBreakdown = document.getElementById('priceBreakdown');
    
    if (!startDate || !endDate) {
        priceDisplay.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }
    
    // Validate dates
    const today = new Date().toISOString().split('T')[0];
    if (startDate < today) {
        priceDisplay.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }
    
    if (endDate < startDate) {
        priceDisplay.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }
    
    const days = calculateDays(startDate, endDate);
    const price = calculatePrice(days);
    
    if (price > 0) {
        priceDisplay.style.display = 'block';
        totalPrice.textContent = `€${price.toFixed(0)}`;
        
        let breakdown = `${days} dag${days > 1 ? 'en' : ''}`;
        if (days >= 3) {
            const originalPrice = pricingRules[productName]['1_day'] * days;
            const discount = pricingRules[productName]['3_plus_discount'];
            breakdown += ` (${discount}% korting: €${originalPrice.toFixed(0)} → €${price.toFixed(0)})`;
        }
        priceBreakdown.textContent = breakdown;
        
        submitBtn.disabled = false;
    } else {
        priceDisplay.style.display = 'none';
        submitBtn.disabled = true;
    }
}

// Event listeners for date inputs
document.getElementById('startDate').addEventListener('change', function() {
    // Set minimum end date to start date
    const startDate = this.value;
    document.getElementById('endDate').min = startDate;
    updatePrice();
});

document.getElementById('endDate').addEventListener('change', updatePrice);

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Main image click to open lightbox
    const mainImageContainer = document.querySelector('.main-image-container');
    if (mainImageContainer) {
        mainImageContainer.addEventListener('click', () => openLightbox());
    }
    
    // Keyboard navigation for lightbox
    document.addEventListener('keydown', function(e) {
        const lightbox = document.getElementById('imageLightbox');
        if (lightbox.classList.contains('active')) {
            switch(e.key) {
                case 'Escape':
                    closeLightbox();
                    break;
                case 'ArrowLeft':
                    previousImage();
                    break;
                case 'ArrowRight':
                    nextImage();
                    break;
            }
        }
    });
    
    // Close lightbox when clicking outside image
    document.getElementById('imageLightbox').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLightbox();
        }
    });
    
    // Initialize price calculation if dates are pre-filled
    updatePrice();
});

// Smooth scrolling animation
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.2}s`;
    });
});

// Form validation feedback
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (!startDate || !endDate) {
        e.preventDefault();
        alert('Selecteer alstublieft een start- en einddatum.');
        return;
    }
    
    const today = new Date().toISOString().split('T')[0];
    if (startDate < today) {
        e.preventDefault();
        alert('Startdatum kan niet in het verleden liggen.');
        return;
    }
    
    if (endDate < startDate) {
        e.preventDefault();
        alert('Einddatum moet na startdatum liggen.');
        return;
    }
    
    // Show loading state
    const button = this.querySelector('.add-to-cart-btn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reservering toevoegen...';
    button.disabled = true;
});

// Review form validation
document.querySelector('.review-form').addEventListener('submit', function(e) {
    const name = this.querySelector('input[name="customer_name"]').value.trim();
    const review = this.querySelector('textarea[name="review"]').value.trim();
    
    if (name.length < 2) {
        e.preventDefault();
        alert('Naam moet minimaal 2 karakters bevatten.');
        return;
    }
    
    if (review.length < 10) {
        e.preventDefault();
        alert('Review moet minimaal 10 karakters bevatten.');
        return;
    }
});
</script>

</body>
</html>