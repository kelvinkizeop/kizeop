document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const passwordInput = document.getElementById("password");
    const passwordLabel = document.querySelector("label[for='password']");
    const submitButton = document.querySelector("input[type='submit']");
    const togglePassword = document.getElementById("togglePassword");
    const loader = document.createElement("div");

    //  spinner loader
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
    form.parentNode.insertBefore(loader, form.nextSibling);

    
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

    passwordInput.addEventListener("input", validatePassword);

    // Toggle password visibility
    togglePassword.addEventListener("click", function () {
        const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
        passwordInput.setAttribute("type", type);
        this.textContent = type === "password" ? "👁" : "👁‍🗨";
    });


    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has("error")) {
        alert(urlParams.get("error"));
    }

    if (urlParams.has("success")) {
        alert("Login was successful! Redirecting to your dashboard...");
    }

    form.addEventListener("submit", function (event) {
        event.preventDefault();

        if (!validatePassword()) {
            alert("Please enter a valid password.");
            return;
        }

        form.style.display = "none";
        loader.style.display = "block";
        submitButton.disabled = true;

        setTimeout(() => {
            form.submit();
        }, 2000); 
    });
});

