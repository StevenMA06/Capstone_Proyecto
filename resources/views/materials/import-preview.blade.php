@extends('materials.layout')
@section('materials-content')
<div class="form-layout">
<div class="standard-form inventory-card">
    <h2>Vista previa de importación</h2>
    <p class="muted">Archivo: <strong>{{ $fileName }}</strong></p>
    
    @if(count($valid) > 0)
    <div class="notice" role="alert" style="background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; margin-bottom: 1.5rem;">
        <h4 style="margin: 0 0 0.5rem 0; color: #065f46;">✓ {{ count($valid) }} {{ count($valid) === 1 ? 'fila válida' : 'filas válidas' }}</h4>
        <p style="margin: 0;">
            @php
                $existing = collect($valid)->filter(fn($row) => \App\Repositories\MaterialRepository::class && \App\Models\Material::where('codigo', $row['data']['codigo'])->exists())->count();
                $new = count($valid) - $existing;
            @endphp
            Se procesarán correctamente.
            @if($new > 0 && $existing > 0)
                Se crearán {{ $new }} {{ $new === 1 ? 'material nuevo' : 'materiales nuevos' }} y se actualizarán {{ $existing }} {{ $existing === 1 ? 'existente' : 'existentes' }}.
            @elseif($new > 0)
                Se {{ $new === 1 ? 'creará' : 'crearán' }} {{ $new }} {{ $new === 1 ? 'material nuevo' : 'materiales nuevos' }}.
            @else
                Se {{ $existing === 1 ? 'actualizará' : 'actualizarán' }} {{ $existing }} {{ $existing === 1 ? 'material existente' : 'materiales existentes' }}.
            @endif
        </p>
    </div>
    
    <details open style="margin-bottom: 1.5rem;">
        <summary style="cursor: pointer; font-weight: 600; padding: 0.5rem; background: #f3f4f6; border-radius: 0.25rem;">Ver filas válidas ({{ count($valid) }})</summary>
        <div class="table-scroll" style="margin-top: 1rem;">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Fila</th>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Unidad base</th>
                        <th>Cantidad ingreso</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($valid as $row)
                    @php
                        $exists = \App\Models\Material::where('codigo', $row['data']['codigo'])->exists();
                        $cantidadIngreso = $row['data']['cantidad_ingreso'] ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $row['row_number'] }}</td>
                        <td><strong>{{ $row['data']['codigo'] }}</strong></td>
                        <td>{{ $row['data']['nombre'] ?: '—' }}</td>
                        <td>{{ $row['data']['unidad_base'] ?? '—' }}</td>
                        <td>
                            @if($cantidadIngreso > 0)
                                <strong style="color: #059669;">+{{ number_format($cantidadIngreso, 2) }}</strong>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($exists)
                                <span style="color: #2563eb;">● Actualizar</span>
                            @else
                                <span style="color: #059669;">★ Crear nuevo</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
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
            @if(!empty($row['data']['nombre']))
                <span style="color: #6b7280;">· {{ $row['data']['nombre'] }}</span>
            @endif
            <ul style="margin: 0.5rem 0 0 1.25rem; color: #991b1b;">
            @foreach($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endforeach
        </div>
    </details>
    @endif
    
    @if(count($valid) === 0 && count($invalid) === 0)
    <div class="notice" style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem;">
        <p style="margin: 0;">No se encontraron filas válidas para importar.</p>
    </div>
    @endif
    
    <div class="form-actions">
        @if(count($valid) > 0)
        <form method="POST" action="{{ route('materials.import.confirm') }}" style="display: inline;">
            @csrf
            <button type="submit" class="action-link">
                Confirmar importación ({{ count($valid) }} {{ count($valid) === 1 ? 'fila' : 'filas' }})
            </button>
        </form>
        @endif
        <form method="POST" action="{{ route('materials.import.cancel') }}" style="display: inline;">
            @csrf
            <button type="submit" class="clear-link" style="cursor: pointer;">
                @if(count($valid) > 0) Cancelar @else Volver @endif
            </button>
        </form>
    </div>
</div>
</div>
@endsection
