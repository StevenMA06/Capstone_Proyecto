<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Muestra la interfaz del chatbot
     */
    public function index(): View
    {
        return view('chatbot.index');
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

        // Verificar que Gemini esté configurado
        if (!$this->gemini->isConfigured()) {
            return response()->json([
                'success' => false,
                'response' => 'El chatbot no está configurado correctamente. Por favor, contacta al administrador.',
                'source' => 'Sistema'
            ]);
        }

        // Procesar la consulta con Gemini
        return response()->json($this->gemini->query($message, $history));
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
}
