@extends('layouts.management')
@section('title', $title)
@section('workspace-content')
<header class="module-heading"><h1>{{ $title }}</h1><p>Consulta limitada a la información autorizada para tu cuenta.</p></header>
<div class="client-list-panel"><div class="table-scroll"><table class="users-table"><thead><tr>@foreach($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($records as $record)<tr>@foreach($columns as $key=>$label)<td>{{ $record->$key ?? '—' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($columns) }}">No hay registros disponibles.</td></tr>@endforelse
</tbody></table></div>@include('clients.pagination',['records'=>$records,'label'=>'Paginación'])</div>
@endsection
