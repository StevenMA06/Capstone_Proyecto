document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('.sidebar-disclosure');
    if (!menu) return;

    const mobile = window.matchMedia('(max-width: 680px)');
    const syncLayout = () => {
        menu.open = !mobile.matches;
    };

    syncLayout();
    mobile.addEventListener('change', syncLayout);
});
