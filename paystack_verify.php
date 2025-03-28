<?php
session_start();
include('db.php'); 

if (!isset($_GET['reference'])) {
    die("No reference supplied");
}

$reference = $_GET['reference'];
$paystack_secret_key = "sk_test_e5f028dd8672a755aa6de42fa99e959ac137f763"; // Replace with your secret key

// Verify the transaction from Paystack
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . $reference);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $paystack_secret_key,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

$paystack_response = json_decode($response, true);

if ($paystack_response['status'] && $paystack_response['data']['status'] == "success") {
    $amount_paid = $paystack_response['data']['amount'] / 100; 
    $email = $paystack_response['data']['customer']['email'];

    $user_sql = "SELECT user_id, username, phone, email, country, city, postal_code, billing_address FROM users WHERE email = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("s", $email);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    $user_stmt->close();

    if (!$user) {
        die("User not found in the database.");
    }

    
    
    // Fetch the latest cart item and the order_id from the shopping_cart table
    $cart_sql = "SELECT product_name, order_id FROM shopping_cart WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("s", $user['user_id']);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $cart = $cart_result->fetch_assoc();
    $cart_stmt->close();

    if (!$cart) {
        die("No items found in the shopping cart.");
    }

    $product_name = $cart['product_name'];
    $order_id = $cart['order_id'];

    if (!$order_id) {
        die("Order ID is missing. Something went wrong.");
    } 

    $payment_sql = "INSERT INTO user_payments 
    (user_id, username, phone_number, email_address, country, city, postal_code, service_name, product_name, amount, payment_method, transaction_id, status, billing_address, transaction_type,order_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Web Development', ?, ?, 'Paystack', ?, 'Completed', ?, 'purchase',?)";

    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("ssssssssdsss", 
        $user['user_id'], $user['username'], $user['phone'], $user['email'], $user['country'], 
        $user['city'], $user['postal_code'], $product_name, $amount_paid, $reference, $user['billing_address'], $order_id);

    if ($payment_stmt->execute()) {
        $_SESSION['success'] = "Payment successful! You'll receive an email with your order details. In the meantime, please visit 'My Projects' to complete your order form.";
        header("Location: mydashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Error inserting payment details.";
    }

    $payment_stmt->close();
} else {
    $_SESSION['error'] = "Payment verification failed!";
}

header("Location: checkoutWebDev.php");
exit();
?>


