@extends('materials.layout')

@section('materials-content')
<div class="form-layout">
    <form class="standard-form" method="POST" action="{{ $material->exists ? route('materials.update', $material) : route('materials.store') }}">
        @csrf
        @if($material->exists)
            @method('PUT')
        @endif
        
        <h2>{{ $material->exists ? 'Editar material' : 'Registrar nuevo material' }}</h2>
        
        @if($material->exists)
        <div class="alert alert-info" style="background: #e0f2fe; border-left: 4px solid #0284c7; padding: 0.75rem 1rem; margin-bottom: 1.5rem; border-radius: 4px;">
            <strong>⚠️ Nota:</strong> El stock actual NO se puede editar directamente. Usa Ingreso, Salida o Ajuste.
        </div>
        @endif
        
        <!-- Fila 1: Código, Nombre, Unidad -->
        <div class="form-row" style="grid-template-columns: 1fr 2fr 1fr;">
            <div class="form-group">
                <label for="codigo">Código *</label>
                <input type="text" id="codigo" name="codigo" value="{{ old('codigo', $material->codigo) }}" required maxlength="60">
                @error('codigo')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="nombre">Nombre del material *</label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $material->nombre) }}" required maxlength="120">
                @error('nombre')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="unidad_medida">Unidad *</label>
                <input type="text" id="unidad_medida" name="unidad_medida" value="{{ old('unidad_medida', $material->unidad_medida) }}" required maxlength="30" placeholder="metros, kg, unidades...">
                @error('unidad_medida')<span class="error-message">{{ $message }}</span>@enderror
            </div>
        </div>
        
        <!-- Fila 2: Descripción (ancho completo) -->
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="500" placeholder="Descripción detallada del material...">{{ old('descripcion', $material->descripcion) }}</textarea>
            @error('descripcion')<span class="error-message">{{ $message }}</span>@enderror
        </div>
        
        <!-- Fila 3: Stock inicial/mínimo, Estado -->
        <div class="form-row" style="grid-template-columns: {{ !$material->exists ? '1fr 1fr 1fr' : '1fr 1fr' }};">
            @if(!$material->exists)
            <div class="form-group">
                <label for="stock_actual">Stock inicial *</label>
                <input type="number" step="0.001" id="stock_actual" name="stock_actual" value="{{ old('stock_actual', 0) }}" required min="0">
                <small>Genera ENTRADA si > 0</small>
                @error('stock_actual')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            @endif
            
            <div class="form-group">
                <label for="stock_minimo">Stock mínimo *</label>
                <input type="number" step="0.001" id="stock_minimo" name="stock_minimo" value="{{ old('stock_minimo', $material->stock_minimo) }}" required min="0">
                <small>Para alertas</small>
                @error('stock_minimo')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="estado">Estado *</label>
                <select id="estado" name="estado" required>
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
        
        <div class="form-actions">
            <button type="submit" class="action-link">{{ $material->exists ? 'Actualizar material' : 'Registrar material' }}</button>
            <a href="{{ $material->exists ? route('materials.show', $material) : route('materials.index') }}" class="clear-link">Cancelar</a>
        </div>
    </form>
</div>
@endsection
