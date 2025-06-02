<?php
session_start();
include 'includes/db.php';

// Controleer of de gebruiker een admin is
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit;
}

// Bericht variabele
$message = "";
$error = "";

// Kortingscode toevoegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_discount'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount_percentage = floatval($_POST['discount_percentage']);
    $valid_from = $_POST['valid_from'];
    $valid_until = $_POST['valid_until'];

    if (!empty($code) && $discount_percentage > 0 && $valid_from && $valid_until) {
        $stmt = $pdo->prepare("INSERT INTO discount_codes (code, discount_percentage, valid_from, valid_until, created_at) 
                               VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$code, $discount_percentage, $valid_from, $valid_until]);
        $message = "Kortingscode succesvol toegevoegd!";
    } else {
        $error = "Vul alle velden correct in.";
    }
}

// Haal alle bestellingen op en voeg klantnaam toe
$stmt = $pdo->prepare("
    SELECT orders.id, orders.user_id, orders.total_price, orders.status, users.name AS klantnaam 
    FROM orders 
    JOIN users ON orders.user_id = users.id
    WHERE orders.status = 'pending' 
    ORDER BY orders.id DESC
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kortingscode verwijderen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_discount'])) {
    $discount_id = $_POST['discount_id'];
    $stmt = $pdo->prepare("DELETE FROM discount_codes WHERE id = ?");
    $stmt->execute([$discount_id]);
    $message = "Kortingscode verwijderd.";
}

// Haal alle kortingscodes op
$stmt = $pdo->query("SELECT * FROM discount_codes ORDER BY created_at DESC");
$discounts = $stmt->fetchAll();

// Bulk beschikbaarheid bijwerken (NIEUW)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bulk_availability'])) {
    $product_id = $_POST['product_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_available = $_POST['is_available'] ? 1 : 0;

    if (!empty($product_id) && !empty($start_date) && !empty($end_date)) {
        // Valideer dat einddatum na startdatum ligt
        if (strtotime($end_date) >= strtotime($start_date)) {
            $current_date = $start_date;
            $success_count = 0;
            
            while (strtotime($current_date) <= strtotime($end_date)) {
                $stmt = $pdo->prepare("INSERT INTO bouncy_castle_availability (product_id, date, is_available)
                    VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_available = ?");
                $stmt->execute([$product_id, $current_date, $is_available, $is_available]);
                $success_count++;
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            
            $message = "Beschikbaarheid succesvol bijgewerkt voor $success_count dagen!";
        } else {
            $error = "Einddatum moet na of gelijk aan startdatum zijn.";
        }
    } else {
        $error = "Vul alle velden in voor bulk update.";
    }
}

// Enkele dag beschikbaarheid bijwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_availability'])) {
    $product_id = $_POST['product_id'];
    $date = $_POST['date'];
    $is_available = $_POST['is_available'] ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO bouncy_castle_availability (product_id, date, is_available)
        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_available = ?");
    $stmt->execute([$product_id, $date, $is_available, $is_available]);
    $message = "Beschikbaarheid succesvol bijgewerkt!";
}

// Gebruiker bewerken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    if (!empty($name) && !empty($email)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, address = ?, is_admin = ? WHERE id = ?");
        $stmt->execute([$name, $email, $address, $is_admin, $user_id]);
        $message = "Gebruiker succesvol bijgewerkt!";
    } else {
        $error = "Naam en email zijn verplicht.";
    }
}

// Springkastelen ophalen voor de beschikbaarheid
$stmt = $pdo->query("SELECT * FROM products");
$products = $stmt->fetchAll();

