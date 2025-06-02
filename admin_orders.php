<?php
session_start();
include 'includes/db.php';

// Controleer of de gebruiker een admin is
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit;
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

if ($filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR o.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get orders statistics
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COALESCE(SUM(total_price), 0) as total_revenue,
        COALESCE(AVG(total_price), 0) as avg_order_value
    FROM orders
");
$stats = $stats_stmt->fetch();

// Haal alle bestellingen op inclusief klantgegevens
$stmt = $pdo->prepare("
    SELECT o.id, o.user_id, o.total_price, o.status, o.created_at, 
           u.name AS customer_name, u.email, u.address 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $where_clause
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bestellingen Beheren - L&G Fun</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: #333;
            background-color: #f8fafc;
            line-height: 1.6;
        }

        /* Header styling */
        header {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .logo img {
            height: 60px;
            width: auto;
            filter: brightness(1.1);
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
            padding: 0;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        nav ul li a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Main container */
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 3rem;
            text-align: center;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .page-title i {
            color: #667eea;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: #64748b;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-color);
        }

        .stat-card.total { --card-color: #667eea; }
        .stat-card.revenue { --card-color: #22c55e; }
        .stat-card.pending { --card-color: #f59e0b; }
        .stat-card.completed { --card-color: #10b981; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: var(--card-color);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-subvalue {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 0.5rem;
        }

        /* Controls section */
        .controls-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .controls-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .controls-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .controls-title i {
            color: #667eea;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 1rem;
            align-items: end;
        }

        .search-group {
            display: flex;
            flex-direction: column;
        }

        .search-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .search-input {
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-select {
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        /* Orders table */
        .orders-table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-title i {
            color: #667eea;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }

        .orders-table td {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .orders-table tr:hover {
            background: #f8fafc;
        }

        .order-id {
            font-weight: 600;
            color: #667eea;
        }

        .customer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .customer-details h4 {
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.2rem;
        }

        .customer-details p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            text-transform: capitalize;
        }

        .status-pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        .status-confirmed {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
        }

        .status-completed {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
        }

        .status-cancelled {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
        }

        .price-cell {
            font-weight: 600;
            color: #22c55e;
            font-size: 1.1rem;
        }

        .date-cell {
            color: #64748b;
            font-size: 0.9rem;
        }

        .address-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #64748b;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #94a3b8;
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .controls-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
                flex-direction: column;
                gap: 0.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .orders-table-container {
                overflow-x: auto;
            }

            .orders-table {
                min-width: 800px;
                font-size: 0.9rem;
            }

            .orders-table td {
                padding: 1rem 0.5rem;
            }

            .navbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            nav ul {
                gap: 1rem;
            }

            nav ul li a {
                padding: 0.6rem 1rem;
                font-size: 1rem;
            }
        }

        /* Animation */
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.6s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        /* Export button */
        .export-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        /* Quick action buttons */
        .quick-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.3rem 0.6rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .action-view {
            background: #3b82f6;
            color: white;
        }

        .action-edit {
            background: #f59e0b;
            color: white;
        }

        .action-btn:hover {
            transform: scale(1.05);
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
                <li><a href="admin_dashboard.php"> Home</a></li>
                <li><a href="deactivate.php"> Deactiveren</a></li>
                <li><a href="admin_panel.php"> Admin Panel</a></li>
                <li><a href="loguit.php"> Uitloggen</a></li>
            </ul>
        </nav>
    </div>
</header>

<main>
    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                Bestellingen Beheren
            </h1>
            <p class="page-subtitle">Overzicht en beheer van alle inkomende bestellingen</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card total">
                <div class="stat-header">
                    <div class="stat-title">Totaal Bestellingen</div>
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-subvalue">Alle tijd</div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-header">
                    <div class="stat-title">Totale Omzet</div>
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                </div>
                <div class="stat-value">€<?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
                <div class="stat-subvalue">Gemiddeld: €<?php echo number_format($stats['avg_order_value'], 0, ',', '.'); ?></div>
            </div>

            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-title">In Afwachting</div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-subvalue">Nog te bevestigen</div>
            </div>

            <div class="stat-card completed">
                <div class="stat-header">
                    <div class="stat-title">Afgerond</div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['completed']; ?></div>
                <div class="stat-subvalue">Succesvol voltooid</div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section fade-in">
            <div class="controls-header">
                <h3 class="controls-title">
                    <i class="fas fa-filter"></i>
                    Filteren & Zoeken
                </h3>
                <button class="btn export-btn" onclick="exportOrders()">
                    <i class="fas fa-download"></i>
                    Exporteren
                </button>
            </div>
            
            <form method="GET" class="controls-grid">
                <div class="search-group">
                    <label for="search">Zoeken op klant, email of bestelling ID</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           class="search-input" 
                           placeholder="Typ om te zoeken..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <select name="filter" class="filter-select">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Alle bestellingen</option>
                    <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>In afwachting</option>
                    <option value="confirmed" <?php echo $filter === 'confirmed' ? 'selected' : ''; ?>>Bevestigd</option>
                    <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Afgerond</option>
                    <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Geannuleerd</option>
                </select>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Zoeken
                </button>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="orders-table-container fade-in">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-table"></i>
                    Bestellingen Overzicht (<?php echo count($orders); ?> resultaten)
                </h3>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Geen bestellingen gevonden</h3>
                    <p>Er zijn geen bestellingen die voldoen aan uw zoekcriteria.</p>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Bestelling</th>
                            <th>Klant</th>
                            <th>Contact</th>
                            <th>Adres</th>
                            <th>Totaal</th>
                            <th>Status</th>
                            <th>Datum</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <div class="order-id">#<?php echo htmlspecialchars($order['id']); ?></div>
                                </td>
                                <td>
                                    <div class="customer-info">
                                        <div class="customer-avatar">
                                            <?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?>
                                        </div>
                                        <div class="customer-details">
                                            <h4><?php echo htmlspecialchars($order['customer_name']); ?></h4>
                                            <p>ID: <?php echo $order['user_id']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['email']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="address-cell" title="<?php echo htmlspecialchars($order['address']); ?>">
                                        <?php echo htmlspecialchars($order['address']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="price-cell">
                                        €<?php echo number_format($order['total_price'], 2, ',', '.'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <i class="fas fa-<?php 
                                            echo $order['status'] === 'pending' ? 'clock' : 
                                                ($order['status'] === 'confirmed' ? 'check' : 
                                                ($order['status'] === 'completed' ? 'check-circle' : 'times')); 
                                        ?>"></i>
                                        <?php 
                                        $status_labels = [
                                            'pending' => 'In afwachting',
                                            'confirmed' => 'Bevestigd',
                                            'completed' => 'Afgerond',
                                            'cancelled' => 'Geannuleerd'
                                        ];
                                        echo $status_labels[$order['status']] ?? ucfirst($order['status']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <?php echo date('d-m-Y H:i', strtotime($order['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="quick-actions">
                                        <button class="action-btn action-view" 
                                                onclick="viewOrder(<?php echo $order['id']; ?>)"
                                                title="Bekijk details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn action-edit" 
                                                onclick="editOrder(<?php echo $order['id']; ?>)"
                                                title="Status wijzigen">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('search');
    const filterSelect = document.querySelector('select[name="filter"]');
    
    // Auto-submit on filter change
    filterSelect.addEventListener('change', function() {
        this.form.submit();
    });

    // Search on Enter key
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.form.submit();
        }
    });

    // Real-time search (optional - comment out if you prefer manual search)
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (this.value.length === 0 || this.value.length >= 2) {
                this.form.submit();
            }
        }, 500);
    });

    // Animate statistics cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });

    // Table row hover effect
    const tableRows = document.querySelectorAll('.orders-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.01)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = 'none';
        });
    });

    // Add loading states to action buttons
    document.querySelectorAll('.action-btn').forEach(button => {
        button.addEventListener('click', function() {
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;
            
            // Re-enable after 3 seconds as fallback
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.disabled = false;
            }, 3000);
        });
    });

    // Tooltip for long addresses
    document.querySelectorAll('.address-cell').forEach(cell => {
        cell.addEventListener('mouseenter', function() {
            if (this.scrollWidth > this.offsetWidth) {
                this.style.whiteSpace = 'normal';
                this.style.zIndex = '10';
                this.style.position = 'relative';
                this.style.backgroundColor = '#fff';
                this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                this.style.padding = '0.5rem';
                this.style.borderRadius = '6px';
            }
        });
        
        cell.addEventListener('mouseleave', function() {
            this.style.whiteSpace = 'nowrap';
            this.style.zIndex = '1';
            this.style.position = 'static';
            this.style.backgroundColor = 'transparent';
            this.style.boxShadow = 'none';
            this.style.padding = '1.5rem';
            this.style.borderRadius = '0';
        });
    });
});

// Order action functions
function viewOrder(orderId) {
    // Hier zou je een modal kunnen openen of doorverwijzen naar een detailpagina
    window.location.href = `order_details.php?id=${orderId}`;
}

function editOrder(orderId) {
    // Hier zou je een modal kunnen openen voor status wijziging
    const newStatus = prompt('Nieuwe status (pending/confirmed/completed/cancelled):');
    if (newStatus && ['pending', 'confirmed', 'completed', 'cancelled'].includes(newStatus)) {
        // AJAX call to update status
        updateOrderStatus(orderId, newStatus);
    }
}

function updateOrderStatus(orderId, status) {
    // Example AJAX implementation
    fetch('update_order_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: orderId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Refresh to show updated status
        } else {
            alert('Er is een fout opgetreden bij het wijzigen van de status.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Er is een fout opgetreden.');
    });
}

