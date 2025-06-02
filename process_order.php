<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];

    if (isset($_POST['approve'])) {
        // Bestelling goedkeuren (status wijzigen naar 'Goedgekeurd')
        $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?");
        $stmt->execute([$order_id]);
    } elseif (isset($_POST['reject'])) {
        // Bestelling afwijzen en verwijderen uit de database
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
    }

    // Terug naar het admin panel
    header("Location: admin_panel.php");
    exit;
} else {
    header("Location: admin_panel.php");
    exit;
}
