@extends('equipments.layout')
@section('equipments-content')
<div class="form-layout">
<div class="standard-form">
    <h2>Importar equipos desde Excel</h2>
    <p class="muted">Carga múltiples equipos desde un archivo Excel. Cada equipo creará automáticamente su movimiento de entrada en el Kardex.</p>
    
    <div class="notice" role="alert" style="background: #dbeafe; border-left: 4px solid #3b82f6; padding: 1rem; margin-bottom: 1.5rem;">
        <h4 style="margin: 0 0 0.5rem 0;">📋 Instrucciones</h4>
        <ol style="margin: 0; padding-left: 1.25rem;">
            <li>Descarga la plantilla Excel haciendo clic en el botón de abajo</li>
            <li>Completa las filas con los datos de tus equipos</li>
            <li>Guarda el archivo y súbelo usando el formulario</li>
            <li>Revisa la vista previa de validación antes de confirmar</li>
        </ol>
    </div>
    
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('equipments.template') }}" class="action-link" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <span aria-hidden="true">📥</span> Descargar plantilla Excel
        </a>
    </div>
    
    <form method="POST" action="{{ route('equipments.import.process') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="archivo">Archivo Excel *</label>
            <input type="file" id="archivo" name="archivo" accept=".xlsx,.xls" required>
            <small>Formatos: .xlsx, .xls (máximo 2MB)</small>
            @error('archivo')<span class="error-message">{{ $message }}</span>@enderror
        </div>
        
        <div class="form-actions">
            <button type="submit" class="action-link">Procesar archivo</button>
            <a href="{{ route('equipments.index') }}" class="clear-link">Volver al listado</a>
        </div>
    </form>
</div>
</div>
@endsection
