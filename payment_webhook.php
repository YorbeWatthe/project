<?php
/**
 * Mollie Payment Webhook Handler
 * This file handles payment status updates from Mollie
 */

require_once 'includes/db.php';

// Mollie API configuration
$mollie_api_key = 'test_wSuCC9DxhnJrBzVHmaKQC7JjByFwSS';

// Function to log webhook events (for debugging)
function logWebhookEvent($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    if ($data) {
        $log_message .= " | Data: " . json_encode($data);
    }
    $log_message .= PHP_EOL;
    
    // Log to file (make sure the logs directory exists and is writable)
    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }
    file_put_contents('logs/mollie_webhook.log', $log_message, FILE_APPEND | LOCK_EX);
}

// Function to get payment details from Mollie
function getMolliePayment($payment_id, $mollie_api_key) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.mollie.com/v2/payments/' . $payment_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $mollie_api_key
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        logWebhookEvent("cURL Error: " . $curl_error);
        return false;
    }
    
    if ($http_code !== 200) {
        logWebhookEvent("HTTP Error: " . $http_code, $response);
        return false;
    }
    
    return json_decode($response, true);
}

// Function to send confirmation email
function sendConfirmationEmail($order_data, $user_data, $order_items) {
    // Basic email headers
    $to = $user_data['email'];
    $subject = "Bevestiging springkasteel reservering #" . $order_data['id'] . " - L&G Fun";
    
    // Email content
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 10px 10px; }
            .item { background: white; margin: 10px 0; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea; }
            .total { background: #667eea; color: white; padding: 15px; text-align: center; font-size: 18px; font-weight: bold; border-radius: 8px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Bedankt voor je reservering!</h1>
                <p>Je springkasteel reservering is bevestigd</p>
            </div>
            
            <div class='content'>
                <h2>Besteldetails</h2>
                <p><strong>Bestelnummer:</strong> #" . $order_data['id'] . "</p>
                <p><strong>Besteldatum:</strong> " . date('d-m-Y H:i', strtotime($order_data['created_at'])) . "</p>
                <p><strong>Totaalbedrag:</strong> €" . number_format($order_data['total_price'], 2, ',', '.') . "</p>
                
                <h3>Gereserveerde springkastelen:</h3>";
    
    foreach ($order_items as $item) {
        $message .= "
                <div class='item'>
                    <h4>" . htmlspecialchars($item['product_name']) . "</h4>";
        
        if ($item['start_date'] && $item['end_date']) {
            $message .= "
                    <p><strong>Verhuurperiode:</strong> " . date('d/m/Y', strtotime($item['start_date'])) . " - " . date('d/m/Y', strtotime($item['end_date'])) . "</p>
                    <p><strong>Aantal dagen:</strong> " . $item['rental_days'] . " dagen</p>";
        }
        
        $message .= "
                    <p><strong>Prijs:</strong> €" . number_format($item['price'], 2, ',', '.') . "</p>
                </div>";
    }
    
    $message .= "
                <div class='total'>
                    Totaalbedrag: €" . number_format($order_data['total_price'], 2, ',', '.') . "
                </div>
                
                <h3>Wat gebeurt er nu?</h3>
                <ul>
                    <li>We nemen binnen 24 uur contact met je op om de levering af te spreken</li>
                    <li>Het springkasteel wordt op de afgesproken datum geleverd en opgebouwd</li>
                    <li>Na afloop halen we alles weer netjes op</li>
                </ul>
                
                <p><strong>Leveringsadres:</strong><br>" . nl2br(htmlspecialchars($user_data['address'])) . "</p>
                
                <p>Heb je vragen? Neem gerust contact met ons op via <a href='mailto:info@lgfun.be'>info@lgfun.be</a> of telefoon.</p>
            </div>
            
            <div class='footer'>
                <p>Met vriendelijke groet,<br>Het L&G Fun team</p>
                <p><small>Dit is een automatisch gegenereerde e-mail.</small></p>
            </div>
        </div>
    </body>
    </html>";
    
    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: L&G Fun <noreply@lgfun.be>',
        'Reply-To: info@lgfun.be',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Send email
    $email_sent = mail($to, $subject, $message, implode("\r\n", $headers));
    
    if ($email_sent) {
        logWebhookEvent("Confirmation email sent to: " . $to);
        return true;
    } else {
        logWebhookEvent("Failed to send confirmation email to: " . $to);
        return false;
    }
}

