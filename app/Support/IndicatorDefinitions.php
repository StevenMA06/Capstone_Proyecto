<?php
namespace App\Support;

/** Business boundaries from LEVANTAMIENTO_PRETEST; not inferred from record creation dates. */
class IndicatorDefinitions
{
    public static function all(): array
    {
        return [
            'clientes'=>['module'=>'clientes','title'=>'Registro de clientes','rf'=>'RF-IND-01','validated'=>true,
                'start'=>'El trabajador comienza a ingresar el DNI del cliente en Mikrowisp.',
                'end'=>'Se completan los datos requeridos y el trabajador hace clic en «Guardar».',
                'evidence'=>'El cliente puede visualizarse posteriormente en Mikrowisp.',
                'scope'=>'Tiempo efectivo de registro. La búsqueda previa y la instalación física no forman parte de este intervalo. Las interrupciones ajenas al registro se anotan por separado.'],
            'contratos'=>['module'=>'contratos','title'=>'Registro de contratos','rf'=>'RF-IND-02','validated'=>true,
                'start'=>'El técnico comienza a completar la plantilla física después de la instalación.',
                'end'=>'El contrato está completo y firmado, la evidencia se envía por WhatsApp y Call Center verifica la conformidad con la plantilla.',
                'evidence'=>'Evidencia enviada, verificación de Call Center y copia física del contrato.',
                'scope'=>'La firma del cliente por sí sola no cierra la medición. Incluye el envío y la verificación de la evidencia; no incluye la ejecución física de la instalación.'],
            'reactivacion'=>['module'=>'pagos','title'=>'Gestión de reactivación del servicio','rf'=>'RF-IND-03','validated'=>false,
                'start'=>'Pendiente de validar el evento que inicia la gestión después de regularizar el pago.',
                'end'=>'Pendiente de validar la evidencia que confirma la reactivación del servicio.',
                'evidence'=>'Por definir con el responsable del proceso.',
                'scope'=>'El tiempo de registro del pago externo no equivale al tiempo de reactivación. El levantamiento describe excepciones, pero no cierra este intervalo.'],
            'instalaciones'=>['module'=>'instalaciones','title'=>'Gestión de pedidos de instalación','rf'=>'RF-IND-04','validated'=>true,
                'start'=>'Call Center comienza a gestionar una solicitud que ya cuenta con la información necesaria del cliente.',
                'end'=>'La fecha está coordinada con el cliente, los técnicos están asignados y la programación se comunica en el grupo de WhatsApp.',
                'evidence'=>'Mensaje de programación con fecha y técnicos asignados.',
                'scope'=>'Mide la coordinación del pedido. No incluye traslado, recojo de recursos ni ejecución física de la instalación.'],
            'acceso_informacion'=>['module'=>'all','title'=>'Acceso a información operativa de Gerencia','rf'=>'RF-IND-05','validated'=>false,
                'start'=>'Pendiente de validar qué solicitud o acción inicia la consulta de Gerencia.',
                'end'=>'Pendiente de validar cuándo la información solicitada se considera obtenida.',
                'evidence'=>'Por definir con Gerencia.',
                'scope'=>'No se sustituye por el tiempo técnico de descargar un archivo ni por la duración de una consulta SQL.'],
            'inventario'=>['module'=>'inventario','title'=>'Registro de inventario','rf'=>'RF-IND-06 / RF-IND-07','validated'=>false,
                'start'=>'Pendiente de validar con la encargada de almacén.',
                'end'=>'Pendiente de validar con la encargada de almacén.',
                'evidence'=>'Por definir: unidad de observación, datos obligatorios y fuente de contraste.',
                'scope'=>'Se distinguirán Equipos, Materiales y Kardex cuando se valide el proceso. No se inventa un intervalo común para estos tres registros.'],
            'pagos_externos'=>['module'=>'pagos','title'=>'Registro de pagos externos · medición complementaria','rf'=>null,'validated'=>true,
                'start'=>'Call Center comienza a procesar un comprobante externo ya disponible para su revisión y registro.',
                'end'=>'Se completan los campos requeridos y se hace clic en «Registrar pago».',
                'evidence'=>'El pago queda visible y asociado al cliente en Mikrowisp.',
                'scope'=>'Incluye la revisión y el registro ordinario. Los comprobantes ilegibles, pagos no corroborados y casos de reactivación se identifican como excepciones. No reemplaza RF-IND-03.'],
        ];
    }

    public static function measurable(): array
    {
        return array_filter(self::all(), fn(array $item)=>$item['validated']);
    }

    public static function forProcess(string $process): array
    {
        return array_filter(self::all(), fn(array $item)=>$process==='all' || $item['module']===$process || $item['module']==='all');
    }
}
