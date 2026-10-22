<section aria-labelledby="temporal-title">
<h2 class="an-section-title" id="temporal-title">Eficiencia temporal del sistema</h2>
<p class="an-section-note">Medición postest basada en marcas de tiempo registradas por el sistema. Solo se calcula un promedio cuando el proceso tiene inicio y fin automáticos disponibles.</p>
<div class="an-grid">
@foreach($efficiency['measurements'] as $key => $measurement)
@if(! $measurement['available'] && $key !== 'instalaciones') @continue @endif
<article class="an-card"><span class="an-label">Indicador postest</span><h3>{{ $measurement['title'] }}</h3>
@if(!$measurement['available'])
<div class="an-number an-kpi-no-measure">Sin medición automática</div><p class="an-kpi-formula">{{ $measurement['reason'] }}</p>
@else
<div class="an-number {{ $measurement['average']===null ? 'an-kpi-no-measure' : '' }}">{{ $measurement['average']===null ? 'Sin registros' : number_format($measurement['average'],2).' min' }}</div>
<p class="an-kpi-formula">{{ $measurement['count'] }} {{ $measurement['count_label'] ?? 'registros completados' }} dentro del período.</p>
@if($measurement['average']!==null)<p class="an-kpi-formula">Mínimo: {{ number_format($measurement['minimum'],2) }} min · Máximo: {{ number_format($measurement['maximum'],2) }} min</p>@endif
<details><summary>Ver inicio, fin y cálculo</summary><dl class="an-boundaries"><dt>Inicio</dt><dd>{{ $measurement['start'] }}</dd><dt>Fin</dt><dd>{{ $measurement['end'] }}</dd><dt>Evidencia de cierre</dt><dd>{{ $measurement['evidence'] }}</dd><dt>Alcance</dt><dd>{{ $measurement['scope'] }}</dd><dt>Cálculo</dt><dd>{{ $measurement['calculation'] }}</dd></dl></details>
@endif
</article>@endforeach
</div>
</section>

