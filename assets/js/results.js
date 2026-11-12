(function () {
    'use strict';

    const checks = Array.from(document.querySelectorAll('[data-compare]'));
    if (!checks.length) return;

    const dock = document.querySelector('[data-compare-dock]');
    const countLabel = document.querySelector('[data-compare-count]');
    const chips = document.querySelector('[data-compare-chips]');
    const button = document.querySelector('[data-compare-button]');
    const max = 3;
    const token = new URLSearchParams(window.location.search).get('token') || '';

    const selected = () => checks.filter((checkbox) => checkbox.checked);

    const compareUrl = () => {
        const url = new URL('compare.php', window.location.href);
        if (token) url.searchParams.set('token', token);
        url.searchParams.set('ids', selected().map((checkbox) => checkbox.dataset.id).join(','));
        return url.toString();
    };

    const render = () => {
        const chosen = selected();
        if (countLabel) countLabel.textContent = String(chosen.length);
        if (dock) dock.hidden = chosen.length === 0;

        checks.forEach((checkbox) => {
            checkbox.disabled = chosen.length >= max && !checkbox.checked;
            checkbox.closest('.compare-check')?.classList.toggle('is-selected', checkbox.checked);
        });

        if (chips) {
            chips.replaceChildren();
            chosen.forEach((checkbox) => {
                const chip = document.createElement('span');
                chip.className = 'compare-chip';
                chip.textContent = checkbox.dataset.name || 'Selected PC';
                chip.title = checkbox.dataset.name || '';
                chips.appendChild(chip);
            });
        }

        if (button) {
            const enabled = chosen.length >= 2;
            button.href = enabled ? compareUrl() : '#';
            button.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            button.classList.toggle('is-disabled', !enabled);
        }
    };

    checks.forEach((checkbox) => checkbox.addEventListener('change', render));

    button?.addEventListener('click', (event) => {
        if (selected().length < 2) {
            event.preventDefault();
        }
    });

    render();
})();
