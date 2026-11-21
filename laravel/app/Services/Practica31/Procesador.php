<?php
namespace App\Services\Practica31;

use Illuminate\Support\Facades\DB;

class Procesador
{
    public function ejecutar(array $mensaje): array
    {
        foreach (['message_id', 'correlation_id', 'tipo', 'ocurrido_en', 'payload'] as $campo) {
            if (!array_key_exists($campo, $mensaje)) {
                throw new \InvalidArgumentException('Campo ausente: '.$campo);
            }
        }
        if (!is_string($mensaje['message_id']) || !is_string($mensaje['correlation_id']) ||
            !\Illuminate\Support\Str::isUuid($mensaje['message_id']) ||
            !\Illuminate\Support\Str::isUuid($mensaje['correlation_id']) ||
            $mensaje['tipo'] !== 'reporte.solicitado' || !is_string($mensaje['ocurrido_en']) ||
            !is_array($mensaje['payload'])) {
            throw new \InvalidArgumentException('Formato o identificadores inválidos');
        }
        $registro = DB::table('tareas_reportes')->where('message_id', $mensaje['message_id'])->first();
        if (!$registro || $registro->correlation_id !== $mensaje['correlation_id']) {
            throw new \InvalidArgumentException('No coincide el registro de correlación');
        }
        if ($registro->estado === 'procesada') {
            return json_decode($registro->resultado, true) ?: [];
        }
        if (!in_array($registro->estado, ['pendiente', 'procesando'], true)) {
            throw new \InvalidArgumentException('El registro no está pendiente');
        }
        $payload = $mensaje['payload'];
        $inicio = microtime(true);
        $tipo = $payload['tipo_reporte'] ?? null;
        $anio = $payload['anio'] ?? null;
        if (!in_array($tipo, ['clinico','financiero'], true) || !is_int($anio) || $anio < 2020 || $anio > 2100) {
            throw new \InvalidArgumentException('Payload de reporte inválido');
        }
        // Datos ficticios; el proceso se inspira en ReporteService del backend real.
        $citas = [
            ['anio'=>2026,'estado'=>'pendiente','precio'=>20,'egreso'=>5],
            ['anio'=>2026,'estado'=>'completada','precio'=>45,'egreso'=>12],
            ['anio'=>2026,'estado'=>'completada','precio'=>30,'egreso'=>8],
            ['anio'=>2025,'estado'=>'cancelada','precio'=>0,'egreso'=>3],
            ['anio'=>2025,'estado'=>'completada','precio'=>35,'egreso'=>10],
        ];
        $filtradas = array_values(array_filter($citas, fn($r) => $r['anio'] === $anio));
        if ($tipo === 'clinico') {
            $porEstado = [];
            foreach ($filtradas as $fila) {
                $porEstado[$fila['estado']] = ($porEstado[$fila['estado']] ?? 0) + 1;
            }
            $resultado = ['tipo'=>'clinico','anio'=>$anio,'total_citas'=>count($filtradas),'por_estado'=>$porEstado];
        } else {
            $ingresos = array_sum(array_column($filtradas,'precio'));
            $egresos = array_sum(array_column($filtradas,'egreso'));
            $resultado = ['tipo'=>'financiero','anio'=>$anio,'ingresos'=>$ingresos,'egresos'=>$egresos,'saldo'=>$ingresos-$egresos];
        }
        $milisegundos = round((microtime(true) - $inicio) * 1000, 4);
        DB::transaction(function () use ($mensaje, $resultado, $milisegundos) {
            DB::table('tareas_reportes')->where('message_id', $mensaje['message_id'])->update([
                'estado' => 'procesada',
                'resultado' => json_encode($resultado, JSON_THROW_ON_ERROR),
                'tiempo_procesamiento_ms' => $milisegundos,
                'procesado_en' => now(), 'updated_at' => now(),
            ]);
        });
        return $resultado;
    }
}
