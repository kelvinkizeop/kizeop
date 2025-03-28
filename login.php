<?php
include('db.php');
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_id = $_POST["login_id"];
    $password = $_POST["password"];

    // Prepare the SQL query to check both email and phone number
    $query = "SELECT * FROM users WHERE email = ? OR phone = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $login_id, $login_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Check if the password matches
        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["user_id"];  
            $_SESSION["full_name"] = $user["username"];
            $_SESSION['is_admin'] = $user['is_admin'];

    //  Updates last login time
            $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("s", $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();

            if ($user['is_admin'] == 1) {
                header("Location: a.php?success=1");
                exit();
            } else {
                header("Location: mydashboard.php?success=1");
                exit();
            }
        } else {
            header("Location: login.php?error=Incorrect Password");
            exit();
        }
    } else {
        header("Location: login.php?error=User Not Found");
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
    <title>Login</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

<div class="kizeop-info">
  <h1>Welcome To Kizeop Group</h1>
  <p>Your One Stop Shop For Digital Solutions<p>
  <img src="img/web.png"> 
  </div>
   
    <!-- Login container -->
    <div class="login-container">

        <h2>Welcome Back Login To Continue</h2>
        <form action="login.php" method="POST">
          
        <div class="field-text">
               <input type="text" name="login_id" required>
               <label for="login_id">Enter email or phone number</label>
               <span></span>
        </div>


            <div class="field-text">
                <input type="password" name="password" id="password" required>
                <label for="password">Enter Password</label>
                <span></span>
                <button type="button"  id="togglePassword" class="togglePassword" onclick="togglePassword()">👁</button>
            </div>

            <!-- Submit Button -->
            <input type="submit" value="Login">
        </form>
        <div class="loader" id="loader"></div>
        <!-- Signup Link -->
        <div class="signup-link">
            Don't have an account? <a href="join-us.php">Sign up</a>
        </div>
    </div>
   

    <script src="login.js"></script>
</body>
</html>