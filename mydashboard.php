<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
include('db.php');
session_start();



$debug_file = "debug_log.txt"; 

function log_debug($message) {
    global $debug_file;
    file_put_contents($debug_file, date("Y-m-d H:i:s") . " - " . $message . "\n", FILE_APPEND);
} 



if (!isset($_SESSION['user_id'])) {
    log_debug("Session user_id is not set. Redirecting to login.");
    header("Location: login.php");
    exit();
}
session_regenerate_id(true);

// Fetch user data for last login update
$user_id = $_SESSION['user_id'];
log_debug("Session user_id: " . $user_id);
$query = "SELECT username, last_login FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query); 
$stmt->bind_param("s", $user_id);
$stmt->execute();
$stmt->bind_result($username, $last_login);
$stmt->fetch();
$stmt->close();

$first_name = explode(' ', trim($username))[0];

$username = $username ?: "Guest";
$last_login = $last_login ? date("F j, Y, g:i A", strtotime($last_login)) : "First login!";


//Sending support tickets
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) { 
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (!empty($subject) && !empty($message)) {
      
        $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        $query = "INSERT INTO support_tickets (id, user_id, username, subject, message) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issss",$id, $user_id, $username, $subject, $message);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Support ticket submitted successfully!";
        } else {
            $_SESSION['error'] = "Error submitting ticket. Try again!";
        }
        $stmt->close();
        } else {
        $_SESSION['error'] = "Support ticket fields are missing!";
        }

  
    header("Location: mydashboard.php#Support");
    exit();
}


//for updating users profile settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $new_email = isset($_POST['email']) ? trim($_POST['email']) : '';

    // Email validation
    if (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: mydashboard.php");
        exit();
    }

    // If no changes were made
    if ($new_username === $username && $new_email === $email) {
        $_SESSION['error'] = "No changes detected!";
        header("Location: mydashboard.php");
        exit();
    }

    // Proceeds to  updating  if there are changes
    if (!empty($new_username) || !empty($new_email)) {
        $query = "UPDATE users SET";
        $params = [];
        $types = "";
        
        if (!empty($new_username)) {
            $query .= " username = ?,";
            $params[] = $new_username;
            $types .= "s";
        }
        if (!empty($new_email)) {
            $query .= " email = ?,";
            $params[] = $new_email;
            $types .= "s";
        }
        
        $query = rtrim($query, ','); 
        $query .= " WHERE user_id = ?";
        $params[] = $user_id;
        $types .= "s";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Profile updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating profile. Try again!";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "No changes detected!";
    }
    header("Location: mydashboard.php");
    exit();
}




// Change Password Logic
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($current_password)) {
        $_SESSION['error'] = "Please enter your current password!";
    } elseif (empty($new_password)) {
        $_SESSION['error'] = "Please enter a new password!";
    } elseif (empty($confirm_password)) {
        $_SESSION['error'] = "Please confirm your new password!";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match!";
    } else {
        $query = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($current_password, $hashed_password)) {
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ss", $new_hashed_password, $user_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Password changed successfully!";
            } else {
                $_SESSION['error'] = "Error changing password!";
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Incorrect current password!";
        }
    }

    header("Location: mydashboard.php");
    exit();
}


$query = "SELECT email FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && isset($user['email'])) {
    $email = $user['email'];
} else {
    $email = ''; 
}


