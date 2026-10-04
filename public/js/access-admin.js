(() => {
    const form = document.querySelector('[data-role-permissions]');
    if (!form) return;
    const boxes = [...form.querySelectorAll('input[name="permissions[]"]')];
    form.querySelectorAll('[data-permission-selection]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', () => boxes.forEach(box => { if (!box.disabled) box.checked = button.dataset.permissionSelection === 'all'; }));
    });
    boxes.forEach(box => box.addEventListener('change', () => {
        const module = box.value.split('.')[0];
        if (box.checked && !box.value.endsWith('.view')) {
            const view = boxes.find(item => item.value === module + '.view');
            if (view) view.checked = true;
        } else if (!box.checked && box.value.endsWith('.view')) {
            boxes.filter(item => item.value.startsWith(module + '.')).forEach(item => { item.checked = false; });
        }
    }));
})();
