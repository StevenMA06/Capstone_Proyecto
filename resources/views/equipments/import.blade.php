@extends('equipments.layout')
@section('equipments-content')
<div class="standard-form">
    <h2>Importar equipos desde Excel</h2>
    <p class="form-intro">Prepara el archivo, revisa los resultados y confirma la importación.</p>
    <div class="inventory-grid">
        <section class="inventory-card">
            <h3><span>02</span> Cargar archivo</h3>
            <p>Cada equipo importado generará su movimiento de entrada en el Kardex.</p>
            <form class="standard-form" method="POST" action="{{ route('equipments.import.process') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="archivo">Archivo Excel *</label>
                    <input type="file" id="archivo" name="archivo" accept=".xlsx,.xls" required>
                    <small>Formatos .xlsx y .xls · Tamaño máximo: 2 MB.</small>
                    @error('archivo')<span class="error-message">{{ $message }}</span>@enderror
                </div>
                <div class="inventory-note">Podrás revisar las filas válidas y los errores antes de guardar los equipos.</div>
                <div class="form-actions">
                    <button type="submit" class="action-link">Validar archivo</button>
                    <a href="{{ route('equipments.index') }}" class="clear-link">Volver al listado</a>
                </div>
            </form>
        </section>
        <aside class="inventory-card">
            <h3><span>01</span> Preparar los datos</h3>
            <ol><li>Descarga la plantilla.</li><li>Completa los datos de tus equipos.</li><li>Conserva los encabezados originales.</li><li>Guarda y selecciona el archivo.</li></ol>
            <a href="{{ route('equipments.template') }}" class="clear-link">Descargar plantilla Excel</a>
            <p>Usa códigos y números de serie únicos. La vista previa te indicará qué filas necesitan corrección.</p>
        </aside>
    </div>
</div>
@endsection
