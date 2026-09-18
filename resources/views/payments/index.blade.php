@extends('payments.layout')
@section('payments-content')
<div class="clients-grid">
<aside class="client-filter-panel" aria-labelledby="filter-title">
    <h2 id="filter-title">Filtros y buscador</h2>
    <p class="muted">Encuentra los pagos que necesitas.</p>
    <form class="filters client-filters" method="GET" action="{{ route('payments.index') }}">
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
        <fieldset>
            <legend>Estado del pago</legend>
            @foreach(['' => 'Todos los estados', 'PENDIENTE' => 'Pendiente', 'REALIZADO' => 'Realizado', 'VENCIDO' => 'Vencido', 'ANULADO' => 'Anulado'] as $value => $label)
                <label class="state-option"><input type="radio" name="status" value="{{ $value }}" @checked((request('status') ?? '') === $value)><span>{{ $label }}</span></label>
            @endforeach
        </fieldset>
        <div>
            <label for="from">Desde (vencimiento)</label>
            <input id="from" name="from" type="date" value="{{ request('from') }}">
        </div>
        <div>
            <label for="to">Hasta (vencimiento)</label>
            <input id="to" name="to" type="date" value="{{ request('to') }}">
        </div>
        <fieldset>
            <legend>Filtros especiales</legend>
            <label class="state-option">
                <input type="checkbox" name="due_soon" value="1" @checked(request('due_soon'))>
                <span>Próximos a vencer (7 días)</span>
            </label>
            <label class="state-option">
                <input type="checkbox" name="overdue" value="1" @checked(request('overdue'))>
                <span>Vencidos</span>
            </label>
        </fieldset>
        <div class="filter-buttons">
            <button class="action-link" type="submit">Aplicar filtros</button>
            <a class="clear-link" href="{{ route('payments.index') }}">Limpiar filtros</a>
        </div>
    </form>
</aside>
<section class="client-list-panel" aria-labelledby="list-title">
    <div class="management-toolbar">
        <div>
            <h2 id="list-title">Listado de pagos</h2>
            <p class="muted">{{ $payments->total() }} {{ $payments->total() === 1 ? 'pago encontrado' : 'pagos encontrados' }}</p>
        </div>
        <a class="action-link" href="{{ route('payments.create') }}"><span aria-hidden="true">＋</span> Registrar pago</a>
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
                        ($payment->estado === 'VENCIDO' ? 'inactive' : 
                        ($payment->estado === 'ANULADO' ? 'inactive' : '')) 
                    }}" style="
                        @if($payment->estado === 'VENCIDO') background: #fee2e2; color: #991b1b; @endif
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
                        <a href="{{ route('payments.show', $payment) }}" aria-label="Ver detalle del pago {{ $payment->codigo }}">Ver detalle</a>
                        @if($payment->estado === 'PENDIENTE' || $payment->estado === 'VENCIDO')
                            <a href="{{ route('payments.edit', $payment) }}" aria-label="Editar pago {{ $payment->codigo }}">Editar</a>
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
