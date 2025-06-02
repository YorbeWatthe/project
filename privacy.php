<?php
session_start();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacyverklaring - L&G Fun Springkastelen</title>
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
        .privacy-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .privacy-header {
            text-align: center;
            margin-bottom: 3rem;
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .page-title {
            font-size: 3rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .page-title i {
            color: #667eea;
        }

        .last-updated {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .intro-text {
            font-size: 1.2rem;
            color: #475569;
            line-height: 1.7;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Content sections */
        .privacy-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .privacy-section {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .privacy-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            border-bottom: 3px solid #667eea;
            padding-bottom: 0.8rem;
        }

        .section-title i {
            color: #667eea;
            font-size: 1.5rem;
        }

        .section-content {
            color: #475569;
            font-size: 1.1rem;
            line-height: 1.7;
        }

        .section-content p {
            margin-bottom: 1.2rem;
        }

        .section-content ul {
            margin: 1rem 0 1.5rem 1.5rem;
            list-style: none;
        }

        .section-content ul li {
            position: relative;
            padding: 0.5rem 0 0.5rem 2rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-content ul li:last-child {
            border-bottom: none;
        }

        .section-content ul li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #22c55e;
            position: absolute;
            left: 0;
            top: 0.6rem;
            font-size: 0.9rem;
        }

        /* Company info highlight */
        .company-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #0ea5e9;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1rem 0;
        }

        .company-info strong {
            color: #0369a1;
        }

        /* Contact info */
        .contact-info {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .contact-info a {
            color: #059669;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .contact-info a:hover {
            color: #047857;
            text-decoration: underline;
        }

        /* Rights section special styling */
        .rights-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .right-item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .right-item:hover {
            background: #f0f9ff;
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .right-item::before {
            content: '\f2bd';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #667eea;
            margin-right: 0.8rem;
            font-size: 1.1rem;
        }

        /* Warning/important boxes */
        .important-box {
            background: linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%);
            border: 2px solid #fb923c;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .important-box i {
            color: #ea580c;
            font-size: 1.5rem;
            margin-top: 0.2rem;
        }

        /* Footer section */
        .privacy-footer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin-top: 3rem;
        }

        .privacy-footer h3 {
            margin-bottom: 1rem;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .footer-link {
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .footer-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Table of contents */
        .table-of-contents {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .toc-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .toc-title i {
            color: #667eea;
        }

        .toc-list {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.8rem;
        }

        .toc-item {
            padding: 0.8rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .toc-item:hover {
            background: #f0f9ff;
            transform: translateX(5px);
        }

        .toc-item a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .privacy-container {
                padding: 1rem;
            }

            .page-title {
                font-size: 2.2rem;
                flex-direction: column;
                gap: 0.5rem;
            }

            .privacy-header {
                padding: 2rem 1.5rem;
            }

            .privacy-section {
                padding: 1.5rem;
            }

            .section-title {
                font-size: 1.5rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .rights-list {
                grid-template-columns: 1fr;
            }

            .footer-links {
                flex-direction: column;
                align-items: center;
            }

            .toc-list {
                grid-template-columns: 1fr;
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
            animation: slideUp 0.6s ease forwards;
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
            <img src="images/Logo L&G Fun-Photoroom.png" alt="Logo">
        </div>
        <nav>
            <ul>
                <li><a href="<?php echo isset($_SESSION['user_id']) ? 'user_dashboard.php' : 'index.php'; ?>">Home</a></li>
                <li><a href="filter_products.php">Producten</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="cart.php">Winkelwagen</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="loguit.php">Uitloggen</a></li>
                <?php else: ?>
                    <li><a href="login.php">Inloggen</a></li>
                    <li><a href="register.php">Registreren</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main>
    <div class="privacy-container">
        <!-- Header Section -->
        <div class="privacy-header fade-in">
            <h1 class="page-title">
                <i class="fas fa-shield-alt"></i>
                Privacyverklaring
            </h1>
            <div class="last-updated">
                <i class="fas fa-calendar-alt"></i>
                Laatst bijgewerkt: 1 juni 2025
            </div>
            <p class="intro-text">
                Bij <strong>L&G Fun</strong> hechten we veel belang aan uw privacy en de bescherming van uw persoonsgegevens. 
                In deze privacyverklaring leggen we uit welke gegevens we verzamelen, waarom we dat doen en wat uw rechten zijn.
            </p>
        </div>

        <!-- Table of Contents -->
        <div class="table-of-contents slide-up">
            <div class="toc-title">
                <i class="fas fa-list"></i>
                Overzicht
            </div>
            <div class="toc-list">
                <div class="toc-item"><a href="#company">1. Wie zijn wij?</a></div>
                <div class="toc-item"><a href="#data-collection">2. Welke gegevens verzamelen we?</a></div>
                <div class="toc-item"><a href="#purpose">3. Waarom verwerken we gegevens?</a></div>
                <div class="toc-item"><a href="#retention">4. Hoe lang bewaren we gegevens?</a></div>
                <div class="toc-item"><a href="#sharing">5. Worden gegevens gedeeld?</a></div>
                <div class="toc-item"><a href="#rights">6. Wat zijn uw rechten?</a></div>
                <div class="toc-item"><a href="#security">7. Beveiliging</a></div>
                <div class="toc-item"><a href="#changes">8. Wijzigingen</a></div>
            </div>
        </div>

        <!-- Content Sections -->
        <div class="privacy-content">
            <!-- Section 1: Company Info -->
            <div class="privacy-section slide-up" id="company" style="animation-delay: 0.1s;">
                <h2 class="section-title">
                    <i class="fas fa-building"></i>
                    1. Wie zijn wij?
                </h2>
                <div class="section-content">
                    <div class="company-info">
                        <p><strong>L&G Fun</strong><br>
                        Emiel Vanderveldestraat 167, 2830 Willebroek<br>
                        BTW: 1003.712.052<br>
                        <i class="fas fa-phone"></i> 0485/67 11 63<br>
                        <i class="fas fa-envelope"></i> <a href="mailto:l-gfun@hotmail.com">l-gfun@hotmail.com</a><br>
                        <i class="fas fa-globe"></i> Website: <a href="https://www.lg-fun.be" target="_blank">www.lg-fun.be</a></p>
                    </div>
                </div>
            </div>

            <!-- Section 2: Data Collection -->
            <div class="privacy-section slide-up" id="data-collection" style="animation-delay: 0.2s;">
                <h2 class="section-title">
                    <i class="fas fa-database"></i>
                    2. Welke persoonsgegevens verzamelen we?
                </h2>
                <div class="section-content">
                    <p>Wij verzamelen enkel gegevens die nodig zijn voor onze dienstverlening. Dit kan onder meer gaan over:</p>
                    <ul>
                        <li>Identificatiegegevens: naam, voornaam</li>
                        <li>Contactgegevens: adres, e-mailadres, telefoonnummer</li>
                        <li>Facturatiegegevens</li>
                        <li>Gegevens over uw reservering: datum, locatie, gekozen springkasteel</li>
                        <li>Berichten of opmerkingen die u via ons contactformulier of via e-mail aan ons bezorgt</li>
                    </ul>
                </div>
            </div>

            <!-- Section 3: Purpose -->
            <div class="privacy-section slide-up" id="purpose" style="animation-delay: 0.3s;">
                <h2 class="section-title">
                    <i class="fas fa-question-circle"></i>
                    3. Waarom verwerken we deze gegevens?
                </h2>
                <div class="section-content">
                    <p>Wij gebruiken uw gegevens uitsluitend voor volgende doeleinden:</p>
                    <ul>
                        <li>Om uw reservering correct te verwerken en te leveren</li>
                        <li>Voor facturatie en administratie</li>
                        <li>Om contact met u op te nemen bij vragen of wijzigingen</li>
                        <li>Voor promotionele doeleinden (alleen met uw toestemming)</li>
                        <li>Om onze dienstverlening te verbeteren</li>
                    </ul>
                </div>
            </div>

            <!-- Section 4: Retention -->
            <div class="privacy-section slide-up" id="retention" style="animation-delay: 0.4s;">
                <h2 class="section-title">
                    <i class="fas fa-clock"></i>
                    4. Hoe lang bewaren we uw gegevens?
                </h2>
                <div class="section-content">
                    <p>Wij bewaren uw gegevens niet langer dan nodig is voor het doel waarvoor ze zijn verzameld. Facturatiegegevens worden conform de wettelijke bewaartermijnen (7 jaar) bijgehouden.</p>
                    
                    <div class="important-box">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Wettelijke bewaartermijn:</strong> Sommige gegevens moeten we volgens de wet bewaren voor administratieve en fiscale doeleinden.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 5: Sharing -->
            <div class="privacy-section slide-up" id="sharing" style="animation-delay: 0.5s;">
                <h2 class="section-title">
                    <i class="fas fa-share-alt"></i>
                    5. Worden uw gegevens gedeeld?
                </h2>
                <div class="section-content">
                    <p>Wij geven uw gegevens <strong>niet door aan derden</strong>, tenzij:</p>
                    <ul>
                        <li>Dit wettelijk verplicht is</li>
                    </ul>
                    
                    <div class="important-box">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Uw privacy staat voorop:</strong> Wij verkopen, verhuren of delen uw persoonlijke gegevens nooit met commerciële partijen.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 6: Rights -->
            <div class="privacy-section slide-up" id="rights" style="animation-delay: 0.6s;">
                <h2 class="section-title">
                    <i class="fas fa-user-shield"></i>
                    6. Wat zijn uw rechten?
                </h2>
                <div class="section-content">
                    <p>U hebt steeds de volgende rechten betreffende uw persoonsgegevens:</p>
                    
                    <div class="rights-list">
                        <div class="right-item">Recht op inzage van uw gegevens</div>
                        <div class="right-item">Recht op verbetering van onjuiste gegevens</div>
                        <div class="right-item">Recht op verwijdering van uw gegevens</div>
                        <div class="right-item">Recht op beperking van verwerking</div>
                        <div class="right-item">Recht van bezwaar tegen verwerking</div>
                        <div class="right-item">Recht op overdraagbaarheid van gegevens</div>
                    </div>
                    
                    <div class="contact-info">
                        <p><strong>Uw rechten uitoefenen?</strong><br>
                        U kunt deze rechten uitoefenen door contact met ons op te nemen via <a href="mailto:l-gfun@hotmail.com">l-gfun@hotmail.com</a>. 
                        U hebt ook het recht om een klacht in te dienen bij de Gegevensbeschermingsautoriteit 
                        (<a href="https://www.gegevensbeschermingsautoriteit.be" target="_blank">www.gegevensbeschermingsautoriteit.be</a>).</p>
                    </div>
                </div>
            </div>

            <!-- Section 7: Security -->
            <div class="privacy-section slide-up" id="security" style="animation-delay: 0.7s;">
                <h2 class="section-title">
                    <i class="fas fa-lock"></i>
                    7. Beveiliging
                </h2>
                <div class="section-content">
                    <p>Wij nemen de nodige technische en organisatorische maatregelen om uw gegevens te beschermen tegen verlies, diefstal of ongeoorloofde toegang.</p>
                    
                    <ul>
                        <li>Beveiligde servers en databases</li>
                        <li>Regelmatige security updates</li>
                        <li>Beperkte toegang tot persoonlijke gegevens</li>
                        <li>Encryptie van gevoelige informatie</li>
                    </ul>
                </div>
            </div>

            <!-- Section 8: Changes -->
            <div class="privacy-section slide-up" id="changes" style="animation-delay: 0.8s;">
                <h2 class="section-title">
                    <i class="fas fa-edit"></i>
                    8. Wijzigingen
                </h2>
                <div class="section-content">
                    <p>L&G Fun behoudt zich het recht voor om deze privacyverklaring aan te passen. De meest recente versie is steeds beschikbaar op onze website.</p>
                    
                    <div class="important-box">
                        <i class="fas fa-bell"></i>
                        <div>
                            <strong>Wijzigingen:</strong> Bij belangrijke wijzigingen zullen wij u hiervan op de hoogte stellen via e-mail of een duidelijke melding op onze website.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="privacy-footer slide-up" style="animation-delay: 0.9s;">
            <h3><i class="fas fa-question-circle"></i> Vragen over deze privacyverklaring?</h3>
            <p>Neem gerust contact op via <a href="mailto:l-gfun@hotmail.com" style="color: white; text-decoration: underline;">l-gfun@hotmail.com</a></p>
            
            <div class="footer-links">
                <a href="contact.php" class="footer-link">
                    <i class="fas fa-envelope"></i> Contact
                </a>
                <a href="<?php echo isset($_SESSION['user_id']) ? 'user_dashboard.php' : 'index.php'; ?>" class="footer-link">
                    <i class="fas fa-home"></i> Terug naar Home
                </a>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for TOC links
    const tocLinks = document.querySelectorAll('.toc-item a');
    tocLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetSection = document.getElementById(targetId);
            
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                
                // Highlight the section briefly
                targetSection.style.background = 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)';
                setTimeout(() => {
                    targetSection.style.background = 'white';
                }, 2000);
            }
        });
    });
    
    // Add intersection observer for animations
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
    
    // Observe all animated elements
    const animatedElements = document.querySelectorAll('.slide-up');
    animatedElements.forEach(el => {
        observer.observe(el);
    });
    
    // Add hover effects to sections
    const sections = document.querySelectorAll('.privacy-section');
    sections.forEach(section => {
        section.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });
        
        section.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-2px)';
        });
    });
});
</script>

</body>
</html>