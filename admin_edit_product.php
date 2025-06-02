<?php
session_start();
include 'includes/db.php';

if (!isset($_GET['id'])) {
    die("Geen product ID opgegeven.");
}

$product_id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product niet gevonden.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $features = $_POST['features'];
    $detailed_description = $_POST['detailed_description'];
    $dimensions = $_POST['dimensions'];
    $price_info = $_POST['price_info'];
    $price = (float) $_POST['price'];

    $updateStmt = $pdo->prepare("
        UPDATE products 
        SET name = ?, description = ?, features = ?, detailed_description = ?, dimensions = ?, price_info = ?, price = ?
        WHERE id = ?
    ");
    $updateStmt->execute([
        $name, $description, $features, $detailed_description, $dimensions, $price_info, $price, $product_id
    ]);

    $success = "Product succesvol bijgewerkt!";
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Bewerken - <?php echo htmlspecialchars($product['name']); ?> - L&G Fun</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 3rem;
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .page-title i {
            color: #667eea;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: #64748b;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #64748b;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Alert messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
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

        .alert i {
            font-size: 1.2rem;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Main content layout */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        /* Form section */
        .form-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #667eea;
        }

        .form-content {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group label i {
            color: #667eea;
            width: 16px;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .textarea-large {
            min-height: 150px;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
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
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Image preview section */
        .image-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .image-preview-container {
            padding: 2rem;
        }

        .image-preview {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(45deg, #f8fafc, #e2e8f0);
            position: relative;
            aspect-ratio: 4/3;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .image-preview:hover img {
            transform: scale(1.05);
        }

        .image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }

        .image-placeholder i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .image-info {
            padding: 1.5rem;
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .info-label {
            font-weight: 600;
            color: #64748b;
            font-size: 0.9rem;
        }

        .info-value {
            color: #334155;
            font-weight: 500;
        }

        /* Product stats */
        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-mini {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }

        .stat-mini-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-mini-label {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .image-section {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
                text-align: center;
            }

            .form-content {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
                padding: 1.5rem;
            }

            .navbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            nav ul {
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
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

        /* Loading states */
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Form validation styling */
        .form-control.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-control.success {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .field-help {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.25rem;
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
                <li><a href="admin_orders.php"> Bestellingen</a></li>
                <li><a href="loguit.php"> Uitloggen</a></li>
            </ul>
        </nav>
    </div>
</header>

<main>

        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Product Bewerken
            </h1>
            <p class="page-subtitle">Wijzig de details van "<?php echo htmlspecialchars($product['name']); ?>"</p>
        </div>

        <!-- Success Message -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="content-grid fade-in">
            <!-- Form Section -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Product Informatie
                    </h2>
                </div>

                <form method="POST" id="productForm">
                    <div class="form-content">
                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-tag"></i>
                                Productnaam
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($product['name']); ?>" 
                                   required>
                            <div class="field-help">Geef het product een duidelijke en herkenbare naam</div>
                        </div>

                        <div class="form-group">
                            <label for="description">
                                <i class="fas fa-align-left"></i>
                                Korte Beschrijving
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      class="form-control"
                                      placeholder="Een korte, aantrekkelijke beschrijving van het product..."><?php echo htmlspecialchars($product['description']); ?></textarea>
                            <div class="field-help">Deze beschrijving wordt getoond in productoverzichten</div>
                        </div>

                        <div class="form-group">
                            <label for="features">
                                <i class="fas fa-list-ul"></i>
                                Kenmerken
                            </label>
                            <textarea id="features" 
                                      name="features" 
                                      class="form-control"
                                      placeholder="• Kenmerk 1&#10;• Kenmerk 2&#10;• Kenmerk 3"><?php echo htmlspecialchars($product['features']); ?></textarea>
                            <div class="field-help">Elke regel wordt een apart kenmerk. Gebruik • voor opsommingen</div>
                        </div>

                        <div class="form-group">
                            <label for="detailed_description">
                                <i class="fas fa-file-alt"></i>
                                Uitgebreide Beschrijving
                            </label>
                            <textarea id="detailed_description" 
                                      name="detailed_description" 
                                      class="form-control textarea-large"
                                      placeholder="Uitgebreide beschrijving met alle details over het product..."><?php echo htmlspecialchars($product['detailed_description']); ?></textarea>
                            <div class="field-help">Volledige productbeschrijving voor de detailpagina</div>
                        </div>

                        <div class="form-group">
                            <label for="dimensions">
                                <i class="fas fa-ruler-combined"></i>
                                Afmetingen
                            </label>
                            <input type="text" 
                                   id="dimensions" 
                                   name="dimensions" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($product['dimensions']); ?>"
                                   placeholder="b.v. 4m x 3m x 2.5m">
                            <div class="field-help">Geef de afmetingen op in het formaat: breedte x lengte x hoogte</div>
                        </div>

                        <div class="form-group">
                            <label for="price_info">
                                <i class="fas fa-euro-sign"></i>
                                Prijsinformatie
                            </label>
                            <textarea id="price_info" 
                                      name="price_info" 
                                      class="form-control"
                                      placeholder="Dagprijs: €XX&#10;Weekendprijs: €XX&#10;Weekprijs: €XX"><?php echo htmlspecialchars($product['price_info']); ?></textarea>
                            <div class="field-help">Alle beschikbare prijsopties, één per regel</div>
                        </div>

                        <div class="form-group">
                            <label for="price">
                                <i class="fas fa-calculator"></i>
                                Standaardprijs (€)
                            </label>
                            <input type="number" 
                                   id="price" 
                                   name="price" 
                                   class="form-control" 
                                   step="0.01" 
                                   value="<?php echo htmlspecialchars($product['price']); ?>"
                                   required>
                            <div class="field-help">Basisprijs die gebruikt wordt voor berekeningen</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="admin_overview_products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Annuleren
                        </a>
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            <i class="fas fa-save"></i>
                            Wijzigingen Opslaan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Image Preview Section -->
            <div class="image-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-image"></i>
                        Product Afbeelding
                    </h3>
                </div>

                <div class="image-preview-container">
                    <div class="image-preview">
                        <?php 
                        $imagePath = 'images/' . $product['image'];
                        $imageExists = file_exists($imagePath) && !empty($product['image']);
                        ?>
                        <?php if ($imageExists): ?>
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="image-placeholder">
                                <i class="fas fa-image"></i>
                                <p>Geen afbeelding beschikbaar</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="image-info">
                    <div class="info-row">
                        <span class="info-label">Bestandsnaam:</span>
                        <span class="info-value"><?php echo htmlspecialchars($product['image'] ?: 'Geen afbeelding'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Product ID:</span>
                        <span class="info-value">#<?php echo $product['id']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value" style="color: <?php echo $imageExists ? '#22c55e' : '#ef4444'; ?>">
                            <i class="fas fa-<?php echo $imageExists ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                            <?php echo $imageExists ? 'Afbeelding gevonden' : 'Afbeelding ontbreekt'; ?>
                        </span>
                    </div>

                    <div class="stats-mini">
                        <div class="stat-mini">
                            <div class="stat-mini-value">€<?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                            <div class="stat-mini-label">Prijs</div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-value">#<?php echo $product['id']; ?></div>
                            <div class="stat-mini-label">Product ID</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('productForm');
    const saveBtn = document.getElementById('saveBtn');

    // Form validation
    const requiredFields = form.querySelectorAll('[required]');
    
    function validateField(field) {
        if (field.value.trim() === '') {
            field.classList.add('error');
            field.classList.remove('success');
            return false;
        } else {
            field.classList.remove('error');
            field.classList.add('success');
            return true;
        }
    }

    function validateForm() {
        let isValid = true;
        requiredFields.forEach(field => {
            if (!validateField(field)) {
                isValid = false;
            }
        });
        return isValid;
    }

    // Real-time validation
    requiredFields.forEach(field => {
        field.addEventListener('blur', () => validateField(field));
        field.addEventListener('input', () => {
            if (field.classList.contains('error')) {
                validateField(field);
            }
        });
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            alert('Vul alle verplichte velden correct in.');
            return;
        }

        // Add loading state
        saveBtn.classList.add('btn-loading');
        saveBtn.innerHTML = '<i class="fas fa-spinner"></i> Opslaan...';
        saveBtn.disabled = true;
    });

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

    // Auto-resize textareas
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        function autoResize() {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }
        
        textarea.addEventListener('input', autoResize);
        autoResize(); // Initial resize
    });

    // Add character counters for textareas
    textareas.forEach(textarea => {
        const maxLength = 1000; // Example max length
        const counter = document.createElement('div');
        counter.className = 'field-help';
        counter.style.textAlign = 'right';
        counter.style.fontSize = '0.8rem';
        
        function updateCounter() {
            const length = textarea.value.length;
            counter.textContent = `${length}/${maxLength} tekens`;
            counter.style.color = length > maxLength * 0.9 ? '#ef4444' : '#64748b';
        }
        
        textarea.addEventListener('input', updateCounter);
        textarea.parentNode.appendChild(counter);
        updateCounter();
    });

    // Add tooltips for help text
    const helpTexts = document.querySelectorAll('.field-help');
    helpTexts.forEach(help => {
        help.style.cursor = 'help';
        help.title = 'Klik voor meer informatie';
    });

    // Smooth transitions for form elements
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentNode.style.transform = 'scale(1.02)';
            this.parentNode.style.transition = 'transform 0.2s ease';
        });
        
        control.addEventListener('blur', function() {
            this.parentNode.style.transform = 'scale(1)';
        });
    });

    // Add confirmation before leaving with unsaved changes
    let formChanged = false;
    form.addEventListener('input', () => formChanged = true);
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Reset form changed flag on successful submit
    form.addEventListener('submit', () => formChanged = false);
});

// Function to preview image if upload functionality is added later
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.image-preview img');
            if (preview) {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<br><br><br>
<?php include 'footer.php'; ?>
</body>
</html>