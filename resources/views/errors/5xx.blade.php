@extends('errors::minimal')
@section('title', 'Error del servidor')
@section('code', $exception->getStatusCode())
@section('message', 'No se pudo completar la operación. Inténtalo nuevamente más tarde.')
