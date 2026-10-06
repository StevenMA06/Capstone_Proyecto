@extends('payments.layout')
@section('payments-content')

{{-- RF-PAG-16, RF-PAG-17: Indicadores por estado de pago --}}
<div class="payment-status-stats">
    <div class="stats-header">
        <h2>Estadísticas de Pagos</h2>
        
@routecan('payments.index')<form method="GET" action="{{ route('payments.index') }}" class="month-filter-form">
            <label for="month-filter">Mes:</label>
            <select 
                id="month-filter" 
                name="month"
                onchange="this.form.submit()"
                style="min-width: 200px; padding: 6px 12px; border-radius: 4px; border: 1px solid #d1d5db;"
            >
                <option value="">Todos los meses</option>
                @php
                    // Generar meses: año actual y siguiente
                    $currentYear = now()->year;
                    $months = [];
                    
                    for ($y = $currentYear; $y <= $currentYear + 1; $y++) {
                        for ($m = 1; $m <= 12; $m++) {
                            $months[] = sprintf('%d-%02d', $y, $m);
                        }
                    }
                @endphp
                
                @foreach($months as $monthValue)
                    <option 
                        value="{{ $monthValue }}" 
                        @selected($selectedMonth === $monthValue)
                    >
                        {{ \Carbon\Carbon::parse($monthValue . '-01')->locale('es')->isoFormat('MMMM [de] YYYY') }}
                    </option>
                @endforeach
            </select>
        </form>@endroutecan

    </div>
    
    <div class="stats-grid">
        @php
            $statusConfig = [
                'PENDIENTE' => [
                    'icon' => 'bi-clock-history',
                    'color' => '#f59e0b',
                    'label' => 'Pendientes',
                    'description' => 'Por cobrar'
                ],
                'VENCIDO' => [
                    'icon' => 'bi-exclamation-triangle-fill',
                    'color' => '#ef4444',
                    'label' => 'Vencidos',
                    'description' => 'Requieren atención'
                ],
                'ANULADO' => [
                    'icon' => 'bi-slash-circle',
                    'color' => '#6b7280',
                    'label' => 'Anulados',
                    'description' => 'Error administrativo'
                ],
                'REALIZADO' => [
                    'icon' => 'bi-check-circle-fill',
                    'color' => '#10b981',
                    'label' => 'Realizados',
                    'description' => 'Pagados'
                ]
            ];
            // Calcular total solo con los estados (excluir 'total' y 'month' del array $statistics)
            $totalPayments = ($statistics['PENDIENTE'] ?? 0) + 
                           ($statistics['VENCIDO'] ?? 0) + 
                           ($statistics['IMPAGO'] ?? 0) + 
                           ($statistics['ANULADO'] ?? 0) + 
                           ($statistics['REALIZADO'] ?? 0);
        @endphp
        
        @foreach($statusConfig as $status => $config)
            @php
                $count = $statistics[$status] ?? 0;
                $percentage = $totalPayments > 0 ? round(($count / $totalPayments) * 100) : 0;
                $isFiltered = request('status') === $status;
            @endphp
            
@routecan('payments.index')<a 
                href="{{ route('payments.index', array_merge(request()->except('status', 'page'), ['status' => $status])) }}" 
                class="stat-card clickable {{ $isFiltered ? 'filtered' : '' }} {{ $status === 'PENDIENTE' || $status === 'VENCIDO' ? 'urgent' : '' }}"
                style="border-left-color: {{ $config['color'] }};"
                title="Clic para filtrar por {{ $config['label'] }}"
            >
                <div class="stat-icon" style="background: {{ $config['color'] }}20;">
                    <i class="bi {{ $config['icon'] }}" style="font-size: 1.5rem; color: {{ $config['color'] }};"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ $config['label'] }}</h3>
                    <p class="stat-number">{{ $count }}</p>
                    <p class="stat-label">{{ $config['description'] }}</p>
                    @if($totalPayments > 0)
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: {{ $percentage }}%; background: {{ $config['color'] }};"></div>
                        </div>
                        <p class="stat-percentage">{{ $percentage }}% del total</p>
                    @endif
                </div>
                @if($isFiltered)
                    <span class="badge-filtered">🔍 Filtrado</span>
                @endif
            </a>@endroutecan

        @endforeach
    </div>
    
    @if($totalPayments === 0)
        <p class="stats-empty">
            @if($selectedMonth)
                No hay pagos en {{ \Carbon\Carbon::parse($selectedMonth)->locale('es')->isoFormat('MMMM [de] YYYY') }}.
            @else
                No hay pagos registrados en el sistema.
            @endif
        </p>
    @else
        <p class="stats-summary">
            Total de pagos 
            @if($selectedMonth)
                en {{ \Carbon\Carbon::parse($selectedMonth)->locale('es')->isoFormat('MMMM [de] YYYY') }}
            @else
                en <strong>todos los meses</strong>
            @endif
            : 
            <strong>{{ $totalPayments }}</strong>
        </p>
    @endif
</div>

<div class="clients-grid">
<aside class="client-filter-panel" aria-labelledby="filter-title">
    <h2 id="filter-title">Filtros y buscador</h2>
    <p class="muted">Encuentra los pagos que necesitas.</p>
    
@routecan('payments.index')<form class="filters client-filters" method="GET" action="{{ route('payments.index') }}">
        <div>
            <label for="search">Cliente o documento</label>
            <input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar por documento…" maxlength="180">
            <small>Número de documento del cliente.</small>
        </div>
        <div>
            <label for="contract">Código de contrato</label>
            <input id="contract" name="contract" value="{{ request('contract') }}" placeholder="Ej: CON-2026-001" maxlength="40">
            <small>Buscar pagos de un contrato específico.</small>
        </div>
