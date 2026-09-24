@extends('errors::minimal')
@section('title', 'Solicitud no disponible')
@section('code', $exception->getStatusCode())
@section('message', 'No se pudo completar esta solicitud. Revisa la dirección e inténtalo nuevamente.')
