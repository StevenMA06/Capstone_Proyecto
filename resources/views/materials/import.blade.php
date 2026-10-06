@extends('materials.layout')
@section('materials-content')
<div class="standard-form">
    <h2>Importar materiales desde Excel</h2>
    <p class="form-intro">Descarga la plantilla con tus materiales actuales, agrégales stock o crea nuevos, y súbela de vuelta.</p>
    
    <div class="inventory-grid">
        <section class="inventory-card">
            <h3><span>02</span> Cargar archivo</h3>
            <p>El sistema creará nuevos materiales o actualizará el stock de los existentes. Cada movimiento generará su registro en el Kardex.</p>
            <form class="standard-form" method="POST" action="{{ route('materials.import.process') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="archivo">Archivo Excel *</label>
                    <input type="file" id="archivo" name="archivo" accept=".xlsx,.xls" required>
                    <small>Formatos .xlsx y .xls · Tamaño máximo: 2 MB.</small>
                    @error('archivo')<span class="error-message">{{ $message }}</span>@enderror
                </div>
                <div class="inventory-note">
                    <strong>¿Qué hace la importación?</strong>
                    <ul style="margin: 0.5rem 0 0 1.25rem;">
                        <li><strong>Materiales existentes:</strong> Si el código ya existe, solo se actualiza el stock según "cantidad_ingreso".</li>
                        <li><strong>Materiales nuevos:</strong> Si el código no existe, se crea el material con su stock inicial.</li>
                        <li>Podrás revisar las filas válidas y los errores antes de confirmar.</li>
                    </ul>
                </div>
                <div class="form-actions">
                    <button type="submit" class="action-link">Validar archivo</button>
                    <a href="{{ route('materials.index') }}" class="clear-link">Volver al listado</a>
                </div>
            </form>
        </section>
        
        <aside class="inventory-card">
            <h3><span>01</span> Preparar los datos</h3>
            <ol>
                <li>Descarga la plantilla Excel.</li>
                <li>Revisa los materiales actuales pre-cargados.</li>
                <li>Llena "cantidad_ingreso" para agregar stock a existentes.</li>
                <li>Usa las filas vacías para crear nuevos materiales.</li>
                <li>Guarda y sube el archivo.</li>
            </ol>
            <a href="{{ route('materials.template') }}" class="clear-link" style="margin-top: 1rem; display: inline-block;">
                📥 Descargar plantilla Excel
            </a>
            <div class="inventory-note" style="margin-top: 1rem;">
                <strong>Incluye instrucciones:</strong>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem;">La plantilla tiene una hoja de instrucciones detalladas con ejemplos y descripción de cada columna.</p>
            </div>
        </aside>
    </div>
</div>
@endsection
