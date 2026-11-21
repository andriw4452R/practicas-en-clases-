# Flujo de generación de reportes clínicos y financieros

```mermaid
sequenceDiagram
    participant P as Postman
    participant API as Laravel productor
    participant DB as SQLite
    participant B as RabbitMQ
    participant Q as Cola p31.reportes.pendientes
    participant C as Consumidor Artisan
    P->>API: POST con datos ficticios
    API->>DB: INSERT pendiente con message_id y correlation_id
    API->>B: Publica mensaje JSON persistente
    B->>Q: Guarda mensaje durable
    B-->>API: Publisher confirm
    API-->>P: HTTP 202 pendiente y correlation_id
    Q->>C: Entrega mientras corre consumidor
    C->>DB: Verifica y registra resultado
    C-->>B: ACK después de guardar
    P->>API: GET por correlation_id
    API->>DB: Consulta estado
    API-->>P: procesada y métricas
```

Ante formato inválido del mensaje, el consumidor realiza NACK sin requeue y RabbitMQ
redirige a `p31.reportes.errores` por DLX. La versión síncrona llama directamente al procesador
y responde cuando termina; no pasa por RabbitMQ.
