<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
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
            'message' => 'required|string|max:500',
            'history' => 'nullable|array',
        ]);

        // Por ahora devolvemos una respuesta de prueba
        // Aquí se integrará con ChatbotService cuando esté configurado
        
        $message = $request->input('message');
        
        // Verificar si contiene palabras sospechosas (protección básica)
        if ($this->containsMaliciousContent($message)) {
            return response()->json([
                'success' => false,
                'response' => 'No puedo procesar esa consulta por razones de seguridad.',
                'source' => null
            ]);
        }

        // Respuesta temporal mientras se configura la API de ChatGPT
        return response()->json([
            'success' => true,
            'response' => 'El chatbot está en configuración. Por favor, espera mientras se completa la integración con la API de ChatGPT.',
            'source' => 'Sistema',
            'timestamp' => now()->toIso8601String()
        ]);
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
            '/ignore\s+(previous|all|instructions)/i',
            '/contraseña|password|passwd/i',
            '/api[_\s]?key/i',
            '/token/i',
            '/\.env/i',
            '/database|sql/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+\w+\s+set/i',
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
}
