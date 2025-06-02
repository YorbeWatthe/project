<?php
session_start();
include 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM products WHERE available = 1 LIMIT 6");
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - L&G Fun | Springkastelen Verhuur</title>
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

        /* Header styling - identical to other pages */
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
            transition: background-color 0.3s ease;
        }

        nav ul li a:hover,
        nav ul li a.active {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="rgba(255,255,255,.1)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient></defs><circle cx="10" cy="10" r="10" fill="url(%23a)"/><circle cx="50" cy="15" r="8" fill="url(%23a)"/><circle cx="90" cy="5" r="12" fill="url(%23a)"/></svg>') repeat;
            opacity: 0.3;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateX(0px); }
            50% { transform: translateX(-20px); }
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            line-height: 1.7;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn-hero {
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        /* Features Section - Normal flow */
        .features-section {
            margin-bottom: 4rem;
            background: #f8fafc;
            transform: none !important;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            opacity: 1;
            transform: translateY(0);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #64748b;
            line-height: 1.7;
        }

        /* Section Headers */
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* Products Section */
        .products-section {
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 4rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .product-card {
            background: #f8fafc;
            border-radius: 16px;
            overflow: hidden;
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
            height: 220px;
            background: white;
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
            background: white;
        }

        .product-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.8rem;
        }

        .product-description {
            color: #64748b;
            margin-bottom: 1.2rem;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 1.5rem;
        }

        .product-actions {
            display: flex;
            gap: 0.8rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex: 1;
            text-align: center;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-product-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-product-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-product-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-product-secondary:hover {
            background: #667eea;
            color: white;
        }

        .view-all-btn {
            text-align: center;
            margin-top: 2rem;
        }

        /* Contact Section */
        .contact-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 4rem;
        }

        .contact-form-container,
        .map-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .contact-form-container h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .contact-form-container h3 i {
            color: #667eea;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .map-container h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .map-container h3 i {
            color: #667eea;
        }

        .google-map {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .google-map iframe {
            width: 100%;
            height: 300px;
            border: none;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .contact-section {
                grid-template-columns: 1fr;
            }

            .main-container {
                padding: 2rem 1rem;
            }

            .hero-section {
                padding: 3rem 1rem;
            }

            .hero-title {
                font-size: 2.5rem;
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
                flex-wrap: wrap;
                justify-content: center;
            }

            nav ul li a {
                padding: 0.6rem 1rem;
                font-size: 1rem;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-hero {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }

            .section-title {
                font-size: 2rem;
            }

            .features-grid,
            .products-grid {
                grid-template-columns: 1fr;
            }

            .products-section {
                padding: 2rem 1rem;
            }

            .contact-form-container,
            .map-container {
                padding: 1.5rem;
            }
        }

        /* Animation for page load */
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.8s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        .slide-up {
            opacity: 0;
            transform: translateY(30px);
            animation: slideUp 0.8s ease forwards;
        }

        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
<header>
    <div class="navbar">
        <div class="logo">
            <img src="images/Logo L&G Fun-Photoroom.png" alt="L&G Fun Logo">
        </div>
        <nav>
            <ul>
                <li><a href="admin_dashboard.php" class="active">Home</a></li>
                <li><a href="cart.php">Winkelwagen</a></li>
                <li><a href="admin_panel.php">Admin Panel</a></li>
                <li><a href="loguit.php">Uitloggen</a></li>
            </ul>
        </nav>
    </div>
</header>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">
            <i class="fas fa-castle" style="margin-right: 1rem;"></i>
            Welkom bij L&G Fun!
        </h1>
        <p class="hero-subtitle">
            Bij L&G Fun maken we iedere dag onvergetelijk voor jong en oud. Wij verhuren hoogwaardige springkastelen aan betaalbare prijzen, zodat jouw feest of evenement een gegarandeerd succes wordt.
        </p>
        <div class="hero-buttons">
            <a href="filter_products.php" class="btn-hero btn-primary">
                <i class="fas fa-eye"></i>
                Bekijk Springkastelen
            </a>
            <a href="contact.php" class="btn-hero btn-secondary">
                <i class="fas fa-phone"></i>
                Neem Contact Op
            </a>
        </div>
    </div>
</section>

<div class="main-container">
    <!-- Features Section -->
    <section class="features-section">
        <div class="section-header">
            <h2 class="section-title">Waarom kiezen voor L&G Fun?</h2>
            <p class="section-subtitle">
                Ontdek waarom zoveel families en organisaties voor ons kiezen voor hun speciale evenementen
            </p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Veilig & Betrouwbaar</h3>
                <p>Alle onze springkastelen worden regelmatig gecontroleerd en voldoen aan de hoogste veiligheidsnormen voor zorgeloos plezier.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3>Levering & Installatie</h3>
                <p>Wij zorgen voor complete service: levering, professionele installatie en ophaling. Jij hoeft alleen maar te genieten!</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <h3>Betaalbare Prijzen</h3>
                <p>Kwaliteit hoeft niet duur te zijn. Onze transparante prijzen maken springkasteel verhuur toegankelijk voor iedereen.</p>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="products-section fade-in">
        <div class="section-header">
            <h2 class="section-title">Onze Populaire Springkastelen</h2>
            <p class="section-subtitle">
                Van klassieke springkastelen tot avontuurlijke obstakelparcours - wij hebben voor elk feest het perfecte springkasteel
            </p>
        </div>
        
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card slide-up" style="animation-delay: <?php echo 0.1 * ($product['id'] % 6); ?>s;">
                    <div class="product-image">
                        <img src="images/<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="product-content">
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <div class="product-price">€ <?php echo number_format($product['price'], 2, ',', '.'); ?></div>
                        <div class="product-actions">
                            <a href="info_product.php?product_id=<?php echo $product['id']; ?>" class="btn btn-product-secondary">
                                <i class="fas fa-info-circle"></i> Details
                            </a>
                            <form action="add_to_cart.php" method="POST" style="flex: 1;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-product-primary">
                                    <i class="fas fa-shopping-cart"></i> Toevoegen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="view-all-btn">
            <a href="filter_products.php" class="btn-hero btn-primary">
                <i class="fas fa-th-large"></i>
                Bekijk Alle Springkastelen
            </a>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section fade-in">
        <div class="contact-form-container">
            <h3><i class="fas fa-envelope"></i> Snel Contact</h3>
            <form action="verwerk_contact.php" method="POST">
                <div class="form-group">
                    <label for="name">Naam:</label>
                    <input type="text" id="name" name="naam" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mailadres:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="message">Bericht:</label>
                    <textarea id="message" name="bericht" rows="4" 
                              placeholder="Vertel ons over je evenement..." required></textarea>
                </div>
                <input type="hidden" name="onderwerp" value="algemeen">
                <input type="hidden" name="privacy" value="1">
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    Verstuur Bericht
                </button>
            </form>
        </div>
        
        <div class="map-container">
            <h3><i class="fas fa-map-marker-alt"></i> Onze Locatie</h3>
            <div class="google-map">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1822.1246403444536!2d4.3467497414018474!3d51.061537042414585!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47c3ee7b7baca0fb%3A0x62c52239c5a85a26!2sEmiel%20Vanderveldestraat%20167%2C%202830%20Willebroek!5e1!3m2!1snl!2sbe!4v1748707978158!5m2!1snl!2sbe" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></iframe>
            </div>
            <div style="margin-top: 1rem; text-align: center;">
                <p style="color: #64748b; margin-bottom: 0.5rem;">
                    Ondernemingsnummer: 1003.172.052
                </p>
            </div>
        </div>
    </section>
</div>

<?php include 'footer.php'; ?>

<script>
// Intersection Observer for animations
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

// Observe elements for animations
document.addEventListener('DOMContentLoaded', function() {
    // Only observe products and contact sections for animations
    document.querySelectorAll('.products-section.fade-in, .contact-section.fade-in, .slide-up').forEach(el => {
        observer.observe(el);
    });

    // Set initial states for animations (only for products)
    document.querySelectorAll('.products-section .slide-up').forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = `opacity 0.8s ease ${index * 0.1}s, transform 0.8s ease ${index * 0.1}s`;
    });

    document.querySelectorAll('.products-section.fade-in, .contact-section.fade-in').forEach(el => {
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.8s ease';
    });

    // Enhanced form interactions
    document.querySelectorAll('.form-group input, .form-group textarea').forEach(input => {
        input.addEventListener('focus', function() {
            this.closest('.form-group').style.transform = 'translateY(-2px)';
            this.closest('.form-group').style.transition = 'transform 0.3s ease';
        });

        input.addEventListener('blur', function() {
            this.closest('.form-group').style.transform = 'translateY(0)';
        });
    });

    // Form submission feedback
    const contactForm = document.querySelector('form[action="verwerk_contact.php"]');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.submit-btn');
            const originalText = submitBtn.innerHTML;
            
            // Basic validation
            const naam = this.querySelector('[name="naam"]').value.trim();
            const email = this.querySelector('[name="email"]').value.trim();
            const bericht = this.querySelector('[name="bericht"]').value.trim();
            
            if (naam.length < 2 || !email.includes('@') || bericht.length < 10) {
                e.preventDefault();
                alert('Vul alle velden correct in:\n- Naam (min. 2 karakters)\n- Geldig e-mailadres\n- Bericht (min. 10 karakters)');
                return;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Versturen...';
            submitBtn.disabled = true;
        });
    }

    // Product card hover effects
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Feature card hover effects
    document.querySelectorAll('.feature-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            const icon = this.querySelector('.feature-icon');
            icon.style.transform = 'scale(1.1) rotate(5deg)';
            icon.style.transition = 'transform 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            const icon = this.querySelector('.feature-icon');
            icon.style.transform = 'scale(1) rotate(0deg)';
        });
    });

    // Smooth scrolling for hero buttons
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add loading states to add-to-cart buttons
    document.querySelectorAll('form[action="add_to_cart.php"]').forEach(form => {
        form.addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Toevoegen...';
            button.disabled = true;
            
            // Reset after timeout (in case of errors)
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 5000);
        });
    });

    // Parallax effect disabled to prevent content shifting
    // window.addEventListener('scroll', function() {
    //     const scrolled = window.pageYOffset;
    //     const hero = document.querySelector('.hero-section');
    //     if (hero && scrolled < window.innerHeight) {
    //         hero.style.transform = `translateY(${scrolled * 0.3}px)`;
    //     }
    // });

    // Add typing effect to hero subtitle (optional)
    const heroSubtitle = document.querySelector('.hero-subtitle');
    if (heroSubtitle) {
        const text = heroSubtitle.textContent;
        heroSubtitle.textContent = '';
        heroSubtitle.style.opacity = '1';
        
        let i = 0;
        const typeWriter = () => {
            if (i < text.length) {
                heroSubtitle.textContent += text.charAt(i);
                i++;
                setTimeout(typeWriter, 30);
            }
        };
        
        // Start typing effect after hero title animation
        setTimeout(typeWriter, 1000);
    }
});

