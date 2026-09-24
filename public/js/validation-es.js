(() => {
    const translated = new WeakSet();
    document.addEventListener('invalid', (event) => {
        const field = event.target;
        if (typeof field.setCustomValidity !== 'function') return;
        if (translated.has(field)) field.setCustomValidity('');
        const v = field.validity;
        if (v.customError) return;
        let message = '';
        if (v.valueMissing) message = 'Completa este campo obligatorio.';
        else if (v.typeMismatch) message = field.type === 'email' ? 'Introduce un correo electrónico válido.' : 'Introduce una dirección web válida.';
        else if (v.patternMismatch) message = 'Introduce un valor con el formato solicitado.';
        else if (v.tooShort) message = 'Introduce al menos ' + field.minLength + ' caracteres.';
        else if (v.tooLong) message = 'Introduce como máximo ' + field.maxLength + ' caracteres.';
        else if (v.rangeUnderflow) message = 'Introduce un valor igual o mayor que ' + field.min + '.';
        else if (v.rangeOverflow) message = 'Introduce un valor igual o menor que ' + field.max + '.';
        else if (v.stepMismatch) message = 'Introduce un valor con el incremento permitido.';
        else if (v.badInput) message = 'Introduce un valor válido.';
        if (message) { field.setCustomValidity(message); translated.add(field); }
    }, true);
    const clear = (event) => {
        if (translated.has(event.target)) { event.target.setCustomValidity(''); translated.delete(event.target); }
    };
    document.addEventListener('input', clear);
    document.addEventListener('change', clear);
    document.addEventListener('reset', (event) => {
        for (const field of event.target.elements || []) {
            if (translated.has(field)) { field.setCustomValidity(''); translated.delete(field); }
        }
    });
})();
