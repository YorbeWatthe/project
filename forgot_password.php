<?php
session_start();
include 'includes/db.php';

// Include PHPMailer (plaats dit bovenaan het bestand)
require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ongeldige aanvraag. Probeer het opnieuw.");
    }
    
    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);

        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate secure reset token
                $reset_token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                
                // Store reset token in database
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (user_id, email, token, expires_at) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    token = VALUES(token), 
                    expires_at = VALUES(expires_at), 
                    created_at = NOW()
                ");
                $stmt->execute([$user['id'], $email, $reset_token, $expires_at]);
                
                // Send reset email
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=" . $reset_token;
                
                if (sendPasswordResetEmail($email, $user['name'], $reset_link)) {
                    $message = "Een wachtwoord reset link is verzonden naar uw e-mailadres. Controleer uw inbox en spam folder.";
                    $message_type = 'success';
                } else {
                    $message = "Er is een fout opgetreden bij het verzenden van de email. Probeer het later opnieuw.";
                    $message_type = 'error';
                }
            } else {
                // Don't reveal if email exists or not for security
                $message = "Als dit e-mailadres bij ons bekend is, ontvangt u binnen enkele minuten een reset link.";
                $message_type = 'info';
            }
        } else {
            $message = "Voer een geldig e-mailadres in.";
            $message_type = 'error';
        }
    }
}

