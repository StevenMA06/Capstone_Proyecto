/**
 * Autocompletado de monto desde contrato (RF-PAG-08)
 * Cuando se selecciona un contrato, autocompleta el monto con el precio_contratado
 */
document.addEventListener('DOMContentLoaded', function() {
    const contractSelect = document.getElementById('contrato_id');
    const montoInput = document.getElementById('monto');
    const contractInfo = document.getElementById('contract-info');
    const infoClient = document.getElementById('info-client');
    const infoPlan = document.getElementById('info-plan');
    const infoMonthly = document.getElementById('info-monthly');

    if (!contractSelect || !montoInput) {
        return; // No estamos en el formulario de pagos
    }

    // Autocompletar monto al seleccionar contrato
    contractSelect.addEventListener('change', async function() {
        const contractId = this.value;
        
        // Limpiar información previa
        contractInfo.style.display = 'none';
        infoClient.textContent = '';
        infoPlan.textContent = '';
        infoMonthly.textContent = '';

        if (!contractId) {
            montoInput.value = '';
            return;
        }

        // Mostrar información básica del option seleccionado
        const selectedOption = this.options[this.selectedIndex];
        const clientName = selectedOption.dataset.client || 'N/A';
        const planName = selectedOption.dataset.plan || 'N/A';
        const monthlyPrice = selectedOption.dataset.monthly || '0';

        infoClient.textContent = `Cliente: ${clientName}`;
        infoPlan.textContent = `Plan: ${planName}`;
        infoMonthly.textContent = `Precio mensual: S/ ${parseFloat(monthlyPrice).toFixed(2)}`;
        contractInfo.style.display = 'block';

        try {
            // Llamar al API para obtener el monto exacto del contrato
            const response = await fetch(`/api/contracts/${contractId}/amount`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Autocompletar el monto con el precio_contratado
            if (data.precio_contratado) {
                montoInput.value = parseFloat(data.precio_contratado).toFixed(2);
                
                // Destacar visualmente el monto autocompletado
                montoInput.style.background = '#e8f5e9';
                setTimeout(() => {
                    montoInput.style.background = '';
                }, 2000);
            }

            // Actualizar información con datos del API
            infoMonthly.textContent = `Precio del contrato: S/ ${parseFloat(data.precio_contratado).toFixed(2)}`;
            
        } catch (error) {
            console.error('Error al obtener monto del contrato:', error);
            
            // Fallback: usar el precio mensual del plan si el API falla
            if (monthlyPrice && parseFloat(monthlyPrice) > 0) {
                montoInput.value = parseFloat(monthlyPrice).toFixed(2);
            }
        }
    });

    // Trigger inicial si hay un contrato preseleccionado (modo edición)
    if (contractSelect.value && !montoInput.value) {
        contractSelect.dispatchEvent(new Event('change'));
    }
});
