<?php
session_start();
include 'includes/db.php';

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';
$token_valid = false;
$user_email = '';

// Check if token is provided and valid
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Validate token from database
    $stmt = $pdo->prepare("
        SELECT pr.*, u.email, u.name 
        FROM password_resets pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.token = ? AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset_data = $stmt->fetch();
    
    if ($reset_data) {
        $token_valid = true;
        $user_email = $reset_data['email'];
        $user_id = $reset_data['user_id'];
    } else {
        $message = "Deze reset link is ongeldig of verlopen. Vraag een nieuwe reset link aan.";
        $message_type = 'error';
    }
} else {
    header("Location: forgot_password.php");
    exit;
}

// Process new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ongeldige aanvraag. Probeer het opnieuw.");
    }
    
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $message = "Vul alle velden in.";
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = "Wachtwoord moet minimaal 6 karakters bevatten.";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Wachtwoorden komen niet overeen.";
        $message_type = 'error';
    } else {
        // Update password in database
        $pdo->beginTransaction();
        
        try {
            // Update user password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Delete the used reset token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            
            // Delete all other reset tokens for this user
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            
            $message = "Uw wachtwoord is succesvol bijgewerkt! U kunt nu inloggen met uw nieuwe wachtwoord.";
            $message_type = 'success';
            $token_valid = false; // Prevent form from showing again
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Er is een fout opgetreden. Probeer het opnieuw.";
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord Resetten - L&G Fun Springkastelen</title>
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
            max-width: 500px;
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

        /* Password strength indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .password-strength.weak {
            background: linear-gradient(90deg, #ef4444 0%, #ef4444 33%, #e5e7eb 33%);
        }

        .password-strength.medium {
            background: linear-gradient(90deg, #f59e0b 0%, #f59e0b 66%, #e5e7eb 66%);
        }

        .password-strength.strong {
            background: linear-gradient(90deg, #22c55e 0%, #22c55e 100%);
        }

        .password-hint {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.3rem;
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

        .btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border: 2px solid #667eea;
            border-radius: 25px;
            margin-top: 1rem;
        }

        .auth-links a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        /* Success state */
        .success-state {
            text-align: center;
            padding: 2rem 0;
        }

        .success-icon {
            font-size: 4rem;
            color: #22c55e;
            margin-bottom: 1rem;
            animation: bounce 1.5s ease-in-out;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }

        /* Error state */
        .error-state {
            text-align: center;
            padding: 2rem 0;
        }

        .error-icon {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 1rem;
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
            <?php if (!$token_valid && $message_type !== 'success'): ?>
                <!-- Invalid/Expired Token State -->
                <div class="error-state">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h1 class="auth-title">
                        Reset Link Ongeldig
                    </h1>
                    <p class="auth-subtitle">
                        Deze reset link is verlopen of al gebruikt. Vraag een nieuwe aan.
                    </p>
                </div>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="auth-links">
                    <a href="forgot_password.php">
                        <i class="fas fa-redo"></i>
                        Nieuwe Reset Link Aanvragen
                    </a>
                </div>
                
            <?php elseif ($message_type === 'success'): ?>
                <!-- Success State -->
                <div class="success-state">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="auth-title">
                        Wachtwoord Bijgewerkt!
                    </h1>
                    <p class="auth-subtitle">
                        Uw wachtwoord is succesvol gewijzigd. U kunt nu inloggen.
                    </p>
                </div>
                
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $message; ?>
                </div>
                
                <div class="auth-links">
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i>
                        Nu Inloggen
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Reset Form State -->
                <div class="auth-header">
                    <h1 class="auth-title">
                        <i class="fas fa-key"></i>
                        Nieuw Wachtwoord
                    </h1>
                    <p class="auth-subtitle">
                        Voer uw nieuwe wachtwoord in voor <strong><?php echo htmlspecialchars($user_email); ?></strong>
                    </p>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="auth-form" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="new_password">
                            <i class="fas fa-lock"></i> Nieuw Wachtwoord
                        </label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-control" 
                               placeholder="Minimaal 6 karakters"
                               required 
                               autocomplete="new-password">
                        <div class="password-strength" id="passwordStrength"></div>
                        <div class="password-hint" id="passwordHint">Kies een sterk wachtwoord met minimaal 6 karakters</div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Bevestig Wachtwoord
                        </label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control" 
                               placeholder="Herhaal uw nieuwe wachtwoord"
                               required 
                               autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Wachtwoord Opslaan
                    </button>
                </form>

                <div class="auth-links">
                    Toch niet resetten? <a href="login.php">Terug naar inloggen</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    const submitBtn = document.getElementById('submitBtn');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordHint = document.getElementById('passwordHint');
    
    if (form) {
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let hints = [];
            
            if (password.length >= 6) strength++;
            else hints.push('minimaal 6 karakters');
            
            if (password.match(/[a-z]/)) strength++;
            else hints.push('kleine letters');
            
            if (password.match(/[A-Z]/)) strength++;
            else hints.push('hoofdletters');
            
            if (password.match(/[0-9]/)) strength++;
            else hints.push('cijfers');
            
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            else hints.push('speciale tekens');
            
            return { strength, hints };
        }
        
        // Real-time password validation
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const result = checkPasswordStrength(password);
            
            // Update strength indicator
            passwordStrength.className = 'password-strength';
            if (result.strength <= 2) {
                passwordStrength.classList.add('weak');
            } else if (result.strength <= 3) {
                passwordStrength.classList.add('medium');
            } else {
                passwordStrength.classList.add('strong');
            }
            
            // Update hint
            if (password.length === 0) {
                passwordHint.textContent = 'Kies een sterk wachtwoord met minimaal 6 karakters';
                passwordHint.style.color = '#64748b';
            } else if (result.strength <= 2) {
                passwordHint.textContent = 'Zwak wachtwoord - voeg toe: ' + result.hints.slice(0, 2).join(', ');
                passwordHint.style.color = '#ef4444';
            } else if (result.strength <= 3) {
                passwordHint.textContent = 'Gemiddeld wachtwoord - overweeg: ' + result.hints.slice(0, 1).join(', ');
                passwordHint.style.color = '#f59e0b';
            } else {
                passwordHint.textContent = 'Sterk wachtwoord!';
                passwordHint.style.color = '#22c55e';
            }
            
            // Update border color
            this.style.borderColor = result.strength <= 2 ? '#ef4444' : 
                                   result.strength <= 3 ? '#f59e0b' : '#22c55e';
        });
        
        // Confirm password validation
        confirmPasswordInput.addEventListener('input', function() {
            const matches = this.value === newPasswordInput.value;
            this.style.borderColor = this.value.length === 0 ? '#e5e7eb' : 
                                   matches ? '#22c55e' : '#ef4444';
        });
        
        // Form submission
        form.addEventListener('submit', function(e) {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Wachtwoorden komen niet overeen.');
                confirmPasswordInput.focus();
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Wachtwoord moet minimaal 6 karakters bevatten.');
                newPasswordInput.focus();
                return;
            }
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Opslaan...';
            submitBtn.disabled = true;
        });
        
        // Focus first input
        newPasswordInput.focus();
    }
});
</script>

</body>
</html>