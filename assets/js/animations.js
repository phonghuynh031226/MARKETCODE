/**
 * ============================================================================
 * MARKETCODE - MODERN SAAS UI/UX ANIMATIONS & MICRO-INTERACTIONS JS
 * Pure Light SaaS Modern Experience
 * ============================================================================
 */

(function () {
    'use strict';

    // 1. Top Loading Bar Controller
    let progressBar = document.getElementById('topProgressBar');
    if (!progressBar) {
        progressBar = document.createElement('div');
        progressBar.id = 'topProgressBar';
        document.body.prepend(progressBar);
    }

    function setTopProgress(percent) {
        if (!progressBar) return;
        progressBar.style.opacity = '1';
        progressBar.style.width = percent + '%';
        if (percent >= 100) {
            setTimeout(() => {
                progressBar.style.opacity = '0';
                setTimeout(() => {
                    progressBar.style.width = '0%';
                }, 400);
            }, 300);
        }
    }

    window.startTopLoading = function () {
        setTopProgress(25);
        setTimeout(() => setTopProgress(65), 150);
    };

    window.finishTopLoading = function () {
        setTopProgress(100);
    };

    // Simulate initial page load progress
    setTopProgress(40);
    window.addEventListener('DOMContentLoaded', () => {
        setTopProgress(85);
    });
    window.addEventListener('load', () => {
        setTopProgress(100);
    });

    // Intercept native fetch for automatic top progress indication
    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
        window.startTopLoading();
        try {
            const response = await originalFetch(...args);
            window.finishTopLoading();
            return response;
        } catch (error) {
            window.finishTopLoading();
            throw error;
        }
    };

    // 2. Animated Number Counter (Using IntersectionObserver)
    function animateValue(element, start, end, duration, prefix = '', suffix = '') {
        const startTime = performance.now();
        const isDecimal = end % 1 !== 0;

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing: easeOutExpo
            const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            const current = start + (end - start) * easeProgress;

            let formatted = '';
            if (isDecimal) {
                formatted = current.toFixed(1);
            } else {
                formatted = Math.floor(current).toLocaleString('vi-VN');
            }

            element.textContent = prefix + formatted + suffix;

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                let finalFormatted = isDecimal ? end.toFixed(1) : end.toLocaleString('vi-VN');
                element.textContent = prefix + finalFormatted + suffix;
            }
        }

        requestAnimationFrame(update);
    }

    function initNumberCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        if (!counters.length) return;

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const rawValue = el.getAttribute('data-counter') || el.textContent;
                    
                    const match = rawValue.match(/^([^0-9]*)([0-9.,]+)(.*)$/);
                    if (match) {
                        const prefix = match[1] || '';
                        let numStr = match[2].replace(/\./g, '').replace(/,/g, '.');
                        const targetNumber = parseFloat(numStr) || 0;
                        const suffix = match[3] || '';

                        animateValue(el, 0, targetNumber, 1500, prefix, suffix);
                    }
                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.15 });

        counters.forEach(c => observer.observe(c));
    }

    // 3. Tab Transition Helper
    window.applyTabTransition = function (tabContentElement) {
        if (!tabContentElement) return;
        tabContentElement.classList.remove('tab-fade-in');
        void tabContentElement.offsetWidth;
        tabContentElement.classList.add('tab-fade-in');
    };

    // 4. Smooth Card Mouse Spotlight (Linear / Apple style)
    function initCardSpotlight() {
        const cards = document.querySelectorAll('.card-spotlight-item, .bento-card-saas');
        cards.forEach(card => {
            let ticking = false;
            card.addEventListener('mousemove', e => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        const rect = card.getBoundingClientRect();
                        const x = e.clientX - rect.left;
                        const y = e.clientY - rect.top;
                        card.style.setProperty('--mouse-x', `${x}px`);
                        card.style.setProperty('--mouse-y', `${y}px`);
                        ticking = false;
                    });
                    ticking = true;
                }
            }, { passive: true });
        });
    }

    // 5. Grid Loading & Transition Dimmer Helper
    window.dimProductGrid = function () {
        const container = document.getElementById('productGridContainer');
        const spinner = document.getElementById('gridLoadingSpinner');
        if (container) {
            container.classList.add('grid-loading');
        }
        if (spinner) {
            spinner.classList.remove('hidden');
        }
        if (window.startTopLoading) {
            window.startTopLoading();
        }
    };

    // 6. Scroll Reveal (AOS Initialization & Fallback)
    document.addEventListener('DOMContentLoaded', () => {
        initNumberCounters();
        initCardSpotlight();

        // Initialize AOS if available
        if (typeof AOS !== 'undefined') {
            AOS.init({
                once: true,
                duration: 650,
                easing: 'ease-out-cubic',
                offset: 50,
                delay: 50
            });
        } else {
            const aosElements = document.querySelectorAll('[data-aos]');
            const aosObserver = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0) scale(1)';
                        obs.unobserve(el);
                    }
                });
            }, { threshold: 0.08 });

            aosElements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(24px)';
                el.style.transition = 'opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
                aosObserver.observe(el);
            });
        }
    });

})();
