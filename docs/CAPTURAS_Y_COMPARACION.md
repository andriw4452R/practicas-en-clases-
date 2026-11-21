# Evidencias individuales obligatorias

1. Postman: **tres POST válidos** con respuesta HTTP 202 y sus identificadores.
2. RabbitMQ: `p31.reportes.pendientes` con **3 mensajes pendientes**, consumidor detenido.
3. PowerShell: consumidor ejecutado, **3 PROCESADA**, y cola en cero después.
4. Postman: mensaje inválido → **HTTP 422**, mostrar reglas de validación.
5. Postman: GET con **mismo `correlation_id`**, antes pendiente y después procesada.
6. Postman: POST síncrono y asíncrono con el **mismo tipo de datos** y tiempos de ambas respuestas.
7. `php artisan test`: pruebas realmente aprobadas (no inventar cifras).
8. Diagrama de `docs/diagrama.md` y enlace al código fuente.

| Comparación | Síncrono | Asíncrono |
|---|---|---|
| Tiempo respuesta inicial | Medir: ___ ms | Medir: ___ ms |
| Dependencia del consumidor | Procesa en la petición | No requiere consumidor activo para publicar |
| Consumidor detenido | No aplica consumidor externo | Mensaje queda pendiente |
| Trazabilidad | message_id y correlation_id | message_id y correlation_id |
| Finalización | Antes de responder | Después de responder, consultable por GET |

No es obligatorio que el flujo asíncrono responda más rápido; depende de la carga y red.

**Nota metodológica:** los datos son demostrativos; los procesos nuevos se inspiran en
módulos del backend real, pero están implementados de forma aislada para esta práctica.