// Add scroll-to-top functionality
window.addEventListener('scroll', function() {
    if (window.pageYOffset > 300) {
        if (!document.querySelector('.scroll-to-top')) {
            const scrollBtn = document.createElement('button');
            scrollBtn.className = 'scroll-to-top';
            scrollBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
            scrollBtn.style.cssText = `
                position: fixed;
                bottom: 2rem;
                right: 2rem;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                cursor: pointer;
                z-index: 1000;
                opacity: 0.8;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            `;
            
            scrollBtn.addEventListener('mouseenter', function() {
                this.style.opacity = '1';
                this.style.transform = 'translateY(-2px) scale(1.1)';
            });
            
            scrollBtn.addEventListener('mouseleave', function() {
                this.style.opacity = '0.8';
                this.style.transform = 'translateY(0) scale(1)';
            });
            
            scrollBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            document.body.appendChild(scrollBtn);
        }
    } else {
        const scrollBtn = document.querySelector('.scroll-to-top');
        if (scrollBtn) {
            scrollBtn.remove();
        }
    }
});

// Add mobile menu functionality (if needed)
const navbar = document.querySelector('.navbar');
if (window.innerWidth <= 768) {
    const mobileMenuBtn = document.createElement('button');
    mobileMenuBtn.className = 'mobile-menu-btn';
    mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    mobileMenuBtn.style.cssText = `
        display: block;
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
    `;
    
    const nav = navbar.querySelector('nav');
    navbar.appendChild(mobileMenuBtn);
    
    mobileMenuBtn.addEventListener('click', function() {
        const ul = nav.querySelector('ul');
        ul.style.display = ul.style.display === 'flex' ? 'none' : 'flex';
        ul.style.flexDirection = 'column';
        ul.style.position = 'absolute';
        ul.style.top = '100%';
        ul.style.left = '0';
        ul.style.right = '0';
        ul.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        ul.style.padding = '1rem';
        ul.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
    });
}
</script>

</body>
</html>