// Fetching  users payment history
$payment_query = "SELECT service_name, amount, payment_method, transaction_id, status, invoice_url, created_at 
                  FROM user_payments WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($payment_query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();



// Handling invoice download
if (isset($_GET['download_invoice'])) {
    require(__DIR__ . '/FPDF-master/fpdf.php');
   
    
    if (isset($_GET['user_id'])) {
        $user_id = $_GET['user_id'];
    } elseif (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else {
        die("User ID not provided.");
    }

    $query = "SELECT * FROM user_payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    if (!$payment) {
        die("No invoices found.");
    }


    class PDF extends FPDF {
        function Header() {
            if (file_exists('logo.png')) {
                $this->Image('logo.png', 10, 1, 50, 50);
            }
            $this->SetFont('Times', 'B', 20);
            $this->SetY(20);
            $this->Cell(190, 10, "Kizeop Group Payment Invoice", 0, 1, 'C');
            $this->Ln(50);
        }

        function Footer() {
            $this->SetY(-115);
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(190, 10, "Thank you for choosing Kizeop Group!", 0, 1, 'C');
        }
    }

    // Create PDF instance
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Courier', '', 12);
    $pdf->SetTextColor(0, 0, 255); // Blue text color

    
    $pdf->SetX(35);

// Table Header 
       $pdf->SetFont('Courier', 'B', 12);
       $pdf->SetTextColor(0, 0, 255); // Blue text
       $pdf->Cell(60, 10, "Description", 1, 0, 'C');
       $pdf->Cell(80, 10, "Details", 1, 1, 'C');

// Table Content 
   $pdf->SetFont('Arial', '', 12);
   $pdf->SetTextColor(0, 0, 0); 
   
    $pdf->SetX(35);

    $pdf->Cell(60, 10, "Customer Name", 1);
    $pdf->Cell(80, 10, $payment['username'], 1, 1);

    
    $pdf->SetX(35);
    $pdf->Cell(60, 10, "Service Purchased", 1);
    $pdf->Cell(80, 10, $payment['service_name'], 1, 1);

   
    $pdf->SetX(35);
    $pdf->Cell(60, 10, "Amount Paid", 1);
    $pdf->Cell(80, 10, "" . number_format($payment['amount'], 2), 1, 1);

    
    $pdf->SetX(35);
    $pdf->Cell(60, 10, "Payment Method", 1);
    $pdf->Cell(80, 10, $payment['payment_method'], 1, 1);

   
    $pdf->SetX(35);
    $pdf->Cell(60, 10, "Transaction ID", 1);
    $pdf->Cell(80, 10, $payment['transaction_id'], 1, 1);


    
    $pdf->SetX(35);
    $pdf->Cell(60, 10, "Status", 1);
    $pdf->Cell(80, 10, $payment['status'], 1, 1);


    $pdf->SetX(35);
    $pdf->Cell(60, 10, "Billing Address", 1);
    $pdf->Cell(80, 10, $payment['billing_address'], 1, 1);


   
    $pdf->SetX(35);
    $pdf->Cell(60, 10, "Date", 1);
    $pdf->Cell(80, 10, date("F j, Y, g:i A", strtotime($payment['created_at'])), 1, 1);

    

    
    // Add Stamp Image
    if (file_exists('stamp.png')) {
        $pdf->Image('stamp.png', 150, 20, 40, 40); 
        $pdf->Image('stamp.png', 50, 150, 35, 35);  
        
        
    }
    
    // Output Invoice PDF
    $pdf->Output("D", "invoice_" . $payment['transaction_id'] . ".pdf");
    exit;
}



// Handling billing settings form submission with the same pattern as the first code
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_billing_details'])) {
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $billing_address = isset($_POST['billing_address']) ? trim($_POST['billing_address']) : '';
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $email_address = isset($_POST['email_address']) ? trim($_POST['email_address']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';

    if (!empty($email_address) && !filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: mydashboard.php");
        exit();
    }

    // Fetch current user details
    $user_sql = "SELECT payment_method, billing_address, phone, email, country, city, postal_code FROM users WHERE user_id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("s", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $current_billing_details = $user_result->fetch_assoc();
    $user_stmt->close();

    // Check if no changes were made
    if (
        $payment_method === $current_billing_details['payment_method'] &&
        $billing_address === $current_billing_details['billing_address'] &&
        $phone_number === $current_billing_details['phone'] &&
        $email_address === $current_billing_details['email'] &&
        $country === $current_billing_details['country'] &&
        $city === $current_billing_details['city'] &&
        $postal_code === $current_billing_details['postal_code']
    ) {
        $_SESSION['error'] = "No changes detected!";
        header("Location: mydashboard.php");
        exit();
    }

    // Proceed to update if there are changes
    $query = "UPDATE users SET";
    $params = [];
    $types = "";

    if (!empty($payment_method)) {
        $query .= " payment_method = ?,";
        $params[] = $payment_method;
        $types .= "s";
    }
    if (!empty($billing_address)) {
        $query .= " billing_address = ?,";
        $params[] = $billing_address;
        $types .= "s";
    }
    if (!empty($phone_number)) {
        $query .= " phone = ?,";
        $params[] = $phone_number;
        $types .= "s";
    }
    if (!empty($email_address)) {
        $query .= " email = ?,";
        $params[] = $email_address;
        $types .= "s";
    }
    if (!empty($country)) {
        $query .= " country = ?,";
        $params[] = $country;
        $types .= "s";
    }
    if (!empty($city)) {
        $query .= " city = ?,";
        $params[] = $city;
        $types .= "s";
    }
    if (!empty($postal_code)) {
        $query .= " postal_code = ?,";
        $params[] = $postal_code;
        $types .= "s";
    }

    $query = rtrim($query, ','); 
    $query .= " WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "s";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Billing details updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating billing details. Try again!";
    }

    $stmt->close();
    header("Location: mydashboard.php");
    exit();
}


// Fetching  all user details for dashboard display
$query = "SELECT username, email, phone, country, created_at, last_login FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $phone, $country, $created_at, $last_login);
$stmt->fetch();
$stmt->close();

$last_login = $last_login ? date("F j, Y, g:i A", strtotime($last_login)) : "First login!";
$created_at = date("F j, Y, g:i A", strtotime($created_at));


$current_hour = date("H");

if ($current_hour >= 5 && $current_hour < 12) {
    $greeting = "Morning";
} elseif ($current_hour >= 12 && $current_hour < 17) {
    $greeting = "Afternoon";
} elseif ($current_hour >= 17 && $current_hour <= 23) {
    $greeting = "Evening";
} else {
    $greeting = "A New Dawn"; // Covers 12 AM - 4:59 AM
}

//subscribe to Newsletters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {

    $email = trim($_POST['subscriber_email']);
    
    if (empty($email)) {
        $_SESSION['error'] = "Email is required!";
        header("Location: mydashboard.php");  
        exit();
    }


    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: mydashboard.php");
        exit();
    }

    $query = "INSERT INTO email_subscribers (email, submitted_on) VALUES (?, NOW())";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        $_SESSION['error'] = "Error preparing the statement: " . $conn->error;
        header("Location: mydashboard.php");  
        exit();
    }
    
    $stmt->bind_param("s", $email);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Subscription successful!";
    } else {
        $_SESSION['error'] = "Error subscribing. Please try again!";
    }

    $stmt->close();
    header("Location: mydashboard.php");  
    exit();
}


