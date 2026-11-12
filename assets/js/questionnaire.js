(function () {
    'use strict';

    const form = document.getElementById('advisor-form');
    if (!form) return;

    const steps = Array.from(form.querySelectorAll('[data-step]'));
    const indicators = Array.from(document.querySelectorAll('[data-step-indicator]'));
    const progressFill = document.querySelector('[data-progress-fill]');
    const progressLabel = document.querySelector('[data-progress-label]');
    const navHint = document.querySelector('[data-nav-hint]');
    const prevButton = form.querySelector('[data-prev]');
    const nextButton = form.querySelector('[data-next]');
    const submitButton = form.querySelector('[data-submit]');
    const customBudget = form.querySelector('#custom-budget');
    const maxStep = steps.length;
    let current = Number(form.dataset.errorStep || 1);
    if (!Number.isFinite(current) || current < 1 || current > maxStep) current = 1;
    let submitting = false;

    form.noValidate = true;

    const scrollToCard = () => {
        const card = steps[current - 1];
        if (!card) return;
        const top = card.getBoundingClientRect().top + window.scrollY - 96;
        window.scrollTo({ top: Math.max(0, top), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    };

    const updateProgress = () => {
        if (progressFill) progressFill.style.width = `${(current / maxStep) * 100}%`;
        if (progressLabel) progressLabel.textContent = `Step ${current} of ${maxStep}`;
        if (navHint) navHint.textContent = `Step ${current} of ${maxStep}`;
        indicators.forEach((item) => {
            const step = Number(item.dataset.stepIndicator);
            item.classList.toggle('is-active', step === current);
            item.classList.toggle('is-complete', step < current);
            if (step === current) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });
        if (prevButton) prevButton.hidden = current === 1;
        if (nextButton) nextButton.hidden = current === maxStep;
        if (submitButton) submitButton.hidden = current !== maxStep;
    };

    const showStep = (step, options = {}) => {
        current = Math.min(maxStep, Math.max(1, step));
        steps.forEach((panel, index) => {
            panel.hidden = index + 1 !== current;
        });
        updateProgress();
        if (options.focus !== false) {
            const heading = steps[current - 1]?.querySelector('h2');
            window.setTimeout(() => heading?.focus({ preventScroll: true }), 30);
        }
        if (options.scroll) scrollToCard();
    };

    const stepError = (step) => steps[step - 1]?.querySelector('[data-step-error]');

    const showError = (step, message) => {
        const error = stepError(step);
        if (!error) return;
        if (message) error.textContent = message;
        error.hidden = false;
        steps[step - 1]?.classList.add('is-invalid');
    };

    const clearError = (step) => {
        const error = stepError(step);
        if (error) error.hidden = true;
        steps[step - 1]?.classList.remove('is-invalid');
    };

    const validateStep = (step, options = {}) => {
        if (step === 1) {
            if (!form.querySelector('input[name="activity_id"]:checked')) {
                if (!options.silent) showError(1, 'Choose one main activity to continue.');
                return false;
            }
        }
        if (step === 2) {
            if (!form.querySelector('input[name="workloads[]"]:checked')) {
                if (!options.silent) showError(2, 'Select at least one intended use to continue.');
                return false;
            }
        }
        if (step === 3) {
            if (!form.querySelector('input[name="category"]:checked')) {
                if (!options.silent) showError(3, 'Choose a computer type to continue.');
                return false;
            }
        }
        if (step === 4) {
            const custom = customBudget?.value.trim() || '';
            if (custom !== '') {
                const value = Number(custom);
                if (!Number.isFinite(value) || value < 300 || value > 20000) {
                    if (!options.silent) showError(4, 'Enter a maximum budget between 300 and 20,000 TND.');
                    return false;
                }
            } else if (!form.querySelector('input[name="budget_range"]:checked')) {
                if (!options.silent) showError(4, 'Choose a budget range or enter an exact maximum budget.');
                return false;
            }
        }
        clearError(step);
        return true;
    };

    const updateSummary = () => {
        const activity = form.querySelector('input[name="activity_id"]:checked');
        const workloads = form.querySelectorAll('input[name="workloads[]"]:checked');
        const category = form.querySelector('input[name="category"]:checked');
        const budget = form.querySelector('input[name="budget_range"]:checked');
        const custom = customBudget?.value.trim() || '';

        const summaryActivity = document.querySelector('[data-summary-activity]');
        const summaryWorkloads = document.querySelector('[data-summary-workloads]');
        const summaryCategory = document.querySelector('[data-summary-category]');
        const summaryBudget = document.querySelector('[data-summary-budget]');

        if (summaryActivity) summaryActivity.textContent = activity?.dataset.activityName || 'Not selected';
        if (summaryWorkloads) summaryWorkloads.textContent = `${workloads.length} selected`;
        if (summaryCategory) summaryCategory.textContent = category?.dataset.categoryName || 'Not selected';
        if (summaryBudget) {
            if (custom !== '') {
                const value = Number(custom);
                summaryBudget.textContent = Number.isFinite(value) && value > 0
                    ? `Up to ${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value)} TND`
                    : 'Not selected';
            } else {
                summaryBudget.textContent = budget?.dataset.budgetLabel || 'Not selected';
            }
        }
    };

    form.addEventListener('change', (event) => {
        const panel = event.target.closest('[data-step]');
        if (panel) clearError(Number(panel.dataset.step));
        if (event.target.name === 'budget_range' && event.target.checked && customBudget) {
            customBudget.value = '';
        }
        updateSummary();
    });

    customBudget?.addEventListener('input', () => {
        if (customBudget.value.trim() !== '') {
            form.querySelectorAll('input[name="budget_range"]').forEach((radio) => { radio.checked = false; });
        }
        clearError(4);
        updateSummary();
    });

    nextButton?.addEventListener('click', () => {
        if (!validateStep(current)) return;
        showStep(current + 1, { scroll: true });
    });

    prevButton?.addEventListener('click', () => {
        showStep(current - 1, { scroll: true });
    });

    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.target.tagName === 'TEXTAREA') return;
        if (current < maxStep) {
            event.preventDefault();
            if (validateStep(current)) showStep(current + 1, { scroll: true });
        }
    });

    form.addEventListener('submit', (event) => {
        if (submitting) return;
        for (let step = 1; step <= maxStep; step += 1) {
            if (!validateStep(step, { silent: true })) {
                event.preventDefault();
                showStep(step);
                showError(step);
                return;
            }
        }

        submitting = true;
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.innerHTML = '<span class="submit-spinner" aria-hidden="true"></span> Comparing the catalogue…';
        }
    });

    showStep(current, { focus: false });
    updateSummary();
})();
