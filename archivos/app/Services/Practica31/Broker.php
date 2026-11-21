<?php
namespace App\Services\Practica31;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Broker
{
    public function conectar(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            config('rabbitmq.host'), config('rabbitmq.port'),
            config('rabbitmq.user'), config('rabbitmq.password'),
            config('rabbitmq.vhost')
        );
    }

    public function preparar($canal): void
    {
        $canal->exchange_declare(config('rabbitmq.dlx'), 'direct', false, true, false);
        $canal->queue_declare(config('rabbitmq.errors'), false, true, false, false);
        $canal->queue_bind(config('rabbitmq.errors'), config('rabbitmq.dlx'), 'errores');
        $argumentos = new AMQPTable([
            'x-dead-letter-exchange' => config('rabbitmq.dlx'),
            'x-dead-letter-routing-key' => 'errores',
        ]);
        $canal->queue_declare(config('rabbitmq.queue'), false, true, false, false, false, $argumentos);
    }

    public function publicar(array $datos): void
    {
        $conexion = $this->conectar();
        $canal = $conexion->channel();
        try {
            $this->preparar($canal);
            $canal->confirm_select();
            $mensaje = new AMQPMessage(json_encode($datos, JSON_THROW_ON_ERROR), [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $datos['message_id'],
                'correlation_id' => $datos['correlation_id'],
            ]);
            $canal->basic_publish($mensaje, '', config('rabbitmq.queue'));
            // No se devuelve 202 sin confirmación del broker.
            $canal->wait_for_pending_acks_returns(5);
        } finally {
            $canal->close();
            $conexion->close();
        }
    }
}
