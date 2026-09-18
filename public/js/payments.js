// Validaciones y funcionalidades del módulo de pagos
const paymentForm = document.querySelector('.account-form');
const contractSelect = document.getElementById('contrato_id');
const contractInfo = document.getElementById('contract-info');
const montoInput = document.getElementById('monto');
const periodoInput = document.getElementById('periodo_servicio');
const fechaVencimientoInput = document.getElementById('fecha_vencimiento');
const fechaPagoInput = document.getElementById('fecha_pago');
const metodoPagoSelect = document.getElementById('metodo_pago');

// Mostrar información del contrato seleccionado
if (contractSelect && contractInfo) {
    contractSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (this.value && selectedOption.dataset.client) {
            const clientName = selectedOption.dataset.client;
            const planName = selectedOption.dataset.plan;
            const monthlyPrice = parseFloat(selectedOption.dataset.monthly) || 0;
            
            document.getElementById('info-client').textContent = `Cliente: ${clientName}`;
            document.getElementById('info-plan').textContent = `Plan: ${planName}`;
            document.getElementById('info-monthly').textContent = `Precio mensual: S/ ${monthlyPrice.toFixed(2)}`;
            
            contractInfo.style.display = 'block';
            
            // Sugerir monto si está vacío
            if (montoInput && !montoInput.value && monthlyPrice > 0) {
                montoInput.value = monthlyPrice.toFixed(2);
            }
            
            // Sugerir período si está vacío
            if (periodoInput && !periodoInput.value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                periodoInput.value = `${year}-${month}`;
            }
        } else {
            contractInfo.style.display = 'none';
        }
    });
    
    // Trigger change si ya hay un contrato seleccionado al cargar
    if (contractSelect.value) {
        contractSelect.dispatchEvent(new Event('change'));
    }
}

// Validación de período de servicio (formato YYYY-MM)
if (periodoInput) {
    const periodoPattern = /^\d{4}-(0[1-9]|1[0-2])$/;
    const periodoError = document.getElementById('periodo_servicio-error');
    
    periodoInput.addEventListener('blur', function() {
        const value = this.value.trim();
        
        if (value && !periodoPattern.test(value)) {
            if (periodoError) {
                periodoError.textContent = 'El formato debe ser YYYY-MM (Ej: 2026-09).';
                periodoError.hidden = false;
                this.setAttribute('aria-invalid', 'true');
                this.setAttribute('aria-describedby', 'periodo_servicio-error');
            }
        } else if (periodoError) {
            periodoError.hidden = true;
            this.removeAttribute('aria-invalid');
            this.removeAttribute('aria-describedby');
        }
    });
}

// Validación: si fecha_pago existe, requiere metodo_pago
if (fechaPagoInput && metodoPagoSelect && paymentForm) {
    const validatePaymentMethod = () => {
        const fechaPagoValue = fechaPagoInput.value.trim();
        const metodoPagoValue = metodoPagoSelect.value.trim();
        
        if (fechaPagoValue && !metodoPagoValue) {
            const metodoPagoError = document.getElementById('metodo_pago-error');
            if (metodoPagoError) {
                metodoPagoError.textContent = 'El método de pago es requerido si indicaste fecha de pago.';
                metodoPagoError.hidden = false;
                metodoPagoSelect.setAttribute('aria-invalid', 'true');
                metodoPagoSelect.setAttribute('aria-describedby', 'metodo_pago-error');
            }
            return false;
        }
        return true;
    };
    
    fechaPagoInput.addEventListener('change', () => {
        const metodoPagoError = document.getElementById('metodo_pago-error');
        if (fechaPagoInput.value && !metodoPagoSelect.value) {
            metodoPagoSelect.focus();
        }
        if (metodoPagoError && metodoPagoSelect.value) {
            metodoPagoError.hidden = true;
            metodoPagoSelect.removeAttribute('aria-invalid');
            metodoPagoSelect.removeAttribute('aria-describedby');
        }
    });
    
    metodoPagoSelect.addEventListener('change', () => {
        const metodoPagoError = document.getElementById('metodo_pago-error');
        if (metodoPagoError && metodoPagoSelect.value) {
            metodoPagoError.hidden = true;
            metodoPagoSelect.removeAttribute('aria-invalid');
            metodoPagoSelect.removeAttribute('aria-describedby');
        }
    });
    
    paymentForm.addEventListener('submit', (event) => {
        if (!validatePaymentMethod()) {
            event.preventDefault();
            metodoPagoSelect.focus();
        }
    });
}

