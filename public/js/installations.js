(() => {
    const scrollTargetIntoView = target => {
        requestAnimationFrame(() => requestAnimationFrame(() => {
            const header = document.querySelector('.workspace-topbar');
            const headerBottom = header && ['fixed', 'sticky'].includes(getComputedStyle(header).position)
                ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
            const gap = 20;
            const top = Math.max(0, window.scrollY + target.getBoundingClientRect().top - headerBottom - gap);
            window.scrollTo({ top, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        }));
    };
    const revealOrderAnchor = () => {
        if (!location.hash) return;
        let target;
        try { target = document.getElementById(decodeURIComponent(location.hash.slice(1))); } catch (_) { return; }
        if (!target?.closest('.order-card')) return;
        const panels = [];
        for (let panel = target.closest('details'); panel; panel = panel.parentElement?.closest('details')) panels.push(panel);
        panels.reverse().forEach(panel => { panel.open = true; });
        const destination = target.id.startsWith('asignar-tecnico-')
            ? target.querySelector('select[name="tecnico_id"]') || target.querySelector('summary')
            : target.id.startsWith('solicitar-recursos-')
                ? target.querySelector('.order-resource-request-form select[name="equipo_id"]')
                    || target.querySelector('.order-resource-request-form select[name^="materials"]')
                    || target.querySelector('.order-next')
                    || target.querySelector('summary')
                : target;
        scrollTargetIntoView(destination);
    };
    window.addEventListener('hashchange', revealOrderAnchor);
    window.addEventListener('popstate', revealOrderAnchor);
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href^="#"]');
        if (!link) return;
        let target;
        try { target = document.getElementById(decodeURIComponent(link.hash.slice(1))); } catch (_) { return; }
        if (!target?.closest('.order-card')) return;
        event.preventDefault();
        if (location.hash !== link.hash) history.pushState(null, '', link.hash);
        revealOrderAnchor();
    });
    if (location.hash) revealOrderAnchor();
    if (document.readyState !== 'complete') window.addEventListener('load', revealOrderAnchor, { once: true });

    document.querySelectorAll('.order-assignment-form').forEach(form => {
        const primary = form.querySelector('select[name="tecnico_id"]');
        const secondary = form.querySelector('select[name="tecnico_secundario_id"]');
        const visitDate = form.querySelector('input[name="fecha_programada"]');
        if (!primary || !secondary) return;
        const updateSecondaryOptions = () => {
            if (primary.value && secondary.value === primary.value) secondary.value = '';
            secondary.querySelectorAll('option[value]:not([value=""])').forEach(option => {
                const isPrimary = primary.value !== '' && option.value === primary.value;
                option.hidden = isPrimary;
                option.disabled = isPrimary;
            });
        };
        primary.addEventListener('change', updateSecondaryOptions);
        updateSecondaryOptions();
        if (!visitDate) return;
        let requestNumber = 0;
        const setChoices = (technicians, message) => {
            const selectedPrimary = primary.value;
            const selectedSecondary = secondary.value;
            const choices = technicians.map(technician => ({ value: String(technician.id), text: technician.name }));
            primary.replaceChildren(new Option(message, ''), ...choices.map(item => new Option(item.text, item.value)));
            secondary.replaceChildren(new Option('Técnico de apoyo', ''), ...choices.map(item => new Option(item.text, item.value)));
            primary.disabled = choices.length === 0;
            secondary.disabled = choices.length === 0;
            if (choices.some(item => item.value === selectedPrimary)) primary.value = selectedPrimary;
            if (choices.some(item => item.value === selectedSecondary)) secondary.value = selectedSecondary;
            updateSecondaryOptions();
        };
        const refreshAvailability = async () => {
            const currentRequest = ++requestNumber;
            if (!visitDate.value) {
                setChoices([], 'Selecciona primero la fecha y hora');
                return;
            }
            primary.disabled = true;
            secondary.disabled = true;
            try {
                const url = new URL(form.dataset.availabilityUrl, location.href);
                url.searchParams.set('fecha_programada', visitDate.value);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('availability');
                const result = await response.json();
                if (currentRequest !== requestNumber) return;
                setChoices(result.technicians, result.technicians.length ? 'Selecciona un técnico' : 'Sin técnicos libres en este horario');
            } catch (_) {
                if (currentRequest === requestNumber) setChoices([], 'No se pudo verificar la disponibilidad');
            }
        };
        visitDate.addEventListener('change', refreshAvailability);
        if (visitDate.value) refreshAvailability();
    });

    const formatInventoryQuantity = value => new Intl.NumberFormat('es-PE', { maximumFractionDigits: 3 }).format(value);
    const renderMaterialAvailability = (status, { available, unit, requested, enough, wholePackage, packaging, factor, baseUnit }) => {
        status.replaceChildren();
        status.classList.add('is-structured');

        const heading = document.createElement('div');
        heading.className = 'resource-availability-heading';
        const title = document.createElement('strong');
        title.textContent = 'Disponibilidad del material';
        const state = document.createElement('span');
        state.className = 'resource-availability-badge';
        state.textContent = enough ? 'Disponible' : (!wholePackage ? 'Revisar cantidad' : 'Stock insuficiente');
        heading.append(title, state);

        const metrics = document.createElement('div');
        metrics.className = 'resource-availability-metrics';
        const addMetric = (label, value) => {
            const item = document.createElement('div');
            const caption = document.createElement('small');
            caption.textContent = label;
            const detail = document.createElement('strong');
            detail.textContent = value;
            item.append(caption, detail);
            metrics.append(item);
        };
        addMetric('Unidad de solicitud', unit);
        addMetric('Stock libre', `${formatInventoryQuantity(available)} ${unit}`);
        if (requested > 0) addMetric('Cantidad solicitada', `${formatInventoryQuantity(requested)} ${unit}`);
        if (packaging && factor) addMetric('Equivalencia', `1 ${unit} = ${formatInventoryQuantity(Number(factor))} ${baseUnit}`);

        const note = document.createElement('p');
        note.className = 'resource-availability-note';
        note.textContent = !wholePackage
            ? 'Indica empaques completos; no se aceptan fracciones de caja o paquete.'
            : (available <= 0 || (requested > 0 && requested > available)
                ? 'Almacén revisará la solicitud y podrá dejarla pendiente hasta contar con existencias.'
                : requested > 0
                    ? 'Almacén reservará la cantidad aprobada. El inventario físico se descontará al registrar el consumo.'
                    : 'Ingresa la cantidad necesaria. El inventario físico se descontará al registrar el consumo.');

        status.append(heading, metrics, note);
        status.classList.add(enough ? 'is-available' : 'is-unavailable');
    };
    const setupMaterialLine = line => {
        const status = line.querySelector('[data-resource-availability]');
        const quantity = line.querySelector('[name$="[cantidad_asignada]"]');
        const select = line.querySelector('select');
        const update = () => {
            const option = select.selectedOptions[0];
            if (quantity) {
                quantity.disabled = !option?.value;
                quantity.required = !!option?.value;
                quantity.step = option?.dataset.packaging === '1' ? '1' : '0.001';
                quantity.min = option?.dataset.packaging === '1' ? '1' : '0.001';
                quantity.placeholder = option?.dataset.packaging === '1' ? 'Cantidad de empaques completos' : 'Cantidad en unidad base';
                if (quantity.disabled) quantity.value = '';
            }
            status.classList.remove('is-available', 'is-unavailable');
            if (!option || !option.value) {
                status.classList.remove('is-structured');
                status.textContent = quantity ? 'Selecciona un material para consultar su stock libre.' : 'Selecciona una unidad para revisar su disponibilidad.';
                return;
            }
            const available = Number(option.dataset.available || 0);
            if (!quantity) {
                status.textContent = available > 0
                    ? `Unidad disponible. Se solicitará este equipo y quedará identificado por código, modelo y serie para Gerencia y Almacén.`
                    : 'Esta unidad ya no está disponible. Actualiza la página y selecciona otro equipo libre.';
                status.classList.add(available > 0 ? 'is-available' : 'is-unavailable');
                return;
            }
            const unit = option.dataset.unit || 'unidades';
            const requested = Number(quantity.value || 0);
            const wholePackage = option.dataset.packaging !== '1' || !requested || Number.isInteger(requested);
            const enough = available > 0 && wholePackage && (!requested || requested <= available);
            renderMaterialAvailability(status, {
                available, unit, requested, enough, wholePackage,
                packaging: option.dataset.packaging === '1',
                factor: option.dataset.factor,
                baseUnit: option.dataset.baseUnit,
            });
        };
        select.addEventListener('change', update);
        quantity?.addEventListener('input', update);
        update();
    };
    document.querySelectorAll('[data-equipment-availability-form]').forEach(form => {
        const select = form.querySelector('select');
        const status = form.querySelector('[data-resource-availability]');
        const update = () => {
            const option = select.selectedOptions[0];
            status.classList.remove('is-available', 'is-unavailable');
            if (!option?.value) {
                status.textContent = 'Selecciona una unidad para revisar su disponibilidad.';
                return;
            }
            const available = Number(option.dataset.available || 0);
            status.textContent = available > 0
                ? 'Unidad disponible. Se solicitará identificada por código, modelo y serie.'
                : 'Esta unidad ya no está disponible. Actualiza la página y selecciona otra.';
            status.classList.add(available > 0 ? 'is-available' : 'is-unavailable');
        };
        select.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-material-line]').forEach(setupMaterialLine);
    document.querySelectorAll('[data-material-lines]').forEach(lines => {
        const form = lines.closest('form');
        const template = form.querySelector('[data-material-template]');
        const currentIndexes = [...lines.querySelectorAll('[name]')].map(input => Number(input.name.match(/materials\[(\d+)\]/)?.[1])).filter(Number.isFinite);
        let nextIndex = currentIndexes.length ? Math.max(...currentIndexes) + 1 : 0;
        const refreshRows = () => {
            const rows = [...lines.querySelectorAll('[data-material-line]')];
            rows.forEach(row => { row.querySelector('[data-remove-material]').hidden = rows.length === 1; });
        };
        form.querySelector('[data-add-material]')?.addEventListener('click', () => {
            const index = nextIndex++;
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach(input => { input.name = input.name.replaceAll('__INDEX__', String(index)); });
            const row = fragment.querySelector('[data-material-line]');
            lines.append(fragment);
            setupMaterialLine(row);
            refreshRows();
            row.querySelector('select')?.focus();
        });
        lines.addEventListener('click', event => {
            const remove = event.target.closest('[data-remove-material]');
            if (!remove) return;
            remove.closest('[data-material-line]').remove();
            refreshRows();
        });
        refreshRows();
    });
    document.querySelectorAll('[data-consumption-form]').forEach(form => {
        const select = form.querySelector('[name="material_id"]');
        const quantity = form.querySelector('[name="cantidad_utilizada"]');
        const unit = form.querySelector('#consumption-unit');
        const update = () => {
            const option = select.selectedOptions[0];
            const packaged = option?.dataset.packaging === '1';
            quantity.step = packaged ? '1' : '0.001';
            quantity.min = packaged ? '1' : '0.001';
            quantity.placeholder = packaged ? 'Empaques completos' : 'Cantidad en unidad base';
            unit.textContent = option?.value ? `Unidad de inventario: ${option.dataset.unit}${packaged ? ' · Registra empaques enteros' : ''}.` : 'Selecciona un material para ver su unidad de inventario.';
        };
        select.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-order-cancel-reason]').forEach(select => {
        const form = select.closest('[data-order-cancel-form]');
        const wrap = form?.querySelector('[data-cancel-detail-wrap]');
        const detail = form?.querySelector('[data-cancel-detail]');
        const update = () => {
            const other = select.value === 'OTRO';
            if (wrap) wrap.hidden = !other;
            if (detail) detail.required = other;
        };
        select.addEventListener('change', update);
        update();
    });
    const forms = document.querySelectorAll('[data-installation-form], .installation-form');
    const cancelDialog = document.getElementById('order-cancel-confirm');
    let pendingCancelForm;
    let confirmedCancelForm;
    const reset = (form) => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-installation-busy]').forEach(button => {
            button.disabled = false;
            delete button.dataset.installationBusy;
        });
        const status = form.querySelector('.installation-loading');
        if (status) status.hidden = true;
    };
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (form.classList.contains('order-resource-request-form')) {
                const equipment = form.querySelector('[name="equipo_id"]')?.value;
                const materials = [...form.querySelectorAll('[data-material-line] select')]
                    .map(select => select.value).filter(Boolean);
                const error = form.querySelector('[data-resource-form-error]');
                if (!equipment && !materials.length) {
                    event.preventDefault();
                    if (error) error.textContent = 'Selecciona al menos un material o un equipo antes de enviar.';
                    if (error) error.hidden = false;
                    return;
                }
                if (new Set(materials).size !== materials.length) {
                    event.preventDefault();
                    if (error) error.textContent = 'Cada material debe aparecer una sola vez. Ajusta la cantidad en su misma fila.';
                    if (error) error.hidden = false;
                    return;
                }
                if (error) error.hidden = true;
            }
            if (form.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }
            if (form.hasAttribute('data-confirm-cancel') && confirmedCancelForm !== form && cancelDialog) {
                event.preventDefault();
                pendingCancelForm = form;
                cancelDialog.showModal();
                return;
            }
            if (confirmedCancelForm === form) confirmedCancelForm = undefined;
            let error = form.querySelector('.installation-network-error');
            if (!navigator.onLine) {
                event.preventDefault();
                if (!error) {
                    error = document.createElement('p');
                    error.className = 'field-error installation-network-error';
                    error.setAttribute('role', 'alert');
                    error.textContent = 'No hay conexión. Comprueba tu conexión y vuelve a intentarlo.';
                    form.append(error);
                }
                return;
            }
            if (error) error.remove();
            if (form.dataset.returnAnchor) {
                try { sessionStorage.setItem('installation-return-anchor', form.dataset.returnAnchor); } catch (_) {}
            }
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type="submit"]').forEach(button => {
                button.dataset.installationBusy = 'true';
                button.disabled = true;
            });
            let status = form.querySelector('.installation-loading');
            if (!status) {
                status = document.createElement('p');
                status.className = 'installation-loading';
                status.setAttribute('role', 'status');
                status.textContent = form.enctype === 'multipart/form-data' ? 'Subiendo archivo y comprobando Drive…' : 'Guardando…';
                form.append(status);
            }
            status.hidden = false;
        });
    });
    if (cancelDialog) {
        cancelDialog.querySelector('[data-cancel-order]').addEventListener('click', () => {
            pendingCancelForm = undefined;
            cancelDialog.close();
        });
        cancelDialog.querySelector('[data-accept-order]').addEventListener('click', () => {
            const form = pendingCancelForm;
            cancelDialog.close();
            pendingCancelForm = undefined;
            if (form) {
                confirmedCancelForm = form;
                form.requestSubmit();
            }
        });
    }
    document.querySelectorAll('.order-card').forEach(card => {
        [card.querySelectorAll('.order-card-toolbar > details'), card.querySelectorAll('.order-actions > details, .order-card-utilities > details')].forEach(panels => {
            panels.forEach(panel => panel.addEventListener('toggle', () => {
                if (!panel.open) return;
                panels.forEach(other => {
                    if (other !== panel) other.open = false;
                });
            }));
        });
    });
    window.addEventListener('pageshow', () => forms.forEach(reset));
    const sectionNav = document.querySelector('[data-installation-tabs]');
    const sectionLinks = [...(sectionNav?.querySelectorAll('[data-installation-tab]') ?? [])];
    const sectionPanels = [...document.querySelectorAll('[data-installation-section]')];
    const sectionForAnchor = anchor => {
        const target = document.getElementById(anchor);
        return target?.closest('[data-installation-section]')?.dataset.installationSection;
    };
    const activateSection = (section, focus = false) => {
        if (!sectionLinks.some(link => link.dataset.installationTab === section)) return;
        sectionPanels.forEach(panel => {
            panel.hidden = panel.dataset.installationSection !== section;
        });
        sectionLinks.forEach(link => {
            const selected = link.dataset.installationTab === section;
            link.classList.toggle('is-active', selected);
            if (selected) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        const firstPanel = sectionPanels.find(panel => panel.dataset.installationSection === section);
        if (section === 'datos' && firstPanel?.tagName === 'DETAILS') firstPanel.open = true;
        if (focus) firstPanel?.focus({ preventScroll: true });
    };
    if (sectionLinks.length) {
        const hashSection = sectionForAnchor(decodeURIComponent(location.hash.slice(1)));
        activateSection(hashSection || 'datos');
        sectionLinks.forEach(link => link.addEventListener('click', event => {
            event.preventDefault();
            activateSection(link.dataset.installationTab, true);
            history.replaceState(null, '', link.hash);
        }));
        window.addEventListener('hashchange', () => activateSection(sectionForAnchor(decodeURIComponent(location.hash.slice(1))) || 'datos'));
    }
    if (document.querySelector('.installation-heading')) {
        let returnAnchor;
        try {
            returnAnchor = sessionStorage.getItem('installation-return-anchor');
            sessionStorage.removeItem('installation-return-anchor');
        } catch (_) {}
        if (returnAnchor && !location.hash) {
            const target = document.getElementById(returnAnchor);
            if (target) {
                activateSection(sectionForAnchor(returnAnchor) || 'datos');
                scrollTargetIntoView(target);
            }
        }
    }
    const incidentType = document.querySelector('[data-incident-type]');
    const incidentResource = document.querySelector('[data-incident-resource]');
    if (incidentType && incidentResource) {
        const updateResources = (clearSelection) => {
            if (clearSelection) incidentResource.value = '';
            incidentResource.querySelectorAll('option[data-resource-type]').forEach(option => {
                const allowed = option.dataset.resourceType === incidentType.value;
                option.hidden = !allowed;
                option.disabled = !allowed;
            });
            incidentResource.disabled = !['EQUIPO', 'MATERIAL'].includes(incidentType.value);
            if (incidentResource.disabled) incidentResource.value = '';
        };
        incidentType.addEventListener('change', () => updateResources(true));
        updateResources(false);
    }
    document.querySelectorAll('[data-evidence-input]').forEach(input => {
        const figure = input.parentElement.querySelector('.installation-file-preview');
        const image = figure.querySelector('img');
        const caption = figure.querySelector('figcaption');
        const choices = input.parentElement.querySelector('[data-evidence-picker]');
        const acceptedFiles = input.accept;
        if (choices) {
            choices.hidden = false;
            input.classList.add('installation-file-input-enhanced');
            choices.querySelector('[data-open-camera]').addEventListener('click', () => {
                input.accept = 'image/*';
                input.setAttribute('capture', 'environment');
                input.click();
            });
            choices.querySelector('[data-open-files]').addEventListener('click', () => {
                input.accept = acceptedFiles;
                input.removeAttribute('capture');
                input.click();
            });
        }
        let objectUrl;
        input.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            input.setCustomValidity('');
            image.removeAttribute('src');
            image.hidden = true;
            figure.hidden = true;
            const file = input.files[0];
            if (!file) return;
            figure.hidden = false;
            caption.textContent = file.name + ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
            if (file.size > 5 * 1024 * 1024) {
                caption.textContent += ' · El archivo supera el límite de 5 MB.';
                input.setCustomValidity('El archivo no debe superar 5 MB. Elige otro archivo.');
                return;
            }
            if (['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                objectUrl = URL.createObjectURL(file);
                image.src = objectUrl;
                image.hidden = false;
            }
        });
        image.addEventListener('error', () => {
            image.hidden = true;
            caption.textContent += ' · No se pudo mostrar la vista previa.';
        });
    });
})();
