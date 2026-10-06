(() => {
    const form = document.querySelector('[data-role-permissions]');
    if (!form) return;
    const boxes = [...form.querySelectorAll('input[name="permissions[]"]')];
    const requires = box => (box.dataset.dependencies || '').split(',').filter(Boolean);
    form.querySelectorAll('[data-permission-selection]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', () => boxes.forEach(box => { if (!box.disabled) box.checked = button.dataset.permissionSelection === 'all'; }));
    });
    boxes.forEach(box => box.addEventListener('change', () => {
        if (box.checked) requires(box).forEach(code => { const dependency = boxes.find(item => item.value === code); if (dependency && !dependency.disabled) dependency.checked = true; });
        else boxes.filter(item => requires(item).includes(box.value)).forEach(item => { if (!item.disabled) item.checked = false; });
    }));
})();
