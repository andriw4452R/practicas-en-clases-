<?php
return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'queue' => 'p31.reportes.pendientes',
    'errors' => 'p31.reportes.errores',
    'dlx' => 'p31.reportes.dlx',
];