//for adding items to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {

    $user_id = $_SESSION['user_id'];
    $product_name = $_POST['product_name'];
    $product_price = (float) $_POST['product_price']; 
    $additional_services = isset($_POST['additional_services']) ? $_POST['additional_services'] : [];

    log_debug("User: $username | Product: $product_name | Price: $product_price | Services: " . implode(", ", $additional_services));

    $service_prices = [
        "Site Pages" => 10000,
        "Login/Sign-Up Feature" => 85000,
        "Monthly Site Maintenance" => 10000,
        "Daily Site Maintenance" => 50000,
        "Request Dedicated Server" => 848000,
        "SMTP Email" => 12000
    ];


    $additional_total = 0;
    foreach ($additional_services as $service) {
        if (isset($service_prices[$service])) {
            $additional_total += $service_prices[$service];
        }
    }


    $total_price = $product_price + $additional_total;
    $additional_services_str = implode(", ", $additional_services);

    log_debug("Total Price: ₦" . number_format($total_price));
    
    $order_id = uniqid('order_', true);

    $sql = "INSERT INTO shopping_cart (user_id, username, product_name, product_price, additional_services, total_price,order_id) 
            VALUES (?, ?, ?, ?, ?, ?,?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        log_debug("SQL Prepare Failed: " . $conn->error);
        die("SQL Prepare Failed: " . $conn->error);
    }


    $stmt->bind_param("sssdsds", $user_id, $username, $product_name, $product_price, $additional_services_str, $total_price,$order_id);
    log_debug("SQL Insert Data: User ID: $user_id | Username: $username | Product: $product_name | Price: $product_price | Services: $additional_services_str | Total: $total_price |Order-id: $order_id ");
    $stmt->execute();
    $stmt->close();
    
    // Insert into cart_history (permanent storage)
    $sql_history = "INSERT INTO cart_history (user_id, username, product_name, product_price, additional_services, total_price, payment_status, order_id) 
    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)";
    $stmt_history = $conn->prepare($sql_history);
      if (!$stmt_history) {
      die("SQL Prepare Failed: " . $conn->error);
    }
    $stmt_history->bind_param("sssdsds", $user_id, $username, $product_name, $product_price, $additional_services_str, $total_price, $order_id);
    $stmt_history->execute();
    $stmt_history->close();

    $_SESSION['success'] = "Items added to cart successfully! To view your items, please click on the Shopping Cart page and proceed to checkout.";

    log_debug("SQL Insert Successful.");

header("Location: mydashboard.php");
exit();
}

//  UPDATEE CART HISTORY WITH PAYMENT STATUS (completed)
$sql = "SELECT ch.product_name, ch.total_price, 
               IF(up.status = 'Completed', 'completed', 'pending') AS payment_status, 
               ch.added_on, ch.order_id
        FROM cart_history ch
        LEFT JOIN user_payments up 
            ON ch.user_id = up.user_id 
            AND ch.product_name = up.product_name
            AND ch.order_id = up.order_id 
        WHERE ch.user_id = ?
        ORDER BY ch.added_on DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
}
$stmt->close();



// Remove item from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_cart'])) {


    $user_id = $_SESSION['user_id'];
    $cart_id = $_POST['cart_id'];


    $sql = "DELETE FROM shopping_cart WHERE user_id = ? AND id = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("si", $user_id, $cart_id); 

    // Execute the query
    if ($stmt->execute()) {
        $_SESSION['success'] = "Item removed from cart successfully! Kindly return back to your shopping cart.";
    } else {
        $_SESSION['error'] = "Error removing item from cart. Please try again, if this persists contact support.";
    }

    header("Location: mydashboard.php");
    exit();
}


//for identifying and displaying  shoping cart items  are available or not 

if (!isset($_SESSION['user_id'])) {
    log_debug("Error: User not logged in!");
    exit();
}

$user_id = $_SESSION['user_id']; 


$sql = "SELECT * FROM shopping_cart WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close(); 

if ($result->num_rows > 0) {
    log_debug("Info: Items found in the cart for user ID: $user_id");
} else {
    log_debug("Info: No items found in the cart for user ID: $user_id");
}

require_once 'config.php';

function encrypt_data($data) {
    $encryption_key = ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $encryption_key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt_data($data) {
    $encryption_key = ENCRYPTION_KEY;
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $iv_length);
    $encrypted_data = substr($data, $iv_length);
    return openssl_decrypt($encrypted_data, 'AES-256-CBC', $encryption_key, 0, $iv);
}

//for updating users projects counter
$user_id = $_SESSION['user_id'] ?? 0; 
$order_id = $_SESSION['order_id'] ?? '';

$query = "
    SELECT 
        (SELECT product_name FROM shopping_cart WHERE user_id = ? AND order_id = ? ORDER BY created_at DESC LIMIT 1) AS product_name,
        (SELECT product_price FROM shopping_cart WHERE user_id = ? AND order_id = ? ORDER BY created_at DESC LIMIT 1) AS product_price,
        0 AS total_paid,  -- Set default to 0
        (SELECT COUNT(*) FROM onboarding_details WHERE user_id = ? AND order_id = ? AND product_name = ?) AS onboarding_count,
        (SELECT project_status FROM onboarding_details WHERE user_id = ? AND order_id = ? AND product_name = ? ORDER BY created_at DESC LIMIT 1) AS project_status
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("ssssssssss", 
    $user_id, $order_id,   
    $user_id, $order_id,  
    $user_id, $order_id, $product_name,  
    $user_id, $order_id, $product_name   
);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

// Default progress
$progress = 0;

// Extract product details
$product_name = $result['product_name'] ?? ''; 
$product_price = $result['product_price'] ?? 0;
$total_paid = 0; 
$onboarding_count = $result['onboarding_count'] ?? 0;
$latest_project_status = $result['project_status'] ?? '';

