<?php
session_start();
include 'includes/db.php';

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ongeldige aanvraag. Probeer het opnieuw.");
    }
    
    if (isset($_POST['deactivate_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        // Don't allow deactivating yourself
        if ($user_id === $_SESSION['user_id']) {
            $message = "U kunt uw eigen account niet deactiveren.";
            $message_type = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = "Gebruiker succesvol gedeactiveerd.";
            $message_type = 'success';
        }
    } elseif (isset($_POST['activate_user'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Gebruiker succesvol geactiveerd.";
        $message_type = 'success';
    } elseif (isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check if email already exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            
            if ($stmt->fetch()) {
                $message = "Dit e-mailadres is al in gebruik door een andere gebruiker.";
                $message_type = 'error';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $email, $address, $user_id]);
                $message = "Gebruiker succesvol bijgewerkt.";
                $message_type = 'success';
            }
        } else {
            $message = "Vul alle velden correct in.";
            $message_type = 'error';
        }
    }
}

// Get users statistics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admins
    FROM users");
$stats = $stmt->fetch();

// Get all users (active and inactive)
$filter = $_GET['filter'] ?? 'active';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($filter === 'active') {
    $where_conditions[] = "is_active = 1";
} elseif ($filter === 'inactive') {
    $where_conditions[] = "is_active = 0";
} elseif ($filter === 'admins') {
    $where_conditions[] = "is_admin = 1";
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $pdo->prepare("SELECT * FROM users $where_clause ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Gebruikers Beheren - L&G Fun</title>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        .stat-card.active { --card-color: #22c55e; }
        .stat-card.inactive { --card-color: #ef4444; }
        .stat-card.admins { --card-color: #f59e0b; }

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

        /* Alert messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            animation: slideIn 0.5s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 1.2rem;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Users table */
        .users-table-container {
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

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        .users-table td {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .users-table tr:hover {
            background: #f8fafc;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .user-details h4 {
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.2rem;
        }

        .user-details p {
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
        }

        .status-active {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
        }

        .status-inactive {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
        }

        .status-admin {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        .actions-cell {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.5rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        .btn-success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .btn-sm:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Edit form modal style */
        .edit-form {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .edit-form.active {
            display: flex;
        }

        .edit-form-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .edit-form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .edit-form-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-cancel {
            background: #6b7280;
            color: white;
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

            .users-table {
                font-size: 0.9rem;
            }

            .users-table td {
                padding: 1rem 0.5rem;
            }

            .actions-cell {
                flex-direction: column;
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
                <li><a href="admin_panel.php"></i> Admin Panel</a></li>
                <li><a href="loguit.php"></i> Uitloggen</a></li>
            </ul>
        </nav>
    </div>
</header>

<main>
    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1 class="page-title">
                <i class="fas fa-users-cog"></i>
                Gebruikers Beheren
            </h1>
            <p class="page-subtitle">Beheer alle gebruikersaccounts en hun gegevens</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card total">
                <div class="stat-header">
                    <div class="stat-title">Totaal Gebruikers</div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>

            <div class="stat-card active">
                <div class="stat-header">
                    <div class="stat-title">Actieve Gebruikers</div>
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['active']; ?></div>
            </div>

            <div class="stat-card inactive">
                <div class="stat-header">
                    <div class="stat-title">Inactieve Gebruikers</div>
                    <div class="stat-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['inactive']; ?></div>
            </div>

            <div class="stat-card admins">
                <div class="stat-header">
                    <div class="stat-title">Beheerders</div>
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['admins']; ?></div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Controls Section -->
        <div class="controls-section fade-in">
            <div class="controls-header">
                <h3 class="controls-title">
                    <i class="fas fa-filter"></i>
                    Filteren & Zoeken
                </h3>
            </div>
            
            <form method="GET" class="controls-grid">
                <div class="search-group">
                    <label for="search">Zoeken op naam of email</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           class="search-input" 
                           placeholder="Typ om te zoeken..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <select name="filter" class="filter-select">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Alle gebruikers</option>
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Actieve gebruikers</option>
                    <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactieve gebruikers</option>
                    <option value="admins" <?php echo $filter === 'admins' ? 'selected' : ''; ?>>Beheerders</option>
                </select>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Zoeken
                </button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-table-container fade-in">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-table"></i>
                    Gebruikersoverzicht (<?php echo count($users); ?> resultaten)
                </h3>
            </div>

            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>Geen gebruikers gevonden</h3>
                    <p>Er zijn geen gebruikers die voldoen aan uw zoekcriteria.</p>
                </div>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Gebruiker</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Aangemaakt</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                                            <p>ID: <?php echo $user['id']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['email']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($user['address'] ?? 'Geen adres'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                                        <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <i class="fas fa-<?php echo $user['is_active'] ? 'check' : 'times'; ?>"></i>
                                            <?php echo $user['is_active'] ? 'Actief' : 'Inactief'; ?>
                                        </span>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="status-badge status-admin">
                                                <i class="fas fa-shield-alt"></i>
                                                Admin
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d-m-Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-edit btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="fas fa-edit"></i>
                                            Bewerken
                                        </button>

                                        <!-- Activate/Deactivate Button -->
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Weet u het zeker?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <?php if ($user['is_active']): ?>
                                                    <button type="submit" name="deactivate_user" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-user-times"></i>
                                                        Deactiveren
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="activate_user" class="btn btn-success btn-sm">
                                                        <i class="fas fa-user-check"></i>
                                                        Activeren
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-badge" style="background: #f3f4f6; color: #6b7280;">
                                                <i class="fas fa-lock"></i>
                                                Eigen account
                                            </span>
                                        <?php endif; ?>
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

<!-- Edit User Modal -->
<div class="edit-form" id="editModal">
    <div class="edit-form-content">
        <div class="edit-form-header">
            <h3 class="edit-form-title">
                <i class="fas fa-user-edit"></i>
                Gebruiker Bewerken
            </h3>
            <button type="button" class="close-btn" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="editUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="form-group">
                <label for="editName">
                    <i class="fas fa-user"></i> Naam
                </label>
                <input type="text" 
                       id="editName" 
                       name="name" 
                       class="form-control" 
                       required>
            </div>

            <div class="form-group">
                <label for="editEmail">
                    <i class="fas fa-envelope"></i> E-mailadres
                </label>
                <input type="email" 
                       id="editEmail" 
                       name="email" 
                       class="form-control" 
                       required>
            </div>

            <div class="form-group">
                <label for="editAddress">
                    <i class="fas fa-map-marker-alt"></i> Adres
                </label>
                <textarea id="editAddress" 
                          name="address" 
                          class="form-control" 
                          rows="3"
                          placeholder="Straat, huisnummer, postcode, plaats"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-cancel" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                    Annuleren
                </button>
                <button type="submit" name="edit_user" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Opslaan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success messages
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            successAlert.style.opacity = '0';
            successAlert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                successAlert.remove();
            }, 500);
        }, 5000);
    }

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
    const tableRows = document.querySelectorAll('.users-table tbody tr');
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
});

// Edit modal functions
function openEditModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editName').value = user.name || '';
    document.getElementById('editEmail').value = user.email || '';
    document.getElementById('editAddress').value = user.address || '';
    
    document.getElementById('editModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Focus first input
    document.getElementById('editName').focus();
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    document.body.style.overflow = 'auto';
    
    // Reset form
    document.getElementById('editUserForm').reset();
}

// Close modal on outside click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('editModal').classList.contains('active')) {
        closeEditModal();
    }
});

// Form validation
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const name = document.getElementById('editName').value.trim();
    const email = document.getElementById('editEmail').value.trim();
    
    if (name.length < 2) {
        e.preventDefault();
        alert('Naam moet minimaal 2 karakters bevatten.');
        document.getElementById('editName').focus();
        return;
    }
    
    if (!email || !email.includes('@')) {
        e.preventDefault();
        alert('Voer een geldig e-mailadres in.');
        document.getElementById('editEmail').focus();
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[name="edit_user"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Opslaan...';
    submitBtn.disabled = true;
});

// Confirmation for user actions
function confirmAction(action, userName) {
    return confirm(`Weet u zeker dat u "${userName}" wilt ${action}?`);
}

// Enhanced table sorting (optional)
function sortTable(columnIndex) {
    const table = document.querySelector('.users-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        return aText.localeCompare(bText);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Add loading states to action buttons
document.querySelectorAll('form button[type="submit"]').forEach(button => {
    button.addEventListener('click', function() {
        const form = this.closest('form');
        if (form && form.checkValidity()) {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;
            
            // Re-enable after 5 seconds as fallback
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 5000);
        }
    });
});
</script>

</body>
</html>