// Gebruikers ophalen
$stmt = $pdo->query("SELECT * FROM users WHERE is_active = 1");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.js"></script>
    <style>
        /* Modern Admin Panel Styling */
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-bg: #f8f9fa;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --border-radius: 8px;
        }

        .admin-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-bg);
        }

        .admin-title {
            color: var(--primary-color);
            font-size: 2.5em;
            margin: 0;
        }

        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .admin-section {
            margin: 40px 0;
            padding: 25px;
            background: var(--light-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.8em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--secondary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .form-group input, .form-group select {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background-color: #e67e22;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .data-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .data-table tr:hover {
            background-color: var(--light-bg);
        }

        .availability-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        .bulk-availability {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
        }

        .bulk-availability .form-group label {
            color: white;
        }

        .bulk-availability .form-group input,
        .bulk-availability .form-group select {
            background: rgba(255,255,255,0.9);
        }

        .single-availability {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
        }

        .single-availability .form-group label {
            color: white;
        }

        .single-availability .form-group input,
        .single-availability .form-group select {
            background: rgba(255,255,255,0.9);
        }

        .calendar-section {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        #calendar {
            margin-top: 20px;
        }

        .user-edit-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .user-edit-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .user-edit-form button {
            padding: 8px 16px;
            font-size: 14px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--light-bg);
        }

        .tab {
            padding: 15px 25px;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: var(--secondary-color);
            border-bottom: 3px solid var(--secondary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .availability-form {
                grid-template-columns: 1fr;
            }
            
            .user-edit-form {
                flex-direction: column;
                align-items: stretch;
            }
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
                <li><a href="index.php">Home</a></li>
                <li><a href="deactivate.php">Deactiveren</a></li>
                <li><a href="admin_overview_products.php">Producten</a></li>
                <li><a href="admin_orders.php">Overzicht Bestellingen</a></li>
                <li><a href="loguit.php">Uitloggen</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Admin Dashboard</h1>
        <div>
            <span>Welkom, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
        </div>
    </div>

    <!-- Berichten -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="showTab('orders')">Bestellingen</button>
        <button class="tab" onclick="showTab('availability')">Beschikbaarheid</button>
        <button class="tab" onclick="showTab('calendar')">Kalender</button>
        <button class="tab" onclick="showTab('discounts')">Kortingscodes</button>
        <button class="tab" onclick="showTab('users')">Gebruikers</button>
    </div>

    <!-- Bestellingen Tab -->
    <div id="orders" class="tab-content active">
        <div class="admin-section">
            <h2 class="section-title">Bestellingen Beheren</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bestelnummer</th>
                        <th>Klantnaam</th>
                        <th>Totaalbedrag</th>
                        <th>Status</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['klantnaam']); ?></td>
                        <td>€ <?php echo number_format($order['total_price'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td>
                            <form method="POST" action="process_order.php" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="approve" class="btn btn-success">Goedkeuren</button>
                                <button type="submit" name="reject" class="btn btn-danger" onclick="return confirm('Weet je zeker dat je deze bestelling wilt afwijzen?');">Afwijzen</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Beschikbaarheid Tab -->
    <div id="availability" class="tab-content">
        <div class="admin-section">
            <h2 class="section-title">Beschikbaarheid Beheren</h2>
            
            <div class="availability-form">
                <!-- Bulk Beschikbaarheid -->
                <div class="bulk-availability">
                    <h3>📅 Meerdere dagen beschikbaar</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="bulk_product_id">Selecteer Springkasteel:</label>
                            <select name="product_id" id="bulk_product_id" required>
                                <option value="">Kies een springkasteel...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="start_date">Startdatum:</label>
                            <input type="date" name="start_date" id="start_date" required>
                        </div>

                        <div class="form-group">
                            <label for="end_date">Einddatum:</label>
                            <input type="date" name="end_date" id="end_date" required>
                        </div>

                        <div class="form-group">
                            <label for="bulk_is_available">Beschikbaarheid:</label>
                            <select name="is_available" id="bulk_is_available">
                                <option value="1">✅ Beschikbaar</option>
                                <option value="0">❌ Niet Beschikbaar</option>
                            </select>
                        </div>

                        <button type="submit" name="update_bulk_availability" class="btn btn-primary">Updaten</button>
                    </form>
                </div>

                <!-- Enkele Dag Beschikbaarheid -->
                <div class="single-availability">
                    <h3>📅 Eén dag beschikbaar</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="single_product_id">Selecteer Springkasteel:</label>
                            <select name="product_id" id="single_product_id" required>
                                <option value="">Kies een springkasteel...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="date">Datum:</label>
                            <input type="date" name="date" id="date" required>
                        </div>

                        <div class="form-group">
                            <label for="single_is_available">Beschikbaarheid:</label>
                            <select name="is_available" id="single_is_available">
                                <option value="1">✅ Beschikbaar</option>
                                <option value="0">❌ Niet Beschikbaar</option>
                            </select>
                        </div>

                        <button type="submit" name="update_availability" class="btn btn-warning">Updaten</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Kalender Tab -->
    <div id="calendar" class="tab-content">
        <div class="admin-section calendar-section">
            <h2 class="section-title">Kalender Overzicht</h2>
            <div id="fullcalendar"></div>
        </div>
    </div>

    <!-- Kortingscodes Tab -->
    <div id="discounts" class="tab-content">
        <div class="admin-section">
            <h2 class="section-title">Kortingscodes Beheren</h2>
            
            <!-- Kortingscode Toevoegen -->
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="code">Kortingscode:</label>
                        <input type="text" name="code" id="code" placeholder="Bijv. ZOMER2024" required>
                    </div>

                    <div class="form-group">
                        <label for="discount_percentage">Korting (%):</label>
                        <input type="number" name="discount_percentage" id="discount_percentage" step="0.01" min="0" max="100" required>
                    </div>

                    <div class="form-group">
                        <label for="valid_from">Geldig vanaf:</label>
                        <input type="date" name="valid_from" id="valid_from" required>
                    </div>

                    <div class="form-group">
                        <label for="valid_until">Geldig tot:</label>
                        <input type="date" name="valid_until" id="valid_until" required>
                    </div>
                </div>
                <button type="submit" name="add_discount" class="btn btn-primary">Kortingscode Toevoegen</button>
            </form>

            <!-- Bestaande Kortingscodes -->
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kortingscode</th>
                        <th>Korting (%)</th>
                        <th>Geldig vanaf</th>
                        <th>Geldig tot</th>
                        <th>Status</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discounts as $discount): ?>
                        <?php 
                        $today = date('Y-m-d');
                        $status = 'Actief';
                        $statusClass = 'btn-success';
                        if ($today < $discount['valid_from']) {
                            $status = 'Nog niet actief';
                            $statusClass = 'btn-warning';
                        } elseif ($today > $discount['valid_until']) {
                            $status = 'Verlopen';
                            $statusClass = 'btn-danger';
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($discount['code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($discount['discount_percentage']); ?>%</td>
                            <td><?php echo htmlspecialchars($discount['valid_from']); ?></td>
                            <td><?php echo htmlspecialchars($discount['valid_until']); ?></td>
                            <td><span class="btn <?php echo $statusClass; ?>" style="padding: 4px 8px; font-size: 12px;"><?php echo $status; ?></span></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="discount_id" value="<?php echo $discount['id']; ?>">
                                    <button type="submit" name="delete_discount" class="btn btn-danger" onclick="return confirm('Weet je zeker dat je deze kortingscode wilt verwijderen?');">
                                        Verwijderen
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gebruikers Tab -->
    <div id="users" class="tab-content">
        <div class="admin-section">
            <h2 class="section-title">Gebruikers Beheren</h2>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Naam</th>
                        <th>Email</th>
                        <th>Adres</th>
                        <th>Admin</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['address']); ?></td>
                            <td><?php echo $user['is_admin'] ? '✅ Ja' : '❌ Nee'; ?></td>
                            <td>
                                <form method="POST" class="user-edit-form">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="text" name="name" placeholder="Naam" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <input type="text" name="address" placeholder="Adres" value="<?php echo htmlspecialchars($user['address']); ?>" required>
                                    <div class="checkbox-group">
                                        <label>Admin:</label>
                                        <input type="checkbox" name="is_admin" value="1" <?php if ($user['is_admin']) echo 'checked'; ?>>
                                    </div>
                                    <button type="submit" name="edit_user" class="btn btn-primary">Bijwerken</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Tab Functionaliteit
function showTab(tabName) {
    // Verberg alle tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Verwijder active class van alle tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Toon geselecteerde tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
    
    // Herinitialiseer kalender als kalender tab wordt geopend
    if (tabName === 'calendar') {
        setTimeout(() => {
            $('#fullcalendar').fullCalendar('render');
        }, 100);
    }
}

// FullCalendar Initialisatie
$(document).ready(function() {
    $('#fullcalendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        defaultView: 'month',
        height: 600,
        events: function(start, end, timezone, callback) {
            $.ajax({
                url: 'get_availability.php',
                dataType: 'json',
                success: function(data) {
                    var events = data.map(function(event) {
                        return {
                            title: event.product_name,
                            start: event.date,
                            description: event.is_available ? 'Beschikbaar' : 'Niet beschikbaar',
                            color: event.is_available ? '#27ae60' : '#e74c3c',
                            textColor: 'white'
                        };
                    });
                    callback(events);
                },
                error: function() {
                    console.log('Error loading calendar events');
                }
            });
        },
        eventRender: function(event, element) {
            element.attr('title', event.title + ' - ' + event.description);
        }
    });
    
    // Datum validatie voor bulk update
    $('#start_date, #end_date').on('change', function() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (startDate && endDate && startDate > endDate) {
            alert('Einddatum moet na of gelijk aan startdatum zijn.');
            $('#end_date').val('');
        }
    });
    
    // Auto-update einddatum naar startdatum als het eerder is
    $('#start_date').on('change', function() {
        var startDate = $(this).val();
        var endDate = $('#end_date').val();
        
        if (endDate && startDate > endDate) {
            $('#end_date').val(startDate);
        }
        
        // Set minimum date voor einddatum
        $('#end_date').attr('min', startDate);
    });
    
    // Bevestiging voor bulk updates
    $('form').on('submit', function(e) {
        if ($(this).find('input[name="update_bulk_availability"]').length > 0) {
            var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();
            var productName = $('#bulk_product_id option:selected').text();
            var availability = $('#bulk_is_available option:selected').text();
            
            if (startDate && endDate && productName !== 'Kies een springkasteel...') {
                var daysDiff = Math.ceil((new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24)) + 1;
                var message = `Je staat op het punt om ${productName} ${availability.toLowerCase()} te maken voor ${daysDiff} dagen (van ${startDate} tot ${endDate}).\n\nWeet je het zeker?`;
                
                if (!confirm(message)) {
                    e.preventDefault();
                }
            }
        }
    });
    
    // Tooltips voor status badges
    $('.data-table tbody tr').hover(
        function() {
            $(this).addClass('table-hover');
        },
        function() {
            $(this).removeClass('table-hover');
        }
    );
});

// Form validatie
function validateForm(formName) {
    var form = document.forms[formName];
    var isValid = true;
    
    // Basis validatie
    var requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            field.style.borderColor = '#e74c3c';
            isValid = false;
        } else {
            field.style.borderColor = '#ddd';
        }
    });
    
    return isValid;
}

