<?php
include 'includes/db.php';

$stmt = $pdo->query("SELECT pr.*, p.name AS product_name FROM product_reviews pr
                     JOIN products p ON pr.product_id = p.id
                     ORDER BY pr.created_at DESC");
$reviews = $stmt->fetchAll();
?>

<h1>Moderatie klantbeoordelingen</h1>
<?php foreach ($reviews as $r): ?>
    <div style="border:1px solid #ccc; padding:10px; margin:10px;">
        <strong><?php echo htmlspecialchars($r['customer_name']); ?></strong> over 
        <em><?php echo htmlspecialchars($r['product_name']); ?></em><br>
        <p><?php echo nl2br(htmlspecialchars($r['review'])); ?></p>
        <form method="POST" action="moderate_review.php">
            <input type="hidden" name="review_id" value="<?php echo $r['id']; ?>">
            <?php if (!$r['is_approved']): ?>
                <button name="action" value="approve">Goedkeuren</button>
            <?php endif; ?>
            <button name="action" value="delete" onclick="return confirm('Weet je zeker dat je deze review wilt verwijderen?');">Verwijderen</button>
        </form>
    </div>
<?php endforeach; ?>
