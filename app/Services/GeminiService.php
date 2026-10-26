<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('gemini.api_key', '');
        $this->model = (string) config('gemini.model', '');
        $this->apiUrl = (string) config('gemini.api_url', '');
    }

    /**
     * Procesa una consulta del usuario con contexto de la base de datos
     */
    public function query(string $userMessage, array $conversationHistory = []): array
    {
        try {
            // Obtener contexto relevante de la base de datos
            $context = $this->getRelevantContext($userMessage);
            
            // Construir el sistema de prompts
            $systemPrompt = $this->buildSystemPrompt($context);
            
            // Construir el historial de conversación
            $messages = $this->buildMessages($systemPrompt, $conversationHistory, $userMessage);
            
            // Llamar a la API de Gemini
            $response = $this->callGeminiApi($messages);
            
            return [
                'success' => true,
                'response' => $response,
                'source' => 'Gemini AI',
                'timestamp' => now()->toIso8601String(),
            ];
            
        } catch (\Exception $e) {
            Log::error('Gemini API Error: ' . $e->getMessage());

            $message = $e instanceof QueryException
                ? 'No pude consultar los datos internos para responder. Contacta al administrador si vuelve a ocurrir.'
                : ($e->getCode() === 429
                    ? 'Gemini alcanzó su límite de consultas. Intenta más tarde.'
                    : 'Lo siento, ocurrió un error al procesar tu consulta. Por favor, intenta nuevamente.');
            
            return [
                'success' => false,
                'response' => $message,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Obtiene contexto relevante de la base de datos según la consulta
     */
    private function getRelevantContext(string $query): array
    {
        $query = strtolower($query);
        $context = [];

        // Estadísticas generales (siempre incluidas)
        $context['stats'] = [
            'total_clientes' => Client::count(),
            'clientes_activos' => Client::where('estado', 'ACTIVO')->count(),
            'total_contratos' => Contract::count(),
            'contratos_activos' => Contract::where('estado', 'ACTIVO')->count(),
            'contratos_registrados_mes_actual' => Contract::whereBetween('fecha_registro', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'total_empleados' => Employee::count(),
            'empleados_activos' => Employee::where('estado', 'ACTIVO')->count(),
            'total_equipos' => Equipment::count(),
            'equipos_disponibles' => Equipment::where('estado', 'DISPONIBLE')->count(),
            'total_materiales' => Material::where('estado', 'ACTIVO')->count(),
        ];

        // Buscar equipo específico por código (ej: EQ-001, equipo 001)
        if (preg_match('/(?:equipo|eq)[\s-]*(\d+|[a-z0-9-]+)/i', $query, $matches)) {
            $codigo = strtoupper($matches[1]);
            if (!str_starts_with($codigo, 'EQ-')) {
                $codigo = 'EQ-' . $codigo;
            }
            $equipo = Equipment::where('codigo', $codigo)->first();
            if ($equipo) {
                $fa = $equipo->fecha_adquisicion;
                $context['equipo_especifico'] = [
                    'codigo' => $equipo->codigo,
                    'nombre' => $equipo->nombre,
                    'tipo' => $equipo->tipo,
                    'marca' => $equipo->marca,
                    'modelo' => $equipo->modelo,
                    'numero_serie' => $equipo->numero_serie,
                    'estado' => $equipo->estado,
                    'condicion' => $equipo->condicion,
                    'ubicacion' => $equipo->ubicacion,
                    'fecha_adquisicion' => $fa ? (is_string($fa) ? substr($fa, 0, 10) : $fa->format('Y-m-d')) : null,
                ];
            }
        }

        // Buscar material específico por código
        if (preg_match('/(?:material|mat)[\s-]*(\d+|[a-z0-9-]+)/i', $query, $matches)) {
            $codigo = strtoupper($matches[1]);
            if (!str_starts_with($codigo, 'MAT-')) {
                $codigo = 'MAT-' . $codigo;
            }
            $material = Material::where('codigo', $codigo)->first();
            if ($material) {
                $context['material_especifico'] = [
                    'codigo' => $material->codigo,
                    'nombre' => $material->nombre,
                    'stock_actual' => $material->stock_actual,
                    'stock_minimo' => $material->stock_minimo,
                    'unidad_medida' => $material->unidad_base,
                    'estado' => $material->estado,
                    'alertas' => $material->stock_actual <= $material->stock_minimo ? 'Stock bajo o crítico' : 'Stock normal',
                ];
            }
        }

        // Buscar contrato específico por ID (sin datos del cliente)
        if (preg_match('/contrato[\s#-]*(\d+)/i', $query, $matches)) {
            $contratoId = $matches[1];
            $contrato = Contract::with('plan')->find($contratoId);
            if ($contrato) {
                $fi = $contrato->fecha_inicio;
                $ff = $contrato->fecha_fin;
                $context['contrato_especifico'] = [
                    'contrato_id' => $contrato->contrato_id,
                    'plan' => $contrato->plan->nombre ?? 'N/A',
                    'estado' => $contrato->estado,
                    'fecha_inicio' => $fi ? (is_string($fi) ? substr($fi, 0, 10) : $fi->format('Y-m-d')) : null,
                    'fecha_fin'    => $ff ? (is_string($ff) ? substr($ff, 0, 10) : $ff->format('Y-m-d')) : null,
                    'precio_contratado' => $contrato->precio_contratado,
                    'nota' => 'Por privacidad, no se muestra información del cliente asociado'
                ];
            }
        }

        // Contexto específico según palabras clave (PROTEGIDO - solo estadísticas)
        if (preg_match('/cliente|clientes/i', $query)) {
            // NO mostrar datos personales de clientes (DNI, teléfonos, direcciones)
            // Solo estadísticas agregadas
            $context['info_clientes'] = [
                'total' => Client::count(),
                'activos' => Client::where('estado', 'ACTIVO')->count(),
                'inactivos' => Client::where('estado', 'INACTIVO')->count(),
                'con_contrato' => Client::whereIn('cliente_id', Contract::select('cliente_id'))->count(),
                'nota' => '⚠️ Por privacidad, no se muestran datos personales de clientes (nombres, DNI, teléfonos, direcciones)'
            ];
        }

        if (preg_match('/contrato|contratos|plan|planes/i', $query)) {
            // Mostrar contratos sin información personal del cliente
            $context['contratos_activos'] = Contract::with('plan')
                ->where('estado', 'ACTIVO')
                ->take(5)
                ->get(['contrato_id', 'plan_id', 'fecha_inicio', 'fecha_fin', 'precio_contratado', 'estado'])
                ->map(function($c) {
                    $fi = $c->fecha_inicio;
                    $ff = $c->fecha_fin;
                    return [
                        'contrato_id' => $c->contrato_id,
                        'plan' => $c->plan->nombre ?? 'N/A',
                        'fecha_inicio' => $fi ? (is_string($fi) ? substr($fi, 0, 10) : $fi->format('Y-m-d')) : null,
                        'fecha_fin'    => $ff ? (is_string($ff) ? substr($ff, 0, 10) : $ff->format('Y-m-d')) : null,
                        'precio_contratado' => $c->precio_contratado,
                        'estado' => $c->estado,
                    ];
                })
                ->toArray();
        }

        if (preg_match('/pago|pagos|deuda|mora|pendiente/i', $query)) {
            $context['pagos_pendientes'] = Payment::where('estado', 'PENDIENTE')->count();
            
            $context['monto_pendiente'] = Payment::whereIn('estado', ['PENDIENTE', 'VENCIDO'])
                ->sum('monto');
            
            $context['pagos_vencidos'] = Payment::where('estado', 'VENCIDO')->count();
        }

        if (preg_match('/equipo|equipos|inventario/i', $query) && !isset($context['equipo_especifico'])) {
            $context['equipos_por_estado'] = Equipment::select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->get()
                ->toArray();

            // Dar detalles de cada equipo para consultas generales, con un límite
            // explícito para no enviar un inventario enorme al modelo.
            $context['equipos_detalle'] = Equipment::orderBy('codigo')
                ->limit(20)
                ->get(['codigo', 'nombre', 'tipo', 'marca', 'modelo', 'estado'])
                ->toArray();
            $context['equipos_detalle_limite'] = 20;
            
            $context['equipos_mantenimiento'] = Equipment::where('estado', 'MANTENIMIENTO')
                ->get(['codigo', 'nombre', 'tipo'])
                ->toArray();
        }

        if (preg_match('/material|materiales|stock/i', $query) && !isset($context['material_especifico'])) {
            $context['materiales_stock_bajo'] = Material::whereRaw('stock_actual <= stock_minimo')
                ->where('estado', 'ACTIVO')
                ->get(['codigo', 'nombre', 'stock_actual', 'stock_minimo', 'unidad_base'])
                ->toArray();
        }

        if (preg_match('/empleado|empleados|personal|técnico|tecnicos/i', $query)) {
            // NO mostrar datos personales de empleados (DNI, teléfonos, salarios)
            // Solo estadísticas agregadas por cargo
            $empleadosPorCargo = Employee::where('estado', 'ACTIVO')
                ->select('cargo', DB::raw('count(*) as total'))
                ->groupBy('cargo')
                ->get()
                ->toArray();
            
            $context['info_empleados'] = [
                'total' => Employee::count(),
                'activos' => Employee::where('estado', 'ACTIVO')->count(),
                'por_cargo' => $empleadosPorCargo,
                'nota' => '⚠️ Por privacidad, no se muestran datos personales de empleados (nombres, DNI, teléfonos, direcciones)'
            ];
        }

        return $context;
    }

    /**
     * Construye el prompt del sistema con el contexto
     */
    private function buildSystemPrompt(array $context): string
    {
        $prompt = "Eres un asistente virtual inteligente de Lunnys Perú, una empresa de servicios de internet y telecomunicaciones.\n\n";
        
        $prompt .= "CAPACIDADES:\n";
        $prompt .= "- Responder preguntas sobre clientes, contratos, pagos, equipos, materiales y empleados\n";
        $prompt .= "- Proporcionar estadísticas y métricas del negocio en tiempo real\n";
        $prompt .= "- Buscar información específica por códigos o IDs (ej: 'equipo EQ-001', 'contrato 5')\n";
        $prompt .= "- Identificar problemas operativos (pagos vencidos, stock bajo, equipos en mantenimiento)\n";
        $prompt .= "- Recomendar acciones basadas en los datos actuales\n\n";
        
        $prompt .= "PERSONALIDAD:\n";
        $prompt .= "- Amigable, profesional y servicial\n";
        $prompt .= "- Proactivo: sugiere información relacionada que pueda ser útil\n";
        $prompt .= "- Claro y conciso en las respuestas\n";
        $prompt .= "- Usa emojis ocasionalmente para hacer la conversación más amigable (📊, ✅, ⚠️, 💰, etc.)\n\n";
        
        $prompt .= "DATOS ACTUALES DE LA BASE DE DATOS:\n";
        $prompt .= "```json\n";
        $prompt .= json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $prompt .= "\n```\n\n";
        
        $prompt .= "INSTRUCCIONES DE FORMATO:\n";
        $prompt .= "1. Usa los datos del contexto JSON para dar respuestas precisas y actualizadas\n";
        $prompt .= "2. IMPORTANTE: Para conteos totales (clientes, contratos, pagos, etc.), usa SIEMPRE las cifras de 'stats' en el JSON, NO cuentes manualmente los elementos listados\n";
        $prompt .= "3. Las listas detalladas (contratos_activos, materiales_stock_bajo, etc.) pueden estar limitadas a los primeros N elementos, pero 'stats' siempre tiene el conteo completo\n";
        $prompt .= "3a. Para equipos, usa 'equipos_detalle' cuando pidan datos de todos los equipos. Si la lista está limitada, dilo claramente; no afirmes que los demás equipos carecen de datos.\n";
        $prompt .= "4. Para montos monetarios, usa el formato: S/ X,XXX.XX (soles peruanos)\n";
        $prompt .= "5. Para fechas, usa formato legible: '15 de octubre de 2026'\n";
        $prompt .= "6. Formatea números grandes con separadores de miles: 1,234 en lugar de 1234\n";
        $prompt .= "7. Si encuentras información específica (equipo, material, contrato), proporciona todos los detalles disponibles\n";
        $prompt .= "8. Si el dato no existe en el contexto, indica claramente que no está disponible\n";
        $prompt .= "9. Puedes hacer pequeños cálculos o análisis con los datos proporcionados\n";
        $prompt .= "10. Si detectas algo preocupante (stock crítico, muchos pagos vencidos), menciónalo proactivamente\n\n";
        
        $prompt .= "SEGURIDAD Y PRIVACIDAD:\n";
        $prompt .= "- NUNCA reveles contraseñas, tokens, API keys o información sensible del sistema\n";
        $prompt .= "- NO ejecutes ni sugieras comandos de base de datos (SQL)\n";
        $prompt .= "- NO proporciones datos personales de clientes: nombres completos, DNI, teléfonos, direcciones, emails\n";
        $prompt .= "- NO proporciones datos personales de empleados: nombres completos, DNI, teléfonos, direcciones, salarios\n";
        $prompt .= "- SOLO proporciona estadísticas agregadas y métricas generales de clientes y empleados\n";
        $prompt .= "- Si te piden datos personales específicos, responde: 'Por razones de privacidad, no puedo proporcionar información personal de clientes o empleados'\n";
        $prompt .= "- SÍ puedes mostrar información técnica: equipos, materiales, contratos (sin datos personales asociados)\n";
        $prompt .= "- Si te piden algo inapropiado o fuera de tu alcance, responde educadamente que no puedes ayudar con eso\n\n";
        
        $prompt .= "Responde SIEMPRE en español y mantén un tono profesional pero cercano.\n";
        
        return $prompt;
    }

    /**
     * Construye el array de mensajes para la API
     */
    private function buildMessages(string $systemPrompt, array $history, string $userMessage): array
    {
        $messages = [];
        
        // Agregar el prompt del sistema
        $messages[] = [
            'role' => 'user',
            'parts' => [['text' => $systemPrompt]]
        ];
        
        // Agregar historial (limitado a los últimos 10 mensajes)
        $recentHistory = array_slice($history, -10);
        foreach ($recentHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $msg['content']]]
            ];
        }
        
        // Agregar el mensaje actual del usuario
        $messages[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]]
        ];
        
        return $messages;
    }

    /**
     * Llama a la API de Gemini
     */
    private function callGeminiApi(array $messages): string
    {
        $url = "{$this->apiUrl}/{$this->model}:generateContent?key={$this->apiKey}";
        
        $response = Http::timeout(config('gemini.timeout', 30))
            ->post($url, [
                'contents' => $messages,
                'generationConfig' => [
                    'temperature' => config('gemini.temperature', 0.7),
                    'maxOutputTokens' => config('gemini.max_tokens', 2048),
                ],
                'safetySettings' => [
                    [
                        'category' => 'HARM_CATEGORY_HARASSMENT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ],
                    [
                        'category' => 'HARM_CATEGORY_HATE_SPEECH',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ],
                ]
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Error en la API de Gemini: ' . $response->body(), $response->status());
        }

        $data = $response->json();
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Respuesta inválida de la API de Gemini');
        }

        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Verifica si la API está configurada correctamente
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->model);
    }
}