// Real-time validatie feedback 
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = '#e74c3c';
                this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
            } else {
                this.style.borderColor = '#ddd';
                this.style.boxShadow = 'none';
            }
        });
        
        input.addEventListener('focus', function() {
            this.style.borderColor = '#3498db';
            this.style.boxShadow = '0 0 0 3px rgba(52, 152, 219, 0.1)';
        });
    });
});

// Animaties voor berichten
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        alert.style.transition = 'all 0.5s ease';
        
        setTimeout(() => {
            alert.style.opacity = '1';
            alert.style.transform = 'translateY(0)';
        }, 100);
        
        // Auto-hide na 5 seconden
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+1-5 voor tab switching
    if (e.ctrlKey) {
        switch(e.key) {
            case '1':
                e.preventDefault();
                showTab('orders');
                break;
            case '2':
                e.preventDefault();
                showTab('availability');
                break;
            case '3':
                e.preventDefault();
                showTab('calendar');
                break;
            case '4':
                e.preventDefault();
                showTab('discounts');
                break;
            case '5':
                e.preventDefault();
                showTab('users');
                break;
        }
    }
});

// Print functionaliteit
function printSection(sectionId) {
    var printContent = document.getElementById(sectionId).innerHTML;
    var originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Export functionaliteit (basis CSV export)
function exportTableToCSV(tableId, filename) {
    var csv = [];
    var rows = document.querySelectorAll(`#${tableId} tr`);
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (var j = 0; j < cols.length - 1; j++) { // -1 om acties kolom te skippen
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(","));
    }
    
    downloadCSV(csv.join("\n"), filename);
}

