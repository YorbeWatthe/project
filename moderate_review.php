<?php
include 'includes/db.php';

$review_id = (int) $_POST['review_id'];
$action = $_POST['action'];

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE product_reviews SET is_approved = 1 WHERE id = ?");
    $stmt->execute([$review_id]);
} elseif ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id = ?");
    $stmt->execute([$review_id]);
}

header("Location: admin_reviews.php");
exit;
?>
