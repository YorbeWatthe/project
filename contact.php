<?php
session_start();
include 'includes/db.php';

// Handle form submission feedback
$message = '';
if (isset($_SESSION['contact_success'])) {
    $message = '<div class="success-message"><i class="fas fa-check-circle"></i> Bedankt voor je bericht! We nemen zo snel mogelijk contact met je op.</div>';
    unset($_SESSION['contact_success']);
} elseif (isset($_SESSION['contact_error'])) {
    $message = '<div class="error-message"><i class="fas fa-exclamation-circle"></i> ' . $_SESSION['contact_error'] . '</div>';
    unset($_SESSION['contact_error']);
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - L&G Fun</title>
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

        nav ul li a.active,
        nav ul li a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .mobile-menu-btn {
            display: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Main Container */
        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Messages */
        .success-message,
        .error-message {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .success-message {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .error-message {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Contact Grid */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        /* Contact Methods */
        .contact-methods {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: fit-content;
        }

        .contact-methods h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .contact-methods h2::before {
            content: '\f095';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #667eea;
            font-size: 1.3rem;
        }

        .contact-method {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #f8fafc;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .contact-method:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
        }

        .contact-method i {
            font-size: 1.5rem;
            color: #667eea;
            margin-top: 0.2rem;
            width: 2rem;
            text-align: center;
        }

        .contact-method h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .contact-method p {
            margin: 0.2rem 0;
        }

        .contact-method a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .contact-method a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        /* Contact Form Section */
        .contact-form-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .contact-form-section h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .contact-form-section h2::before {
            content: '\f0e0';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #667eea;
            font-size: 1.3rem;
        }

        .contact-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.half-width {
            grid-column: span 1;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
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
            width: 1rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .privacy-policy {
            flex-direction: row;
            align-items: center;
            gap: 0.8rem;
        }

        .privacy-policy input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .privacy-policy label {
            margin: 0;
            font-weight: normal;
        }

        .privacy-policy a {
            color: #667eea;
            text-decoration: none;
        }

        .privacy-policy a:hover {
            text-decoration: underline;
        }

        .submit-btn {
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
            justify-self: start;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* FAQ Section */
        .faq {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .faq h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .faq h2::before {
            content: '\f059';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #667eea;
            font-size: 1.3rem;
        }

        .faq-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .faq-question {
            width: 100%;
            padding: 1.2rem 1.5rem;
            background: #f8fafc;
            border: none;
            text-align: left;
            font-size: 1.1rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            background: #f1f5f9;
        }

        .faq-question::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #667eea;
            transition: transform 0.3s ease;
        }

        .faq-item.active .faq-question {
            background: #667eea;
            color: white;
        }

        .faq-item.active .faq-question::after {
            color: white;
            transform: rotate(180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .faq-item.active .faq-answer {
            max-height: 200px;
        }

        .faq-answer p {
            padding: 1.5rem;
            color: #475569;
            line-height: 1.6;
            background: white;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .contact-container {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
            }

            nav ul {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                flex-direction: column;
                gap: 0;
                display: none;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }

            nav ul.show {
                display: flex;
            }

            nav ul li {
                width: 100%;
            }

            nav ul li a {
                display: block;
                padding: 1rem 2rem;
                border-radius: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .mobile-menu-btn {
                display: block;
            }

            .page-header h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .contact-method {
                padding: 1rem;
            }

            .contact-methods,
            .contact-form-section,
            .faq {
                padding: 1.5rem;
            }
        }

        /* Loading animation */
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
                <li><a href="about.php">Over ons</a></li>
                <li><a href="cart.php">Winkelwagen</a></li>
                <li><a href="contact.php" class="active">Contact</a></li>
                <li><a href="filter_products.php">Beschikbaarheid</a></li>
                <li><a href="loguit.php">Uitloggen</a></li>
            </ul>
        </nav>
        <div class="mobile-menu-btn">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</header>

<div class="page-header">
    <h1><i class="fas fa-envelope"></i> Contacteer ons</h1>
    <p>Heb je vragen of wil je een offerte? Neem gerust contact met ons op!</p>
</div>

<main class="contact-container">
    <?php echo $message; ?>

    <div class="contact-grid fade-in">
        <section class="contact-methods">
            <h2>Neem contact op</h2>
            
            <div class="contact-method">
                <i class="fas fa-phone"></i>
                <div>
                    <h3>Telefoon</h3>
                    <p><a href="tel:+32488610540">+32 488 61.05.40</a></p>
                    <p><a href="tel:+32485671163">+32 485 67.11.63</a></p>
                    <p><small>Ma-Vr: 9:00-18:00 | Za: 9:00-16:00</small></p>
                </div>
            </div>
            
            <div class="contact-method">
                <i class="fab fa-whatsapp"></i>
                <div>
                    <h3>WhatsApp</h3>
                    <p><a href="https://api.whatsapp.com/send?phone=32488610540" target="_blank">Stuur een bericht</a></p>
                    <p><small>Snelle reactie gegarandeerd</small></p>
                </div>
            </div>
            
            <div class="contact-method">
                <i class="fas fa-envelope"></i>
                <div>
                    <h3>Email</h3>
                    <p><a href="mailto:contact@lg-fun.be">contact@lg-fun.be</a></p>
                    <p><small>We reageren binnen 24 uur</small></p>
                </div>
            </div>
            
            <div class="contact-method">
                <i class="fab fa-facebook"></i>
                <div>
                    <h3>Facebook</h3>
                    <p><a href="https://facebook.com/profile.php?id=61554313078628" target="_blank">L&G Fun</a></p>
                    <p><small>Volg ons voor updates</small></p>
                </div>
            </div>
        </section>

        <section class="contact-form-section">
            <h2>Stuur ons een bericht</h2>
            
            <form action="verwerk_contact.php" method="post" class="contact-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="naam"><i class="fas fa-user"></i> Naam *</label>
                        <input type="text" id="naam" name="naam" required>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> E-mailadres *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="telefoon"><i class="fas fa-phone"></i> Telefoonnummer</label>
                        <input type="tel" id="telefoon" name="telefoon" placeholder="+32 xxx xx xx xx">
                    </div>
                    
                    <div class="form-group">
                        <label for="onderwerp"><i class="fas fa-tag"></i> Onderwerp</label>
                        <select id="onderwerp" name="onderwerp">
                            <option value="algemeen">Algemene vraag</option>
                            <option value="reservering">Reservering</option>
                            <option value="prijzen">Informatie over prijzen</option>
                            <option value="beschikbaarheid">Beschikbaarheid</option>
                            <option value="klacht">Klacht</option>
                            <option value="anders">Anders</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="bericht"><i class="fas fa-comment"></i> Bericht *</label>
                    <textarea id="bericht" name="bericht" rows="5" 
                              placeholder="Beschrijf hier je vraag of opmerking..." required></textarea>
                </div>
                
                <div class="form-group privacy-policy">
                    <input type="checkbox" id="privacy" name="privacy" required>
                    <label for="privacy">Ik ga akkoord met de <a href="privacy.php" target="_blank">privacyverklaring</a> *</label>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    Verstuur bericht
                </button>
            </form>
        </section>
    </div>

    <section class="faq fade-in">
        <h2>Veelgestelde vragen</h2>
        
        <div class="faq-item">
            <button class="faq-question">Wat zijn de huurprijzen van de springkastelen?</button>
            <div class="faq-answer">
                <p>De huurprijzen variëren afhankelijk van de duur en het type springkasteel. Neem contact met ons op voor een persoonlijke offerte op maat voor jouw evenement.</p>
            </div>
        </div>
        
        <div class="faq-item">
            <button class="faq-question">Bieden jullie levering en installatie van de gehuurde items?</button>
            <div class="faq-answer">
                <p>Ja, wij bieden volledige service: levering, professionele installatie en ophaling van alle gehuurde items. Zo kun je zorgeloos genieten van je evenement!</p>
            </div>
        </div>
        
        <div class="faq-item">
            <button class="faq-question">Leveren jullie enkel in de buurt van Willebroek?</button>
            <div class="faq-answer">
                <p>Wij leveren tot een afstand van 20km gratis rond Willebroek. Voor evenementen verder weg vragen we €0,35 per kilometer. Neem contact op voor een exact tarief.</p>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>

<script>
// FAQ accordion functionality
document.querySelectorAll('.faq-question').forEach(button => {
    button.addEventListener('click', () => {
        const item = button.parentElement;
        
        // Close all other open FAQ items
        document.querySelectorAll('.faq-item.active').forEach(openItem => {
            if (openItem !== item) {
                openItem.classList.remove('active');
            }
        });
        
        // Toggle current item
        item.classList.toggle('active');
    });
});

// Mobile menu toggle
document.querySelector('.mobile-menu-btn').addEventListener('click', () => {
    document.querySelector('nav ul').classList.toggle('show');
});

// Form validation and submission
const contactForm = document.querySelector('.contact-form');
contactForm.addEventListener('submit', (e) => {
    const email = document.getElementById('email').value;
    const naam = document.getElementById('naam').value;
    const bericht = document.getElementById('bericht').value;
    const privacy = document.getElementById('privacy').checked;
    
    let isValid = true;
    let errorMessage = '';
    
    if (naam.trim().length < 2) {
        isValid = false;
        errorMessage += '• Vul een geldige naam in (minimaal 2 karakters)\n';
    }
    
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        isValid = false;
        errorMessage += '• Vul een geldig e-mailadres in\n';
    }
    
    if (bericht.trim().length < 10) {
        isValid = false;
        errorMessage += '• Je bericht moet minimaal 10 tekens bevatten\n';
    }

    if (!privacy) {
        isValid = false;
        errorMessage += '• Je moet akkoord gaan met de privacyverklaring\n';
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Er zijn fouten in het formulier:\n\n' + errorMessage);
        return;
    }

    // Show loading state
    const submitBtn = contactForm.querySelector('.submit-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<div class="loading"></div> Versturen...';
    submitBtn.disabled = true;

    // Reset form if submission fails
    setTimeout(() => {
        if (submitBtn.disabled) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }, 10000);
});

// Smooth animations on page load
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.2}s`;
    });

    // Auto-dismiss messages after 5 seconds
    const messages = document.querySelectorAll('.success-message, .error-message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => {
                message.remove();
            }, 300);
        }, 5000);
    });
});

// Enhanced form interactions
document.querySelectorAll('.form-group input, .form-group select, .form-group textarea').forEach(input => {
    input.addEventListener('focus', function() {
        this.closest('.form-group').style.transform = 'translateY(-2px)';
        this.closest('.form-group').style.transition = 'transform 0.3s ease';
    });

    input.addEventListener('blur', function() {
        this.closest('.form-group').style.transform = 'translateY(0)';
    });
});
</script>
</body>
</html>