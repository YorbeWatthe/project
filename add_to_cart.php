<?php
session_start();
include 'includes/db.php'; // Gebruik de standaard databaseverbinding

// Controleer of er een POST-verzoek is
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?error=Ongeldige aanvraag.");
    exit();
}

// Haal productinformatie op uit de POST
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Validatie van invoer
if (!$product_id || $quantity <= 0) {
    header("Location: index.php?error=Ongeldige productinformatie.");
    exit();
}

// Controleer of het product beschikbaar is
$stmt = $pdo->prepare("SELECT available FROM products WHERE id = :product_id");
$stmt->execute(['product_id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product || $product['available'] == 0) {
    header("Location: index.php?error=Het geselecteerde product is niet beschikbaar.");
    exit();
}

// Controleer of de gebruiker is ingelogd
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Voeg het product toe aan de database-winkelwagen
    $stmt = $pdo->prepare("
        INSERT INTO cart_items (user_id, product_id, quantity, created_at)
        VALUES (:user_id, :product_id, :quantity, NOW())
        ON DUPLICATE KEY UPDATE quantity = quantity + :quantity
    ");
    $stmt->execute([
        'user_id' => $user_id,
        'product_id' => $product_id,
        'quantity' => $quantity,
    ]);
} else {
    // Gastmodus: winkelwagen opslaan in de sessie
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Voeg product toe aan de sessie-winkelwagen of verhoog het aantal
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

// Redirect naar de winkelwagen met succesbericht
header("Location: cart.php?success=Product toegevoegd!");
exit();
?>