// Debugging logs
file_put_contents("debug_log.txt", date("Y-m-d H:i:s") . " - User ID: $user_id | Product: $product_name | Product Price: $product_price | Total Paid (defaulted to 0): $total_paid\n", FILE_APPEND);

// If no product in cart, progress is 0
if (!empty($product_name)) { 
    $progress = 25;
}

// Fetch total_price from shopping_cart
$query = "SELECT total_price FROM shopping_cart WHERE user_id = ? AND order_id = ? ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $user_id, $order_id);
$stmt->execute();
$total_price_result = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total_price = $total_price_result['total_price'] ?? 0;

// Log total_price for debugging
file_put_contents("debug_log.txt", date("Y-m-d H:i:s") . " - Total Price (from shopping_cart): $total_price\n", FILE_APPEND);


// Fetch the latest actual paid amount from user_payments
$query = "SELECT amount AS total_paid FROM user_payments 
          WHERE user_id = ? 
          AND status = 'Completed' 
          AND transaction_type = 'purchase' 
          AND product_name = ? 
          AND order_id =?
          AND amount = ?  
          ORDER BY created_at DESC 
          LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssds", $user_id, $product_name, $total_price,$order_id);  
$stmt->execute();
$payment_result = $stmt->get_result()->fetch_assoc();
$total_paid = $payment_result['total_paid'] ?? 0;



// Log the actual total paid
$log_message = date("Y-m-d H:i:s") . " - Actual Total Paid (from user_payments): " . number_format($total_paid, 2) . "\n";
file_put_contents("debug_log.txt", $log_message, FILE_APPEND);



// If payment is completed, progress goes to 50
if ($total_paid == $total_price && $total_price > 0) {
    $progress = 50;
}


// If onboarding form is submitted for the product, progress goes to 75
if ($onboarding_count > 0) {
    $progress = 75;
}

// If project is completed, progress goes to 100
if ($latest_project_status === 'completed') {
    $progress = 100;
}

// Final log
file_put_contents("debug_log.txt", date("Y-m-d H:i:s") . " - Final Calculated Progress: $progress\n", FILE_APPEND);



// Handle onboarding form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_onboarding"])) {
    $website_name = $_POST['website_name'];
    $privacy_policy = $_POST['privacy_policy'];
    $terms_of_use = $_POST['terms_of_use'];
    $website_mission = $_POST['website_mission'];
    $paystack_private_key = encrypt_data($_POST['paystack_private_key']);
    $paystack_secret_key = encrypt_data($_POST['paystack_secret_key']);
    $company_registration_details = encrypt_data($_POST['company_registration_details']);
    $founded_year = !empty($_POST['founded_year']) ? intval($_POST['founded_year']) : NULL;
    $reviews = $_POST['reviews'];

    // Handle logo upload
    $company_logo = NULL;
    if (!empty($_FILES["company_logo"]["name"])) {
        $logo_name = "user_" . $user_id . "_" . time() . "_" . $_FILES["company_logo"]["name"];
        $target_path = "uploads/" . $logo_name;

        if (move_uploaded_file($_FILES["company_logo"]["tmp_name"], $target_path)) {
            $company_logo = $target_path;
        }
    }

    // Insert into database
    $query = "INSERT INTO onboarding_details 
              (user_id, username, product_name, website_name, company_logo, privacy_policy, terms_of_use, website_mission, paystack_private_key, paystack_secret_key, company_registration_details, founded_year, reviews, order_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssssssssiss", $user_id,$username, $product_name, $website_name, $company_logo, $privacy_policy, $terms_of_use, $website_mission, $paystack_private_key, $paystack_secret_key, $company_registration_details, $founded_year, $reviews, $order_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Onboarding details submitted successfully! You will receive an email from our team with information on the development timeline for your project.";
    } else {
        $_SESSION['error'] = "Error submitting details,please contact support";
    }

    header("Location: mydashboard.php");
    exit;
}

if (isset($_GET['fetch_progress'])) {
    echo $progress;
    exit;
}

// Handle admin project completion update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["mark_completed"])) {
    $query = "UPDATE onboarding_details SET project_status = 'completed' WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Hurray! Your project has been completed. Our team will send you an email with the project details. Thank you for choosing Kizeop Group!";
    } else {
        $_SESSION['error_message'] = "Error updating project status.";
    }
    header("Location: mydashboard.php");
    exit;
}

//displaying the projects below the page
$query = "SELECT product_name, website_name, founded_year, company_logo, project_status 
          FROM onboarding_details 
          WHERE user_id = ? 
          ORDER BY id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$projects = $result->fetch_all(MYSQLI_ASSOC);

log_debug("Total projects found: " . count($projects));
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kizeop Group  – User Dashbaord">
    
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

    <title>My Dashboard - Kizeop Group </title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="mydashboard.css">
</head>


<header class="header">

<div class="hamburger" id="hamburger">
    <img src="img/menu-icon.png" alt="Menu Icon" class="menu-icon">
</div>

<div class="usersname" id="usersname">
<h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?> 👋</h1>

</div>



<div class="logo-c">
     <span class="site-name">KIZEOP GROUP</span>
</div>



<div class="info" id="info">
 <a href="/webpricing.html">Sieze the opportunity to enjoy 30% off on our website development plans</a>
</div>


<div class="cart" id="cart">
<a href="#" onclick="navigate('Cart')">
        <img src="img/cart2.png" alt="Cart" class="Cart">
</a>
</div>



</header>


