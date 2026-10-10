(() => {
    const form = document.querySelector('[data-role-permissions]');
    if (!form) return;
    const boxes = [...form.querySelectorAll('input[name="permissions[]"]')];
    const modules = [...form.querySelectorAll('[data-permission-module]')];
    const search = form.querySelector('#permission-search');
    const moduleFilter = form.querySelector('#permission-module');
    const state = form.querySelector('#permission-state');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const requires = box => (box.dataset.dependencies || '').split(',').filter(Boolean);
    const editable = box => !box.matches(':disabled');
    const byCode = new Map(boxes.map(box => [box.value, box]));
    const setPermission = (box, checked, visited = new Set()) => {
        if (!box || !editable(box) || visited.has(box.value)) return;
        visited.add(box.value);
        box.checked = checked;
        if (checked) requires(box).forEach(code => setPermission(byCode.get(code), true, visited));
        else boxes.filter(item => requires(item).includes(box.value)).forEach(item => setPermission(item, false, visited));
    };
    const refresh = () => {
        const query = normalize(search.value.trim()).split(/\s+/).filter(Boolean);
        let visible = 0, visibleModules = 0;
        modules.forEach(section => {
            const rows = [...section.querySelectorAll('[data-permission-option]')];
            let matches = 0;
            rows.forEach(row => {
                const box = row.querySelector('input');
                const text = normalize(section.dataset.permissionModule + ' ' + row.textContent + ' ' + box.value);
                const shown = (!moduleFilter.value || moduleFilter.value === section.dataset.permissionModule)
                    && query.every(word => text.includes(word))
                    && (!state.value || (state.value === 'selected' ? box.checked : !box.checked));
                row.hidden = !shown;
                if (shown) matches++;
            });
            section.hidden = matches === 0;
            if (matches) visibleModules++;
            visible += matches;
            section.querySelector('[data-module-count]').textContent =
                rows.filter(row => row.querySelector('input').checked).length + ' de ' + rows.length + ' seleccionados';
            const button = section.querySelector('[data-module-selection]');
            const candidates = rows.filter(row => !row.hidden).map(row => row.querySelector('input')).filter(editable);
            button.disabled = !candidates.length;
            button.textContent = candidates.length && candidates.every(box => box.checked)
                ? 'Deseleccionar resultados de este módulo' : 'Seleccionar resultados de este módulo';
            if (query.length || moduleFilter.value || state.value) section.open = true;
        });
        form.querySelector('[data-permission-total]').textContent = boxes.filter(box => box.checked).length + ' de ' + boxes.length + ' seleccionados';
        form.querySelector('[data-permission-results]').textContent = visible + ' permisos en ' + visibleModules + ' módulos';
        form.querySelector('[data-permission-empty]').hidden = visible > 0;
        form.querySelectorAll('[data-permission-selection]').forEach(button => {
            button.disabled = !boxes.some(box => editable(box) && !box.closest('[data-permission-option]').hidden);
        });
    };
    if (!search || !moduleFilter || !state) return;
    form.querySelector('[data-permission-toolbar]').hidden = false;
    form.querySelectorAll('[data-permission-selection], [data-module-selection]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', () => {
            const scope = button.closest('[data-permission-module]') || form;
            const candidates = [...scope.querySelectorAll('input[name="permissions[]"]')]
                .filter(box => editable(box) && !box.closest('[data-permission-option]').hidden);
            const checked = button.hasAttribute('data-module-selection')
                ? !candidates.every(box => box.checked) : button.dataset.permissionSelection === 'all';
            candidates.forEach(box => setPermission(box, checked));
            refresh();
        });
    });
    boxes.forEach(box => box.addEventListener('change', () => { setPermission(box, box.checked); refresh(); }));
    search.addEventListener('input', refresh);
    moduleFilter.addEventListener('change', refresh);
    state.addEventListener('change', refresh);
    form.querySelector('[data-clear-permission-filters]').addEventListener('click', () => {
        search.value = ''; moduleFilter.value = ''; state.value = ''; refresh(); search.focus();
    });
    refresh();
})();
