
function googleTranslateElementInit() {
    new google.translate.TranslateElement({ pageLanguage: 'en' }, 'google_translate_element');
}

//  Google Translate 
let script = document.createElement('script');
script.src = "//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit";
document.head.appendChild(script);


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

//sidebar drop down toggle
document.querySelectorAll(".sidebar-dropdown").forEach(item => {
    item.addEventListener("click", function(event) {
        event.stopPropagation(); 
        this.classList.toggle("open");
    });
});



// Open Modal
function openModal() {
    document.getElementById("backendModal").style.display = "flex";
}

// Close Modal
function closeModal() {
    document.getElementById("backendModal").style.display = "none";
}

// Close when clicking outside the modal
window.onclick = function(event) {
    var modal = document.getElementById("backendModal");
    if (event.target === modal) {
        modal.style.display = "none";
    }
}

document.querySelectorAll(".open-modal").forEach(button => {
    button.addEventListener("click", function (event) {
        event.preventDefault();
        const modalId = this.getAttribute("data-modal");
        document.getElementById(modalId).style.display = "flex";
    });
});

document.querySelectorAll(".close-modal").forEach(button => {
    button.addEventListener("click", function () {
        this.closest(".modal-overlay").style.display = "none";
    });
});

document.addEventListener("click", function (event) {
    if (event.target.classList.contains("modal-overlay")) {
        event.target.style.display = "none";
    }
});


//for scroll effect when in viewpoint
const observer = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible'); 
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });


document.querySelectorAll('.scroll-zoom').forEach(element => {
    observer.observe(element);
});



// Run on load
enableCarousel();

// Re-check when window resizes
window.addEventListener("resize", () => {
  enableCarousel();
});


//for pop up message alert
function openPopup() {
    const popup = document.getElementById("installmentPopup");
    popup.style.display = "block";

    // Apply fade-in effect
    setTimeout(() => {
        popup.classList.add("show");
    }, 1000);

    // Hide the popup automatically after 30 seconds
    setTimeout(closePopup, 30 * 1000);
}

function closePopup() {
    const popup = document.getElementById("installmentPopup");

    // Fade out effect before hiding
    popup.classList.remove("show");

    setTimeout(() => {
        popup.style.display = "none";
    }, 1000); 
}

// Shows popup every 3 minutes (it will appear, stay for 30 seconds, then disappear)
setInterval(openPopup, 3 * 60 * 1000);

// First popup appears after 3 seconds
setTimeout(openPopup, 3000);


document.addEventListener("DOMContentLoaded", function () {
    const criteriaLinks = document.querySelectorAll(".see-criteria");
    const popupOverlay = document.getElementById("criteriaPopup");
    const popupTitle = document.getElementById("popupTitle");
    const popupRequirements = document.getElementById("popupRequirements");
    const closePopup = document.querySelector(".close-popups");

    criteriaLinks.forEach(link => {
        link.addEventListener("click", openPopup);
        link.addEventListener("touchstart", openPopup); // Fix for mobile
    });

    function openPopup(event) {
        event.preventDefault();
        const jobTitle = this.getAttribute("data-title");
        const jobRequirements = this.getAttribute("data-requirements");

        popupTitle.textContent = jobTitle;
        popupRequirements.innerHTML = jobRequirements;

        popupOverlay.classList.add("active");
    }

    closePopup.addEventListener("click", function () {
        popupOverlay.classList.remove("active");
    });

    popupOverlay.addEventListener("click", function (event) {
        if (event.target === popupOverlay) {
            popupOverlay.classList.remove("active");
        }
    });
});






