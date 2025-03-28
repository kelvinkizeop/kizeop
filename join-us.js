document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const phoneInput = document.getElementById("phone");
    const passwordInput = document.getElementById("password");
    const phoneLabel = document.querySelector("label[for='phone']");
    const passwordLabel = document.querySelector("label[for='password']");
    const submitButton = document.querySelector("input[type='submit']");
    const loader = document.createElement("div"); 

    // Style the spinner
    loader.className = "loader";
    loader.style.display = "none"; 
    loader.style.border = "5px solid #f3f3f3";
    loader.style.borderTop = "5px solid #3498db";
    loader.style.borderRadius = "50%";
    loader.style.width = "50px";
    loader.style.height = "50px";
    loader.style.animation = "spin 1s linear infinite";
    loader.style.margin = "20px auto";
    loader.style.textAlign = "center";

    // Append spinner below the form
    form.parentNode.insertBefore(loader, form.nextSibling);

    function validatePhone() {
        const phonePattern = /^[0-9]{7,25}$/;
        if (!phonePattern.test(phoneInput.value)) {
            phoneLabel.textContent = "Invalid phone number (7-25 digits only)";
            phoneLabel.style.color = "red";
            return false;
        } else {
            phoneLabel.textContent = "Valid phone number ✅";
            phoneLabel.style.color = "green";
            return true;
        }
    }

    function validatePassword() {
        const passwordPattern = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/;
        if (!passwordPattern.test(passwordInput.value)) {
            passwordLabel.textContent = "Weak password";
            passwordLabel.style.color = "red";
            return false;
        } else {
            passwordLabel.textContent = "Strong password ✅";
            passwordLabel.style.color = "green";
            return true;
        }
    }

    // Real-time validation
    phoneInput.addEventListener("input", validatePhone);
    passwordInput.addEventListener("input", validatePassword);

   
    form.addEventListener("submit", function (event) {
        if (!validatePhone() || !validatePassword()) {
            event.preventDefault(); 
            alert("Please fix errors before submitting.");
        } else {
            
            form.style.display = "none"; 
            loader.style.display = "block"; 
            submitButton.disabled = true; 


            setTimeout(() => {
                alert("Hello! Your registration was successful! A welcome email has been sent to you, and you will be redirected to your dashboard.");
                window.location.href = "mydashboard.php"; // Redirect after success
            }, 10000); 
        }
    });

    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has("success")) {
        alert("Hello! Your registration was successful! A welcome email has been sent to you, and you will be redirected to your dashboard.");
        window.location.href = "mydashboard.php";
    }
});

// Toggle password visibility
const togglePassword = document.getElementById('togglePassword');
const password = document.getElementById('password');

togglePassword.addEventListener('click', function () {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.textContent = type === 'password' ? '👁' : '👁‍🗨';
});

