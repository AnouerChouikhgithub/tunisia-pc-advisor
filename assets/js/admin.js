(function () {
    'use strict';

    const body = document.body;
    const toggle = document.querySelector('[data-admin-sidebar-toggle]');
    toggle?.addEventListener('click', () => {
        body.classList.toggle('sidebar-open');
        toggle.setAttribute('aria-expanded', body.classList.contains('sidebar-open') ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        if (!body.classList.contains('sidebar-open')) return;
        if (event.target.closest('.admin-sidebar') || event.target.closest('[data-admin-sidebar-toggle]')) return;
        body.classList.remove('sidebar-open');
        toggle?.setAttribute('aria-expanded', 'false');
    });

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirm || 'Are you sure?')) {
                event.preventDefault();
            }
        });
    });

    const category = document.querySelector('#product_category');
    const laptopOnly = document.querySelectorAll('[data-laptop-only]');
    const syncCategory = () => {
        const isLaptop = !category || category.value === 'laptop';
        laptopOnly.forEach((element) => {
            element.hidden = !isLaptop;
            element.querySelectorAll('input, select').forEach((input) => {
                if (isLaptop && input.dataset.requiredForLaptop === '1') {
                    input.required = true;
                } else {
                    input.required = false;
                }
            });
        });
    };
    category?.addEventListener('change', syncCategory);
    syncCategory();

    const imageUrl = document.querySelector('#image_url');
    const preview = document.querySelector('[data-image-preview]');
    const syncPreview = () => {
        if (!preview) return;
        const url = imageUrl?.value.trim();
        preview.replaceChildren();
        if (url) {
            const img = document.createElement('img');
            img.src = url;
            img.alt = 'Product image preview';
            img.addEventListener('error', () => {
                preview.replaceChildren();
                const span = document.createElement('span');
                span.textContent = 'Image could not be loaded';
                preview.appendChild(span);
            });
            preview.appendChild(img);
        } else {
            const span = document.createElement('span');
            span.textContent = 'No image URL';
            preview.appendChild(span);
        }
    };
    imageUrl?.addEventListener('input', syncPreview);
    syncPreview();

    document.querySelectorAll('[data-flash]').forEach((toast) => {
        window.setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
            window.setTimeout(() => toast.remove(), 200);
        }, 7000);
    });
})();
