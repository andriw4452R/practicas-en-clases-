<?php
namespace Tests\Feature;

use App\Services\Practica31\Broker;
use App\Services\Practica31\Procesador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PracticaReportesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_datos_invalidos_sin_publicar(): void
    {
        $this->postJson('/api/p31/reportes', [])->assertStatus(422);
    }
    public function test_publica_202_con_ids_y_registro_pendiente(): void
    {
        $this->mock(Broker::class, function($mock) { $mock->shouldReceive('publicar')->once(); });
        $respuesta = $this->postJson('/api/p31/reportes', ['tipo_reporte' => 'clinico', 'anio' => 2026])->assertStatus(202)->assertJsonPath('estado','pendiente');
        $this->assertTrue(Str::isUuid($respuesta->json('message_id')));
        $this->assertTrue(Str::isUuid($respuesta->json('correlation_id')));
        $this->assertDatabaseHas('tareas_reportes', ['message_id'=>$respuesta->json('message_id'), 'estado'=>'pendiente']);
        $this->getJson('/api/p31/reportes/'.$respuesta->json('correlation_id'))->assertOk();
    }
    public function test_sincrono_procesa_y_guarda_resultado(): void
    {
        $r = $this->postJson('/api/p31/reportes/sincrono', ['tipo_reporte' => 'clinico', 'anio' => 2026])
            ->assertOk()->assertJsonPath('estado','procesada');
        $this->assertDatabaseHas('tareas_reportes', ['message_id'=>$r->json('message_id'),'estado'=>'procesada']);
    }
    public function test_rechazo_del_broker_no_afirma_procesamiento(): void
    {
        $this->mock(Broker::class, function($mock) { $mock->shouldReceive('publicar')->andThrow(new \RuntimeException('inaccesible')); });
        $this->postJson('/api/p31/reportes', ['tipo_reporte' => 'clinico', 'anio' => 2026])->assertStatus(503);
        $this->assertDatabaseCount('tareas_reportes', 1);
        $this->assertDatabaseHas('tareas_reportes', ['estado'=>'fallo_publicacion']);
    }
    public function test_procesamiento_duplicado_es_idempotente(): void
    {
        $id = (string) Str::uuid(); $corr=(string) Str::uuid();
        $payload = ['tipo_reporte' => 'clinico', 'anio' => 2026];
        DB::table('tareas_reportes')->insert([
            'message_id'=>$id,'correlation_id'=>$corr,'modo'=>'asincrono','estado'=>'pendiente',
            'solicitud_json'=>json_encode($payload), 'created_at'=>now(),'updated_at'=>now(),
        ]);
        $msg=['message_id'=>$id,'correlation_id'=>$corr,'tipo'=>'reporte.solicitado',
            'ocurrido_en'=>now()->toIso8601String(),'payload'=>$payload];
        $procesador=app(Procesador::class);
        $a=$procesador->ejecutar($msg);
        $b=$procesador->ejecutar($msg);
        $this->assertSame($a,$b);
        $this->assertDatabaseHas('tareas_reportes', ['message_id'=>$id,'estado'=>'procesada']);
    }
}
