@extends('materials.layout')

@section('materials-content')
<div class="form-layout">
    @can('materials.manage')
@routecan($material->exists ? 'materials.update' : 'materials.store')<form class="standard-form" method="POST" action="{{ $material->exists ? route('materials.update', $material) : route('materials.store') }}" novalidate>
        @csrf
        @if($material->exists)
            @method('PUT')
        @endif
        
        <h2>{{ $material->exists ? 'Editar material' : 'Registrar nuevo material' }}</h2><p class="form-intro">Completa los campos marcados con *. Los demás son opcionales.</p>
        
        @if($material->exists)
        <div class="alert alert-info" style="background: #e0f2fe; border-left: 4px solid #0284c7; padding: 0.75rem 1rem; margin-bottom: 1.5rem; border-radius: 4px;">
            <strong>⚠️ Nota:</strong> El stock actual NO se puede editar directamente. Usa Ingreso, Salida o Ajuste.
        </div>
        @endif
        
        <div class="inventory-grid"><section class="inventory-card"><h3><span>01</span> Información del material</h3>
        
        @if($material->exists)
        {{-- Mostrar código solo cuando se está editando --}}
        <div class="form-row" style="grid-template-columns: 1fr 2fr;">
            <div class="form-group">
                <label for="codigo">Código</label>
                <input type="text" id="codigo" name="codigo" value="{{ old('codigo', $material->codigo) }}" maxlength="60">
                @error('codigo')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="nombre">Nombre del material *</label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $material->nombre) }}" maxlength="120">
                @error('nombre')<span class="error-message">{{ $message }}</span>@enderror
            </div>
        </div>
        @else
        {{-- Ocultar campo código cuando es nuevo --}}
        <div class="form-row" style="grid-template-columns: 1fr;">
            <div class="form-group">
                <label for="nombre">Nombre del material *</label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $material->nombre) }}" maxlength="120">
                <small style="color: #64748b;">El código se generará automáticamente</small>
                @error('nombre')<span class="error-message">{{ $message }}</span>@enderror
            </div>
        </div>
        @endif
        
        <div style="height:20px" aria-hidden="true"></div>
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="500" placeholder="Descripción detallada del material...">{{ old('descripcion', $material->descripcion) }}</textarea>
            @error('descripcion')<span class="error-message">{{ $message }}</span>@enderror
        </div>
        
        <div style="height:20px" aria-hidden="true"></div>
        
        {{-- Unidades de medida --}}
        <div class="form-row" style="grid-template-columns: 1fr;">
            <div class="form-group">
                <label for="unidad_base">Unidad de medida *</label>
                <select id="unidad_base" name="unidad_base" required>
                    <option value="">Selecciona una unidad...</option>
                    <optgroup label="Unidades de longitud">
                        <option value="metros" @selected(old('unidad_base', $material->unidad_base) === 'metros')>metros (m)</option>
                        <option value="centímetros" @selected(old('unidad_base', $material->unidad_base) === 'centímetros')>centímetros (cm)</option>
                        <option value="pulgadas" @selected(old('unidad_base', $material->unidad_base) === 'pulgadas')>pulgadas (")</option>
                    </optgroup>
                    <optgroup label="Unidades de peso">
                        <option value="kg" @selected(old('unidad_base', $material->unidad_base) === 'kg')>kilogramos (kg)</option>
                        <option value="gramos" @selected(old('unidad_base', $material->unidad_base) === 'gramos')>gramos (g)</option>
                        <option value="libras" @selected(old('unidad_base', $material->unidad_base) === 'libras')>libras (lb)</option>
                        <option value="onzas" @selected(old('unidad_base', $material->unidad_base) === 'onzas')>onzas (oz)</option>
                    </optgroup>
                    <optgroup label="Unidades de volumen">
                        <option value="litros" @selected(old('unidad_base', $material->unidad_base) === 'litros')>litros (L)</option>
                        <option value="mililitros" @selected(old('unidad_base', $material->unidad_base) === 'mililitros')>mililitros (ml)</option>
                        <option value="galones" @selected(old('unidad_base', $material->unidad_base) === 'galones')>galones (gal)</option>
                    </optgroup>
                    <optgroup label="Unidades contables">
                        <option value="unidades" @selected(old('unidad_base', $material->unidad_base) === 'unidades')>unidades (und)</option>
                        <option value="piezas" @selected(old('unidad_base', $material->unidad_base) === 'piezas')>piezas (pz)</option>
                        <option value="pares" @selected(old('unidad_base', $material->unidad_base) === 'pares')>pares</option>
                    </optgroup>
                    <optgroup label="Otras unidades">
                        <option value="otras" @selected(old('unidad_base', $material->unidad_base) === 'otras')>Otra unidad (especificar)</option>
                    </optgroup>
                </select>
                <small style="color: #64748b;">Ej: Cables → metros, Clavos → pulgadas, Pintura → litros</small>
                @error('unidad_base')<span class="error-message">{{ $message }}</span>@enderror
                
                {{-- Campo para unidad personalizada si selecciona "otras" --}}
                <div id="unidad_base_otra" style="display: none; margin-top: 0.5rem;">
                    <input type="text" id="unidad_base_custom" maxlength="30" placeholder="Especifica la unidad..." style="width: 100%;">
                    <small style="color: #64748b;">Escribe la unidad que necesitas</small>
                </div>
            </div>
        </div>
        
        <div style="height:16px" aria-hidden="true"></div>
        
        {{-- Checkbox para activar empaque --}}
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="usa_empaque" {{ old('unidad_empaque', $material->unidad_empaque) ? 'checked' : '' }} style="width: auto; margin: 0;">
                <span>Este material se maneja en empaques (bobinas, cajas, paquetes, etc.)</span>
            </label>
            <small style="color: #64748b; margin-left: 28px;">Marca esta opción si el material viene en unidades de empaque</small>
        </div>
        
        <div style="height:12px" aria-hidden="true"></div>
        
        {{-- Campos de empaque (ocultos por defecto) --}}
        <div id="empaque-fields" style="display: {{ old('unidad_empaque', $material->unidad_empaque) ? 'block' : 'none' }};">
            <div class="inventory-note" style="background: #f0f9ff; border-color: #bae6fd;">
                <strong>📦 Configuración de empaque</strong>
                <p style="margin: 4px 0 0;">Define cómo se empaqueta este material.</p>
            </div>
            
            <div style="height:12px" aria-hidden="true"></div>
            
            <div class="form-row" style="grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="unidad_empaque">Tipo de empaque *</label>
                    <select id="unidad_empaque" name="unidad_empaque">
                        <option value="">Selecciona un tipo...</option>
                        <option value="cajas" @selected(old('unidad_empaque', $material->unidad_empaque) === 'cajas')>Cajas</option>
                        <option value="paquetes" @selected(old('unidad_empaque', $material->unidad_empaque) === 'paquetes')>Paquetes</option>
                        <option value="bolsas" @selected(old('unidad_empaque', $material->unidad_empaque) === 'bolsas')>Bolsas</option>
                        <option value="sacos" @selected(old('unidad_empaque', $material->unidad_empaque) === 'sacos')>Sacos</option>
                        <option value="rollos" @selected(old('unidad_empaque', $material->unidad_empaque) === 'rollos')>Rollos</option>
                        <option value="bobinas" @selected(old('unidad_empaque', $material->unidad_empaque) === 'bobinas')>Bobinas</option>
                        <option value="tubos" @selected(old('unidad_empaque', $material->unidad_empaque) === 'tubos')>Tubos</option>
                        <option value="tarros" @selected(old('unidad_empaque', $material->unidad_empaque) === 'tarros')>Tarros</option>
                        <option value="galones" @selected(old('unidad_empaque', $material->unidad_empaque) === 'galones')>Galones</option>
                        <option value="bidones" @selected(old('unidad_empaque', $material->unidad_empaque) === 'bidones')>Bidones</option>
                        <option value="fardos" @selected(old('unidad_empaque', $material->unidad_empaque) === 'fardos')>Fardos</option>
                        <option value="pallets" @selected(old('unidad_empaque', $material->unidad_empaque) === 'pallets')>Pallets</option>
                        <option value="otro" @selected(old('unidad_empaque', $material->unidad_empaque) === 'otro')>Otro (especificar)</option>
                    </select>
                    @error('unidad_empaque')<span class="error-message">{{ $message }}</span>@enderror
                    
                    {{-- Campo para empaque personalizado si selecciona "otro" --}}
                    <div id="unidad_empaque_otra" style="display: none; margin-top: 0.5rem;">
                        <input type="text" id="unidad_empaque_custom" maxlength="30" placeholder="Especifica el tipo de empaque..." style="width: 100%;">
                        <small style="color: #64748b;">Escribe el tipo de empaque</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="factor_conversion">Cantidad por empaque *</label>
                    <input type="number" step="0.001" id="factor_conversion" name="factor_conversion" value="{{ old('factor_conversion', $material->factor_conversion) }}" min="0.001" max="999999.999" placeholder="Ej: 305">
                    <small id="conversion-hint" style="color: #64748b;"></small>
                    @error('factor_conversion')<span class="error-message">{{ $message }}</span>@enderror
                </div>
            </div>
            
            <div style="height:8px" aria-hidden="true"></div>
            
            <div class="inventory-note" style="background: #fefce8; border-color: #fde047;">
                <strong>💡 Ejemplos:</strong>
                <p style="margin: 4px 0 0;">• Cable UTP: 1 bobina = 305 metros<br>• Clavos: 1 caja = 100 unidades<br>• Conectores: 1 paquete = 50 unidades</p>
            </div>
        </div>
        
        </section><section class="inventory-card"><h3><span>02</span> Control de inventario</h3>
        <div class="form-row" style="grid-template-columns: 1fr;">
            @if(!$material->exists)
            <div class="form-group">
                <label for="stock_actual">Stock inicial *</label>
                <input type="number" step="0.001" id="stock_actual" name="stock_actual" value="{{ old('stock_actual', 0) }}" min="0" max="999999999.999">
                <small>Genera ENTRADA si > 0</small>
                @error('stock_actual')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            @endif
            
            <div class="form-group">
                <label for="stock_minimo">Stock mínimo *</label>
                <input type="number" step="0.001" id="stock_minimo" name="stock_minimo" value="{{ old('stock_minimo', $material->stock_minimo) }}" min="0" max="999999999.999">
                <small>Para alertas</small>
                @error('stock_minimo')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="estado">Estado *</label>
                @if($material->exists && !auth()->user()->hasPermission('materials.status'))<input type="hidden" name="estado" value="{{ $material->estado }}">@endif
                <select id="estado" name="estado" @disabled($material->exists && !auth()->user()->hasPermission('materials.status'))>
                    @php
                        $estados = ['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'];
                    @endphp
                    @foreach($estados as $valor => $label)
                        <option value="{{ $valor }}" @selected(old('estado', $material->estado) === $valor)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('estado')<span class="error-message">{{ $message }}</span>@enderror
            </div>
        </div>
        
        </section></div><div class="form-actions">
            <button type="submit" class="action-link">{{ $material->exists ? 'Actualizar material' : 'Registrar material' }}</button>
            
@routecan('materials.show')<a href="{{ $material->exists ? route('materials.show', $material) : route('materials.index') }}" class="clear-link">Cancelar</a>@endroutecan

        </div>
    </form>@endroutecan
@endcan
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.standard-form');
    
    // Remover aria-invalid de todos los campos al cargar (para evitar bordes rojos iniciales)
    const allInputs = form.querySelectorAll('input, select, textarea');
    allInputs.forEach(input => {
        input.removeAttribute('aria-invalid');
    });
    
    // Manejar checkbox de empaque
    const usaEmpaque = document.getElementById('usa_empaque');
    const empaqueFields = document.getElementById('empaque-fields');
    const unidadBase = document.getElementById('unidad_base');
    const unidadEmpaque = document.getElementById('unidad_empaque');
    const factorConversion = document.getElementById('factor_conversion');
    const conversionHint = document.getElementById('conversion-hint');
    
    // Campos personalizados
    const unidadBaseCustomDiv = document.getElementById('unidad_base_otra');
    const unidadBaseCustomInput = document.getElementById('unidad_base_custom');
    const unidadEmpaqueCustomDiv = document.getElementById('unidad_empaque_otra');
    const unidadEmpaqueCustomInput = document.getElementById('unidad_empaque_custom');
    
    // Mostrar/ocultar campos de empaque
    usaEmpaque?.addEventListener('change', function() {
        if (this.checked) {
            empaqueFields.style.display = 'block';
        } else {
            empaqueFields.style.display = 'none';
            // Limpiar campos de empaque
            unidadEmpaque.value = '';
            factorConversion.value = '';
            conversionHint.textContent = '';
        }
    });
    
    // Manejar unidad base personalizada
    unidadBase?.addEventListener('change', function() {
        if (this.value === 'otras') {
            unidadBaseCustomDiv.style.display = 'block';
            unidadBaseCustomInput.focus();
        } else {
            unidadBaseCustomDiv.style.display = 'none';
            unidadBaseCustomInput.value = '';
        }
        updateConversionHint();
    });
    
    // Al escribir en el campo personalizado, actualizar el select
    unidadBaseCustomInput?.addEventListener('input', function() {
        updateConversionHint();
    });
    
    // Manejar unidad empaque personalizada
    unidadEmpaque?.addEventListener('change', function() {
        if (this.value === 'otro') {
            unidadEmpaqueCustomDiv.style.display = 'block';
            unidadEmpaqueCustomInput.focus();
        } else {
            unidadEmpaqueCustomDiv.style.display = 'none';
            unidadEmpaqueCustomInput.value = '';
        }
        updateConversionHint();
    });
    
    // Al escribir en el campo personalizado de empaque, actualizar
    unidadEmpaqueCustomInput?.addEventListener('input', function() {
        updateConversionHint();
    });
    
    // Actualizar hint de conversión
    function updateConversionHint() {
        let base = unidadBase?.value.trim();
        let empaque = unidadEmpaque?.value.trim();
        const factor = factorConversion?.value;
        
        // Si seleccionó "otras" u "otro", usar el valor personalizado
        if (base === 'otras' && unidadBaseCustomInput?.value.trim()) {
            base = unidadBaseCustomInput.value.trim();
        }
        if (empaque === 'otro' && unidadEmpaqueCustomInput?.value.trim()) {
            empaque = unidadEmpaqueCustomInput.value.trim();
        }
        
        if (base && empaque && factor && factor > 0) {
            conversionHint.textContent = `1 ${empaque} = ${factor} ${base}`;
            conversionHint.style.color = '#059669';
            conversionHint.style.fontWeight = '600';
        } else {
            conversionHint.textContent = '';
        }
    }
    
    factorConversion?.addEventListener('input', updateConversionHint);
    
    // Actualizar al cargar si hay valores
    updateConversionHint();
    
    // Al hacer submit, si hay valores personalizados, reemplazar en el select
    form.addEventListener('submit', function(e) {
        // Si seleccionó "otras" y escribió algo, usar ese valor
        if (unidadBase?.value === 'otras' && unidadBaseCustomInput?.value.trim()) {
            // Crear una opción temporal con el valor personalizado
            const customOption = document.createElement('option');
            customOption.value = unidadBaseCustomInput.value.trim();
            customOption.selected = true;
            unidadBase.appendChild(customOption);
        }
        
        // Lo mismo para empaque
        if (unidadEmpaque?.value === 'otro' && unidadEmpaqueCustomInput?.value.trim()) {
            const customOption = document.createElement('option');
            customOption.value = unidadEmpaqueCustomInput.value.trim();
            customOption.selected = true;
            unidadEmpaque.appendChild(customOption);
        }
        
        let hasErrors = false;
        
        // Validar campos requeridos básicos
        const requiredFields = [
            { id: 'nombre', label: 'Nombre del material' },
            { id: 'unidad_base', label: 'Unidad de medida' },
            { id: 'stock_minimo', label: 'Stock mínimo' },
            { id: 'estado', label: 'Estado' }
        ];
        
        // Agregar stock_actual solo si es un material nuevo
        @if(!$material->exists)
        requiredFields.push({ id: 'stock_actual', label: 'Stock inicial' });
        @endif
        
        requiredFields.forEach(field => {
            const input = document.getElementById(field.id);
            if (!input) return;
            
            const value = input.value.trim();
            
            if (!value) {
                hasErrors = true;
                input.style.borderColor = '#c34444';
                
                const errorMsg = input.parentElement.querySelector('.error-message');
                if (!errorMsg) {
                    const span = document.createElement('span');
                    span.className = 'error-message';
                    span.textContent = `El campo ${field.label} es obligatorio.`;
                    input.parentElement.appendChild(span);
                } else {
                    errorMsg.textContent = `El campo ${field.label} es obligatorio.`;
                    errorMsg.hidden = false;
                }
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.style.borderColor = '';
                const errorMsg = input.parentElement.querySelector('.error-message');
                if (errorMsg && !errorMsg.textContent.includes('ya está registrado')) {
                    errorMsg.remove();
                }
            }
        });
        
        // Si usa empaque, validar campos de empaque
        if (usaEmpaque.checked) {
            const empaqueVal = unidadEmpaque?.value.trim();
            const factor = factorConversion?.value;
            
            if (!empaqueVal) {
                hasErrors = true;
                unidadEmpaque.style.borderColor = '#c34444';
                
                const errorMsg = unidadEmpaque.parentElement.querySelector('.error-message');
                if (!errorMsg) {
                    const span = document.createElement('span');
                    span.className = 'error-message';
                    span.textContent = 'Debes seleccionar el tipo de empaque.';
                    unidadEmpaque.parentElement.appendChild(span);
                }
            } else if (empaqueVal === 'otro' && !unidadEmpaqueCustomInput?.value.trim()) {
                hasErrors = true;
                unidadEmpaqueCustomInput.style.borderColor = '#c34444';
                e.preventDefault();
                alert('Debes especificar el tipo de empaque personalizado');
                return;
            }
            
            if (!factor || factor <= 0) {
                hasErrors = true;
                factorConversion.style.borderColor = '#c34444';
                
                const errorMsg = factorConversion.parentElement.querySelector('.error-message');
                if (!errorMsg) {
                    const span = document.createElement('span');
                    span.className = 'error-message';
                    span.textContent = 'Debes especificar la cantidad por empaque.';
                    factorConversion.parentElement.appendChild(span);
                }
            }
        }
        
        // Validar unidad base personalizada
        if (unidadBase?.value === 'otras' && !unidadBaseCustomInput?.value.trim()) {
            hasErrors = true;
            unidadBaseCustomInput.style.borderColor = '#c34444';
            e.preventDefault();
            alert('Debes especificar la unidad de medida personalizada');
            return;
        }
        
        if (hasErrors) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    
    // Limpiar errores cuando el usuario empieza a escribir
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            this.style.borderColor = '';
            this.removeAttribute('aria-invalid');
            const errorMsg = this.parentElement.querySelector('.error-message');
            if (errorMsg && !errorMsg.textContent.includes('ya está registrado')) {
                errorMsg.remove();
            }
        });
    });
});
</script>
@endsection
