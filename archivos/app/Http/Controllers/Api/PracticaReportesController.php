<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Practica31\Broker;
use App\Services\Practica31\Procesador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PracticaReportesController extends Controller
{
    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo_reporte' => ['required', 'in:clinico,financiero'],
            'anio' => ['required', 'integer', 'between:2020,2100'],
            'correlation_id' => ['sometimes','nullable','uuid'],
        ]);
    }

    private function crearMensaje(array $valido, string $modo): array
    {
        $correlationId = $valido['correlation_id'] ?? (string) Str::uuid();
        $messageId = (string) Str::uuid();
        $datos = ['tipo_reporte' => $valido['tipo_reporte'], 'anio' => (int) $valido['anio']];
        $mensaje = [
            'message_id' => $messageId,
            'correlation_id' => $correlationId,
            'tipo' => 'reporte.solicitado',
            'ocurrido_en' => now()->toIso8601String(),
            'payload' => $datos,
        ];
        DB::table('tareas_reportes')->insert([
            'message_id'=>$messageId,'correlation_id'=>$correlationId,
            'modo'=>$modo,'estado'=>'pendiente',
            'solicitud_json'=>json_encode($datos, JSON_THROW_ON_ERROR),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $mensaje;
    }

    public function publicar(Request $request, Broker $broker): JsonResponse
    {
        $valido = $this->validar($request);
        $inicio = microtime(true);
        $mensaje = $this->crearMensaje($valido, 'asincrono');
        try {
            $broker->publicar($mensaje);
        } catch (\Throwable $error) {
            DB::table('tareas_reportes')->where('message_id', $mensaje['message_id'])->update([
                'estado'=>'fallo_publicacion', 'detalle'=>'No se confirmó la publicación', 'updated_at'=>now(),
            ]);
            report($error);
            return response()->json(['error'=>'Broker no disponible','correlation_id'=>$mensaje['correlation_id']], 503);
        }
        $duracion = round((microtime(true)-$inicio)*1000, 3);
        DB::table('tareas_reportes')->where('message_id', $mensaje['message_id'])->update([
            'tiempo_respuesta_ms'=>$duracion, 'updated_at'=>now(),
        ]);
        return response()->json([
            'estado'=>'pendiente','message_id'=>$mensaje['message_id'],
            'correlation_id'=>$mensaje['correlation_id'],
            'tiempo_respuesta_ms'=>$duracion,
        ], 202);
    }

    public function sincrono(Request $request, Procesador $procesador): JsonResponse
    {
        $valido = $this->validar($request);
        $inicio = microtime(true);
        $mensaje = $this->crearMensaje($valido, 'sincrono');
        try {
            $resultado = $procesador->ejecutar($mensaje);
        } catch (\Throwable $error) {
            DB::table('tareas_reportes')->where('message_id', $mensaje['message_id'])->update([
                'estado'=>'error','detalle'=>'Error al procesar', 'updated_at'=>now(),
            ]);
            report($error);
            return response()->json(['error'=>'Procesamiento fallido','correlation_id'=>$mensaje['correlation_id']], 422);
        }
        $duracion = round((microtime(true)-$inicio)*1000, 3);
        DB::table('tareas_reportes')->where('message_id', $mensaje['message_id'])->update([
            'tiempo_respuesta_ms'=>$duracion, 'updated_at'=>now(),
        ]);
        return response()->json([
            'estado'=>'procesada', 'message_id'=>$mensaje['message_id'],
            'correlation_id'=>$mensaje['correlation_id'],
            'resultado'=>$resultado,'tiempo_respuesta_ms'=>$duracion,
        ], 200);
    }

    public function consultar(string $correlationId): JsonResponse
    {
        if (!Str::isUuid($correlationId)) {
            return response()->json(['error'=>'correlation_id inválido'], 422);
        }
        $registros = DB::table('tareas_reportes')->where('correlation_id', $correlationId)
            ->orderBy('id')->get()->map(function($registro) {
                $registro->resultado = $registro->resultado ? json_decode($registro->resultado, true) : null;
                $registro->solicitud_json = $registro->solicitud_json ? json_decode($registro->solicitud_json, true) : null;
                return $registro;
            });
        if ($registros->isEmpty()) return response()->json(['error'=>'No encontrado'], 404);
        return response()->json(['correlation_id'=>$correlationId,'tareas'=>$registros]);
    }
}
