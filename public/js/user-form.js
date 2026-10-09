(() => {
    const employee = document.getElementById('empleado_id');
    const email = document.getElementById('correo');
    if (employee?.tagName === 'SELECT' && email) {
        const useEmployeeEmail = () => {
            if (!employee.value) return;
            email.value = employee.selectedOptions[0]?.dataset.email || '';
            email.dispatchEvent(new Event('input', { bubbles: true }));
        };
        employee.addEventListener('change', useEmployeeEmail);
        if (!email.value.trim()) useEmployeeEmail();
    }

    document.querySelectorAll('[data-password-toggle]').forEach(toggle => {
        const password = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!password) return;
        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            const show = password.type === 'password';
            password.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(show));
            toggle.setAttribute('aria-label', show ? toggle.dataset.hideLabel : toggle.dataset.showLabel);
        });
    });
})();
