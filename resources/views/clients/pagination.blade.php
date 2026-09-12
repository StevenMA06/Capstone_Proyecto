<nav class="pagination" aria-label="{{ $label }}">
    <span>{{ $records->total() }} registros · Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span>
    <div>
        @if($records->previousPageUrl())<a class="secondary" href="{{ $records->previousPageUrl() }}">Anterior</a>@endif
        @if($records->nextPageUrl())<a class="secondary" href="{{ $records->nextPageUrl() }}">Siguiente</a>@endif
    </div>
</nav>
