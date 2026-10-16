(() => {
    const employeeSources = ['contratos', 'ventas', 'pagos', 'instalaciones', 'kardex'];

    function bindExplorer() {
        const panel = document.querySelector('[aria-labelledby="explorer-title"]');
        if (!panel) return;

        const form = panel.querySelector('form');
        const indicator = panel.querySelector('#bi-indicator');
        const group = panel.querySelector('#bi-group');
        const type = panel.querySelector('#bi-chart');
        const measure = panel.querySelector('#bi-measure');
        if (!form || !indicator || !group || !type || !measure) return;

        function refreshOptions() {
            const money = ['ventas', 'pagos'].includes(indicator.value);
            measure.querySelector('option[value="monto"]').disabled = !money;
            if (!money) measure.value = 'cantidad';

            const employee = employeeSources.includes(indicator.value);
            group.querySelector('option[value="empleado"]').disabled = !employee;
            if (!employee && group.value === 'empleado') group.value = 'mes';

            const temporal = ['dia', 'semana', 'mes'].includes(group.value);
            ['line', 'area'].forEach(value => { type.querySelector(`option[value="${value}"]`).disabled = !temporal; });
            if (!temporal && ['line', 'area'].includes(type.value)) type.value = 'bar';
        }

        indicator.addEventListener('change', refreshOptions);
        group.addEventListener('change', refreshOptions);
        refreshOptions();

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.loading === 'true') return;

            form.dataset.loading = 'true';
            const button = form.querySelector('button');
            const previousLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Actualizando…';

            try {
                const query = new URLSearchParams(new FormData(form));
                const response = await fetch(`${form.action}?${query.toString()}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error('No se pudo actualizar el gráfico.');

                const html = await response.text();
                const nextPanel = new DOMParser().parseFromString(html, 'text/html')
                    .querySelector('[aria-labelledby="explorer-title"]');
                if (!nextPanel) throw new Error('No se encontró el gráfico actualizado.');

                panel.replaceWith(nextPanel);
                bindExplorer();
            } catch (error) {
                let message = panel.querySelector('.an-error');
                if (!message) {
                    message = document.createElement('p');
                    message.className = 'an-error';
                    message.setAttribute('role', 'alert');
                    form.insertAdjacentElement('afterend', message);
                }
                message.textContent = 'No se pudo actualizar el gráfico. Inténtalo nuevamente.';
                button.disabled = false;
                button.textContent = previousLabel;
                form.dataset.loading = 'false';
            }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindExplorer);
    else bindExplorer();
})();

