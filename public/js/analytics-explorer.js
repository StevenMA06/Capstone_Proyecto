(() => {
    const indicator = document.getElementById('bi-indicator');
    if (!indicator) return;
    const group = document.getElementById('bi-group');
    const type = document.getElementById('bi-chart');
    const measure = document.getElementById('bi-measure');
    const employeeSources = ['contratos', 'ventas', 'pagos', 'instalaciones', 'kardex'];
    function refresh() {
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
    indicator.addEventListener('change', refresh);
    group.addEventListener('change', refresh);
    refresh();
})();
