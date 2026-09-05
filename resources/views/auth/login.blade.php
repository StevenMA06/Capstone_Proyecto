@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('content')
<div class="login-shell">
    <section class="welcome-panel" aria-labelledby="welcome-heading">
        <div class="world-map" aria-hidden="true"></div>
        <div class="welcome-copy">
            <h1 id="welcome-heading">Bienvenido de nuevo</h1>
            <p>Accede a tu cuenta para continuar gestionando tus proyectos y descubrir nuevas herramientas.</p>
        </div>
        <span class="map-beacon" aria-hidden="true"><span></span></span>
        <svg class="panel-waves" viewBox="0 0 600 180" preserveAspectRatio="none" aria-hidden="true"><path d="M0 115C170 0 300 110 600 180H0Z" fill="#00b276" opacity=".46"/><path d="M0 145C180 20 340 160 600 180H0Z" fill="#004d3d" opacity=".6"/><path d="M-30 135C180 5 300 120 640 190M-30 124C180-6 300 109 640 179M-30 113C180-17 300 98 640 168M-30 102C180-28 300 87 640 157" fill="none" stroke="#a7e8c7" opacity=".1"/></svg>
    </section>
    <section class="form-panel" aria-label="Iniciar sesión">
        <div class="company-logo"><img src="{{ asset('images/lunnys-peru.png') }}" alt="LUNNY’S PERU R.2.D. — colibríes y orquídea" width="1600" height="1600"></div>
        <div class="form-divider" aria-hidden="true"><span></span><i><svg viewBox="0 0 24 24"><rect x="3" y="12" width="4" height="9" rx="1"/><rect x="10" y="3" width="4" height="18" rx="1"/><rect x="17" y="8" width="4" height="13" rx="1"/></svg></i><span></span></div>
        @if (session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label class="sr-only" for="email">Correo electrónico</label>
                <div class="input-row @error('email') invalid @enderror">
                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 22v-4a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v4"/></svg></span>
                    <input id="email" name="email" type="email" autocomplete="username" placeholder="Correo electrónico" value="{{ old('email') }}" required maxlength="254" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                </div>
                @error('email')<p id="email-error" class="error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="sr-only" for="password">Contraseña</label>
                <div class="input-row @error('password') invalid @enderror">
                    <span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 15v3"/></svg></span>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Contraseña" required maxlength="1024" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    <button id="toggle-password" class="password-toggle" type="button" aria-label="Mostrar contraseña" aria-controls="password" aria-pressed="false" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button>
                </div>
                @error('password')<p id="password-error" class="error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button class="primary" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 15v3"/></svg> Iniciar sesión</button>
        </form>
    </section>
</div>
@endsection
