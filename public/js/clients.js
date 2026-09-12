const clientForm = document.querySelector('.account-form');
const documentType = document.getElementById('tipo_documento');
const documentNumber = document.getElementById('numero_documento');
const documentError = document.getElementById('numero_documento-error');
const regexDni = /^[0-9]{8}$/;

if (clientForm && documentType && documentNumber && documentError) {
    const configureDocument = () => {
        if (documentType.value === 'DNI') {
            documentNumber.setAttribute('inputmode', 'numeric');
            documentNumber.setAttribute('pattern', '[0-9]{8}');
        } else {
            documentNumber.removeAttribute('inputmode');
            documentNumber.removeAttribute('pattern');
        }
    };
    const clearDocumentError = () => {
        documentNumber.removeAttribute('aria-invalid');
        documentNumber.removeAttribute('aria-describedby');
        documentError.hidden = true;
        documentError.textContent = '';
    };

    configureDocument();
    documentType.addEventListener('change', () => {
        configureDocument();
        clearDocumentError();
    });
    documentNumber.addEventListener('input', clearDocumentError);
    clientForm.addEventListener('submit', (event) => {
        if (documentType.value === 'DNI' && !regexDni.test(documentNumber.value.trim())) {
            event.preventDefault();
            documentError.textContent = 'El DNI debe contener exactamente 8 dígitos numéricos.';
            documentError.hidden = false;
            documentNumber.setAttribute('aria-invalid', 'true');
            documentNumber.setAttribute('aria-describedby', 'numero_documento-error');
            documentNumber.focus();
        }
    });
}