function exportOrders() {
    // Export functionality
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('export', 'csv');
    window.location.href = currentUrl.toString();
}

// Enhanced table sorting
function sortTable(columnIndex, type = 'text') {
    const table = document.querySelector('.orders-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        let aValue = a.cells[columnIndex].textContent.trim();
        let bValue = b.cells[columnIndex].textContent.trim();
        
        if (type === 'number') {
            aValue = parseFloat(aValue.replace(/[€,\.]/g, ''));
            bValue = parseFloat(bValue.replace(/[€,\.]/g, ''));
            return aValue - bValue;
        } else if (type === 'date') {
            aValue = new Date(aValue);
            bValue = new Date(bValue);
            return aValue - bValue;
        } else {
            return aValue.localeCompare(bValue);
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Auto-refresh for real-time updates (optional)
function startAutoRefresh() {
    setInterval(() => {
        // Check for new orders without full page reload
        fetch(window.location.href + '&ajax=1')
            .then(response => response.json())
            .then(data => {
                if (data.newOrderCount > 0) {
                    // Show notification or update counter
                    showNotification(`${data.newOrderCount} nieuwe bestelling(en) ontvangen!`);
                }
            })
            .catch(error => console.log('Auto-refresh failed:', error));
    }, 30000); // Check every 30 seconds
}

function showNotification(message) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
    `;
    notification.innerHTML = `
        <i class="fas fa-bell"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Add CSS for notification animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Initialize auto-refresh (uncomment if needed)
// startAutoRefresh();