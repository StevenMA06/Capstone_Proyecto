<section aria-labelledby="temporal-title">
<h2 class="an-section-title" id="temporal-title">{{ $screen==='bi' ? 'Tiempos de los procesos · pretest' : 'Eficiencia temporal · pretest' }}</h2>
<p class="an-section-note">Observaciones del trabajo actual. Promedios en minutos de casos ordinarios finalizados dentro del período seleccionado, descontando interrupciones ajenas al proceso. No corresponden a tiempos automáticos del sistema web.</p>
@if(session('status'))<p class="notice success" role="status">{{ session('status') }}</p>@endif
<div class="an-grid">
@foreach($pretest['measurements'] as $key=>$measurement)
<article class="an-card"><span class="an-label">{{ $measurement['rf'] ? 'Indicador del proceso' : 'Medición complementaria del levantamiento' }}</span><h3>{{ $measurement['title'] }}</h3>
<div class="an-number {{ $measurement['average']===null ? 'an-kpi-no-measure' : '' }}">{{ !$measurement['validated'] ? 'Por validar' : ($measurement['average']===null ? 'Sin observaciones' : number_format($measurement['average'],2).' min') }}</div>
@if($measurement['validated'])<p class="an-kpi-formula">{{ $measurement['count'] }} casos ordinarios · {{ $measurement['exceptions'] }} excepcionales, separados del promedio.</p>@endif
<details><summary>Ver inicio, fin y cálculo</summary><dl class="an-boundaries"><dt>Inicio</dt><dd>{{ $measurement['start'] }}</dd><dt>Fin</dt><dd>{{ $measurement['end'] }}</dd><dt>Evidencia de cierre</dt><dd>{{ $measurement['evidence'] }}</dd><dt>Alcance</dt><dd>{{ $measurement['scope'] }}</dd><dt>Cálculo</dt><dd>Suma de (fin − inicio − interrupciones ajenas al proceso) / cantidad de casos ordinarios observados. Conversión de segundos a minutos. Objetivo: reducir el tiempo promedio.</dd></dl></details>
</article>@endforeach</div>
@if($screen==='indicators' && auth()->user()->isManager())
<details class="an-panel an-observation"><summary>Registrar observación del pretest</summary><p class="an-description">Ingresa los horarios anotados durante la observación del trabajo actual (hora de Lima). Una referencia identifica el caso sin copiar DNI, contraseñas ni comprobantes. La firma del contrato sola no completa el proceso.</p>
@if(!$pretest['enabled'])<p>El registro de observaciones aún no está habilitado.</p>@else
<form method="post" action="{{ route('indicators.pretest.store') }}" class="an-observation-form">@csrf<input type="hidden" name="token" value="{{ old('token', (string)\Illuminate\Support\Str::uuid()) }}">
<div><label for="medicion-indicador">Proceso observado</label><select id="medicion-indicador" name="indicador" required>@foreach(\App\Support\IndicatorDefinitions::measurable() as $key=>$definition)<option value="{{ $key }}" @selected(old('indicador')===$key)>{{ $definition['title'] }}</option>@endforeach</select></div>
<div><label for="medicion-referencia">Referencia de la guía o caso</label><input id="medicion-referencia" name="referencia" maxlength="120" value="{{ old('referencia') }}" required></div>
<div><label for="medicion-inicio">Inicio observado</label><input id="medicion-inicio" name="inicio" type="datetime-local" step="1" value="{{ old('inicio') }}" required></div>
<div><label for="medicion-fin">Fin observado</label><input id="medicion-fin" name="fin" type="datetime-local" step="1" value="{{ old('fin') }}" required></div>
<div><label for="medicion-interrupciones">Interrupciones ajenas al proceso (segundos)</label><input id="medicion-interrupciones" name="interrupciones_segundos" type="number" min="0" step="1" value="{{ old('interrupciones_segundos',0) }}" required></div>
<label class="an-check"><input type="checkbox" name="excepcional" value="1" @checked(old('excepcional'))> Caso excepcional: conservar separado del promedio ordinario.</label>
<div class="an-wide"><label for="medicion-nota">Incidencias o explicación de interrupciones</label><textarea id="medicion-nota" name="observacion" rows="3" maxlength="2000">{{ old('observacion') }}</textarea></div>
<label class="an-check an-wide"><input type="checkbox" name="limites_confirmados" value="1" required @checked(old('limites_confirmados'))> Confirmo que observé el inicio, el fin y la evidencia de cierre definidos para el proceso seleccionado.</label>
<div class="an-wide"><button class="an-primary">Guardar observación</button></div>
</form>@endif</details>
@if($pretest['recent']->isNotEmpty())<section class="an-panel an-observation"><h2>Últimas observaciones del período</h2><p class="an-description">Hasta 10 casos recientes. El promedio considera todos los casos ordinarios del período, no solo los visibles aquí.</p><div class="an-table-wrap"><table class="an-table"><thead><tr><th>Proceso</th><th>Referencia</th><th>Inicio</th><th>Fin</th><th>Tiempo efectivo</th><th>Clasificación</th></tr></thead><tbody>@foreach($pretest['recent'] as $record)<tr><td>{{ \App\Support\IndicatorDefinitions::all()[$record->indicador]['title'] }}</td><td>{{ $record->referencia }}</td><td>{{ $record->inicio }}</td><td>{{ $record->fin }}</td><td>{{ number_format($record->duracion_segundos/60,2) }} min</td><td>{{ $record->excepcional ? 'Excepcional' : 'Ordinario' }}</td></tr>@endforeach</tbody></table></div></section>@endif
@endif
</section>
