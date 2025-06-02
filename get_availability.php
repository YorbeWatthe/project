<?php
header('Content-Type: application/json');

// Verbind met de database
include 'includes/db.php';

try {
    // Haal de beschikbaarheid van de springkastelen op
    $stmt = $pdo->query("
        SELECT 
            bca.date, 
            bca.is_available, 
            p.name as product_name,
            p.id as product_id
        FROM bouncy_castle_availability bca
        JOIN products p ON bca.product_id = p.id
        ORDER BY bca.date ASC
    ");

    // Maak een array om de evenementen voor de kalender op te slaan
    $events = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Bepaal de titel en kleur op basis van beschikbaarheid
        $title = $row['product_name'];
        $color = $row['is_available'] == 1 ? '#27ae60' : '#e74c3c'; // Groen voor beschikbaar, rood voor niet beschikbaar
        $status = $row['is_available'] == 1 ? 'Beschikbaar' : 'Niet beschikbaar';
        
        // Voeg de eventinformatie toe aan de array
        $events[] = [
            'title' => $title,
            'start' => $row['date'], // FullCalendar verwacht 'start' voor de datum
            'color' => $color,
            'textColor' => '#ffffff',
            'description' => $status,
            'product_name' => $row['product_name'],
            'product_id' => $row['product_id'],
            'is_available' => $row['is_available'],
            'allDay' => true // Hele dag evenement
        ];
    }

    // Zet de events om naar JSON en geef het resultaat terug
    echo json_encode($events);

} catch (PDOException $e) {
    // Error handling
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>