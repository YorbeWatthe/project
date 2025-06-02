<?php
session_start();
include 'includes/db.php'; // Zorg voor een juiste verbinding met PDO

// Haal filterwaarden op uit GET-parameters
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 1000;
$availability = isset($_GET['availability']) ? (int)$_GET['availability'] : 1;
$selected_date = isset($_GET['selected_date']) ? $_GET['selected_date'] : null;
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';

// Controleer of de PDO-verbinding bestaat
if (!isset($pdo)) {
    die("Databaseverbinding ontbreekt.");
}

// Haal alle unieke categorieën op uit de database
$stmt = $pdo->query("SELECT DISTINCT category FROM products");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Basis SQL-query
$sql = "SELECT p.* FROM products p 
        LEFT JOIN bouncy_castle_availability bca ON p.id = bca.product_id
        WHERE p.price BETWEEN :min_price AND :max_price 
        AND bca.is_available = :availability";

// Voeg datumfilter toe als er een geselecteerde datum is
if ($selected_date) {
    $sql .= " AND bca.date = :selected_date";
}

// Voeg categorie-filter toe als er een categorie is geselecteerd
if ($selected_category) {
    $sql .= " AND p.category = :selected_category";
}

// Query voorbereiden
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':min_price', $min_price, PDO::PARAM_STR);
$stmt->bindParam(':max_price', $max_price, PDO::PARAM_STR);
$stmt->bindParam(':availability', $availability, PDO::PARAM_INT);

if ($selected_date) {
    $stmt->bindParam(':selected_date', $selected_date, PDO::PARAM_STR);
}
if ($selected_category) {
    $stmt->bindParam(':selected_category', $selected_category, PDO::PARAM_STR);
}

// Query uitvoeren
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Zorg ervoor dat elk product_id slechts één keer wordt weergegeven
$unique_products = [];
foreach ($products as $product) {
    if (!isset($unique_products[$product['id']])) {
        $unique_products[$product['id']] = $product;
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producten Filteren - L&G Fun</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="images/favicon-16x16.png">
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
        }

        /* Header styling - geen hover effecten */
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

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 3rem 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Main Layout Container */
        .main-layout {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            gap: 2rem;
            padding: 2rem;
            align-items: flex-start;
        }

        /* Sidebar voor filters */
        .sidebar {
            width: 320px;
            flex-shrink: 0;
        }

        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 120px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 2rem;
            color: #333;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .filter-title h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
        }

        .filter-title i {
            color: #667eea;
            font-size: 1.3rem;
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .form-group label i {
            color: #667eea;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .filter-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        /* Main Content Area */
        .content-area {
            flex: 1;
            min-width: 0;
        }

        .products-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: #334155;
        }

        .section-title h2 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .section-title i {
            color: #667eea;
            font-size: 1.3rem;
        }

        .results-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .product-image {
            position: relative;
            overflow: hidden;
            height: 200px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.05);
        }

        .product-content {
            padding: 1.5rem;
        }

        .product-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #334155;
        }

        .product-description {
            color: #64748b;
            margin-bottom: 1rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 0.95rem;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 1.5rem;
        }

        .product-actions {
            display: flex;
            gap: 0.8rem;
        }

        .btn {
            padding: 0.7rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            flex: 1;
            text-align: center;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #475569;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-layout {
                flex-direction: column;
                padding: 1rem;
            }

            .sidebar {
                width: 100%;
            }

            .filter-section {
                position: static;
            }

            .filter-form {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
                align-items: end;
            }

            .filter-button {
                margin-top: 0;
                grid-column: 1 / -1;
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

            .page-header {
                padding: 2rem 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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

<div class="page-header">
    <h1><i class="fas fa-castle"></i> Springkastelen Verhuur</h1>
    <p>Ontdek onze geweldige collectie springkastelen en vind de perfecte keuze voor jouw evenement</p>
</div>

<div class="main-layout">
    <!-- Sidebar met filters -->
    <aside class="sidebar">
        <div class="filter-section">
            <div class="filter-title">
                <i class="fas fa-filter"></i>
                <h2>Filters</h2>
            </div>
            
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label><i class="fas fa-euro-sign"></i> Min prijs</label>
                    <input type="number" name="min_price" value="<?php echo $min_price; ?>" min="0" step="0.01" placeholder="€ 0,00">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-euro-sign"></i> Max prijs</label>
                    <input type="number" name="max_price" value="<?php echo $max_price; ?>" min="0" step="0.01" placeholder="€ 1000,00">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Beschikbaarheid</label>
                    <select name="availability">
                        <option value="1" <?php echo $availability ? 'selected' : ''; ?>>Beschikbaar</option>
                        <option value="0" <?php echo !$availability ? 'selected' : ''; ?>>Niet beschikbaar</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Datum</label>
                    <input type="date" name="selected_date" value="<?php echo $selected_date; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-tags"></i> Categorie</label>
                    <select name="category">
                        <option value="">-- Alle categorieën --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['category']) ?>"
                                <?= ($selected_category == $category['category']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="filter-button">
                    <i class="fas fa-search"></i>
                    Zoeken
                </button>
            </form>
        </div>
    </aside>

    <!-- Main content area -->
    <main class="content-area">
        <div class="products-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-th-large"></i>
                    <h2>Beschikbare Springkastelen</h2>
                </div>
                <div class="results-count">
                    <?php echo count($unique_products); ?> resultaten gevonden
                </div>
            </div>

            <?php if (empty($unique_products)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Geen resultaten gevonden</h3>
                    <p>Probeer je filters aan te passen om meer resultaten te zien.</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($unique_products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="images/<?php echo htmlspecialchars($product['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="product-content">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                                <div class="product-price">€ <?php echo number_format($product['price'], 2, ',', '.'); ?></div>
                                <div class="product-actions">
                                    <a href="info_product.php?product_id=<?php echo $product['id']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-info-circle"></i> Details
                                    </a>
                                    <a href="add_to_cart.php?product_id=<?php echo $product['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart"></i> Toevoegen
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'footer.php'; ?>

<script>
// Loading state voor filter formulier
document.querySelector('.filter-form').addEventListener('submit', function() {
    const button = this.querySelector('.filter-button');
    const originalText = button.innerHTML;
    button.innerHTML = '<div class="loading"></div> Zoeken...';
    button.disabled = true;
    
    // Reset na 3 seconden voor als er een fout optreedt
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    }, 3000);
});

// Animate cards on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Initially hide cards and observe them
document.querySelectorAll('.product-card').forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
    observer.observe(card);
});
</script>

</body>
</html>