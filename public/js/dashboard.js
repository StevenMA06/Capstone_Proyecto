document.querySelectorAll('[data-inventory-open]').forEach((trigger) => {
    const dialog = document.getElementById(trigger.dataset.inventoryOpen);
    if (!dialog) return;
    trigger.addEventListener('click', () => dialog.showModal());
    dialog.addEventListener('close', () => trigger.focus());
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right ||
            event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
});
