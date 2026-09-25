@extends('equipments.layout')

@section('equipments-content')
<div class="form-layout">
    <form class="standard-form" method="POST" action="{{ $equipment->exists ? route('equipments.update', $equipment) : route('equipments.store') }}">
        @csrf
        @if($equipment->exists)
            @method('PUT')
        @endif
        
        <h2>{{ $equipment->exists ? 'Editar equipo' : 'Registrar nuevo equipo' }}</h2><p class="form-intro">Completa los campos marcados con *. Los demás son opcionales.</p>
        
        <div class="inventory-grid"><section class="inventory-card"><h3><span>01</span> Identificación del equipo</h3>
        <div class="form-row" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label for="codigo">Código *</label>
                <input type="text" id="codigo" name="codigo" value="{{ old('codigo', $equipment->codigo) }}" required maxlength="60" @if($equipment->estado === 'ASIGNADO') readonly @endif>
                @error('codigo')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="numero_serie">N° Serie</label>
                <input type="text" id="numero_serie" name="numero_serie" value="{{ old('numero_serie', $equipment->numero_serie) }}" maxlength="100">
                @error('numero_serie')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="nombre">Nombre del equipo *</label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $equipment->nombre) }}" required maxlength="120">
                @error('nombre')<span class="error-message">{{ $message }}</span>@enderror
            </div>
        </div>
        
        <!-- Fila 2: Tipo, Marca, Modelo -->
        <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="form-group">
                <label for="tipo">Tipo *</label>
                <input type="text" id="tipo" name="tipo" value="{{ old('tipo', $equipment->tipo) }}" required maxlength="80" placeholder="Router, ONT, Switch...">
                @error('tipo')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="marca">Marca</label>
                <input type="text" id="marca" name="marca" value="{{ old('marca', $equipment->marca) }}" maxlength="80" placeholder="TP-Link, Huawei...">
                @error('marca')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="modelo">Modelo</label>
                <input type="text" id="modelo" name="modelo" value="{{ old('modelo', $equipment->modelo) }}" maxlength="100" placeholder="Archer C6, HG8546M...">
                @error('modelo')<span class="error-message">{{ $message }}</span>@enderror
            </div>
        </div>
        
        </section><section class="inventory-card"><h3><span>02</span> Estado y condición</h3>
        <div class="form-row" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label for="condicion">Condición *</label>
                <select id="condicion" name="condicion" required>
                    @foreach(['NUEVO' => 'Nuevo', 'BUENO' => 'Bueno', 'REGULAR' => 'Regular', 'MALO' => 'Malo', 'MALOGRADO' => 'Malogrado'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('condicion', $equipment->condicion) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('condicion')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group">
                <label for="estado">Estado *</label>
                <select id="estado" name="estado" required @if($equipment->estado === 'ASIGNADO') disabled @endif>
                    @foreach(['DISPONIBLE' => 'Disponible', 'ASIGNADO' => 'Asignado', 'MANTENIMIENTO' => 'Mantenimiento', 'INACTIVO' => 'Inactivo'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('estado', $equipment->estado) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($equipment->estado === 'ASIGNADO')
                    <input type="hidden" name="estado" value="ASIGNADO">
                    <small style="color: #dc2626;">⚠️ Equipo asignado</small>
                @endif
                @error('estado')<span class="error-message">{{ $message }}</span>@enderror
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="2" maxlength="500" placeholder="Notas adicionales...">{{ old('observaciones', $equipment->observaciones) }}</textarea>
                @error('observaciones')<span class="error-message">{{ $message }}</span>@enderror
            </div>
        </div>
        
        </section></div><div class="form-actions">
            <button type="submit" class="action-link">{{ $equipment->exists ? 'Actualizar equipo' : 'Registrar equipo' }}</button>
            <a href="{{ $equipment->exists ? route('equipments.show', $equipment) : route('equipments.index') }}" class="clear-link">Cancelar</a>
        </div>
    </form>
</div>
@endsection
