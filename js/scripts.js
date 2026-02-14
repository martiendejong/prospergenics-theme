/**
 * Prospergenics Theme Scripts
 *
 * @package Prospergenics
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Smooth scrolling for anchor links
     */
    function initSmoothScroll() {
        const links = document.querySelectorAll('a[href^="#"]');

        links.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');

                // Skip if it's just #
                if (href === '#') {
                    e.preventDefault();
                    return;
                }

                const target = document.querySelector(href);

                if (target) {
                    e.preventDefault();
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    // Close mobile menu if open
                    const navigation = document.querySelector('.main-navigation');
                    const menuToggle = document.querySelector('.menu-toggle');
                    if (navigation && navigation.classList.contains('active')) {
                        navigation.classList.remove('active');
                        if (menuToggle) {
                            menuToggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                }
            });
        });
    }

    /**
     * Parallax effect for sections
     */
    function initParallax() {
        const parallaxSections = document.querySelectorAll('.parallax-section');

        // Check if device supports parallax (not mobile)
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

        if (!isMobile && parallaxSections.length > 0) {
            // Store initial background positions
            const initialPositions = new Map();
            parallaxSections.forEach(section => {
                const computedStyle = window.getComputedStyle(section);
                const bgPos = computedStyle.backgroundPosition.split(' ');
                const yPos = bgPos[1] || '0%';

                // Convert percentage to pixels if needed
                let yOffset = 0;
                if (yPos.includes('%')) {
                    const percentage = parseFloat(yPos);
                    yOffset = (section.offsetHeight * percentage) / 100;
                } else {
                    yOffset = parseFloat(yPos);
                }

                initialPositions.set(section, yOffset);
            });

            window.addEventListener('scroll', function() {
                parallaxSections.forEach(section => {
                    // Skip hero section - it has a fixed position
                    if (section.classList.contains('hero-section')) {
                        return;
                    }

                    const scrolled = window.pageYOffset;
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.offsetHeight;

                    // Only apply parallax when section is in view
                    if (scrolled + window.innerHeight > sectionTop && scrolled < sectionTop + sectionHeight) {
                        const parallaxOffset = (scrolled - sectionTop) * 0.5;
                        const initialOffset = initialPositions.get(section) || 0;
                        section.style.backgroundPositionY = (initialOffset + parallaxOffset) + 'px';
                    }
                });
            });
        } else {
            // Remove fixed attachment on mobile for better performance
            parallaxSections.forEach(section => {
                section.style.backgroundAttachment = 'scroll';
            });
        }
    }

    /**
     * Fade in animation for elements
     */
    function initFadeIn() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-visible');
                }
            });
        }, observerOptions);

        const fadeElements = document.querySelectorAll('.card, .program-card, .stat-item');
        fadeElements.forEach(el => {
            el.classList.add('fade-in');
            observer.observe(el);
        });
    }

    /**
     * Add CSS for fade-in effect
     */
    function addFadeInStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .fade-in {
                opacity: 0;
                transform: translateY(20px);
                transition: opacity 0.6s ease, transform 0.6s ease;
            }
            .fade-in-visible {
                opacity: 1;
                transform: translateY(0);
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Handle contact form submission (fallback form)
     */
    function initContactForm() {
        const form = document.querySelector('.prospergenics-contact-form');

        if (form) {
            form.addEventListener('submit', function(e) {
                // Basic client-side validation is handled by HTML5 required attributes
                // This is just for any additional custom handling

                const submitButton = form.querySelector('.submit-button');
                if (submitButton) {
                    submitButton.textContent = 'Sending...';
                    submitButton.disabled = true;
                }
            });
        }
    }

    /**
     * Mobile menu toggle
     */
    function initMobileMenu() {
        const menuToggle = document.querySelector('.menu-toggle');
        const navigation = document.querySelector('.main-navigation');
        const header = document.querySelector('.site-header');

        if (menuToggle && navigation) {
            menuToggle.addEventListener('click', function() {
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !isExpanded);
                navigation.classList.toggle('active');
                if (header) {
                    header.classList.toggle('menu-open');
                }
            });
        }
    }

    /**
     * Header show/hide on scroll
     */
    function initHeaderScroll() {
        const header = document.querySelector('.site-header');
        if (!header) return;

        let lastScrollY = window.scrollY;
        let ticking = false;

        window.addEventListener('scroll', function() {
            if (ticking) return;

            ticking = true;
            requestAnimationFrame(function() {
                const currentScrollY = window.scrollY;
                const scrollingDown = currentScrollY > lastScrollY;

                // Scrolled state (dark transparent background)
                if (currentScrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }

                // Hidden state (slide up with animation)
                if (scrollingDown && currentScrollY > 150) {
                    if (!header.classList.contains('hidden')) {
                        header.classList.remove('showing');
                        // Force animation retrigger
                        void header.offsetWidth;
                        header.classList.add('hidden');
                        console.log('SLIDING UP');
                    }
                } else if (!scrollingDown && currentScrollY > 0) {
                    if (header.classList.contains('hidden')) {
                        header.classList.remove('hidden');
                        // Force animation retrigger
                        void header.offsetWidth;
                        header.classList.add('showing');
                        console.log('SLIDING DOWN');
                    }
                }

                lastScrollY = currentScrollY;
                ticking = false;
            });
        });
    }

    /**
     * Initialize all functions when DOM is ready
     */
    function init() {
        addFadeInStyles();
        initSmoothScroll();
        initParallax();
        initFadeIn();
        initContactForm();
        initMobileMenu();
        initHeaderScroll();

        // Add loaded class to body for CSS animations
        document.body.classList.add('loaded');
    }

    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
