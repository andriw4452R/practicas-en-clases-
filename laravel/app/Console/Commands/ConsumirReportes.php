<?php
namespace App\Console\Commands;

use App\Services\Practica31\Broker;
use App\Services\Practica31\Procesador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Exception\AMQPTimeoutException;

class ConsumirReportes extends Command
{
    protected $signature = 'p31:consumir-reportes {--max=0 : Cantidad máxima; 0 para ejecución continua}';
    protected $description = 'Consume tareas individuales reportes desde RabbitMQ';

    public function handle(Broker $broker, Procesador $procesador): int
    {
        $limite = (int) $this->option('max');
        $conexion = $broker->conectar();
        $canal = $conexion->channel();
        $broker->preparar($canal);
        $canal->basic_qos(null, 1, null);
        $cantidad = 0;
        $this->info('Consumidor iniciado: '.config('rabbitmq.queue'));
        try {
            $canal->basic_consume(config('rabbitmq.queue'), '', false, false, false, false,
                function ($entrega) use ($procesador, &$cantidad, $limite) {
                    $mensaje = null;
                    try {
                        $mensaje = json_decode($entrega->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        if (!is_array($mensaje)) throw new \InvalidArgumentException('JSON no es un objeto');
                        $procesador->ejecutar($mensaje);
                        $entrega->ack();
                        $this->line('PROCESADA message_id='.$mensaje['message_id'].' correlation_id='.$mensaje['correlation_id']);
                    } catch (\Throwable $error) {
                        if (is_array($mensaje) && isset($mensaje['message_id']) && is_string($mensaje['message_id'])) {
                            DB::table('tareas_reportes')->where('message_id',$mensaje['message_id'])
                                ->where('estado','!=','procesada')->update([
                                'estado'=>'error','detalle'=>substr($error->getMessage(),0,150),
                                'updated_at'=>now(),
                            ]);
                        }
                        // Rechazo sin requeue: RabbitMQ redirige a la cola de errores (DLX).
                        $entrega->nack(false, false);
                        $this->error('RECHAZADA: '.$error->getMessage());
                    }
                    $cantidad++;
                    if ($limite > 0 && $cantidad >= $limite) {
                        $entrega->getChannel()->basic_cancel($entrega->getConsumerTag());
                    }
                });
            while ($canal->is_consuming()) {
                try { $canal->wait(null, false, 2); }
                catch (AMQPTimeoutException $ignorar) { /* Seguir esperando. */ }
            }
        } finally {
            $canal->close();
            $conexion->close();
        }
        return self::SUCCESS;
    }
}
