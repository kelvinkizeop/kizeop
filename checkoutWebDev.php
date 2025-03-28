<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
include('db.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    die("User not logged in");
}

$user_id = $_SESSION['user_id'];



$user_sql = "SELECT id, username, phone, email, country, city, postal_code, billing_address FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

$cart_sql = "SELECT SUM(total_price) AS total FROM shopping_cart WHERE user_id = ?";
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("s", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart = $cart_result->fetch_assoc();
$total_amount = $cart['total'] ?? 0;
$cart_stmt->close();


// Fetch latest product name from shopping_cart
$cart_product_sql = "SELECT product_name FROM shopping_cart WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$cart_product_stmt = $conn->prepare($cart_product_sql);
$cart_product_stmt->bind_param("s", $user_id);
$cart_product_stmt->execute();
$cart_product_result = $cart_product_stmt->get_result();
$cart_product = $cart_product_result->fetch_assoc();
$cart_product_stmt->close();

$product_name = $cart_product ? $cart_product['product_name'] : "Unknown Product"; 



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_web'])) {
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : null;

        header("Location: checkoutWebDev.php");
        exit();

    $transaction_id = uniqid("TXN_");
    $status = "Pending";

  
    $order_id = uniqid('order_', true);
    $payment_sql = "INSERT INTO user_payments 
                       (user_id, username, phone_number, email_address, country, city, postal_code, service_name, product_name, amount, payment_method, transaction_id, status, billing_address, transaction_type , order_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'Web Development', ?, ?, ?, ?, ?, ?, 'purchase')";

    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("ssssssssdsisss", 
        $user['id'], $user['username'], $user['phone'], $user['email'], $user['country'], 
        $city, $postal_code,$product_name, $total_amount, $payment_method, $transaction_id, $status, $billing_address,$order_id);

    if ($payment_stmt->execute()) {
        $_SESSION['success'] = "Payment initiated successfully! Please complete your payment.";
        header("Location: payment_gateway.php?transaction_id=$transaction_id");
        exit();
    } else {
        $_SESSION['error'] = "Error processing payment. Try again.";
    }

    $payment_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kizeop Group – Checkout-Web Development">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="512x512" href="img/favicon-512x512.png">
    <link rel="icon" type="image/png" sizes="192x192" href="img/favicon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link rel="manifest" href="img/site.webmanifest">

    <meta property="og:title" content="Kizeop Group – Software Development & IT Solutions">
    <meta property="og:description" content="We provide expert customizable software development services for websites, apps, and digital platforms.">
    <meta property="og:image" content="https://kizeopgroup.org/img/LOGO FOR IG (320x320 px).png">
    <meta property="og:url" content="https://kizeopgroup.org">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Kizeop Group">

    <title>Checkout-Web Development</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="checkout.css">
</head>

<header class="header">

    <div class="usersname" id="usersname">
        <h1><?php echo "Hello, " . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?> 👋</h1>
    </div>

    <div class="logo-c">
        <span class="site-name">KIZEOP GROUP</span>
    </div>

    <div class="dashboard" id="dashboard">
        <a href="mydashboard.php">Back</a>
    </div>
</header>

<body>
    <div class="form-container">
    <h2>Checkout</h2>

    <form method="POST" action="checkoutWebDev.php">
        <label>Full Name:</label>
        <input type="text" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" readonly>

        <label>Email:</label>
        <input type="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" readonly>

        <label>Phone Number:</label>
        <input type="text" value="<?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>" readonly>

        <label>Country:</label>
        <input type="text" value="<?php echo htmlspecialchars($user['country'], ENT_QUOTES, 'UTF-8'); ?>" readonly>

        <label>City:</label>
        <input type="text" name="city" value="<?php echo htmlspecialchars($user['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>

        <label>Postal Code:</label>
        <input type="text" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>

        <label>Billing Address:</label>
        <textarea name="billing_address" required><?php echo htmlspecialchars($user['billing_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Total Amount:</label>
        <input type="text" value="₦<?php echo number_format($total_amount, 2); ?>" readonly>

        <label>Payment Method:</label>
        <select name="payment_method" required>
            <option value="Paystack">Paystack</option>
            <option value="KoraPay">KoraPay</option>
            <option value="Bank Transfer">Bank Transfer</option>
        </select>

        <button type="button" id="checkout-button">Proceed to Payment</button>

    </form>
</div>


</body>
<script>
    var userEmail = "<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>";
    var totalAmount = <?php echo $total_amount * 100; ?>; // Convert to kobo
</script>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script src="checkout.js"> </script>
</html>

