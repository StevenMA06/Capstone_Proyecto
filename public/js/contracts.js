document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.contracts-form');
    if (!form) return;
    const client = form.querySelector('#cliente_id');
    const plan = form.querySelector('#plan_id');
    const zone = form.querySelector('#zona_id');
    const start = form.querySelector('#fecha_inicio');
    const months = form.querySelector('#duracion_meses');
    const end = form.querySelector('#fecha_fin');
    const price = form.querySelector('#precio_contratado');
    const address = form.querySelector('#direccion_servicio');
    const message = form.querySelector('#coverage-message');
    const fileInput = form.querySelector('#documento_pdf');
    const dropzone = form.querySelector('[data-dropzone]');
    const selectedPdf = form.querySelector('#selected-pdf');
    const selectedPdfHint = form.querySelector('#selected-pdf-hint');
    const coverage = JSON.parse(form.dataset.coverage || '[]');
    const normalize = value => (value || '').trim().toLocaleLowerCase('es-PE');

    function refresh() {
        address.value = client.selectedOptions[0]?.dataset.address || '';
        const selectedPlan = plan.selectedOptions[0];
        const planPrice = form.dataset.currentPlan === plan.value && form.dataset.currentPrice ? form.dataset.currentPrice : selectedPlan?.dataset.price;
        price.value = planPrice ? `S/ ${Number(planPrice).toFixed(2)}` : '';
        end.value = '';
        if (start.value && Number.isInteger(Number(months.value)) && Number(months.value) > 0) {
            const [year, month, day] = start.value.split('-').map(Number);
            const lastOfMonth = new Date(0);
            lastOfMonth.setUTCFullYear(year, month + Number(months.value), 0);
            const anniversary = new Date(0);
            anniversary.setUTCFullYear(year, month - 1 + Number(months.value), Math.min(day, lastOfMonth.getUTCDate()));
            anniversary.setUTCDate(anniversary.getUTCDate() - 1);
            end.value = anniversary.getUTCFullYear() > 9999 ? 'Fuera de rango' : anniversary.toISOString().slice(0, 10);
        }
        if (!client.value || !plan.value || !zone.value) {
            message.textContent = 'Selecciona el cliente, plan y zona para validar cobertura.';
            return;
        }
        const district = normalize(client.selectedOptions[0].dataset.district);
        const zoneDistrict = normalize(zone.selectedOptions[0].dataset.district);
        const matchingDistrict = !district || district === zoneDistrict;
        const assignments = coverage.filter(item => String(item.plan_id) === plan.value);
        const matchingPlan = !assignments.length || assignments.some(item => String(item.zona_id) === zone.value && item.estado === 'ACTIVO');
        message.textContent = matchingDistrict && matchingPlan ? 'Cobertura disponible para este cliente y plan.' : 'La ubicación no tiene cobertura con la zona y el plan seleccionados.';
        message.classList.toggle('coverage-error', !matchingDistrict || !matchingPlan);
    }
    [client, plan, zone, start, months].forEach(input => input.addEventListener('change', refresh));
    months.addEventListener('input', refresh);
    if (fileInput && dropzone && selectedPdf) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            selectedPdf.textContent = file?.name || 'Arrastra un PDF aquí o selecciona uno';
            dropzone.classList.toggle('has-selection', Boolean(file));
            dropzone.classList.toggle('has-empty-file', Boolean(file && file.size === 0));
            if (selectedPdfHint) {
                selectedPdfHint.hidden = !file;
                selectedPdfHint.textContent = file?.size === 0
                    ? 'El archivo está vacío (0 bytes). Vuelve a exportarlo desde PDFgear antes de guardar.'
                    : 'Archivo seleccionado · se guardará al pulsar «Guardar cambios».';
            }
        });
        ['dragenter', 'dragover'].forEach(event => dropzone.addEventListener(event, e => {
            e.preventDefault();
            dropzone.classList.add('is-dragging');
        }));
        ['dragleave', 'drop'].forEach(event => dropzone.addEventListener(event, e => {
            e.preventDefault();
            dropzone.classList.remove('is-dragging');
        }));
        dropzone.addEventListener('drop', e => {
            if (!e.dataTransfer?.files.length) return;
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        });
    }
    form.addEventListener('submit', event => {
        if (fileInput?.files[0]?.size === 0) {
            event.preventDefault();
            dropzone.classList.add('has-empty-file');
            selectedPdfHint.hidden = false;
            selectedPdfHint.textContent = 'El archivo está vacío (0 bytes). Vuelve a exportarlo desde PDFgear antes de guardar.';
        }
    });
    refresh();
});
