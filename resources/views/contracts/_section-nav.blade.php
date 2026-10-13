<nav class="contracts-section-nav" aria-label="Secciones de contratos">
    <a href="{{ route('contracts.index') }}" @if(($active ?? '') === 'contracts') aria-current="page" @endif>Contratos</a>
    @if(auth()->user()->hasPermission('contracts.cancel.inbox'))
        <a href="{{ route('contracts.cancellations') }}" @if(($active ?? '') === 'cancellations') aria-current="page" @endif>
            Solicitudes de cancelación
            @if(($pendingCancellationCount ?? 0) > 0)<span class="contracts-nav-count" aria-label="{{ $pendingCancellationCount }} pendientes">{{ $pendingCancellationCount }}</span>@endif
        </a>
    @endif
</nav>
