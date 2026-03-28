// Navigation Burger Menu
const burger = document.querySelector('.burger');
const nav = document.querySelector('.nav-links');
const navLinks = document.querySelectorAll('.nav-links li');

burger.addEventListener('click', () => {
    // Toggle Nav
    nav.classList.toggle('nav-active');
    
    // Animate Links
    navLinks.forEach((link, index) => {
        if (link.style.animation) {
            link.style.animation = '';
        } else {
            link.style.animation = `navLinkFade 0.5s ease forwards ${index / 7 + 0.3}s`;
        }
    });
    
    // Burger Animation
    burger.classList.toggle('toggle');
});

// Smooth Scrolling für Navigation Links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Formular Validierung und Submission
const contactForm = document.getElementById('contact-form');

contactForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Einfache Formularvalidierung
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const message = document.getElementById('message').value;
    const privacy = document.getElementById('privacy').checked;
    
    if (!name || !email || !message) {
        alert('Bitte füllen Sie alle Felder aus.');
        return;
    }

    if (!privacy) {
        alert('Bitte stimmen Sie der Datenschutzerklärung zu.');
        return;
    }
    
    // Formular-Daten vorbereiten
    const formData = new FormData();
    formData.append('name', name);
    formData.append('email', email);
    formData.append('message', message);

    // Sende die Daten an das PHP-Skript
    fetch('send-mail.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            contactForm.reset();
        } else {
            alert(data.error || 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
    });
});

// Animation beim Scrollen
const observerOptions = {
    threshold: 0.5
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate');
        }
    });
}, observerOptions);

// Beobachte alle Sektionen
document.querySelectorAll('section').forEach(section => {
    observer.observe(section);
});

// Slideshow Funktionalität
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelector('.slides');
    const slideItems = document.querySelectorAll('.slide');
    const prevButton = document.querySelector('.prev');
    const nextButton = document.querySelector('.next');
    const dotsContainer = document.querySelector('.slideshow-dots');
    
    let currentSlide = 0;
    const slideCount = slideItems.length;

    // Erstelle Dots
    slideItems.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.classList.add('dot');
        if (index === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(index));
        dotsContainer.appendChild(dot);
    });

    const dots = document.querySelectorAll('.dot');

    function updateSlideshow() {
        slides.style.transform = `translateX(-${currentSlide * 100}%)`;
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentSlide);
        });
    }

    function goToSlide(index) {
        currentSlide = index;
        updateSlideshow();
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % slideCount;
        updateSlideshow();
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + slideCount) % slideCount;
        updateSlideshow();
    }

    // Event Listener für Buttons
    prevButton.addEventListener('click', prevSlide);
    nextButton.addEventListener('click', nextSlide);

    // Automatische Slideshow
    let slideshowInterval = setInterval(nextSlide, 5000);

    // Pause bei Hover über den Slides
    slides.addEventListener('mouseenter', () => {
        clearInterval(slideshowInterval);
    });

    slides.addEventListener('mouseleave', () => {
        slideshowInterval = setInterval(nextSlide, 5000);
    });

    // Pause bei Hover über den Navigationsbuttons
    prevButton.addEventListener('mouseenter', () => {
        clearInterval(slideshowInterval);
    });

    nextButton.addEventListener('mouseenter', () => {
        clearInterval(slideshowInterval);
    });

    prevButton.addEventListener('mouseleave', () => {
        slideshowInterval = setInterval(nextSlide, 5000);
    });

    nextButton.addEventListener('mouseleave', () => {
        slideshowInterval = setInterval(nextSlide, 5000);
    });

    // Touch-Unterstützung
    let touchStartX = 0;
    let touchEndX = 0;

    slides.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    });

    slides.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });

    function handleSwipe() {
        const swipeThreshold = 50;
        if (touchEndX < touchStartX - swipeThreshold) {
            nextSlide();
        }
        if (touchEndX > touchStartX + swipeThreshold) {
            prevSlide();
        }
    }
}); 