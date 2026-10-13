<details class="cancellation-request-form" id="cancelacion-contrato" @if($errors->has('categoria') || $errors->has('explicacion') || $errors->has('evidencia')) open @endif>
    <summary><span>Solicitar cancelación del contrato</span><small>Solo si corresponde · requiere evidencia y revisión de otra cuenta</small></summary>
    <form method="POST" action="{{ route('contracts.cancellation.request', $contract->contrato_id) }}" enctype="multipart/form-data">@csrf
        <p>Registra el motivo y adjunta el respaldo. Otra cuenta autorizada de Gerencia o Administración deberá revisar la evidencia y resolver la solicitud. El contrato seguirá vigente hasta esa decisión.</p>
        <label for="categoria">Motivo *</label>
        <select id="categoria" name="categoria" required>
            <option value="">Selecciona el motivo</option>
            @foreach($cancellationCategories as $category)
                <option value="{{ $category }}" @selected(old('categoria') === $category)>{{ $category }}</option>
            @endforeach
        </select>
        @error('categoria')<small class="field-error">{{ $message }}</small>@enderror

        <label for="explicacion">Explicación del caso *</label>
        <textarea id="explicacion" name="explicacion" rows="4" minlength="15" maxlength="400" required placeholder="Describe qué ocurrió, cuándo y qué se acordó con el cliente">{{ old('explicacion') }}</textarea>
        @error('explicacion')<small class="field-error">{{ $message }}</small>@enderror

        <label for="evidencia">Evidencia de respaldo *</label>
        <input id="evidencia" type="file" name="evidencia" accept="application/pdf,image/jpeg,image/png,image/webp" required>
        <small>PDF o imagen · máximo 10 MB. Se guardará una huella para verificar que el archivo no cambie.</small>
        @error('evidencia')<small class="field-error">{{ $message }}</small>@enderror
        @error('contrato')<p class="field-error">{{ $message }}</p>@enderror
        <button class="action-link cancellation-submit" type="submit">Enviar solicitud a revisión</button>
    </form>
</details>
