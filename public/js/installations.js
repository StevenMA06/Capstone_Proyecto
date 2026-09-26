(() => {
    const forms = document.querySelectorAll('[data-installation-form], .installation-form');
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
        let finishConfirmed = false;
        form.addEventListener('submit', event => {
            if (form.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }
            if (form.hasAttribute('data-confirm-finish') && !finishConfirmed) {
                event.preventDefault();
                const dialog = document.getElementById('installation-finish-confirm');
                dialog.showModal();
                return;
            }
            finishConfirmed = false;
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
                status.textContent = 'Guardando…';
                form.append(status);
            }
            status.hidden = false;
        });
        if (form.hasAttribute('data-confirm-finish')) {
            const dialog = document.getElementById('installation-finish-confirm');
            dialog.querySelector('[data-cancel-finish]').addEventListener('click', () => dialog.close());
            dialog.querySelector('[data-accept-finish]').addEventListener('click', () => {
                dialog.close();
                finishConfirmed = true;
                form.requestSubmit();
            });
        }
    });
    window.addEventListener('pageshow', () => forms.forEach(reset));
    document.querySelectorAll('[data-evidence-input]').forEach(input => {
        const figure = input.parentElement.querySelector('.installation-file-preview');
        const image = figure.querySelector('img');
        const caption = figure.querySelector('figcaption');
        let objectUrl;
        input.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            image.removeAttribute('src');
            image.hidden = true;
            figure.hidden = true;
            const file = input.files[0];
            if (!file) return;
            figure.hidden = false;
            caption.textContent = file.name + ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
            if (file.size > 5 * 1024 * 1024) {
                caption.textContent += ' · El archivo supera el límite de 5 MB.';
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
