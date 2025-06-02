<?php
$servername = "ID458558_lgfun.db.webhosting.be";  // Database server (hostname)
$username = "ID458558_lgfun";                    // Gebruikersnaam (username)
$password = "LG2025FunProject";             // Vul hier je databasewachtwoord in
$dbname = "ID458558_lgfun";                     // Database naam

try {
    // Set up the PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If connection fails, show error message
    echo "Connection failed: " . $e->getMessage();
}
?>
