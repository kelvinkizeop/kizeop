//function to hide contents on the dashboard and displayed by Id when clicked
function navigate(sectionId) {
    document.querySelectorAll('.content-section').forEach(section => {
        section.style.display = 'none';
    });

    let selectedSection = document.getElementById(sectionId);
    if (selectedSection) {
        selectedSection.style.display = 'block';
    }
}

// default section when page loads(popupform,lastlogin)
document.addEventListener("DOMContentLoaded", function () {
    navigate('lastLogin'); 
});


document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("popupForm").style.display = "none";
});



//to modify prices of additional services 
function openPopup(productName, productPrice) {
    document.getElementById("productTitle").textContent = productName; // Fix: Use productName
    document.getElementById("productName").value = productName; 
    document.getElementById("productPrice").value = productPrice; 

    document.getElementById("totalPriceDisplay").innerText = "Total Price: ₦" + productPrice.toLocaleString();

    document.getElementById("popupForm").style.display = "block";
}


function closePopup() {
    document.getElementById("popupForm").style.display = "none";
}

function updateTotalPrice() {
    let basePrice = parseFloat(document.getElementById("productPrice").value);
    let totalPrice = basePrice;

    let servicePrices = {
        "Site Pages": 10000,
        "Login/Sign-Up Feature": 85000,
        "Monthly Site Maintenance": 10000,
        "Daily Site Maintenance": 50000,
        "Request Dedicated Server": 848000,
        "SMTP Email": 12000
    };

    let selectedServices = document.querySelectorAll("input[name='additional_services[]']:checked");

    selectedServices.forEach(service => {
        totalPrice += servicePrices[service.value];
    });


    document.getElementById("totalPriceDisplay").innerText = "Total Price: ₦" + totalPrice.toLocaleString();
}






////for updating users projects counter
document.addEventListener("DOMContentLoaded", function () {
    function updateProgress() {
        let xhr = new XMLHttpRequest();
        xhr.open("GET", "mydashboard.php?fetch_progress=true", true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                let progress = parseInt(xhr.responseText.trim());

                let progressBar = document.querySelector("#progress-bar");

                progressBar.style.width = progress + "%";
                progressBar.textContent = progress + "%";

                // Change color dynamically
                if (progress < 50) {
                    progressBar.style.backgroundColor = "#ff4d4d"; 
                } else if (progress < 75) {
                    progressBar.style.backgroundColor = "#ffcc00"; 
                } else {
                    progressBar.style.backgroundColor = "#4caf50"; 
                }
            }
        };
        xhr.send();
    }

    setInterval(updateProgress, 5000);
    updateProgress(); 

   
    document.addEventListener("click", function (event) {
        if (event.target.classList.contains("add-to-cart")) { 
            setTimeout(updateProgress, 2000); 
        }
    });
});


//modify  section log out  condition and notification 
window.onload = function () {
    let timeoutDuration = 600000; 
    setTimeout(() => {
        alert("Your session has expired due to inactivity. You will be logged out.");
        fetch("logout.php") 
            .then(() => {
                window.location.href = "login.php?timeout=1"; 
            });
    }, timeoutDuration);
};



//menu toogle
document.addEventListener("DOMContentLoaded", () => {
    const hamburger = document.getElementById("hamburger"); 
    const sidebar = document.querySelector(".sidebar"); 
  

  
    hamburger.addEventListener("click", () => {
        sidebar.classList.toggle("show"); 
    });


    const sidebarItems = sidebar.querySelectorAll("ul li");
    sidebarItems.forEach(item => {
        item.addEventListener("click", () => {
            sidebar.classList.remove("show"); 
        });
    });
});
