

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

//section for typing effects for intro-txt
const textArray = [
    "We develop the best websites tailored to drive success for your brand...",
    "Let us take your online presence to the next level...",
    "Your business deserves a professional touch!",
    "We craft stunning websites that drive real business growth...",
    "Transform your brand with high-performance web solutions...",
    "Your vision, our expertise – let’s build something incredible...",
    "Every pixel counts. We design with precision and purpose...",
    "Stay ahead of the competition with cutting-edge web development...",
    "Let your website do the talking – convert visitors into customers...",
    "We are known for custom developments ,whatever idea you have we bring them to live."
];

let textIndex = 0;
let charIndex = 0;
const typingSpeed = 100; 
const erasingSpeed = 50; 
const delayBetweenTexts = 2000; 

const typingTextElement = document.getElementById("typing-text");

function typeText() {
    if (charIndex < textArray[textIndex].length) {
        typingTextElement.innerHTML += textArray[textIndex].charAt(charIndex);
        charIndex++;
        setTimeout(typeText, typingSpeed);
    } else {
        setTimeout(eraseText, delayBetweenTexts);
    }
}

function eraseText() {
    if (charIndex > 0) {
        typingTextElement.innerHTML = textArray[textIndex].substring(0, charIndex - 1);
        charIndex--;
        setTimeout(eraseText, erasingSpeed);
    } else {
        textIndex = (textIndex + 1) % textArray.length;
        setTimeout(typeText, typingSpeed);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    setTimeout(typeText, 500);
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

document.querySelectorAll('.scroll-reveal').forEach(element => {
    observer.observe(element);
});

//sidebar drop down toggle
document.querySelectorAll(".sidebar-dropdown").forEach(item => {
    item.addEventListener("click", function(event) {
        event.stopPropagation(); // Prevents the sidebar from closing
        this.classList.toggle("open");
    });
});

//reviews toggle
const carousel = document.querySelector(".carousel");
const dots = document.querySelectorAll(".dot");

let currentIndex = 0;

// Update carousel and active dot
function updateCarousel(index) {
  const cardWidth = carousel.querySelector(".reviews-section").offsetWidth;
  carousel.style.transform = `translateX(-${index * cardWidth}px)`;

  dots.forEach((dot, idx) => {
    dot.classList.toggle("active", idx === index);
  });
}

// Add event listeners to dots
dots.forEach((dot, index) => {
    dot.addEventListener("click", () => {
      currentIndex = index;
      updateCarousel(currentIndex);
    });
  });
  
// Swipe functionality for touch devices
let startX = 0;

carousel.addEventListener("touchstart", (e) => {
  startX = e.touches[0].clientX;
});

carousel.addEventListener("touchend", (e) => {
  const endX = e.changedTouches[0].clientX;
  const diff = endX - startX;

  if (diff > 50 && currentIndex > 0) {
    currentIndex--; // Swipe right
  } else if (diff < -50 && currentIndex < dots.length - 1) {
    currentIndex++; // Swipe left
  }

  updateCarousel(currentIndex);
});
// Automatic carousel cycling every 3 seconds (3000ms)
setInterval(() => {
    if (currentIndex < dots.length - 1) {
      currentIndex++;
    } else {
      currentIndex = 0;
    }
    updateCarousel(currentIndex);
  }, 3000);
  
  document.addEventListener("DOMContentLoaded", function() {
    const postButtons = document.querySelectorAll("button");

    postButtons.forEach(button => {
        button.addEventListener("click", function() {
            const textarea = this.previousElementSibling;
            if (textarea.value.trim() !== "") {
                const newPost = document.createElement("p");
                newPost.innerHTML = `<strong>You:</strong> ${textarea.value}`;
                textarea.value = "";
                
                const parentSection = this.closest(".forum-section");
                const postContainer = parentSection.querySelector(".user-posts") || parentSection.querySelector(".admin-posts");
                postContainer.appendChild(newPost);
            }
        });
    });
});
