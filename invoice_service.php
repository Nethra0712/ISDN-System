<?php
// ============================================
// includes/invoice_service.php
// Centralized service for sending Email Invoices
// ============================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/libs/phpmailer/Exception.php';
require_once __DIR__ . '/libs/phpmailer/PHPMailer.php';
require_once __DIR__ . '/libs/phpmailer/SMTP.php';

/**
 * Sends a digital invoice email to a customer.
 * 
 * @param mysqli $conn Database connection
 * @param int $invoice_id The ID of the invoice
 * @return bool True on success, false on failure
 */
function sendInvoiceEmail($conn, $invoice_id) {
    // 1. Fetch Invoice & Related Data
    $query = "SELECT i.*, o.order_number, o.order_date, o.shipping_address, o.notes AS order_notes,
                     u.name AS customer_name, u.email AS customer_email,
                     (SELECT SUM(amount_paid) FROM payments WHERE invoice_id = i.id) AS total_paid
              FROM invoices i
              JOIN orders o ON i.order_id = o.id
              JOIN users u ON i.customer_id = u.id
              WHERE i.id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $invoice_id);
    mysqli_stmt_execute($stmt);
    $inv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$inv) return false;

    // 2. Fetch Order Items for the itemized list
    $items_res = mysqli_query($conn, 
        "SELECT oi.*, p.name AS product_name, p.sku, p.unit_price AS original_price, p.discount_percentage
         FROM order_items oi
         JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = " . $inv['order_id']
    );

    // 3. Configure PHPMailer
    $mail = new PHPMailer(true);

    try {
        // --- SMTP CONFIGURATION ---
        // IMPORTANT: Update these with real credentials or move to a config file
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'nethunawarathna07@gmail.com';
        $mail->Password   = 'nbkz cmdc udha lwgs';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('no-reply@isdn.com', 'ISDN Distribution');
        $mail->addAddress($inv['customer_email'], $inv['customer_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Invoice #' . $inv['invoice_number'] . ' - ISDN Distribution';
        
        // --- HTML TEMPLATE ---
        $items_html = '';
        $subtotal_sum = 0;
        $discount_sum = 0;

        while ($item = mysqli_fetch_assoc($items_res)) {
            $line_original = $item['quantity'] * $item['original_price'];
            $line_final    = $item['quantity'] * $item['unit_price'];
            $line_discount = $line_original - $line_final;
            
            $subtotal_sum += $line_original;
            $discount_sum += $line_discount;

            $items_html .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>
                        <strong>{$item['product_name']}</strong><br>
                        <small style='color: #888;'>SKU: {$item['sku']}</small>
                    </td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$item['quantity']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>LKR " . number_format($item['unit_price'], 2) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>LKR " . number_format($line_final, 2) . "</td>
                </tr>";
        }

        $outstanding = max(0, $inv['total_amount'] - $inv['total_paid']);
        $status_color = ($inv['status'] === 'paid') ? '#198754' : '#fd7e14';

        $mail->Body = "
        <div style='font-family: sans-serif; color: #333; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
            <div style='text-align: center; border-bottom: 2px solid #1a3a5c; padding-bottom: 10px; margin-bottom: 20px;'>
                <h1 style='color: #1a3a5c; margin: 0;'>ISDN DISTRIBUTION</h1>
                <p style='color: #888; font-size: 12px; margin: 5px 0;'>Digital Invoice Report</p>
            </div>
            
            <table style='width: 100%; margin-bottom: 30px;'>
                <tr>
                    <td style='vertical-align: top;'>
                        <h4 style='margin: 0 0 5px 0;'>Billed To:</h4>
                        <p style='margin: 0; font-size: 14px;'>
                            <strong>{$inv['customer_name']}</strong><br>
                            {$inv['customer_email']}<br>
                            " . nl2br(htmlspecialchars($inv['shipping_address'])) . "
                        </p>
                    </td>
                    <td style='vertical-align: top; text-align: right;'>
                        <h4 style='margin: 0 0 5px 0; color: #1a3a5c;'>Invoice Details:</h4>
                        <p style='margin: 0; font-size: 14px;'>
                            <strong>Invoice #:</strong> {$inv['invoice_number']}<br>
                            <strong>Order #:</strong> {$inv['order_number']}<br>
                            <strong>Date:</strong> " . date('d M Y', strtotime($inv['issued_at'])) . "<br>
                            <strong>Status:</strong> <span style='color: {$status_color}; font-weight: bold;'>" . strtoupper($inv['status']) . "</span>
                        </p>
                    </td>
                </tr>
            </table>

            <table style='width: 100%; border-collapse: collapse; margin-bottom: 30px;'>
                <thead>
                    <tr style='background: #f8f9fa;'>
                        <th style='padding: 10px; text-align: left; border-bottom: 2px solid #eee;'>Product</th>
                        <th style='padding: 10px; text-align: center; border-bottom: 2px solid #eee;'>Qty</th>
                        <th style='padding: 10px; text-align: right; border-bottom: 2px solid #eee;'>Price</th>
                        <th style='padding: 10px; text-align: right; border-bottom: 2px solid #eee;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    {$items_html}
                </tbody>
            </table>

            <div style='width: 250px; margin-left: auto;'>
                <table style='width: 100%; font-size: 14px;'>
                    <tr>
                        <td style='padding: 5px 0;'>Subtotal:</td>
                        <td style='padding: 5px 0; text-align: right;'>LKR " . number_format($subtotal_sum, 2) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 5px 0;'>Discount:</td>
                        <td style='padding: 5px 0; text-align: right; color: #dc3545;'>- LKR " . number_format($discount_sum, 2) . "</td>
                    </tr>
                    <tr style='font-weight: bold; font-size: 16px; border-top: 1px solid #1a3a5c;'>
                        <td style='padding: 10px 0;'>Final Amount:</td>
                        <td style='padding: 10px 0; text-align: right; color: #1a3a5c;'>LKR " . number_format($inv['total_amount'], 2) . "</td>
                    </tr>
                    <tr style='color: #198754;'>
                        <td style='padding: 5px 0;'>Amount Paid:</td>
                        <td style='padding: 5px 0; text-align: right;'>LKR " . number_format($inv['total_paid'], 2) . "</td>
                    </tr>
                    <tr style='font-weight: bold; border-top: 2px double #eee; color: #dc3545;'>
                        <td style='padding: 10px 0;'>OUTSTANDING:</td>
                        <td style='padding: 10px 0; text-align: right;'>LKR " . number_format($outstanding, 2) . "</td>
                    </tr>
                </table>
            </div>

            <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #888; font-size: 12px;'>
                <p>Thank you for your business! If you have any questions, please contact our support at support@isdn.com</p>
                <p>&copy; " . date('Y') . " ISDN Distribution Management System</p>
            </div>
        </div>";

        $mail->AltBody = "Invoice #{$inv['invoice_number']}\nTotal: LKR " . number_format($inv['total_amount'], 2) . "\nAmount Paid: LKR " . number_format($inv['total_paid'], 2) . "\nOutstanding: LKR " . number_format($outstanding, 2);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error silently or handle as needed
        return false;
    }
}
