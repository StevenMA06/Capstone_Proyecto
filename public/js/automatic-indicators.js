(() => {
    const inactivityMs = 120000;
    const now = () => Date.now();
    const uuid = () => crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.floor(Math.random() * 16); return (c === 'x' ? r : (r & 3) | 8).toString(16); });
    const validInteraction = field => field && (field.tagName === 'SELECT' ? Boolean(field.value) : String(field.value ?? '').trim() !== '');

    const stateFor = form => {
        const get = name => form.querySelector(`[name="${name}"]`);
        const token = get('indicador_token');
        const start = get('indicador_inicio');
        const last = get('indicador_ultima_interaccion');
        const active = get('indicador_activo_segundos');
        const discarded = get('indicador_pausa_descartada_segundos');
        const context = get('indicador_context');
        return { token, start, last, active, discarded, context };
    };
    const ensure = form => {
        let state = stateFor(form);
        if (state.token) return state;
        ['indicador_token', 'indicador_inicio', 'indicador_ultima_interaccion', 'indicador_activo_segundos', 'indicador_pausa_descartada_segundos', 'indicador_context'].forEach(name => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = name; form.append(input);
        });
        return stateFor(form);
    };
    const hasState = form => {
        const state = stateFor(form);
        return Boolean(state.token?.value) && (!state.context || state.context.value === (form.dataset.autoContext || ''));
    };
    const start = form => {
        const state = ensure(form);
        if (hasState(form)) return;
        const instant = now();
        if (state.context) state.context.value = form.dataset.autoContext || '';
        state.token.value = uuid(); state.start.value = new Date(instant).toISOString();
        state.last.value = new Date(instant).toISOString(); state.active.value = '0'; state.discarded.value = '0';
    };
    const touch = form => {
        const state = stateFor(form);
        if (!state.token?.value) return;
        const instant = now(); const previous = Date.parse(state.last.value);
        const elapsed = Number.isFinite(previous) ? Math.max(0, instant - previous) : 0;
        // Conserva milisegundos entre interacciones. Redondear cada tramo hacía
        // desaparecer acciones cortas; el servidor convierte a segundos una sola vez
        // cuando la operación termina.
        state.active.value = String(Number(state.active.value || 0) + Math.min(elapsed, inactivityMs) / 1000);
        state.discarded.value = String(Number(state.discarded.value || 0) + Math.max(0, elapsed - inactivityMs) / 1000);
        state.last.value = new Date(instant).toISOString();
    };
    const bind = form => {
        const fields = (form.dataset.autoStartFields || '').split(',').map(selector => selector.trim()).filter(Boolean).map(selector => form.querySelector(selector)).filter(Boolean);
        const handle = event => {
            if (!validInteraction(event.currentTarget)) return;
            if (!hasState(form)) start(form); else touch(form);
        };
        fields.forEach(field => ['input', 'change'].forEach(name => field.addEventListener(name, handle)));
        const trackInteraction = () => {
            if (!hasState(form) && fields.length === 0) start(form);
            touch(form);
        };
        form.addEventListener('input', trackInteraction);
        form.addEventListener('change', trackInteraction);
        form.addEventListener('submit', trackInteraction);
    };
    document.querySelectorAll('form[data-auto-indicator]').forEach(bind);
    document.querySelectorAll('[data-auto-start-trigger]').forEach(trigger => {
        const startWhenOpen = () => {
            if (!trigger.open) return;
            const form = trigger.querySelector('form[data-auto-indicator]');
            if (form && !hasState(form)) start(form);
        };
        trigger.addEventListener('toggle', startWhenOpen);
        startWhenOpen();
    });
})();