function downloadCSV(csv, filename) {
    var csvFile = new Blob([csv], {type: "text/csv"});
    var downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}
</script>

<style>
/* Aanvullende hover effecten */
.table-hover {
    background-color: #f1f3f4 !important;
    transform: scale(1.01);
    transition: all 0.2s ease;
}

/* Loading spinner voor AJAX calls */
.loading {
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive verbeteringen */
@media (max-width: 480px) {
    .admin-title {
        font-size: 1.8em;
    }
    
    .tabs {
        flex-wrap: wrap;
    }
    
    .tab {
        flex: 1;
        min-width: 120px;
        padding: 10px 15px;
        font-size: 14px;
    }
    
    .data-table {
        font-size: 14px;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px 8px;
    }
}

/* Focus verbeteringen voor toegankelijkheid */
.btn:focus,
input:focus,
select:focus {
    outline: 2px solid #3498db;
    outline-offset: 2px;
}

/* Status indicators */
.status-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-active {
    background-color: #27ae60;
}

.status-inactive {
    background-color: #e74c3c;
}

.status-pending {
    background-color: #f39c12;
}

/* Verbeterde form styling */
.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
}

/* Custom checkbox styling */
.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
}

/* Tooltip styling */
.tooltip {
    position: relative;
    display: inline-block;
    cursor: help;
}

.tooltip .tooltiptext {
    visibility: hidden;
    width: 200px;
    background-color: #555;
    color: white;
    text-align: center;
    border-radius: 6px;
    padding: 5px 10px;
    position: absolute;
    z-index: 1;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 14px;
}

.tooltip:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}
</style>

</body>
</html>