(function () {
    'use strict';

    function initHomeReveals() {
        var page = document.querySelector('.home-page');
        if (!page || !window.IntersectionObserver || !window.matchMedia) return;

        var motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (motion.matches) return;

        var targets = Array.from(page.querySelectorAll('[data-home-reveal]'));
        if (!targets.length) return;

        var observer;
        function reveal(target, instant) {
            if (instant) target.classList.remove('home-reveal-ready');
            target.classList.remove('home-reveal-pending');
            if (observer) observer.unobserve(target);
        }

        function revealAll() {
            targets.forEach(function (target) { reveal(target, true); });
            if (observer) observer.disconnect();
        }

        try {
            observer = new window.IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) reveal(entry.target, false);
                });
            }, { rootMargin: '0px 0px -24px 0px', threshold: 0 });

            page.addEventListener('focusin', function (event) {
                var target = event.target.closest('.home-reveal-pending');
                if (target) reveal(target, true);
            });

            if (motion.addEventListener) {
                motion.addEventListener('change', function (event) {
                    if (event.matches) revealAll();
                });
            }

            targets.forEach(function (target) {
                // Keep first-paint and restored-scroll content immediately visible.
                if (target.getBoundingClientRect().top < window.innerHeight) return;
                target.classList.add('home-reveal-ready', 'home-reveal-pending');
                observer.observe(target);
            });
        } catch {
            // Observer setup failures must never leave homepage content hidden.
            revealAll();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHomeReveals, { once: true });
    } else {
        initHomeReveals();
    }
}());
