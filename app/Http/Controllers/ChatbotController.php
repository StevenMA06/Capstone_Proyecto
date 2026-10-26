<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use App\Services\LocalChatService;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Installation;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Kardex;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __construct(private GeminiService $gemini, private LocalChatService $localChat) {}

    /**
     * Muestra la interfaz del chatbot
     */
    public function index(): View
    {
        return view('chatbot.index');
    }

    /**
     * Muestra la interfaz de pruebas del chatbot
     */
    public function testInterface(): View
    {
        return view('chatbot.test-interface');
    }

    /**
     * Procesa una consulta del usuario
     */
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
        ]);

        $message = $request->input('message');
        $history = $request->input('history', []);
        
        // Verificar si contiene palabras sospechosas (protección básica)
        if ($this->containsMaliciousContent($message)) {
            return response()->json([
                'success' => false,
                'response' => 'No puedo procesar esa consulta por razones de seguridad.',
                'source' => null
            ]);
        }

        // Consultar Gemini cuando esté disponible; el asistente interno cubre
        // las métricas conocidas si la API está desactivada o falla.
        if ($this->gemini->isConfigured()) {
            $result = $this->gemini->query($message, $history);
            if ($result['success']) {
                return response()->json($result);
            }
        }

        try {
            return response()->json($this->localChat->query($message));
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'response' => 'No pude consultar los datos internos en este momento. Intenta nuevamente.',
                'source' => 'Sistema',
            ]);
        }
    }

    /**
     * Inicia una nueva conversación
     */
    public function newConversation(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Nueva conversación iniciada'
        ]);
    }

    /**
     * Verifica contenido malicioso básico
     */
    private function containsMaliciousContent(string $input): bool
    {
        $maliciousPatterns = [
            '/ignore\s+(previous|all|your)\s+instructions/i',
            '/contraseña|password\s*(hash)?|passwd/i',
            '/api[_\s]?key/i',
            '/secret[_\s]?key/i',
            '/bearer\s+token/i',
            '/\.env\s+file/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/truncate\s+table/i',
            '/grant\s+all/i',
            '/revoke\s+all/i',
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * RF-IA-03: Consultar información operativa mediante chatbot
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function queryOperationalData(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string',
            'module' => 'required|string|in:clientes,contratos,pagos,ventas,instalaciones,equipos,materiales'
        ]);

        $query = $request->input('query');
        $module = $request->input('module');

        // Validar permisos del usuario
        if (!$this->canAccessModule($module)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar información de ' . $module,
                'reason' => 'permission_denied'
            ], 403);
        }

        $data = $this->getModuleData($module);

        return response()->json([
            'success' => true,
            'query' => $query,
            'module' => $module,
            'data' => $data,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * RF-IA-04: Consultar información de inventario
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function queryInventory(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string',
            'item_type' => 'nullable|string|in:equipos,materiales,kardex',
            'filters' => 'nullable|array'
        ]);

        $query = $request->input('query');
        $itemType = $request->input('item_type');
        $filters = $request->input('filters', []);

        // Validar permisos
        if (!$this->canAccessModule('equipos') && !$this->canAccessModule('materiales')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar inventario'
            ], 403);
        }

        $data = [];

        if ($itemType === 'equipos' || $itemType === null) {
            $equiposQuery = Equipment::query();

            // Aplicar filtros
            if (isset($filters['estado'])) {
                $equiposQuery->where('estado', $filters['estado']);
            }
            if (isset($filters['tipo'])) {
                $equiposQuery->where('tipo', $filters['tipo']);
            }

            $data['equipos'] = [
                'total_equipos' => Equipment::count(),
                'disponibles' => Equipment::where('estado', 'DISPONIBLE')->count(),
                'asignados' => Equipment::where('estado', 'ASIGNADO')->count(),
                'mantenimiento' => Equipment::where('estado', 'MANTENIMIENTO')->count(),
                'inhabilitados' => Equipment::where('estado', 'INHABILITADO')->count(),
                'por_tipo' => Equipment::select('tipo', DB::raw('count(*) as cantidad'))
                    ->groupBy('tipo')
                    ->get()
                    ->pluck('cantidad', 'tipo')
            ];

            // Si hay filtros específicos, incluir lista de equipos
            if (!empty($filters)) {
                $data['equipos']['resultados'] = $equiposQuery->limit(20)->get([
                    'codigo', 'nombre', 'tipo', 'marca', 'modelo', 'estado'
                ]);
            }
        }

        if ($itemType === 'materiales' || $itemType === null) {
            $data['materiales'] = [
                'total_materiales' => Material::where('estado', 'ACTIVO')->count(),
                'bajo_stock' => Material::whereRaw('cantidad_actual <= cantidad_minima')
                    ->where('estado', 'ACTIVO')->count(),
                'sin_stock' => Material::where('cantidad_actual', 0)
                    ->where('estado', 'ACTIVO')->count(),
                'valor_total_inventario' => Material::where('estado', 'ACTIVO')
                    ->selectRaw('SUM(cantidad_actual * precio_unitario) as total')
                    ->value('total'),
                'materiales_bajo_stock' => Material::whereRaw('cantidad_actual <= cantidad_minima')
                    ->where('estado', 'ACTIVO')
                    ->limit(10)
                    ->get(['codigo', 'nombre', 'cantidad_actual', 'cantidad_minima', 'unidad_medida'])
            ];
        }

        if ($itemType === 'kardex') {
            $data['kardex'] = [
                'movimientos_mes' => Kardex::whereMonth('created_at', now()->month)->count(),
                'entradas_mes' => Kardex::where('tipo_movimiento', 'ENTRADA')
                    ->whereMonth('created_at', now()->month)->count(),
                'salidas_mes' => Kardex::where('tipo_movimiento', 'SALIDA')
                    ->whereMonth('created_at', now()->month)->count(),
                'ultimos_movimientos' => Kardex::orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(['tipo_movimiento', 'cantidad', 'created_at', 'observaciones'])
            ];
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'item_type' => $itemType ?? 'general',
            'filters' => $filters,
            'data' => $data,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * RF-IA-08: Verificar permisos de acceso para consulta del chatbot
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkPermissions(Request $request): JsonResponse
    {
        $request->validate([
            'module' => 'required|string',
            'user_id' => 'nullable|integer'
        ]);

        $module = $request->input('module');
        $userId = $request->input('user_id') ?? auth()->id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'has_permission' => false,
                'message' => 'Usuario no autenticado',
                'reason' => 'unauthenticated'
            ], 401);
        }

        $user = User::with('roles.permissions')->findOrFail($userId);

        $modulePermissions = [
            'clientes' => 'ver_clientes',
            'contratos' => 'ver_contratos',
            'pagos' => 'ver_pagos',
            'ventas' => 'ver_ventas',
            'equipos' => 'ver_equipos',
            'materiales' => 'ver_materiales',
            'instalaciones' => 'ver_instalaciones',
            'reportes' => 'ver_reportes',
            'dashboard' => 'ver_dashboard',
            'empleados' => 'ver_empleados',
            'usuarios' => 'gestionar_usuarios'
        ];

        $requiredPermission = $modulePermissions[$module] ?? null;

        if (!$requiredPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo no reconocido',
                'has_permission' => false,
                'available_modules' => array_keys($modulePermissions)
            ], 400);
        }

        // Verificar si el usuario tiene el permiso
        $hasPermission = $user->roles->flatMap->permissions
            ->pluck('nombre')
            ->contains($requiredPermission);

        return response()->json([
            'success' => true,
            'user_id' => $userId,
            'user_name' => $user->name,
            'module' => $module,
            'required_permission' => $requiredPermission,
            'has_permission' => $hasPermission,
            'user_roles' => $user->roles->pluck('nombre'),
            'message' => $hasPermission 
                ? 'Acceso permitido' 
                : 'No tienes permisos para acceder a este módulo'
        ]);
    }

    /**
     * RF-IA-05: Consultar indicadores del sistema
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function queryIndicators(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string',
            'indicator_type' => 'nullable|string|in:ventas,instalaciones,pagos,todos'
        ]);

        $query = $request->input('query');
        $indicatorType = $request->input('indicator_type');

        $indicators = [];

        if (!$indicatorType || $indicatorType === 'ventas') {
            $indicators['ventas'] = [
                'total_mes_actual' => Sale::whereMonth('created_at', now()->month)->count(),
                'monto_total_mes' => Sale::whereMonth('created_at', now()->month)->sum('monto'),
                'promedio_venta' => Sale::whereMonth('created_at', now()->month)->avg('monto'),
                'tasa_conversion' => $this->calculateConversionRate()
            ];
        }

        if (!$indicatorType || $indicatorType === 'instalaciones') {
            $totalInstalaciones = Installation::whereMonth('created_at', now()->month)->count();
            $completadas = Installation::where('estado', 'COMPLETADO')
                ->whereMonth('created_at', now()->month)
                ->count();

            $indicators['instalaciones'] = [
                'completadas_mes' => $completadas,
                'pendientes' => Installation::where('estado', 'PENDIENTE')->count(),
                'en_proceso' => Installation::where('estado', 'EN_PROCESO')->count(),
                'tasa_exito' => $totalInstalaciones > 0 
                    ? round(($completadas / $totalInstalaciones) * 100, 2) 
                    : 0
            ];
        }

        if (!$indicatorType || $indicatorType === 'pagos') {
            $totalPagos = Payment::count();
            $realizados = Payment::where('estado', 'REALIZADO')->count();

            $indicators['pagos'] = [
                'pendientes' => Payment::where('estado', 'PENDIENTE')->count(),
                'vencidos' => Payment::where('estado', 'VENCIDO')->count(),
                'realizados' => $realizados,
                'tasa_cumplimiento' => $totalPagos > 0 
                    ? round(($realizados / $totalPagos) * 100, 2) 
                    : 0,
                'monto_por_cobrar' => Payment::whereIn('estado', ['PENDIENTE', 'VENCIDO'])->sum('monto')
            ];
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'indicator_type' => $indicatorType ?? 'todos',
            'indicators' => $indicators,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * RF-IA-06: Consultar paneles de Business Intelligence
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function queryBI(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string',
            'panel' => 'nullable|string|in:general,ventas,operaciones'
        ]);

        $query = $request->input('query');
        $panel = $request->input('panel');

        $dashboardData = [];

        if (!$panel || $panel === 'general') {
            $dashboardData = [
                'clientes_totales' => Client::count(),
                'contratos_activos' => Contract::where('estado', 'ACTIVO')->count(),
                'ventas_mes' => Sale::whereMonth('created_at', now()->month)->sum('monto'),
                'instalaciones_completadas' => Installation::where('estado', 'COMPLETADO')->count(),
                'pagos_pendientes' => Payment::where('estado', 'PENDIENTE')->count(),
                'equipos_disponibles' => Equipment::where('estado', 'DISPONIBLE')->count()
            ];
        } elseif ($panel === 'ventas') {
            $dashboardData = [
                'ventas_hoy' => Sale::whereDate('created_at', today())->count(),
                'ventas_semana' => Sale::whereBetween('created_at', [
                    now()->startOfWeek(), 
                    now()->endOfWeek()
                ])->count(),
                'ventas_mes' => Sale::whereMonth('created_at', now()->month)->count(),
                'monto_total_mes' => Sale::whereMonth('created_at', now()->month)->sum('monto'),
                'top_vendedores' => Sale::select('responsable_id', DB::raw('COUNT(*) as total'))
                    ->with('responsable:id,nombres,apellidos')
                    ->groupBy('responsable_id')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get()
            ];
        } elseif ($panel === 'operaciones') {
            $dashboardData = [
                'instalaciones_pendientes' => Installation::where('estado', 'PENDIENTE')->count(),
                'instalaciones_proceso' => Installation::where('estado', 'EN_PROCESO')->count(),
                'instalaciones_completadas_mes' => Installation::where('estado', 'COMPLETADO')
                    ->whereMonth('created_at', now()->month)->count(),
                'equipos_en_uso' => Equipment::where('estado', 'ASIGNADO')->count(),
                'materiales_bajo_stock' => Material::whereRaw('cantidad_actual <= cantidad_minima')
                    ->where('estado', 'ACTIVO')->count()
            ];
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'panel' => $panel ?? 'general',
            'dashboard_data' => $dashboardData,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * RF-IA-07: Reportar ausencia de datos suficientes
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reportNoData(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string',
            'reason' => 'nullable|string|in:sin_registros,modulo_vacio,periodo_sin_datos,permiso_denegado,consulta_invalida'
        ]);

        $query = $request->input('query');
        $reason = $request->input('reason');

        $defaultReasons = [
            'sin_registros' => 'No se encontraron registros que coincidan con la consulta',
            'modulo_vacio' => 'El módulo consultado no tiene datos disponibles',
            'periodo_sin_datos' => 'No hay datos para el periodo especificado',
            'permiso_denegado' => 'No tienes permisos para acceder a esta información',
            'consulta_invalida' => 'La consulta no es válida o no se puede procesar'
        ];

        $message = $reason && isset($defaultReasons[$reason]) 
            ? $defaultReasons[$reason]
            : 'No hay información suficiente disponible para responder a su consulta';

        return response()->json([
            'success' => false,
            'query' => $query,
            'message' => $message,
            'reason_code' => $reason ?? 'no_data',
            'sugerencias' => [
                'Intenta reformular tu consulta con términos más específicos',
                'Verifica que los datos existan en el sistema',
                'Consulta con un administrador si necesitas acceso a información restringida',
                'Revisa la ortografía de los nombres de módulos o códigos'
            ],
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * RF-IA-09: Mostrar la fuente de datos utilizada
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function showDataSource(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string',
            'module' => 'required|string|in:clientes,contratos,pagos,ventas,equipos,materiales,instalaciones'
        ]);

        $query = $request->input('query');
        $module = $request->input('module');

        $dataSources = [
            'clientes' => [
                'tabla' => 'clientes',
                'modelo' => 'Client',
                'actualizado' => Client::max('updated_at')
            ],
            'contratos' => [
                'tabla' => 'contratos',
                'modelo' => 'Contract',
                'actualizado' => Contract::max('updated_at')
            ],
            'pagos' => [
                'tabla' => 'pagos',
                'modelo' => 'Payment',
                'actualizado' => Payment::max('updated_at')
            ],
            'ventas' => [
                'tabla' => 'ventas',
                'modelo' => 'Sale',
                'actualizado' => Sale::max('updated_at')
            ],
            'equipos' => [
                'tabla' => 'equipos',
                'modelo' => 'Equipment',
                'actualizado' => Equipment::max('updated_at')
            ],
            'materiales' => [
                'tabla' => 'materiales',
                'modelo' => 'Material',
                'actualizado' => Material::max('updated_at')
            ],
            'instalaciones' => [
                'tabla' => 'instalaciones',
                'modelo' => 'Installation',
                'actualizado' => Installation::max('updated_at')
            ]
        ];

        $source = $dataSources[$module] ?? null;

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo no encontrado'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'data_source' => [
                'module' => $module,
                'tabla_origen' => $source['tabla'],
                'modelo_laravel' => $source['modelo'],
                'ultima_actualizacion' => $source['actualizado'],
                'tipo_base_datos' => config('database.default'),
                'nota' => 'Los datos provienen directamente de la base de datos del sistema en tiempo real'
            ],
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * RF-IA-10: Iniciar una nueva conversación con tracking mejorado
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function startNewConversation(Request $request): JsonResponse
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        // Generar ID de sesión único
        $sessionId = Str::uuid()->toString();

        // Obtener módulos accesibles
        $accessibleModules = $this->getAccessibleModules();

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'started_at' => now()->toDateTimeString(),
            'message' => '¡Hola! Soy tu asistente virtual de Lunnys Perú. ¿En qué puedo ayudarte?',
            'accessible_modules' => $accessibleModules,
            'capabilities' => [
                'consultar_datos_operativos',
                'consultar_inventario',
                'consultar_indicadores',
                'consultar_reportes',
                'buscar_informacion_especifica'
            ]
        ]);
    }

    /**
     * Obtener datos específicos por módulo
     */
    private function getModuleData(string $module): array
    {
        switch ($module) {
            case 'clientes':
                return [
                    'total_clientes' => Client::count(),
                    'clientes_activos' => Client::where('estado', 'ACTIVO')->count(),
                    'clientes_inactivos' => Client::where('estado', 'INACTIVO')->count(),
                    'nuevos_mes_actual' => Client::whereMonth('created_at', now()->month)->count()
                ];

            case 'contratos':
                return [
                    'total_contratos' => Contract::count(),
                    'contratos_activos' => Contract::where('estado', 'ACTIVO')->count(),
                    'contratos_finalizados' => Contract::where('estado', 'FINALIZADO')->count(),
                    'contratos_cancelados' => Contract::where('estado', 'CANCELADO')->count(),
                    'nuevos_mes_actual' => Contract::whereMonth('created_at', now()->month)->count()
                ];

            case 'pagos':
                return [
                    'total_pagos' => Payment::count(),
                    'pagos_pendientes' => Payment::where('estado', 'PENDIENTE')->count(),
                    'pagos_vencidos' => Payment::where('estado', 'VENCIDO')->count(),
                    'pagos_realizados' => Payment::where('estado', 'REALIZADO')->count(),
                    'pagos_impagos' => Payment::where('estado', 'IMPAGO')->count(),
                    'monto_pendiente' => Payment::where('estado', 'PENDIENTE')->sum('monto'),
                    'monto_vencido' => Payment::where('estado', 'VENCIDO')->sum('monto')
                ];

            case 'ventas':
                $ventasMes = Sale::whereMonth('created_at', now()->month);
                return [
                    'total_ventas' => Sale::count(),
                    'ventas_mes_actual' => $ventasMes->count(),
                    'monto_mes_actual' => $ventasMes->sum('monto'),
                    'ventas_confirmadas' => Sale::where('estado', 'CONFIRMADO')->count()
                ];

            case 'instalaciones':
                return [
                    'total_instalaciones' => Installation::count(),
                    'instalaciones_pendientes' => Installation::where('estado', 'PENDIENTE')->count(),
                    'instalaciones_en_proceso' => Installation::where('estado', 'EN_PROCESO')->count(),
                    'instalaciones_completadas' => Installation::where('estado', 'COMPLETADO')->count(),
                    'completadas_mes_actual' => Installation::where('estado', 'COMPLETADO')
                        ->whereMonth('created_at', now()->month)->count()
                ];

            case 'equipos':
                return [
                    'total_equipos' => Equipment::count(),
                    'equipos_disponibles' => Equipment::where('estado', 'DISPONIBLE')->count(),
                    'equipos_asignados' => Equipment::where('estado', 'ASIGNADO')->count(),
                    'equipos_mantenimiento' => Equipment::where('estado', 'MANTENIMIENTO')->count(),
                    'equipos_inhabilitados' => Equipment::where('estado', 'INHABILITADO')->count()
                ];

            case 'materiales':
                return [
                    'total_materiales' => Material::where('estado', 'ACTIVO')->count(),
                    'materiales_bajo_stock' => Material::whereRaw('cantidad_actual <= cantidad_minima')
                        ->where('estado', 'ACTIVO')->count(),
                    'valor_total_inventario' => Material::where('estado', 'ACTIVO')
                        ->selectRaw('SUM(cantidad_actual * precio_unitario) as total')
                        ->value('total')
                ];

            default:
                return ['mensaje' => 'Módulo no implementado'];
        }
    }

    /**
     * Verificar si el usuario puede acceder al módulo
     */
    private function canAccessModule(string $module): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Mapeo de módulos a permisos
        $modulePermissions = [
            'clientes' => 'ver_clientes',
            'contratos' => 'ver_contratos',
            'pagos' => 'ver_pagos',
            'ventas' => 'ver_ventas',
            'instalaciones' => 'ver_instalaciones',
            'equipos' => 'ver_equipos',
            'materiales' => 'ver_materiales'
        ];

        $requiredPermission = $modulePermissions[$module] ?? null;

        if (!$requiredPermission) {
            return false;
        }

        // Verificar si el usuario tiene el permiso
        return $user->roles->flatMap->permissions
            ->pluck('nombre')
            ->contains($requiredPermission);
    }

    /**
     * Obtener todos los módulos accesibles para el usuario actual
     */
    private function getAccessibleModules(): array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        $allModules = [
            'clientes', 'contratos', 'pagos', 'ventas', 
            'equipos', 'materiales', 'instalaciones'
        ];

        $accessibleModules = [];

        foreach ($allModules as $module) {
            if ($this->canAccessModule($module)) {
                $accessibleModules[] = $module;
            }
        }

        return $accessibleModules;
    }

    /**
     * Calcular tasa de conversión de contratos a ventas
     */
    private function calculateConversionRate(): float
    {
        $totalContratos = Contract::whereMonth('created_at', now()->month)->count();
        $contratosConfirmados = Sale::whereMonth('created_at', now()->month)->count();

        return $totalContratos > 0 
            ? round(($contratosConfirmados / $totalContratos) * 100, 2) 
            : 0;
    }
}