<div class="sidebar" id="sidebar">
    <ul>
        <h2>KIZEOP GROUP</h2>

        <h3>Account & Transactions</h3>
        <li onclick="navigate('lastLogin')">Dashboard</li>
        <li onclick="navigate('Projects')">My Web Projects</li>
        <li onclick="navigate('Cart')">Shopping Cart</li>
        <li onclick="navigate('Billing')">Billing Transactions</li>
      
        <h3>Our Services</h3>
        <li onclick="navigate('WebsitesPricing')">Websites Development</li>
        <li onclick="navigate('AppsPricing')">Apps Development</li>

        <h3>User Information</h3>
        <li onclick="navigate('UpdateProfile')">Update Profile</li>
        <li onclick="navigate('ChangePassword')">Change Password</li>
        <li onclick="navigate('billing-settings')">Update Billing Information</li>
        

        <h3>Support</h3>
        <li onclick="navigate('Support')">Submit Tickets</li>

        <h3>Take a Break</h3>
        <li><a href="logout.php">Log Out</a></li> 
        <li><a href="/index.html">Vist Home Page</a></li> 
        
        

        
    </ul>
</div>

<body>


<section class="content-section" id="lastLogin">
              
             <div class="welcome">
             <h2>Welcome to your dashboard</h2>
             <p>Last Login: <?php echo htmlspecialchars($last_login, ENT_QUOTES, 'UTF-8'); ?></p>
             </div>
            
             <div class="location">
                <h3>Registration Location </h3>
                <p>Country: <?php echo htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?></p>
             </div>

             
   <div class="stay-informed">
                 <h3>Stay Informed, Stay Updated</h3>
                 <p>Add your email to get the latest from Kizeop </p>
    
             <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['success']; ?>
                    <?php unset($_SESSION['success']); ?>
               </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                     <?php echo $_SESSION['error']; ?>
                     <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

                 <form method="POST" action="mydashboard.php">
                      <input type="email" name="subscriber_email" placeholder="Enter your email" required>
                     <input type="submit" name="subscribe" value="Subscribe">
                </form>
    </div>
    


         
         
    <div class="user-info">
                  <h3>Your Information</h3>
                  <p>Full Name: <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>
                  <p>Phone Number: <?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></p>
                  <p>Email Address: <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></p>
                  <p>Joined on: <?php echo htmlspecialchars($created_at, ENT_QUOTES, 'UTF-8'); ?></p>
     </div>
         
         
         
</section>



<div class="content-section" id="Projects">
    <h2>My Web Projects</h2>
    <p>Track your web project status and updates.</p>

    <div class="progress-container">
        <div class="progress-bar" id="progress-bar" style="width: <?php echo $progress; ?>%;">
            <?php echo $progress; ?>%
        </div>
    </div>
     
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-message">
           <?php echo $_SESSION['success']; ?>
           <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
         <div class="error-message">
           <?php echo $_SESSION['error']; ?>
           <?php unset($_SESSION['error']); ?>
         </div>
    <?php endif; ?>


    <?php if ($progress == 50): ?>
    <form id="onboarding-form" method="post" enctype="multipart/form-data">
        <h3>Provide Your Website  Details</h3>

        <label>Selected Product Name:</label>
        <input type="text" name="product_name" value="<?php echo htmlspecialchars($product_name); ?>" readonly>

        <label>Your Website Name:</label>
        <input type="text" name="website_name" required>

        <label>Your Website Company Logo:</label>
        <input type="file" name="company_logo" accept="image/*">

        <label>Your Website Privacy Policy:</label>
        <textarea name="privacy_policy" required></textarea>

        <label>Your Website Terms of Use:</label>
        <textarea name="terms_of_use" required></textarea>

        <label>Your Website Mission:</label>
        <textarea name="website_mission" required></textarea>

        <label>Your Paystack Private Key(for integrating payment gateway,if none input, "I dont need  payment integration"):</label>
        <input type="text" name="paystack_private_key">

        <label>Your Paystack Secret Key(for integrating payment gateway,if none input, "I dont need  payment integration"):</label>
        <input type="text" name="paystack_secret_key">

        <label>Company Registration Details(if none,"input not yet registered"):</label>
        <textarea name="company_registration_details"></textarea>

        <label>Founded Year:</label>
        <input type="number" name="founded_year" min="1900" max="2099">

        <label>Your Website Reviews From clients(include atleast 1- 3 reviews with names):</label>
        <textarea name="reviews"></textarea>

        <button type="submit" name="submit_onboarding">Submit</button>
    </form>
    <?php endif; ?>


    <h3>Project History</h3>
<div id="project-history">

<?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-message">
           <?php echo $_SESSION['success_message']; ?>
           <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
         <div class="error-message">
           <?php echo $_SESSION['error_message']; ?>
           <?php unset($_SESSION['error_message']); ?>
         </div>
    <?php endif; ?>


    <?php if (!empty($projects)) : ?>
        <ul>
            <?php foreach ($projects as $project) : ?>
                <li>
                    <strong><?php echo htmlspecialchars($project['product_name']); ?></strong> 
                    (<?php echo htmlspecialchars($project['website_name']); ?>, <?php echo $project['founded_year']; ?>)
                    
                    <!-- Show Project Status -->
                    <?php if ($project['project_status'] === 'completed') : ?>
                        <span style="color: green; font-weight: bold;">✔ Completed</span>
                    <?php else : ?>
                        <span style="color: orange; font-weight: bold;">⏳ In Progress</span>
                    <?php endif; ?>

                    <!-- Show Logo If Available -->
                    <?php if (!empty($project['company_logo'])) : ?>
                        <br><img src="<?php echo $project['company_logo']; ?>" alt="Project Logo" style="width: 100px; height: auto;">
                    <?php endif; ?>
                </li>
                <hr>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p>No projects found.</p>
    <?php endif; ?>