// Function to send password reset email using PHPMailer
function sendPasswordResetEmail($to_email, $user_name, $reset_link) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp-auth.mailprotect.be'; // Vervang met jouw SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'reset@lg-fun.be';
        $mail->Password   = 'L02-01g24'; // Vervang met jouw email wachtwoord
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom('reset@lg-fun.be', 'L&G Fun Springkastelen');
        $mail->addAddress($to_email, $user_name);
        $mail->addReplyTo('reset@lg-fun.be', 'L&G Fun Reset');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Wachtwoord reset - L&G Fun Springkastelen';
        
        // HTML email content
        $html_message = "
        <!DOCTYPE html>
        <html lang='nl'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Wachtwoord Reset</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8fafc; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px 20px; }
                .reset-button { display: inline-block; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .warning { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .logo { max-width: 150px; height: auto; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Wachtwoord Reset</h1>
                    <p>L&G Fun Springkastelen</p>
                </div>
                <div class='content'>
                    <h2>Hallo " . htmlspecialchars($user_name) . ",</h2>
                    <p>U heeft een wachtwoord reset aangevraagd voor uw L&G Fun account.</p>
                    <p>Klik op de onderstaande knop om uw wachtwoord te resetten:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $reset_link . "' class='reset-button' style='color: white; text-decoration: none;'>Wachtwoord Resetten</a>
                    </div>
                    
                    <p>Of kopieer deze link naar uw browser:</p>
                    <p style='word-break: break-all; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; font-family: monospace;'>" . $reset_link . "</p>
                    
                    <div class='warning'>
                        <strong>⚠️ Belangrijk:</strong>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>Deze link is slechts <strong>1 uur geldig</strong></li>
                            <li>Als u deze reset niet heeft aangevraagd, negeer dan deze email</li>
                            <li>Deel deze link nooit met anderen</li>
                            <li>De link kan slechts één keer gebruikt worden</li>
                        </ul>
                    </div>
                    
                    <p>Heeft u vragen? Neem gerust contact met ons op via <a href='mailto:l-gfun@hotmail.com' style='color: #667eea;'>l-gfun@hotmail.com</a> of bel ons op 0485/67 11 63.</p>
                    
                    <p>Met vriendelijke groet,<br><strong>Het L&G Fun team</strong></p>
                </div>
                <div class='footer'>
                    <p><strong>L&G Fun Springkastelen</strong><br>
                    Emiel Vanderveldestraat 167, 2830 Willebroek<br>
                    📞 0485/67 11 63 | ✉️ l-gfun@hotmail.com<br>
                    🌐 <a href='https://www.lg-fun.be' style='color: #667eea;'>www.lg-fun.be</a></p>
                    <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 15px 0;'>
                    <p><small>Dit is een automatisch gegenereerde email. Antwoord niet op deze email.<br>
                    Voor vragen kunt u contact opnemen via l-gfun@hotmail.com</small></p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->Body = $html_message;
        
        // Plain text version
        $text_message = "
Hallo " . $user_name . ",

U heeft een wachtwoord reset aangevraagd voor uw L&G Fun account.

Klik op deze link om uw wachtwoord te resetten:
" . $reset_link . "

BELANGRIJK:
- Deze link is slechts 1 uur geldig
- Als u deze reset niet heeft aangevraagd, negeer dan deze email
- Deel deze link nooit met anderen
- De link kan slechts één keer gebruikt worden

Heeft u vragen? Neem contact op via:
- Email: l-gfun@hotmail.com
- Telefoon: 0485/67 11 63

Met vriendelijke groet,
Het L&G Fun team

L&G Fun Springkastelen
Emiel Vanderveldestraat 167, 2830 Willebroek
Website: www.lg-fun.be

Dit is een automatisch gegenereerde email.
";
        
        $mail->AltBody = $text_message;
        
        // Send email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log error for debugging
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord Vergeten - L&G Fun Springkastelen</title>
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

        /* Main auth container */
        .auth-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 100px);
            padding: 2rem;
        }

        .auth-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
        }

        .auth-title i {
            color: #667eea;
            font-size: 2rem;
        }

        .auth-subtitle {
            color: #64748b;
            font-size: 1.1rem;
            line-height: 1.5;
        }

        /* Message styling */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
        }

        .message.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .message i {
            font-size: 1.2rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form styling */
        .auth-form {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .form-control:hover {
            border-color: #d1d5db;
        }

        /* Button styling */
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* Links */
        .auth-links {
            text-align: center;
            color: #64748b;
        }

        .auth-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        /* Info box */
        .info-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-box h4 {
            color: #0369a1;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box h4 i {
            color: #0284c7;
        }

        .info-box ul {
            color: #0369a1;
            margin-left: 1.2rem;
        }

        .info-box ul li {
            margin-bottom: 0.3rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .auth-container {
                padding: 1rem;
            }

            .auth-card {
                padding: 2rem 1.5rem;
                margin: 1rem 0;
            }

            .auth-title {
                font-size: 1.8rem;
                flex-direction: column;
                gap: 0.5rem;
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

        /* Loading state */
        .btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
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
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Inloggen</a></li>
                <li><a href="register.php"><i class="fas fa-user-plus"></i> Registreren</a></li>
            </ul>
        </nav>
    </div>
</header>

<main>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title">
                    <i class="fas fa-key"></i>
                    Wachtwoord Vergeten
                </h1>
                <p class="auth-subtitle">
                    Geen zorgen! Voer uw e-mailadres in en we sturen u een reset link.
                </p>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php 
                        echo $message_type === 'success' ? 'check-circle' : 
                            ($message_type === 'error' ? 'exclamation-triangle' : 'info-circle'); 
                    ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php" class="auth-form" id="resetForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> E-mailadres
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="uw@email.com"
                           required 
                           autocomplete="email">
                </div>

                <button type="submit" class="btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i>
                    Reset Link Versturen
                </button>
            </form>

            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> Belangrijk om te weten:</h4>
                <ul>
                    <li>De reset link is 1 uur geldig</li>
                    <li>Controleer ook uw spam/junk folder</li>
                    <li>U ontvangt alleen een email als uw adres bij ons bekend is</li>
                    <li>De link kan slechts één keer gebruikt worden</li>
                </ul>
            </div>

            <div class="auth-links">
                Weet u uw wachtwoord weer? <a href="login.php">Log hier in</a><br>
                Nog geen account? <a href="register.php">Registreer hier</a>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    const submitBtn = document.getElementById('submitBtn');
    const emailInput = document.getElementById('email');
    
    // Form submission handling
    form.addEventListener('submit', function(e) {
        // Show loading state
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Versturen...';
        submitBtn.disabled = true;
    });
    
    // Email validation
    emailInput.addEventListener('input', function() {
        const email = this.value.trim();
        const isValid = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        
        if (email) {
            this.style.borderColor = isValid ? '#22c55e' : '#ef4444';
        } else {
            this.style.borderColor = '#e5e7eb';
        }
    });
    
    // Auto-hide success messages after 10 seconds
    const successMessage = document.querySelector('.message.success');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            successMessage.style.opacity = '0';
            successMessage.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                successMessage.remove();
            }, 500);
        }, 10000);
    }
    
    // Focus email input on page load
    emailInput.focus();
});
</script>

</body>
</html>