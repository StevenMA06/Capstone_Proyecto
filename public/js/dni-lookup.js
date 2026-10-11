(() => {
    document.querySelectorAll('[data-dni-lookup]').forEach(widget => {
        const form = widget.closest('form');
        const number = form?.querySelector('[name="numero_documento"]');
        const type = form?.querySelector('[name="tipo_documento"]');
        const button = widget.querySelector('[data-dni-query]');
        const message = widget.querySelector('[data-dni-message]');
        if (!form || !number || !type || !button || !message) return;
        let pending = null;
        let revision = 0;
        const say = (text, error = false) => {
            message.textContent = text;
            message.dataset.error = String(error);
        };
        const reset = () => {
            revision++;
            pending?.abort();
            pending = null;
            button.disabled = false;
            button.textContent = 'Consultar DNI';
            button.hidden = type.value !== 'DNI';
            say('');
        };
        number.addEventListener('input', reset);
        type.addEventListener('change', reset);
        reset();
        button.addEventListener('click', async () => {
            const dni = number.value.trim();
            if (type.value !== 'DNI' || !/^[0-9]{8}$/.test(dni)) {
                say('El DNI debe contener exactamente 8 dígitos numéricos.', true);
                number.focus();
                return;
            }
            pending?.abort();
            const controller = new AbortController();
            pending = controller;
            const current = ++revision;
            const targets = [widget.dataset.nameField, 'apellidos', 'direccion']
                .map(name => form.querySelector('[name="' + name + '"]')).filter(Boolean);
            const before = new Map(targets.map(input => [input, input.value]));
            button.disabled = true;
            button.textContent = 'Consultando…';
            say('Consultando datos del DNI…');
            try {
                const response = await fetch(widget.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                    body: JSON.stringify({ dni }),
                    signal: controller.signal,
                });
                const payload = await response.json();
                if (current !== revision || number.value.trim() !== dni || type.value !== 'DNI') return;
                if (!response.ok) {
                    say(payload.errors?.dni?.[0] || payload.message || 'No se pudo consultar el DNI. Puedes completar los datos manualmente.', true);
                    return;
                }
                const data = payload.data;
                if (!data || data.dni !== dni || typeof data.nombres !== 'string' || typeof data.apellidos !== 'string') {
                    say('La consulta no devolvió datos válidos. Puedes completar los datos manualmente.', true);
                    return;
                }
                let changed = false, addressSuggested = false, preserved = false;
                const values = { [widget.dataset.nameField]: data.nombres, apellidos: data.apellidos };
                if (typeof data.direccion === 'string' && data.direccion.trim()) values.direccion = data.direccion;
                Object.entries(values).forEach(([name, value]) => {
                    const input = form.querySelector('[name="' + name + '"]');
                    if (!input || !value) return;
                    if (input.value !== before.get(input)) { preserved = true; return; }
                    // Una dirección existente puede ser el lugar de instalación: se conserva.
                    if (name === 'direccion' && input.value.trim()) { preserved = true; return; }
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    changed = true;
                    if (name === 'direccion') addressSuggested = true;
                });
                say((changed ? 'Datos encontrados. Revisa los nombres y apellidos antes de guardar.' : 'Consulta completada.')
                    + (addressSuggested ? ' Confirma la dirección sugerida: puede no ser el lugar de instalación.' : '')
                    + (preserved ? ' Se conservaron los campos que ya habías completado o modificado durante la consulta.' : ''));
            } catch (error) {
                if (error.name !== 'AbortError' && current === revision) say('No se pudo consultar el DNI. Puedes completar los datos manualmente.', true);
            } finally {
                if (current === revision) {
                    pending = null;
                    button.disabled = false;
                    button.textContent = 'Consultar DNI';
                }
            }
        });
    });
})();
