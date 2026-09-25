document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('sales-filters');
    if (!form) return;

    const states = [...document.querySelectorAll('input[name="estados[]"]')];
    const search = document.querySelector('[data-sales-search]');
    let searchTimer;

    document.querySelectorAll('[data-sales-filter]').forEach(input => {
        input.addEventListener('change', () => {
            if (input.name === 'estados[]' && !states.some(state => state.checked)) {
                input.checked = true;
                return;
            }
            form.requestSubmit();
        });
    });

    search?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => form.requestSubmit(), 500);
    });

    document.querySelectorAll('[data-sales-state]').forEach(slice => {
        const toggle = () => {
            const input = states.find(state => state.value === slice.dataset.salesState);
            if (!input) return;
            input.checked = !input.checked;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        slice.addEventListener('click', toggle);
        slice.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggle();
            }
        });
    });
});
