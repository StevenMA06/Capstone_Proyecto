const scrollCoordinationTarget = target => {
    requestAnimationFrame(() => requestAnimationFrame(() => {
        const header = document.querySelector('.workspace-topbar');
        const headerBottom = header && ['fixed', 'sticky'].includes(getComputedStyle(header).position)
            ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
        const top = Math.max(0, window.scrollY + target.getBoundingClientRect().top - headerBottom - 20);
        window.scrollTo({ top, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }));
};

const revealCoordinationAnchor = () => {
    if (!location.hash) return;
    let target;
    try { target = document.getElementById(decodeURIComponent(location.hash.slice(1))); } catch (_) { return; }
    if (!target?.closest('.order-card')) return;
    const panels = [];
    for (let panel = target.closest('details'); panel; panel = panel.parentElement?.closest('details')) panels.push(panel);
    panels.reverse().forEach(panel => { panel.open = true; });
    scrollCoordinationTarget(target);
};
window.addEventListener('hashchange', revealCoordinationAnchor);
window.addEventListener('popstate', revealCoordinationAnchor);
document.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;
    let target;
    try { target = document.getElementById(decodeURIComponent(link.hash.slice(1))); } catch (_) { return; }
    if (!target?.closest('.order-card')) return;
    event.preventDefault();
    if (location.hash !== link.hash) history.pushState(null, '', link.hash);
    revealCoordinationAnchor();
});
if (location.hash) revealCoordinationAnchor();
if (document.readyState !== 'complete') window.addEventListener('load', revealCoordinationAnchor, { once: true });

document.querySelectorAll('[data-coordination-form]').forEach(form => {
    const day = form.querySelector('[data-visit-date]');
    const windowSelect = form.querySelector('[data-visit-window]');
    const timeSelect = form.querySelector('[data-visit-time]');
    const channelSelect = form.querySelector('[data-visit-channel]');
    const review = form.querySelector('[data-visit-review]');
    const summaryDate = form.querySelector('[data-summary-date]');
    const summaryWindow = form.querySelector('[data-summary-window]');
    const summaryTime = form.querySelector('[data-summary-time]');
    const summaryChannel = form.querySelector('[data-summary-channel]');

    const refresh = () => {
        const window = windowSelect.value;
        for (const option of timeSelect.querySelectorAll('option[data-window]')) {
            const available = option.dataset.window === window;
            option.hidden = !available;
            option.disabled = !available;
        }
        if (timeSelect.selectedOptions[0]?.disabled) timeSelect.value = '';
        timeSelect.options[0].textContent = window ? 'Selecciona la hora de inicio' : 'Primero selecciona la franja';

        const date = day.value ? new Date(`${day.value}T12:00:00`) : null;
        summaryDate.textContent = date && !Number.isNaN(date.getTime())
            ? new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'long', year: 'numeric' }).format(date)
            : 'Por seleccionar';
        summaryWindow.textContent = window || 'Por seleccionar';
        summaryTime.textContent = timeSelect.value || 'Por seleccionar';
        summaryChannel.textContent = channelSelect.value
            ? channelSelect.selectedOptions[0].textContent : 'Por seleccionar';
        review.classList.toggle('is-complete', Boolean(day.value && window && timeSelect.value && channelSelect.value));
    };

    day.addEventListener('change', refresh);
    windowSelect.addEventListener('change', refresh);
    timeSelect.addEventListener('change', refresh);
    channelSelect.addEventListener('change', refresh);
    refresh();
});
