document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.inventory-management');
    if (!root) return;
    root.querySelectorAll('.form-group').forEach(group => {
        const field = group.querySelector('input, select, textarea');
        if (!field) return;
        let error = group.querySelector('.error-message');
        if (!error) {
            error = document.createElement('span');
            error.className = 'error-message';
            error.hidden = true;
            group.append(error);
        }
        error.id = `${field.id}-error`;
        field.setAttribute('aria-describedby', error.id);
        if (error.textContent.trim()) field.setAttribute('aria-invalid', 'true');
        field.addEventListener('invalid', () => {
            error.textContent = field.validationMessage;
            error.hidden = false;
            field.setAttribute('aria-invalid', 'true');
        });
        field.addEventListener('input', () => {
            error.hidden = true;
            field.removeAttribute('aria-invalid');
        });
        if (field.type === 'file') field.addEventListener('change', () => {
            const file = field.files[0];
            const message = file && (!/\.(xlsx|xls)$/i.test(file.name) || file.size > 2 * 1024 * 1024)
                ? 'Selecciona un archivo Excel (.xlsx o .xls) de hasta 2 MB.' : '';
            field.setCustomValidity(message);
            if (message) field.reportValidity();
        });
    });
    root.querySelectorAll('.table-scroll').forEach(table => {
        table.tabIndex = 0;
        table.setAttribute('role', 'region');
        table.setAttribute('aria-label', 'Tabla de inventario; desplázate para ver todas las columnas');
    });
    const from = root.querySelector('#fecha_desde');
    const to = root.querySelector('#fecha_hasta');
    if (from && to) {
        const sync = () => { to.min = from.value; };
        from.addEventListener('change', sync);
        sync();
    }
});
