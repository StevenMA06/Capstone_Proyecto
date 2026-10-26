<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Payment;
use Illuminate\Support\Str;

class LocalChatService
{
    public function query(string $message): array
    {
        $question = Str::lower(Str::ascii($message));
        $reply = $this->answer($question);

        return [
            'success' => true,
            'response' => $reply,
            'source' => 'Base de datos',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function answer(string $question): string
    {
        if (preg_match('/\b(resumen|general|negocio)\b/', $question)) {
            return "**Resumen del negocio**\n"
                . '- **Clientes:** '.Client::count().' en total; '.Client::where('estado', 'ACTIVO')->count()." activos.\n"
                . '- **Contratos:** '.Contract::count().' en total; '.Contract::where('estado', 'ACTIVO')->count()." activos.\n"
                . '- **Pagos pendientes:** '.Payment::where('estado', 'PENDIENTE')->count().".\n"
                . '- **Equipos disponibles:** '.Equipment::where('estado', 'DISPONIBLE')->count().".\n"
                . '- **Materiales activos:** '.Material::where('estado', 'ACTIVO')->count().'.';
        }

        if (preg_match('/\b(cliente|clientes)\b/', $question)) {
            if (preg_match('/\b(datos|lista|nombres|dni|telefonos|correos|direcciones|todos)\b/', $question)) {
                return 'Por privacidad, solo puedo mostrar métricas agregadas de clientes. Hay **'.Client::count().' clientes** en total y **'.Client::where('estado', 'ACTIVO')->count().' activos**.';
            }
            return 'Hay **'.Client::count().' clientes** en total y **'.Client::where('estado', 'ACTIVO')->count().' activos**.';
        }

        if (preg_match('/\b(contrato|contratos)\b/', $question)) {
            if (preg_match('/\b(mes|mensual|este mes)\b/', $question)) {
                $count = Contract::whereBetween('fecha_registro', [now()->startOfMonth(), now()->endOfMonth()])->count();
                return 'Este mes se registraron **'.$count.' contratos** (según fecha de registro).';
            }
            return 'Hay **'.Contract::count().' contratos** en total y **'.Contract::where('estado', 'ACTIVO')->count().' activos**.';
        }

        if (preg_match('/\b(pago|pagos|deuda|mora)\b/', $question)) {
            if (preg_match('/\b(vencid|atrasad|mora)/', $question)) {
                return 'Hay **'.Payment::where('estado', 'VENCIDO')->count().' pagos vencidos**.';
            }
            return 'Hay **'.Payment::where('estado', 'PENDIENTE')->count().' pagos pendientes** y **'.Payment::where('estado', 'VENCIDO')->count().' vencidos**.';
        }

        if (preg_match('/\b(equipo|equipos)\b/', $question)) {
            return 'Hay **'.Equipment::count().' equipos** en total; **'.Equipment::where('estado', 'DISPONIBLE')->count().' disponibles** y **'.Equipment::where('estado', 'MANTENIMIENTO')->count().' en mantenimiento**.';
        }

        if (preg_match('/\b(material|materiales|stock|inventario)\b/', $question)) {
            $low = Material::where('estado', 'ACTIVO')->whereColumn('stock_actual', '<=', 'stock_minimo')->count();
            return 'Hay **'.Material::where('estado', 'ACTIVO')->count().' materiales activos**; **'.$low.' con stock bajo o crítico**.';
        }

        if (preg_match('/\b(empleado|empleados|personal)\b/', $question)) {
            return 'Hay **'.Employee::count().' empleados** en total y **'.Employee::where('estado', 'ACTIVO')->count().' activos**. Por privacidad, solo muestro cifras agregadas.';
        }

        if (preg_match('/\b(hola|buenas|saludos|hey)\b/', $question)) {
            return '¡Hola! Puedo consultar métricas de clientes, contratos, pagos, equipos, materiales y empleados. También puedes pedirme un resumen general.';
        }

        return 'Ahora estoy usando el asistente interno. Puedo responder consultas de métricas sobre clientes, contratos, pagos, equipos, materiales y empleados. Prueba, por ejemplo: “¿Cuántos pagos pendientes hay?”';
    }
}
