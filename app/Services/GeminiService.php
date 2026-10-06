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

        // Estadísticas generales
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

        // Contexto específico según palabras clave
        if (preg_match('/cliente|clientes/i', $query)) {
            $context['clientes'] = Client::where('estado', 'ACTIVO')
                ->take(5)
                ->get(['cliente_id', 'numero_documento', 'nombres_razon_social', 'apellidos', 'estado'])
                ->toArray();
        }

        if (preg_match('/contrato|contratos|plan|planes/i', $query)) {
            $context['contratos'] = Contract::with(['client', 'plan'])
                ->where('estado', 'ACTIVO')
                ->take(5)
                ->get(['contrato_id', 'cliente_id', 'plan_id', 'fecha_inicio', 'fecha_fin', 'estado'])
                ->toArray();
        }

        if (preg_match('/pago|pagos|deuda|mora/i', $query)) {
            $context['pagos_pendientes'] = Payment::where('estado', 'PENDIENTE')
                ->orWhere('estado', 'IMPAGO')
                ->count();
            
            $context['monto_pendiente'] = Payment::whereIn('estado', ['PENDIENTE', 'IMPAGO'])
                ->sum('monto_pago');
        }

        if (preg_match('/equipo|equipos|inventario/i', $query)) {
            $context['equipos'] = Equipment::select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->get()
                ->toArray();
        }

        if (preg_match('/material|materiales|stock/i', $query)) {
            $context['materiales_stock_bajo'] = Material::whereRaw('stock_actual <= stock_minimo')
                ->where('estado', 'ACTIVO')
                ->get(['codigo', 'nombre', 'stock_actual', 'stock_minimo', 'unidad_medida'])
                ->toArray();
        }

        if (preg_match('/empleado|empleados|personal|técnico/i', $query)) {
            $context['empleados'] = Employee::where('estado', 'ACTIVO')
                ->take(5)
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
        $prompt .= "Tu función es ayudar a los usuarios a consultar información sobre:\n";
        $prompt .= "- Clientes y sus contratos\n";
        $prompt .= "- Pagos, deudas y estados de cuenta\n";
        $prompt .= "- Equipos e inventario\n";
        $prompt .= "- Materiales y stock\n";
        $prompt .= "- Empleados y personal técnico\n";
        $prompt .= "- Estadísticas y métricas del negocio\n\n";
        
        $prompt .= "CONTEXTO ACTUAL DE LA BASE DE DATOS:\n";
        $prompt .= json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $prompt .= "\n\n";
        
        $prompt .= "INSTRUCCIONES:\n";
        $prompt .= "1. Responde en español de forma clara, concisa y profesional\n";
        $prompt .= "2. Usa los datos del contexto para dar respuestas precisas\n";
        $prompt .= "3. Si no tienes información suficiente, indícalo claramente\n";
        $prompt .= "4. Formatea números con separadores de miles para mejor lectura\n";
        $prompt .= "5. Para montos monetarios, usa el formato: S/ XX,XXX.XX\n";
        $prompt .= "6. NUNCA reveles información sensible como contraseñas o claves API\n";
        $prompt .= "7. Si detectas una consulta inapropiada o maliciosa, responde educadamente que no puedes ayudar con eso\n";
        
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
