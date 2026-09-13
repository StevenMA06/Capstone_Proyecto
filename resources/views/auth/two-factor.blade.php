@extends('layouts.app')
@section('title', 'Verificar acceso')
@section('content')
<section class="dashboard management two-factor-panel">
    <span class="eyebrow">VERIFICACIÓN DE ACCESO</span>
    <h1>Revisa tu correo</h1>
    <p class="muted">Enviamos un código de 6 dígitos al correo registrado de tu cuenta. Vence en {{ config('access.code_minutes') }} minutos.</p>
    @if(session('status'))<p class="notice success" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div id="code-error" class="notice error-summary" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('two-factor.store') }}">@csrf
        <label for="code">Código temporal</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
        <button class="primary" type="submit">Verificar e ingresar</button>
    </form>
    <p class="muted">Puedes solicitar otro código después de 60 segundos. Revisa también la carpeta de correo no deseado.</p>
    <div class="row-actions">
        <form method="POST" action="{{ route('two-factor.resend') }}">@csrf<button class="secondary" type="submit">Reenviar código</button></form>
        <form method="POST" action="{{ route('two-factor.cancel') }}">@csrf<button class="secondary" type="submit">Volver al inicio de sesión</button></form>
    </div>
</section>
@endsection
