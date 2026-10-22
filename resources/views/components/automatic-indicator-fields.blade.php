@props(['context'])
<input type="hidden" name="indicador_context" value="{{ old('indicador_context') === $context ? $context : '' }}">
<input type="hidden" name="indicador_token" value="{{ old('indicador_context') === $context ? old('indicador_token') : '' }}">
<input type="hidden" name="indicador_inicio" value="{{ old('indicador_context') === $context ? old('indicador_inicio') : '' }}">
<input type="hidden" name="indicador_ultima_interaccion" value="{{ old('indicador_context') === $context ? old('indicador_ultima_interaccion') : '' }}">
<input type="hidden" name="indicador_activo_segundos" value="{{ old('indicador_context') === $context ? old('indicador_activo_segundos') : '' }}">
<input type="hidden" name="indicador_pausa_descartada_segundos" value="{{ old('indicador_context') === $context ? old('indicador_pausa_descartada_segundos') : '' }}">