</div>

</div>



    <div class="content-section" id="Billing">
            <h2>Billing & Payments</h2>
            <p>View and manage invoices and transactions.</p>
               
                    <!-- Payment History Section -->
        <div class="payment-history">
                  <h3>Payment History</h3>
            <table>
            <thead>
              <tr>
                <th>Service</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <th>Transaction ID</th>
                <th>Status</th>
                <th>Invoice</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!empty($payments)) : ?>
                <?php foreach ($payments as $payment) : ?>
                    <tr>
                        <td><?= htmlspecialchars($payment['service_name']) ?></td>
                        <td>₦<?= number_format($payment['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                        <td><?= htmlspecialchars($payment['transaction_id']) ?></td>
                        <td><?= htmlspecialchars($payment['status']) ?></td>
                        <td>
                            <?php if (!empty($payment['invoice_url'])) : ?>
                                <a href="<?= htmlspecialchars($payment['invoice_url']) ?>" target="_blank">View Invoice</a>
                            <?php else : ?>
                                Check invoice management
                            <?php endif; ?>
                        </td>
                        <td><?= date("F j, Y, g:i A", strtotime($payment['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="7">No payment history found.</td></tr>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    
            <!-- Invoice Management Section -->
        <div class="invoice-management">
                <h3>Invoice Management</h3>
                <p>Download invoices for your past transactions.</p>

               <a href="mydashboard.php?download_invoice=true">
               <button class="download-invoice">Download Latest Invoice</button>
            </a>

        </div>
    </div>

             <!-- Billing Settings Section -->
       <div class="content-section" id="billing-settings">
                  <h3>Billing Settings</h3>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['success']; ?>
                    <?php unset($_SESSION['success']); ?>
               </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                     <?php echo $_SESSION['error']; ?>
                     <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        
            <form action="" method="POST">
                    <label for="payment_method">Payment Method:</label>
                         <select name="payment_method" required>
                             <option value="Paystack">Paystack</option>
                             <option value="KoraPay">KoraPay</option>
                             <option value="Bank Transfer">Bank Transfer</option>
                        </select>


                   <label for="billing_address">Billing Address:</label>
                          <textarea name="billing_address" required></textarea>


                    <label for="phone_number">Phone Number:</label>
                           <input type="text" name="phone_number" required>


                    <label for="email_address">Email Address:</label>
                             <input type="email" name="email_address" required>


                   <label for="country">Country:</label>
                             <input type="text" name="country" required>


                    <label for="city">City:</label>
                            <input type="text" name="city" required>

                    <label for="postal_code">Postal Code:</label>
                           <input type="text" name="postal_code" required>

              <input type="submit" name="update_billing_details" value="Update Billing Info">
            
            </form>


    
        </div>



        <div  class="content-section" id="Support">
             <h2>Support</h2>
             <p>Need help? Open a ticket and our support team will assist you.</p>

           <form action="" method="POST">
                 <label for="subject">Subject:</label>
                 <input type="text" id="subject" name="subject" required>

                <label for="message">Message:</label>
                <textarea id="message" name="message" required></textarea>

                <button type="submit" name="submit_ticket">Submit Ticket</button>
            </form>

                       <h3>Your Support Tickets</h3>
                          <ul id="ticket-list">
                    <?php
    
                         $ticket_query = "SELECT id, subject, status, created_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC";
                         $stmt = $conn->prepare($ticket_query);
                         $stmt->bind_param("i", $user_id);
                         $stmt->execute();
                         $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                       while ($ticket = $result->fetch_assoc()) {
                      echo "<li><strong>" . htmlspecialchars($ticket['subject']) . "</strong> - " . htmlspecialchars($ticket['status']) . " (" . date("F j, Y", strtotime($ticket['created_at'])) . ")</li>";
                       }
                       } else {
                       echo "<li>No support tickets found.</li>";
                        }
                     $stmt->close();
                      ?>
                     </ul>
         </div>




        <div id="UpdateProfile" class="content-section">
                <h2>Personal Information</h2>


                <?php if (isset($_SESSION['success'])): ?>
                    <div class="success-message">
                         <?php echo $_SESSION['success']; ?>
                         <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="error-message">
                       <?php echo $_SESSION['error']; ?>
                       <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>



                   <form action="" method="POST">
                       <label for="username">Full Name:</label>
                       <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

                       <label for="email">Email:</label>
                       <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>

                        <button type="submit" name="update_profile">Update Profile</button>
                   </form>

        </div>



<div id="ChangePassword" class="content-section">
    
     <h3>Change Password</h3>
    
     <form action="" method="POST">
        <label for="current_password">Current Password:</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="new_password">New Password:</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit" name="change_password">Change Password</button>
    </form>
</div>


<div id="AppsPricing" class="content-section">
    <h3>App Development – Expanding Soon!</h3>
    <p>We are committed to delivering top-notch digital solutions, including websites and applications. While we currently focus on website development, we are actively working on expanding our app development capabilities to serve you even better. Stay tuned for exciting updates!</p>
</div>



<div id="WebsitesPricing" class="content-section">
    <h3>Select a web development package that suits your business</h3> 
    
    <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['success']; ?>
                    <?php unset($_SESSION['success']); ?>
               </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                     <?php echo $_SESSION['error']; ?>
                     <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>


    <div class="web-plans-box">

        <div class="web-plans" onclick="openPopup('WebXpress', 100000)" id="webxpress">
                <div class="promo-badge">🔥PROMO PACKAGE- WebXpress</div> 
             <strong>WebXpress - ₦100,000 / First Year</strong><br>
              <del>Old Price - ₦200,000</del><br>
             <span style="color: red; font-weight: bold;">Discount - SAVE 50%</span><br>
                       Renews at ₦150,000 (Includes domain, hosting, SSL, and more)<br>
                       ✔ Custom-designed 1-3 pages<br>
                       ✔ Mobile-friendly and responsive design<br>
                       ✔ Advanced SEO optimization<br>
                       ✔ Favicon added for brand recognition<br>
                       ✔ Social media links integration<br>
                       ✔ Variety of domain extensions to match your industry: .shop, .food, .boutique, .recipes, .bar, .delivery, .help, .digital, .life, .tattoo, .world, .xyz, .top, .cfd, .cam, .fit, .fitness, .sale, .onl, .cheap, .bid, .bio, .date, .forum, .lifestyle, .vip, .win, .me<br>
                       ✔ 24/7 customer service access<br>
                       ✔ Reliable shared hosting<br>
                       ✔ Free one-year SSL certificate<br>
                       ✔ Live chat integration (Free basics)<br>
                       ✔ CDN Integration (faster global site loading)<br>
                       ✔ Image Compression & Lazy Load<br>
                       ✔ SEO ranking tool<br>
                       ✔ WhatsApp Order Processing<br>
                       ✔ Google PageSpeed Optimization<br>
                       ✔ Lifetime Discount for Renewals (25% off every year)<br>
        </div>










      

      <div class="web-plans" onclick="openPopup('Kizeop Starter', 206500)" id="starter">
      <div class="starter-badge">MOST POPULAR- Kizeop Starter </div> 
        <strong>Kizeop Starter - ₦206,500 / Year</strong><br>
        <del>Old price - ₦243,000</del><br>
        <span style="color: red; font-weight: bold;">Discount - SAVE 15%</span><br>
         Renews at ₦215,500 (Includes domain, hosting, SSL, and more)<br>
         ✔ Custom-designed 1-5 pages<br>
         ✔ Mobile-friendly and responsive design<br>
         ✔ Basic SEO optimization<br>
         ✔ Favicon added for brand recognition<br>
         ✔ Social media links integration<br>
         ✔ Domain options: .com, .org, .me<br>
         ✔ Custom Domain name (on request)<br>
         ✔ Payment Gateways integration<br>
         ✔ 24/7 customer service access<br>
         ✔ Reliable shared hosting<br>
         ✔ Free one-year SSL certificate<br>
         ✔ Live chat integration (Free basics)<br>
         ✔ Free 1 month daily backups of user accounts( worth ₦10,000)<br>
         ✔ Basic Admin panel<br>
         ✔ 2 weeks free extensive daily maintenance/updates <a href="/terms-page.html">(see terms)</a> worth ₦25,000<br>
         ✔ Web Application Firewall (WAF)<br>
         ✔ 2FA Login Security<br>
         ✔ CDN Integration<br>
         ✔ Image Compression & Lazy Load<br>
         ✔ User Login & Signup Feature (On Request: 85,000 NGN)<br>

      </div>

        <div class="web-plans" onclick="openPopup('Kizeop Brand', 325500)" id="brand">
        <div class="brand-badge">MINI-BUSINESS PACKAGE - Kizeop Brand </div> 
           <strong>Kizeop Brand - ₦325,500 / Year</strong><br>
           <del>Old price - ₦465,000</del><br>
           <span style="color: red; font-weight: bold;">Discount - SAVE 30%</span><br>
           Renews at ₦305,500 (Includes domain, hosting, SSL, and more)<br>
           ✔ Custom-designed 1-10 pages<br>
           ✔ Mobile-friendly and responsive design<br>
           ✔ Basic SEO optimization<br>
           ✔ Favicon added for brand recognition<br>
           ✔ Social media links integration<br>
           ✔ All domain options in Kizeop Starter, plus .net, .ca, .cc, .africa, .page<br>
           ✔ Custom Domain name (on request)<br>
           ✔ Payment Gateways integration<br>
           ✔ Multiple mails sending integration<br>
           ✔ 24/7 customer service access<br>
           ✔ Reliable shared hosting<br>
           ✔ Free one-year SSL certificate<br>
           ✔ Live chat integration (Free basics)<br>
           ✔ Free 3 months daily backups of user accounts (worth ₦30,000 )<br>
           ✔ Basic + customized Admin panel<br>
           ✔ 1 month free extensive daily maintenance/updates <a href="/terms-page.html">(see terms)</a> worth ₦50,000<br>
           ✔ Web Application Firewall (WAF)<br>
           ✔ 2FA Login Security<br>
           ✔ CDN Integration (faster global site loading)<br>
           ✔ Image Compression & Lazy Load<br>
           ✔ 3 mail SMTP accounts integration for sending automated mails to users<br>
           ✔ 1 database<br>
           ✔ SEO ranking <br>
           ✔ Basic backend integrations<br>
           ✔ Google PageSpeed Optimization<br>
           ✔ Anti-Spam Email Protection<br>
           ✔ Lifetime Discount for Renewals (10% off every year)<br>


        </div>

        <div class="web-plans" onclick="openPopup('Kizeop Gaint', 1533350)" id="gaint">
        <div class="starter-badge">E-COMMERCE PACKAGE - Kizeop Gaint </div> 
           <strong>Kizeop Giant - ₦1,533,350 / Year</strong><br>
           <del>Old price - ₦2,190,500</del><br>
           <span style="color: red; font-weight: bold;">Discount - SAVE 30%</span><br>
           Renews at ₦1,233,400 (Includes domain, hosting, SSL, and more)<br>
           ✔ Unlimited custom-designed pages<br>
           ✔ Mobile-friendly and responsive design<br>
           ✔ Advanced SEO optimization<br>
           ✔ Favicon added for brand recognition<br>
           ✔ Social media links integration<br>
           ✔ Domain options in all (3) plans,including .io, .co<br>
           ✔ Custom Domain name (on request)<br>
           ✔ Payment Gateways integration<br>
           ✔ Multiple mails sending integration<br>
           ✔ 24/7 customer service access<br>
           ✔ VPN Hosting<br>
           ✔ Free one-year SSL certificate<br>
           ✔ Live chat integration (Paid basics)<br>
           ✔ Free 12 months daily backups of user accounts(worth ₦120k ) <br> 
           ✔ Advanced PRO customized Admin panel with various automations<br>
           ✔ 3 month free extensive daily maintenance/updates <a href="/terms-page.html">(see terms)</a> worth ₦150,000<br>
           ✔ Web Application Firewall (WAF)<br>
           ✔ 2FA Login Security<br>
           ✔ CDN Integration(Ultra-fast global access)<br>
           ✔ Image Compression & Lazy Load<br>
           ✔ 5 mail SMTP accounts integration for sending automated mails to users<br>
           ✔ 5 databases<br>
           ✔ SEO ranked Website<br>
           ✔ Advanced custom backend integrations<br>
           ✔ Google PageSpeed Optimization<br>
           ✔ Anti-Spam Email Protection<br>
           ✔ Lifetime Discount for Renewals (10% off every year)<br>
           ✔ VIP Support with 24/7 priority assistance<br>
           ✔ AI-Powered Chatbot (Advanced AI for automated customer interactions & lead generation)<br>
           ✔ DDoS Protection<br>
           ✔ Password protection<br>
           ✔ 5 add-on domains (Users pay for additional subdomains)<br>
           ✔ Cron jobs for automated task scheduling<br>

        </div>
    </div>
</div>


<!-- Popup Form for web pricing plans -->
<div id="popupForm" class="popup">
    <div class="popup-content">
        <span class="close" onclick="closePopup()">&times;</span>
        <h3 id="productTitle"></h3>
        
        <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['success']; ?>
                    <?php unset($_SESSION['success']); ?>
               </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                     <?php echo $_SESSION['error']; ?>
                     <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        <form id="cartForm" method="POST" action="mydashboard.php">
            <input type="hidden" id="productName" name="product_name">
            <input type="hidden" id="productPrice" name="product_price">

            <label>Select Additional Services:</label><br>
               <input type="checkbox" name="additional_services[]" value="Site Pages" onclick="updateTotalPrice()"> EXTRA Site Pages - ₦10,000 <br>
               <input type="checkbox" name="additional_services[]" value="Login/Sign-Up Feature" onclick="updateTotalPrice()"> Request Login/Sign-Up Feature - ₦85,000 <br>
               <input type="checkbox" name="additional_services[]" value="Monthly Site Maintenance" onclick="updateTotalPrice()">1 Month  Users Database Backup  - ₦10,000 <br>
               <input type="checkbox" name="additional_services[]" value="Daily Site Maintenance" onclick="updateTotalPrice()"> Daily Site Maintenance - ₦50,000<br>
               <input type="checkbox" name="additional_services[]" value="Request Dedicated Server" onclick="updateTotalPrice()"> Request Dedicated Server- ₦848,000 <br>
               <input type="checkbox" name="additional_services[]" value="SMTP Email" onclick="updateTotalPrice()"> EXTRA SMTP Emails - ₦12,000<br><br>

            <h4 id="totalPriceDisplay">Total Price: ₦0</h4>

            <button type="submit" name="add" value="submit">Add to Cart</button>

        </form>
    </div>
</div>


        <?php
             $user_id = $_SESSION['user_id']; 

            $sql = "SELECT * FROM shopping_cart WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $stmt->close();

            if ($result->num_rows > 0) {
               log_debug("Info: Items found in the cart for user ID: $user_id");
           } else {
               log_debug("Info: No items found in the cart for user ID: $user_id");
            }          
        ?>
<div class="content-section" id="Cart">
   <h2>Your Shopping Cart Items</h2>
   
   <div class="cart-history">
   <table >
        <thead>
            <tr>
                <th>Item Name</th>
                <th>Price</th>
                <th>Additional Services</th>
                <th>Total Price</th>
                <th>Delete Items</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            
            while ($row = $result->fetch_assoc()) { 
                log_debug("Displaying item: " . print_r($row, true)); // Log each item for debugging
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                    <td>₦<?php echo number_format($row['product_price'], 2); ?></td>
                    <td><?php echo htmlspecialchars($row['additional_services']); ?></td>
                    <td>₦<?php echo number_format($row['total_price'], 2); ?></td>
                    <td>

                        <form method="POST" action="mydashboard.php">
                            <input type="hidden" name="cart_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="remove_from_cart">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php if ($result->num_rows > 0) { ?>
       
        <form method="POST" action="checkoutWebDev.php">
            <button type="submit" name="checkout_web">Proceed to Checkout</button>
        </form>
    <?php } else { ?>
        <p>Your cart is empty. Please add some items to your cart.</p>
    <?php } ?>
</div>
</div>



<script src="mydashboard.js"></script>

</body>

</html>



