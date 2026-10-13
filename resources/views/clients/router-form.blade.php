<fieldset class="client-router-fields">
    <legend>Router y red WiFi</legend>
    <div class="client-router-grid">
        @foreach(['ip_router' => 'IP del router', 'modelo_router' => 'Modelo del router', 'usuario_router' => 'Usuario del router', 'ssid' => 'SSID'] as $name => $label)
        <div>
            <label for="{{ $name }}">{{ $label }} <span class="muted">(se completa tras la instalación)</span></label>
            <input id="{{ $name }}" name="{{ $name }}" type="text" maxlength="{{ $name === 'ip_router' ? 45 : 100 }}"
                value="{{ is_scalar(old($name, $client->{$name})) ? old($name, $client->{$name}) : '' }}"
                @if($name === 'usuario_router') autocomplete="off" autocapitalize="none" spellcheck="false" @endif
                @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
            @error($name)<p class="error" id="{{ $name }}-error">{{ $message }}</p>@enderror
        </div>
        @endforeach
        @foreach(['contrasena_router' => 'Contraseña del router', 'contrasena_wifi' => 'Contraseña WiFi'] as $name => $label)
        @php($configured = $client->exists && filled($client->getRawOriginal($name)))
        <div>
            <label for="{{ $name }}">{{ $label }} <span class="muted">(se completa tras la instalación)</span></label>
            <div class="client-secret-field">
                <input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="new-password" maxlength="128"
                    @if($configured) aria-describedby="{{ $name }}-help{{ $errors->has($name) ? ' '.$name.'-error' : '' }}" @elseif($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
                    @error($name) aria-invalid="true" @enderror>
                <button type="button" class="client-secret-toggle" data-client-secret-toggle aria-controls="{{ $name }}" aria-label="Mostrar {{ mb_strtolower($label) }}" aria-pressed="false" hidden>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="secret-slash" d="m3 3 18 18"/></svg>
                </button>
            </div>
            @if($configured)<small id="{{ $name }}-help">Ya está configurada. Deja este campo vacío para conservarla.</small>@endif
            @error($name)<p class="error" id="{{ $name }}-error">{{ $message }}</p>@enderror
        </div>
        @endforeach
    </div>
</fieldset>