// Validación: monto debe ser mayor que 0
if (montoInput && paymentForm) {
    montoInput.addEventListener('blur', function() {
        const value = parseFloat(this.value);
        const montoError = document.getElementById('monto-error');
        
        if (this.value && (isNaN(value) || value <= 0)) {
            if (montoError) {
                montoError.textContent = 'El monto debe ser mayor que 0.';
                montoError.hidden = false;
                this.setAttribute('aria-invalid', 'true');
                this.setAttribute('aria-describedby', 'monto-error');
            }
        } else if (montoError) {
            montoError.hidden = true;
            this.removeAttribute('aria-invalid');
            this.removeAttribute('aria-describedby');
        }
    });
}

// Validación: fecha_vencimiento no puede ser anterior a hoy (solo en create)
if (fechaVencimientoInput && fechaVencimientoInput.hasAttribute('min')) {
    fechaVencimientoInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const fechaVencimientoError = document.getElementById('fecha_vencimiento-error');
        
        if (this.value && selectedDate < today) {
            if (fechaVencimientoError) {
                fechaVencimientoError.textContent = 'La fecha de vencimiento no puede ser anterior a hoy.';
                fechaVencimientoError.hidden = false;
                this.setAttribute('aria-invalid', 'true');
                this.setAttribute('aria-describedby', 'fecha_vencimiento-error');
            }
        } else if (fechaVencimientoError) {
            fechaVencimientoError.hidden = true;
            this.removeAttribute('aria-invalid');
            this.removeAttribute('aria-describedby');
        }
    });
}

// Validación: fecha_pago no puede ser posterior a hoy
if (fechaPagoInput) {
    fechaPagoInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const today = new Date();
        today.setHours(23, 59, 59, 999);
        
        const fechaPagoError = document.getElementById('fecha_pago-error');
        
        if (this.value && selectedDate > today) {
            if (fechaPagoError) {
                fechaPagoError.textContent = 'La fecha de pago no puede ser posterior a hoy.';
                fechaPagoError.hidden = false;
                this.setAttribute('aria-invalid', 'true');
                this.setAttribute('aria-describedby', 'fecha_pago-error');
            }
        } else if (fechaPagoError) {
            fechaPagoError.hidden = true;
            this.removeAttribute('aria-invalid');
            this.removeAttribute('aria-describedby');
        }
    });
}

// Validación: fecha_pago no puede ser anterior a fecha_vencimiento
if (fechaPagoInput && fechaVencimientoInput && paymentForm) {
    const validatePaymentDate = () => {
        const fechaPago = fechaPagoInput.value;
        const fechaVencimiento = fechaVencimientoInput.value;
        
        if (fechaPago && fechaVencimiento) {
            const datePago = new Date(fechaPago);
            const dateVencimiento = new Date(fechaVencimiento);
            
            if (datePago < dateVencimiento) {
                const fechaPagoError = document.getElementById('fecha_pago-error');
                if (fechaPagoError) {
                    fechaPagoError.textContent = 'La fecha de pago no puede ser anterior a la fecha de vencimiento.';
                    fechaPagoError.hidden = false;
                    fechaPagoInput.setAttribute('aria-invalid', 'true');
                    fechaPagoInput.setAttribute('aria-describedby', 'fecha_pago-error');
                }
                return false;
            }
        }
        return true;
    };
    
    fechaPagoInput.addEventListener('change', validatePaymentDate);
    fechaVencimientoInput.addEventListener('change', validatePaymentDate);
    
    paymentForm.addEventListener('submit', (event) => {
        if (!validatePaymentDate()) {
            event.preventDefault();
            fechaPagoInput.focus();
        }
    });
}

// Auto-formatear período al escribir
if (periodoInput) {
    periodoInput.addEventListener('input', function(e) {
        let value = this.value.replace(/[^0-9-]/g, '');
        
        // Auto-agregar guión después de 4 dígitos
        if (value.length === 4 && !value.includes('-')) {
            value += '-';
        }
        
        // Limitar longitud
        if (value.length > 7) {
            value = value.substring(0, 7);
        }
        
        this.value = value;
    });
}
