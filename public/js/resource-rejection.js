document.querySelectorAll('[data-resource-rejection-form]').forEach((form) => {
    const reason = form.querySelector('[name="decision_motivo_codigo"]');
    const explanation = form.querySelector('[name="decision_observacion"]');
    if (!reason || !explanation) return;

    const updateExplanation = () => {
        explanation.required = reason.value === 'OTRO';
        explanation.setAttribute('aria-required', explanation.required ? 'true' : 'false');
    };

    reason.addEventListener('change', updateExplanation);
    updateExplanation();
});
