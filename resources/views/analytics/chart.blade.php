@php $max=max(1,...$chart['values']); $total=array_sum($chart['values']); $colors=['#087b59','#388cab','#d9a13c','#a35f69','#8876b7','#6c8261']; @endphp
@if(!$total)<div class="an-empty">Sin datos para esta combinación de filtros.</div>@else
@if(in_array($chart['type'],['pie','doughnut'],true))
@php $offset=0; $segments=[]; $circular=collect($chart['values'])->map(fn($value,$i)=>['label'=>$chart['labels'][$i],'value'=>$value])->filter(fn($row)=>$row['value']>0)->sortByDesc('value')->values(); if($circular->count()>12) { $remainder=$circular->slice(11)->sum('value'); $circular=$circular->take(11)->push(['label'=>'Otros grupos','value'=>$remainder]); } @endphp
@foreach($circular as $row)@php $next=$offset+$row['value']/$total*100; $segments[]=$colors[$loop->index%6].' '.$offset.'% '.$next.'%'; $offset=$next; @endphp@endforeach
<div class="an-circle-layout"><div class="an-donut {{ $chart['type']==='pie' ? 'an-pie' : '' }}" style="background:conic-gradient({{ implode(',',$segments) }})" role="img" aria-label="Distribución de {{ $chart['title'] }}"><div><strong>{{ number_format($total,$chart['measure']==='monto'?2:0) }}</strong><span>{{ $chart['unit'] }}</span></div></div><div class="an-legend">@foreach($circular as $i=>$row)<span><i class="an-dot" style="display:inline-block;background:{{ $colors[$i%6] }}"></i> {{ $row['label'] }} · {{ number_format($row['value'],$chart['measure']==='monto'?2:0) }}</span>@endforeach</div></div>
@elseif(in_array($chart['type'],['line','area'],true))
@php $points=[]; $labelStep=max(1,(int)ceil(count($chart['values'])/6)); @endphp
@foreach($chart['values'] as $value)@php $points[]=(55+$loop->index*520/max(1,count($chart['values'])-1)).','. (205-$value/$max*170); @endphp@endforeach
<div class="an-chart-canvas"><svg viewBox="0 0 600 260" role="img" aria-label="Evolución de {{ $chart['title'] }}">
@foreach([0,0.25,0.5,0.75,1] as $fraction)
@php $y=205-$fraction*170; @endphp
<line x1="55" y1="{{ $y }}" x2="575" y2="{{ $y }}" stroke="#e1e9e4" stroke-width="1"/>
<text x="47" y="{{ $y+4 }}" text-anchor="end">{{ number_format($max*$fraction,$chart['measure']==='monto'?2:0) }}</text>
@endforeach
@if($chart['type']==='area')<polygon points="55,205 {{ implode(' ',$points) }} {{ 55+(count($points)-1)*520/max(1,count($points)-1) }},205" fill="#e0f1e7" stroke="none"/>@endif
<polyline points="{{ implode(' ',$points) }}" fill="none" stroke="#087b59" stroke-width="3"/>
@foreach($chart['values'] as $value)
@php $x=55+$loop->index*520/max(1,count($chart['values'])-1); $y=205-$value/$max*170; @endphp
<circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#087b59" stroke="#fff" stroke-width="2"><title>{{ $chart['labels'][$loop->index] }}: {{ $value }} {{ $chart['unit'] }}</title></circle>
@if(count($chart['values'])<=12)<text class="an-point-value" x="{{ $x }}" y="{{ $y-12 }}" text-anchor="middle">{{ number_format($value,$chart['measure']==='monto'?2:0) }}</text>@endif
@if($loop->index % $labelStep===0 || $loop->last)<text x="{{ $x }}" y="232" text-anchor="{{ $loop->first ? 'start' : ($loop->last ? 'end' : 'middle') }}">{{ $chart['labels'][$loop->index] }}</text>@endif
@endforeach
</svg></div>
@else<div class="an-bars an-explorer-bars">@foreach($chart['labels'] as $i=>$label)<div class="an-bar-row"><span>{{ $label }}</span><div class="an-track"><div class="an-fill" style="width:{{ $chart['values'][$i]/$max*100 }}%"></div></div><strong>{{ number_format($chart['values'][$i],$chart['measure']==='monto'?2:0) }}</strong></div>@endforeach</div>@endif
@endif
<details class="an-chart-details"><summary>Ver valores exactos del gráfico</summary><div class="an-table-wrap an-chart-values"><table class="an-table"><thead><tr><th>Grupo</th><th>{{ $chart['unit'] }}</th></tr></thead><tbody>@foreach($chart['labels'] as $i=>$label)<tr><td>{{ $label }}</td><td>{{ number_format($chart['values'][$i],$chart['measure']==='monto'?2:0) }}</td></tr>@endforeach</tbody></table></div></details>