// Main webhook handler
try {
    // Log incoming webhook
    logWebhookEvent("Webhook received", $_POST);
    
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logWebhookEvent("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
        http_response_code(405);
        exit;
    }
    
    // Get payment ID from POST data
    $payment_id = $_POST['id'] ?? null;
    
    if (!$payment_id) {
        logWebhookEvent("No payment ID provided");
        http_response_code(400);
        exit;
    }
    
    logWebhookEvent("Processing payment ID: " . $payment_id);
    
    // Get payment details from Mollie
    $payment = getMolliePayment($payment_id, $mollie_api_key);
    
    if (!$payment) {
        logWebhookEvent("Failed to retrieve payment from Mollie");
        http_response_code(400);
        exit;
    }
    
    logWebhookEvent("Payment status from Mollie: " . $payment['status'], $payment);
    
    // Get order ID from metadata
    $order_id = $payment['metadata']['order_id'] ?? null;
    
    if (!$order_id) {
        logWebhookEvent("No order ID in payment metadata");
        http_response_code(400);
        exit;
    }
    
    // Get order from database
    $stmt = $pdo->prepare("
        SELECT o.*, u.name, u.email, u.address 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        logWebhookEvent("Order not found in database: " . $order_id);
        http_response_code(404);
        exit;
    }
    
    logWebhookEvent("Found order in database", $order);
    
    // Check if payment status has changed
    $current_status = $order['payment_status'];
    $new_status = $payment['status'];
    
    if ($current_status === $new_status) {
        logWebhookEvent("Payment status unchanged: " . $current_status);
        http_response_code(200);
        exit;
    }
    
    // Update payment status in database
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    logWebhookEvent("Updated payment status from $current_status to $new_status for order $order_id");
    
    // Handle different payment statuses
    switch ($new_status) {
        case 'paid':
            // Payment successful
            $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?");
            $stmt->execute([$order_id]);
            
            logWebhookEvent("Order confirmed: " . $order_id);
            
            // Get order items for email
            $stmt = $pdo->prepare("
                SELECT oi.*, p.name as product_name 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Send confirmation email
            sendConfirmationEmail($order, $order, $order_items);
            
            // Create bookings for availability tracking
            foreach ($order_items as $item) {
                if ($item['start_date'] && $item['end_date']) {
                    // Check if booking already exists
                    $stmt = $pdo->prepare("
                        SELECT id FROM bookings 
                        WHERE user_id = ? AND product_id = ? AND start_date = ? AND end_date = ?
                    ");
                    $stmt->execute([$order['user_id'], $item['product_id'], $item['start_date'], $item['end_date']]);
                    
                    if (!$stmt->fetch()) {
                        // Create new booking
                        $stmt = $pdo->prepare("
                            INSERT INTO bookings (user_id, product_id, quantity, start_date, end_date, rental_days, order_id, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $order['user_id'],
                            $item['product_id'],
                            $item['quantity'],
                            $item['start_date'],
                            $item['end_date'],
                            $item['rental_days'],
                            $order_id
                        ]);
                        
                        logWebhookEvent("Created booking for product " . $item['product_id']);
                    }
                }
            }
            
            break;
            
        case 'failed':
        case 'canceled':
        case 'expired':
            // Payment failed/canceled
            $stmt = $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = ?");
            $stmt->execute([$order_id]);
            
            logWebhookEvent("Order failed: " . $order_id);
            
            // Optionally send failure notification email
            break;
            
        case 'pending':
        case 'open':
            // Payment is still pending
            logWebhookEvent("Payment pending for order: " . $order_id);
            break;
            
        default:
            logWebhookEvent("Unknown payment status: " . $new_status);
    }
    
    // Send success response to Mollie
    http_response_code(200);
    echo "OK";
    
    logWebhookEvent("Webhook processed successfully");

} catch (Exception $e) {
    // Log error
    logWebhookEvent("Webhook error: " . $e->getMessage());
    
    // Send error response
    http_response_code(500);
    echo "ERROR";
}
?>