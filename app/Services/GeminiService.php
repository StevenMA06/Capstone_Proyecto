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

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');
        $this->model = config('gemini.model');
        $this->apiUrl = config('gemini.api_url');
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
            
            return [
                'success' => false,
                'response' => 'Lo siento, ocurrió un error al procesar tu consulta. Por favor, intenta nuevamente.',
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
                    'fecha_adquisicion' => $equipo->fecha_adquisicion?->format('Y-m-d'),
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
                    'unidad_medida' => $material->unidad_medida,
                    'estado' => $material->estado,
                    'alertas' => $material->stock_actual <= $material->stock_minimo ? 'Stock bajo o crítico' : 'Stock normal',
                ];
            }
        }

        // Buscar contrato específico por ID
        if (preg_match('/contrato[\s#-]*(\d+)/i', $query, $matches)) {
            $contratoId = $matches[1];
            $contrato = Contract::with(['client', 'plan'])->find($contratoId);
            if ($contrato) {
                $context['contrato_especifico'] = [
                    'contrato_id' => $contrato->contrato_id,
                    'cliente' => $contrato->client->nombres_razon_social . ' ' . ($contrato->client->apellidos ?? ''),
                    'plan' => $contrato->plan->nombre ?? 'N/A',
                    'estado' => $contrato->estado,
                    'fecha_inicio' => $contrato->fecha_inicio?->format('Y-m-d'),
                    'fecha_fin' => $contrato->fecha_fin?->format('Y-m-d'),
                    'monto_mensual' => $contrato->monto_mensual,
                ];
            }
        }

        // Contexto específico según palabras clave
        if (preg_match('/cliente|clientes/i', $query)) {
            $context['clientes_recientes'] = Client::where('estado', 'ACTIVO')
                ->orderBy('creado_en', 'desc')
                ->take(5)
                ->get(['cliente_id', 'numero_documento', 'nombres_razon_social', 'apellidos', 'telefono', 'estado'])
                ->toArray();
        }

        if (preg_match('/contrato|contratos|plan|planes/i', $query)) {
            $context['contratos_activos'] = Contract::with(['client', 'plan'])
                ->where('estado', 'ACTIVO')
                ->take(5)
                ->get(['contrato_id', 'cliente_id', 'plan_id', 'fecha_inicio', 'fecha_fin', 'monto_mensual', 'estado'])
                ->toArray();
        }

        if (preg_match('/pago|pagos|deuda|mora|pendiente/i', $query)) {
            $context['pagos_pendientes'] = Payment::where('estado', 'PENDIENTE')
                ->orWhere('estado', 'IMPAGO')
                ->count();
            
            $context['monto_pendiente'] = Payment::whereIn('estado', ['PENDIENTE', 'IMPAGO'])
                ->sum('monto_pago');
            
            $context['pagos_vencidos'] = Payment::where('estado', 'IMPAGO')->count();
        }

        if (preg_match('/equipo|equipos|inventario/i', $query) && !isset($context['equipo_especifico'])) {
            $context['equipos_por_estado'] = Equipment::select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->get()
                ->toArray();
            
            $context['equipos_mantenimiento'] = Equipment::where('estado', 'MANTENIMIENTO')
                ->get(['codigo', 'nombre', 'tipo'])
                ->toArray();
        }

        if (preg_match('/material|materiales|stock/i', $query) && !isset($context['material_especifico'])) {
            $context['materiales_stock_bajo'] = Material::whereRaw('stock_actual <= stock_minimo')
                ->where('estado', 'ACTIVO')
                ->get(['codigo', 'nombre', 'stock_actual', 'stock_minimo', 'unidad_medida'])
                ->toArray();
        }

        if (preg_match('/empleado|empleados|personal|técnico|tecnicos/i', $query)) {
            $context['empleados_activos'] = Employee::where('estado', 'ACTIVO')
                ->take(10)
                ->get(['empleado_id', 'nombres', 'apellidos', 'tipo_identificacion', 'numero_identificacion', 'cargo', 'estado'])
                ->toArray();
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
        $prompt .= "2. Para montos monetarios, usa el formato: S/ X,XXX.XX (soles peruanos)\n";
        $prompt .= "3. Para fechas, usa formato legible: '15 de octubre de 2026'\n";
        $prompt .= "4. Formatea números grandes con separadores de miles: 1,234 en lugar de 1234\n";
        $prompt .= "5. Si encuentras información específica (equipo, material, contrato), proporciona todos los detalles disponibles\n";
        $prompt .= "6. Si el dato no existe en el contexto, indica claramente que no está disponible\n";
        $prompt .= "7. Puedes hacer pequeños cálculos o análisis con los datos proporcionados\n";
        $prompt .= "8. Si detectas algo preocupante (stock crítico, muchos pagos vencidos), menciónalo proactivamente\n\n";
        
        $prompt .= "SEGURIDAD:\n";
        $prompt .= "- NUNCA reveles contraseñas, tokens, API keys o información sensible del sistema\n";
        $prompt .= "- NO ejecutes ni sugieras comandos de base de datos (SQL)\n";
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
            throw new \Exception('Error en la API de Gemini: ' . $response->body());
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
