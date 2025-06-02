
<?php
include '../includes/db.php';

$stmt = $pdo->query("SELECT id, name, email, active FROM users");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    echo "<div>{$user['name']} - {$user['email']} - Actief: {$user['active']}
        <a href='edit_user.php?id={$user['id']}'>Bewerken</a>
        <a href='deactivate_user.php?id={$user['id']}'>Deactiveren</a></div>";
}
?>
