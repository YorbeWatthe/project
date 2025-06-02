<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $address = $_POST['address'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        $error = "Dit e-mailadres is al geregistreerd.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $address]);
        
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Registreren</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="navbar">
            <div class="logo">
                <img src="images/Logo L&G Fun-Photoroom.png" alt="Logo">
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="login.php">Inloggen</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="auth-container">
        <h1>Registreren</h1>

        <?php if (isset($error)): ?>
            <p style="color: red; text-align: center;"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="name">Naam:</label>
            <input type="text" name="name" id="name" required>

            <label for="email">E-mailadres:</label>
            <input type="email" name="email" id="email" required>

            <label for="password">Wachtwoord:</label>
            <input type="password" name="password" id="password" required>

            <label for="address">Bezorgadres:</label>
            <textarea name="address" id="address" required></textarea>

            <button type="submit">Registreren</button>
        </form>
    </div>
</body>
</html>
