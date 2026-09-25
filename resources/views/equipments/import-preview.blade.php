@extends('equipments.layout')
@section('equipments-content')
<div class="form-layout">
<div class="standard-form inventory-card">
    <h2>Vista previa de importación</h2>
    <p class="muted">Archivo: <strong>{{ $fileName }}</strong></p>
    
    @if(count($valid) > 0)
    <div class="notice" role="alert" style="background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; margin-bottom: 1.5rem;">
        <h4 style="margin: 0 0 0.5rem 0; color: #065f46;">✓ {{ count($valid) }} {{ count($valid) === 1 ? 'fila válida' : 'filas válidas' }}</h4>
        <p style="margin: 0;">{{ count($valid) === 1 ? 'Este equipo' : 'Estos equipos' }} se {{ count($valid) === 1 ? 'creará' : 'crearán' }} correctamente.</p>
    </div>
    
    <details open style="margin-bottom: 1.5rem;">
        <summary style="cursor: pointer; font-weight: 600; padding: 0.5rem; background: #f3f4f6; border-radius: 0.25rem;">Ver filas válidas ({{ count($valid) }})</summary>
        <div class="table-scroll" style="margin-top: 1rem;"><table class="users-table"><thead><tr><th>Fila</th><th>Código</th><th>Nombre</th><th>Tipo</th><th>Serie</th></tr></thead><tbody>
        @foreach($valid as $row)<tr><td>{{ $row['row_number'] }}</td><td>{{ $row['data']['codigo'] }}</td><td>{{ $row['data']['nombre'] }}</td><td>{{ $row['data']['tipo'] }}</td><td>{{ $row['data']['numero_serie'] ?? '—' }}</td></tr>@endforeach
        </tbody></table></div>
    </details>
    @endif
    
    @if(count($invalid) > 0)
    <div class="notice error-summary" role="alert">
        <h4 style="margin: 0 0 0.5rem 0;">✗ {{ count($invalid) }} {{ count($invalid) === 1 ? 'fila inválida' : 'filas inválidas' }}</h4>
        <p style="margin: 0;">{{ count($invalid) === 1 ? 'Esta fila' : 'Estas filas' }} no se {{ count($invalid) === 1 ? 'importará' : 'importarán' }} debido a errores.</p>
    </div>
    
    <details open style="margin-bottom: 1.5rem;">
        <summary style="cursor: pointer; font-weight: 600; padding: 0.5rem; background: #fee2e2; border-radius: 0.25rem; color: #991b1b;">Ver filas inválidas ({{ count($invalid) }})</summary>
        <div style="margin-top: 1rem;">
        @foreach($invalid as $row)
        <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; margin-bottom: 1rem;">
            <strong>Fila {{ $row['row_number'] }}</strong> - {{ $row['data']['codigo'] ?? 'sin código' }}
            <ul style="margin: 0.5rem 0 0 1.25rem; color: #991b1b;">
            @foreach($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endforeach
        </div>
    </details>
    @endif
    
    <div class="form-actions">
        @if(count($valid) > 0)
        <form method="POST" action="{{ route('equipments.import.confirm') }}" style="display: inline;">
            @csrf
            <button type="submit" class="action-link">Confirmar importación ({{ count($valid) }} {{ count($valid) === 1 ? 'equipo' : 'equipos' }})</button>
        </form>
        @endif
        <form method="POST" action="{{ route('equipments.import.cancel') }}" style="display: inline;">
            @csrf
            <button type="submit" class="clear-link" style="cursor: pointer;">Cancelar</button>
        </form>
    </div>
</div>
</div>
@endsection