<!--         <fieldset>
            <legend>Estado del pago</legend>
            @foreach(['' => 'Todos los estados', 'PENDIENTE' => 'Pendiente', 'REALIZADO' => 'Realizado', 'VENCIDO' => 'Vencido', 'ANULADO' => 'Anulado'] as $value => $label)
                <label class="state-option"><input type="radio" name="status" value="{{ $value }}" @checked((request('status') ?? '') === $value)><span>{{ $label }}</span></label>
            @endforeach
        </fieldset> -->
        <div>
            <label for="from">Desde (vencimiento)</label>
            <input id="from" name="from" type="date" value="{{ request('from') }}">
        </div>
        <div>
            <label for="to">Hasta (vencimiento)</label>
            <input id="to" name="to" type="date" value="{{ request('to') }}">
        </div>
        <div class="filter-buttons">
            <button class="action-link" type="submit">Aplicar filtros</button>
            
@routecan('payments.index')<a class="clear-link" href="{{ route('payments.index') }}">Limpiar filtros</a>@endroutecan

        </div>
    </form>@endroutecan

</aside>
<section class="client-list-panel" aria-labelledby="list-title">
    <div class="management-toolbar">
        <div>
            <h2 id="list-title">Listado de pagos</h2>
            <p class="muted">{{ $payments->total() }} {{ $payments->total() === 1 ? 'pago encontrado' : 'pagos encontrados' }}</p>
        </div>
        @routecan('payments.create')
        <a class="action-link" href="{{ route('payments.create') }}">Registrar pago</a>
        @endroutecan
    </div>
    <div class="table-scroll">
        <table class="users-table client-table">
            <caption class="sr-only">Pagos registrados</caption>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Contrato</th>
                    <th>Monto</th>
                    <th>F. Vencimiento</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $payment)
            <tr>
                <td>
                    <strong>{{ $payment->codigo }}</strong>
                    @if($payment->periodo_servicio)
                        <small class="table-subtitle">{{ $payment->periodo_servicio }}</small>
                    @endif
                </td>
                <td>
                    <span class="client-name">{{ trim(($payment->contract->client->nombres_razon_social ?? '') . ' ' . ($payment->contract->client->apellidos ?? '')) }}</span>
                    <small class="table-subtitle">{{ $payment->contract->client->tipo_documento ?? '' }} {{ $payment->contract->client->numero_documento ?? '' }}</small>
                </td>
                <td>
                    <strong>{{ $payment->contract->codigo ?? '' }}</strong>
                    @if($payment->contract->plan)
                        <small class="table-subtitle">{{ $payment->contract->plan->nombre }}</small>
                    @endif
                </td>
                <td><strong>S/ {{ number_format($payment->monto, 2) }}</strong></td>
                <td>
                    {{ \Carbon\Carbon::parse($payment->fecha_vencimiento)->format('d/m/Y') }}
                    @php
                        $vencimiento = \Carbon\Carbon::parse($payment->fecha_vencimiento);
                        $hoy = \Carbon\Carbon::today();
                        $diasRestantes = $hoy->diffInDays($vencimiento, false);
                    @endphp
                    @if($payment->estado === 'PENDIENTE' && $diasRestantes <= 7 && $diasRestantes >= 0)
                        <small class="table-subtitle" style="color: #d97706;">⚠ {{ $diasRestantes }} {{ $diasRestantes === 1 ? 'día' : 'días' }}</small>
                    @elseif($payment->estado === 'PENDIENTE' && $diasRestantes < 0)
                        <small class="table-subtitle" style="color: #dc2626;">⚠ Vencido</small>
                    @endif
                </td>
                <td>
                    <span class="state {{ 
                        $payment->estado === 'REALIZADO' ? 'active' : 
                        ($payment->estado === 'VENCIDO' || $payment->estado === 'IMPAGO' ? 'inactive' : '') 
                    }}" style="
                        @if($payment->estado === 'VENCIDO') background: #fee2e2; color: #991b1b; @endif
                        @if($payment->estado === 'IMPAGO') background: #ede9fe; color: #5b21b6; @endif
                        @if($payment->estado === 'PENDIENTE') background: #fef3c7; color: #92400e; @endif
                        @if($payment->estado === 'ANULADO') background: #e5e7eb; color: #374151; @endif
                    ">
                        {{ ucfirst(strtolower($payment->estado)) }}
                    </span>
                    @if($payment->fecha_pago)
                        <small class="table-subtitle">{{ \Carbon\Carbon::parse($payment->fecha_pago)->format('d/m/Y') }}</small>
                    @endif
                </td>
                <td>
                    <div class="row-actions">
                        
@routecan('payments.show')<a href="{{ route('payments.show', $payment) }}" aria-label="Ver detalle del pago {{ $payment->codigo }}">Ver detalle</a>@endroutecan

                        @if($payment->estado === 'PENDIENTE' || $payment->estado === 'VENCIDO')
                            @can('payments.manage')
@routecan('payments.edit')<a href="{{ route('payments.edit', $payment) }}" aria-label="Validar pago {{ $payment->codigo }}">Validar pago</a>@endroutecan
@endcan
                        @endif
                        @if($payment->constancia_ruta && $payment->estado === 'REALIZADO')
                            
@routecan('payments.receipt')<a href="{{ route('payments.receipt', $payment) }}" target="_blank" aria-label="Ver comprobante del pago {{ $payment->codigo }}" title="Ver comprobante PDF">
                                <i class="bi bi-file-pdf-fill" style="color: #dc2626;"></i> Comprobante
                            </a>@endroutecan

                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="empty-state">No se encontraron pagos con esos criterios.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('payments.pagination', ['records' => $payments, 'label' => 'Paginación de pagos'])
</section>
</div>
@endsection
