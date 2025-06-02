<?php
session_start();
include 'includes/db.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // If using Composer
// OR include manually if downloaded:
// require 'phpmailer/src/Exception.php';
// require 'phpmailer/src/PHPMailer.php';
// require 'phpmailer/src/SMTP.php';

// SMTP Configuration - UPDATE THESE SETTINGS
$smtp_config = [
    // For admin notifications (to contact@lg-fun.be)
    'admin' => [
        'host' => 'smtp-auth.mailprotect.be',
        'username' => 'contact@lg-fun.be',
        'password' => 'L02-01g24',  // contact@lg-fun.be password
        'port' => 587,
        'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
        'from_email' => 'contact@lg-fun.be',
        'from_name' => 'L&G Fun Contact'
    ],
    // For auto-replies (from no-reply@lg-fun.be)
    'noreply' => [
        'host' => 'smtp-auth.mailprotect.be',
        'username' => 'no-reply@lg-fun.be',
        'password' => 'L02-01g24',  // no-reply@lg-fun.be password
        'port' => 587,
        'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
        'from_email' => 'no-reply@lg-fun.be',
        'from_name' => 'L&G Fun'
    ]
];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['contact_error'] = 'Ongeldig verzoek.';
    header('Location: contact.php');
    exit;
}

// Get and sanitize form data
$naam = trim($_POST['naam'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefoon = trim($_POST['telefoon'] ?? '');
$onderwerp = $_POST['onderwerp'] ?? 'algemeen';
$bericht = trim($_POST['bericht'] ?? '');
$privacy = isset($_POST['privacy']);

// Validation
$errors = [];

if (strlen($naam) < 2) {
    $errors[] = 'Naam moet minimaal 2 karakters bevatten.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Ongeldig e-mailadres.';
}

if (strlen($bericht) < 10) {
    $errors[] = 'Bericht moet minimaal 10 karakters bevatten.';
}

if (!$privacy) {
    $errors[] = 'Je moet akkoord gaan met de privacyverklaring.';
}

// Check for spam (basic honeypot and rate limiting)
if (isset($_POST['website']) && !empty($_POST['website'])) {
    // Hidden honeypot field was filled - likely spam
    $_SESSION['contact_error'] = 'Er is een fout opgetreden.';
    header('Location: contact.php');
    exit;
}

// Rate limiting - check if same IP submitted recently
$ip_address = $_SERVER['REMOTE_ADDR'];
$time_limit = date('Y-m-d H:i:s', strtotime('-5 minutes'));

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_submissions WHERE ip_address = ? AND submitted_at > ?");
    $stmt->execute([$ip_address, $time_limit]);
    $recent_submissions = $stmt->fetchColumn();
    
    if ($recent_submissions >= 3) {
        $errors[] = 'Te veel berichten verzonden. Probeer het over 5 minuten opnieuw.';
    }
} catch (Exception $e) {
    // If table doesn't exist, create it
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS contact_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(255) NOT NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip_address, submitted_at)
        )");
    } catch (Exception $e) {
        // Ignore if we can't create table
        error_log("Failed to create contact_submissions table: " . $e->getMessage());
    }
}

// If there are validation errors, return to form
if (!empty($errors)) {
    $_SESSION['contact_error'] = implode(' ', $errors);
    header('Location: contact.php');
    exit;
}

// Prepare email content
$onderwerp_labels = [
    'algemeen' => 'Algemene vraag',
    'reservering' => 'Reservering',
    'prijzen' => 'Informatie over prijzen',
    'beschikbaarheid' => 'Beschikbaarheid',
    'klacht' => 'Klacht',
    'anders' => 'Anders'
];

$onderwerp_text = $onderwerp_labels[$onderwerp] ?? 'Algemene vraag';

