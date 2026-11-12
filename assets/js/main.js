(function () {
    'use strict';

    const initIcons = () => {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({
                attrs: {
                    'stroke-width': 1.8,
                    'aria-hidden': 'true'
                }
            });
        }
    };

    const initHeader = () => {
        const header = document.querySelector('[data-header]');
        const toggle = document.querySelector('[data-nav-toggle]');
        if (!header) return;

        const syncHeader = () => {
            header.classList.toggle('scrolled', window.scrollY > 12);
        };
        syncHeader();
        window.addEventListener('scroll', syncHeader, { passive: true });

        if (!toggle) return;
        const close = () => {
            header.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        };
        toggle.addEventListener('click', () => {
            const open = !header.classList.contains('is-open');
            header.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        header.querySelectorAll('.site-nav a').forEach((link) => link.addEventListener('click', close));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
        });
        document.addEventListener('click', (event) => {
            if (!header.contains(event.target)) close();
        });
    };

    const initToasts = () => {
        document.querySelectorAll('[data-toast]').forEach((toast) => {
            const dismiss = () => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-6px)';
                window.setTimeout(() => toast.remove(), 220);
            };
            toast.querySelector('[data-toast-close]')?.addEventListener('click', dismiss);
            window.setTimeout(dismiss, 8000);
        });
    };

    const initReveal = () => {
        const items = document.querySelectorAll('[data-reveal]');
        if (!items.length) return;
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            items.forEach((item) => item.classList.add('is-visible'));
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -40px 0px', threshold: 0.08 });
        items.forEach((item) => observer.observe(item));
    };

    const initImageFallbacks = () => {
        document.querySelectorAll('img[data-fallback]').forEach((image) => {
            image.addEventListener('error', () => {
                if (image.dataset.fallbackApplied) return;
                image.dataset.fallbackApplied = '1';
                image.src = image.dataset.fallback;
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initIcons();
        initHeader();
        initToasts();
        initReveal();
        initImageFallbacks();
    });
})();
