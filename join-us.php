<?php
session_start();

// Security Headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Referrer-Policy: no-referrer-when-downgrade");
header("Content-Security-Policy: default-src 'self'");

include('db.php');

// Generate CSRF Token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    // Sanitize & Trim Inputs
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $country = trim($_POST['country']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    $billing_address = trim($_POST['billing_address']);
    $password = $_POST['password'];
    $payment_method = "Not Set"; 

    // Generate Unique user_id
    $user_id = uniqid();

    // Check if Username Already Exists
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        die("Error: Username already taken.");
    } else {
        // Hash Password Before Storing
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert User into Database
        $sql = "INSERT INTO users (user_id, username, password, email, phone, country, city, postal_code, billing_address, payment_method, created_at, last_login) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssssssssss", $user_id, $username, $hashedPassword, $email, $phone, $country, $city, $postal_code, $billing_address, $payment_method);

        if (!$stmt->execute()) {
            die("Database error: " . $stmt->error);
        } else {
            echo "User registered successfully!";
        }

        // Redirect to success page
        header("Location: join-us.php?success=1");
        exit();
    }
}

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join us - at Kizeop Group</title>
    <link rel="stylesheet" href="join-us.css">
</head>
<body>

    <div class="kizeop-info">
        <h1>Welcome To Kizeop Group</h1>
        <p>Your One Stop Shop For Digital Solutions</p>
        <img src="img/web.png"> 
    </div>

    <div class="signup-container">
        <h2>Create an account to continue enjoying our services </h2>

        <form action="join-us.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- Username Field -->
            <div class="field-text">
                <input type="text" id="username" name="username" required>
                <label for="username">Enter full name</label>
                <span></span>
            </div>

            <!-- Email Field -->
            <div class="field-text">
                <input type="email" id="email" name="email" required>
                <label for="email"> Enter your email</label>
                <span></span>
            </div>

            <!-- Phone Number Field -->
            <div class="field-text">
                <input type="tel" id="phone" name="phone" required>
                <label for="phone">Enter Phone Number</label>
                <span></span>
            </div>

            <!-- Country Name Field -->
            <div class="field-country">
                <select id="country" name="country" required>
                    <option value="" selected disabled>Select Your Country</option>
                    <option value="Nigeria">Nigeria</option>
                    <option value="United States">United States</option>
                    <option value="United Kingdom">United Kingdom</option>
                    <option value="Canada">Canada</option>
                    <option value="Italy">Italy</option>
                    <option value="Ghana">Ghana</option>
                    <option value="Australia">Australia</option>
                </select>
                <label for="country"> Select Country</label>
                <span></span>
            </div>


            
              <!-- City -->
             <div class="field-text">
                   <input type="text" id="city" name="city" required>
                   <label for="city">Enter City</label>
                   <span></span>
             </div>

              <!-- Postal Code -->
            <div class="field-text">
                   <input type="text" id="postal_code" name="postal_code" required>
                   <label for="postal_code">Enter Postal Code</label>
                   <span></span>
            </div>

            <!-- Billing Address -->
             <div class="field-text">
                 <input type="text" id="billing_address" name="billing_address" required>
                 <label for="billing_address">Enter Billing Address</label>
                 <span></span>
            </div>
            
            <!-- Password Field -->
            <div class="field-text">
                <input type="password" id="password" name="password" required>
                <label for="password">Enter Password</label>
                <span></span>
                <button type="button" id="togglePassword" class="togglePassword">👁</button>
            </div>

            <!-- Submit Button -->
            <input type="submit" value="Sign Up">

        </form> 
        <div class="loader" id="loader"></div>
        <div class="login-link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
    <script src="join-us.js"></script>
</body>
</html>
