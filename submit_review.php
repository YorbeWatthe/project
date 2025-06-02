<?php
include 'includes/db.php';

$product_id = $_POST['product_id'];
$customer_name = $_POST['customer_name'];
$review = $_POST['review'];

$stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, customer_name, review) VALUES (?, ?, ?)");
$stmt->execute([$product_id, $customer_name, $review]);

header("Location: info_product.php?id=" . $product_id);
exit;
?>