// Create HTML email content for admin notification
$admin_email_html = "
<!DOCTYPE html>
<html lang='nl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            margin: 0; 
            padding: 0; 
            background-color: #f8fafc; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            font-weight: 700;
        }
        .header p { 
            margin: 10px 0 0 0; 
            opacity: 0.9; 
            font-size: 16px;
        }
        .content { 
            padding: 30px 20px; 
        }
        .field { 
            margin-bottom: 20px; 
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 15px;
        }
        .field:last-of-type {
            border-bottom: none;
        }
        .label { 
            font-weight: 600; 
            color: #334155; 
            font-size: 14px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .value { 
            background: #f8fafc; 
            padding: 15px; 
            border-radius: 8px; 
            border-left: 4px solid #667eea; 
            font-size: 15px;
        }
        .message { 
            background: #f8fafc; 
            padding: 20px; 
            border-radius: 8px; 
            white-space: pre-wrap; 
            line-height: 1.8; 
            font-size: 15px;
            border-left: 4px solid #667eea;
        }
        .priority { 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); 
            color: white; 
            padding: 10px 15px; 
            border-radius: 6px; 
            display: inline-block; 
            font-weight: 600; 
            margin-bottom: 20px; 
            font-size: 14px;
        }
        .quick-actions { 
            background: #eff6ff; 
            border: 1px solid #dbeafe; 
            padding: 20px; 
            border-radius: 8px; 
            margin-top: 20px; 
        }
        .quick-actions h3 {
            margin: 0 0 15px 0;
            color: #1e40af;
            font-size: 16px;
        }
        .quick-actions a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .quick-actions a:hover {
            text-decoration: underline;
        }
        .footer { 
            background: #f1f5f9; 
            padding: 20px; 
            text-align: center; 
            font-size: 12px; 
            color: #64748b; 
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🚀 Nieuw Contactbericht</h1>
            <p>Via het contactformulier op lg-fun.be</p>
        </div>
        <div class='content'>";

if ($onderwerp === 'reservering') {
    $admin_email_html .= "<div class='priority'>⭐ PRIORITEIT: Reserveringsaanvraag</div>";
}

$admin_email_html .= "
            <div class='field'>
                <div class='label'>👤 Contactpersoon</div>
                <div class='value'>" . htmlspecialchars($naam) . "</div>
            </div>
            
            <div class='field'>
                <div class='label'>📧 E-mailadres</div>
                <div class='value'>" . htmlspecialchars($email) . "</div>
            </div>";

if (!empty($telefoon)) {
    $admin_email_html .= "
            <div class='field'>
                <div class='label'>📞 Telefoonnummer</div>
                <div class='value'>" . htmlspecialchars($telefoon) . "</div>
            </div>";
}

$admin_email_html .= "
            <div class='field'>
                <div class='label'>🏷️ Onderwerp</div>
                <div class='value'>" . htmlspecialchars($onderwerp_text) . "</div>
            </div>
            
            <div class='field'>
                <div class='label'>💬 Bericht</div>
                <div class='message'>" . nl2br(htmlspecialchars($bericht)) . "</div>
            </div>
            
            <div class='quick-actions'>
                <h3>🚀 Quick Actions</h3>
                📧 <a href='mailto:" . htmlspecialchars($email) . "?subject=Re: " . htmlspecialchars($onderwerp_text) . "'>Direct beantwoorden</a><br><br>";

if (!empty($telefoon)) {
    $phone_clean = preg_replace('/[^0-9+]/', '', $telefoon);
    $admin_email_html .= "📞 <a href='tel:" . htmlspecialchars($phone_clean) . "'>Bellen: " . htmlspecialchars($telefoon) . "</a><br><br>";
    $admin_email_html .= "💬 <a href='https://api.whatsapp.com/send?phone=" . str_replace('+', '', $phone_clean) . "'>WhatsApp sturen</a><br><br>";
}

$admin_email_html .= "
                🎪 <a href='https://lg-fun.be/filter_products.php'>Beschikbaarheid checken</a>
            </div>
        </div>
        <div class='footer'>
            <p>📅 Ontvangen op: " . date('d-m-Y H:i:s') . "</p>
            <p>🌐 IP-adres: " . htmlspecialchars($ip_address) . "</p>
            <p>Deze email is automatisch gegenereerd door het L&G Fun contactsysteem</p>
        </div>
    </div>
</body>
</html>";

// Create customer auto-reply HTML
$customer_email_html = "
<!DOCTYPE html>
<html lang='nl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            margin: 0; 
            padding: 0; 
            background-color: #f8fafc; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            font-weight: 700;
        }
        .header p { 
            margin: 10px 0 0 0; 
            opacity: 0.9; 
            font-size: 16px;
        }
        .content { 
            padding: 30px 20px; 
        }
        .highlight { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
            color: white; 
            padding: 20px; 
            border-radius: 12px; 
            margin: 25px 0; 
        }
        .highlight h3 { 
            margin: 0 0 15px 0; 
            font-size: 18px; 
        }
        .highlight ul { 
            margin: 0; 
            padding-left: 20px; 
        }
        .highlight li { 
            margin-bottom: 8px; 
        }
        .contact-info { 
            background: #eff6ff; 
            border: 1px solid #dbeafe; 
            padding: 20px; 
            border-radius: 12px; 
            margin: 25px 0; 
        }
        .contact-info h3 { 
            margin: 0 0 15px 0; 
            color: #1e40af; 
            font-size: 18px;
        }
        .contact-method { 
            margin-bottom: 10px; 
            font-size: 15px;
        }
        .contact-method strong { 
            color: #1e40af; 
        }
        .contact-method a {
            color: #2563eb;
            text-decoration: none;
        }
        .contact-method a:hover {
            text-decoration: underline;
        }
        .button-container { 
            text-align: center; 
            margin: 30px 0; 
        }
        .button { 
            display: inline-block; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: 600; 
            margin: 10px 5px; 
        }
        .button:hover {
            opacity: 0.9;
        }
        .footer { 
            background: #f1f5f9; 
            padding: 20px; 
            text-align: center; 
            font-size: 12px; 
            color: #64748b; 
        }
        .logo { 
            text-align: center; 
            margin-bottom: 20px; 
        }
        .special-notice {
            background: #fef3c7; 
            border: 1px solid #f59e0b; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 20px 0;
            font-size: 15px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>✅ Bedankt voor je bericht!</h1>
            <p>We hebben je aanvraag goed ontvangen</p>
        </div>
        <div class='content'>
            <div class='logo'>
                <h2 style='color: #667eea; margin: 0; font-size: 28px;'>🎪 L&G Fun</h2>
                <p style='color: #64748b; margin: 5px 0 0 0; font-style: italic;'>Springkastelen verhuur</p>
            </div>
            
            <p style='font-size: 16px;'>Beste <strong>" . htmlspecialchars($naam) . "</strong>,</p>
            
            <p>Hartelijk dank voor je bericht via onze website! We hebben je aanvraag betreffende <strong>" . htmlspecialchars($onderwerp_text) . "</strong> goed ontvangen en waarderen je interesse in onze diensten.</p>";

if ($onderwerp === 'reservering') {
    $customer_email_html .= "
            <div class='special-notice'>
                <strong>🎯 Reserveringsaanvraag ontvangen!</strong><br>
                We gaan direct aan de slag met je reservering en nemen zo snel mogelijk contact op voor de details en beschikbaarheid.
            </div>";
}

$customer_email_html .= "
            <div class='highlight'>
                <h3>🚀 Wat gebeurt er nu?</h3>
                <ul>
                    <li><strong>Binnen 24 uur</strong> bekijken we je bericht persoonlijk</li>
                    <li><strong>Persoonlijk antwoord</strong> van ons ervaren team</li>
                    <li><strong>Bij beschikbaarheidsvragen</strong> controleren we onze agenda direct</li>
                    <li><strong>Bij reserveringen</strong> ontvang je een gedetailleerde offerte</li>
                </ul>
            </div>
            
            <p><strong>Dringende vraag?</strong> Aarzel niet om ons direct te contacteren!</p>
            
            <div class='contact-info'>
                <h3>📞 Direct Contact</h3>
                <div class='contact-method'>
                    <strong>Telefoon:</strong> 
                    <a href='tel:+32488610540'>+32 488 61.05.40</a> | 
                    <a href='tel:+32485671163'>+32 485 67.11.63</a>
                </div>
                <div class='contact-method'>
                    <strong>WhatsApp:</strong> 
                    <a href='https://api.whatsapp.com/send?phone=32488610540'>Stuur direct een bericht</a>
                </div>
                <div class='contact-method'>
                    <strong>Email:</strong> 
                    <a href='mailto:contact@lg-fun.be'>contact@lg-fun.be</a>
                </div>
            </div>
            
            <div class='button-container'>
                <a href='https://api.whatsapp.com/send?phone=32488610540' class='button'>💬 WhatsApp ons</a>
                <a href='tel:+32488610540' class='button'>📞 Bel direct</a>
            </div>
            
            <p>We kijken ernaar uit om van je te horen en jouw evenement onvergetelijk te maken!</p>
            
            <p style='margin-top: 30px;'>Met vriendelijke groet,<br>
            <strong>Het L&G Fun team 🎪</strong></p>
        </div>
        <div class='footer'>
            <p>Dit is een automatische bevestiging. Je hoeft hier niet op te antwoorden.</p>
            <p>© " . date('Y') . " L&G Fun - Voor onvergetelijke momenten</p>
            <p>Website: <a href='https://lg-fun.be'>lg-fun.be</a></p>
        </div>
    </div>
</body>
</html>";

// Function to send email using PHPMailer
function sendEmailWithPHPMailer($to, $to_name, $subject, $html_body, $config_type = 'admin', $reply_to_email = null, $reply_to_name = null) {
    global $smtp_config;
    
    $config = $smtp_config[$config_type];
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Enable verbose debug output (disable in production)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to, $to_name);
        
        // Reply-to
        if ($reply_to_email) {
            $mail->addReplyTo($reply_to_email, $reply_to_name);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_body));
        
        // Send
        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error ($config_type): " . $mail->ErrorInfo);
        return false;
    }
}

// Send email to admin (using contact@lg-fun.be account)
$admin_subject = "[L&G Fun] $onderwerp_text - $naam";
$admin_sent = sendEmailWithPHPMailer(
    'contact@lg-fun.be',           // TO: Admin email
    'L&G Fun Contact',             // TO: Admin name
    $admin_subject,                // Subject
    $admin_email_html,             // HTML content
    'admin',                       // Use admin SMTP config
    $email,                        // Reply-to: Customer email
    $naam                          // Reply-to: Customer name
);

// Send auto-reply to customer (using no-reply@lg-fun.be account)
$customer_subject = "Bedankt voor je bericht - L&G Fun";
$customer_sent = sendEmailWithPHPMailer(
    $email,                        // TO: Customer email
    $naam,                         // TO: Customer name
    $customer_subject,             // Subject
    $customer_email_html,          // HTML content
    'noreply'                      // Use no-reply SMTP config
);

// Log submission to database (if possible)
try {
    $stmt = $pdo->prepare("INSERT INTO contact_submissions (ip_address, email, submitted_at) VALUES (?, ?, NOW())");
    $stmt->execute([$ip_address, $email]);
} catch (Exception $e) {
    // Ignore database errors for logging
    error_log("Database logging failed: " . $e->getMessage());
}

// Set success/error message
if ($admin_sent) {
    $_SESSION['contact_success'] = true;
    
    // Log successful email for debugging
    error_log("Contact form email sent successfully to admin from: $email");
    
    if (!$customer_sent) {
        error_log("Auto-reply email failed for: $email");
    }
} else {
    $_SESSION['contact_error'] = 'Er is een fout opgetreden bij het verzenden van je bericht. Probeer het opnieuw of neem telefonisch contact op: +32 488 61.05.40';
    error_log("Contact form email failed from: $email");
}

// Redirect back to contact page
header('Location: contact.php');
exit;