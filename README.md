# Práctica 3.1 – Generación de reportes clínicos y financieros

Este trabajo es **individual e independiente**. Está vinculado al módulo existente
`backend-api/app/Services/ReporteService.php` del proyecto odontológico
`https://github.com/Jordyfr2004/proyect_clinico`, pero **no modifica el repositorio
original ni accede a sus datos**. Se usan datos ficticios y SQLite.

## Requisitos

- Windows 11, PHP 8.2+ (extensiones `sockets`, `mbstring`, `pdo_sqlite`), Composer y Docker Desktop.
- RabbitMQ en `rabbitmq-practica31`, accesible por `127.0.0.1:5672`.
- Internet solo para la primera instalación de dependencias Laravel/Composer.
- Postman para las capturas de las tres solicitudes.

## Inicio (no saltarse esta instalación)

1. Desde PowerShell, **en esta carpeta**, ejecutar `./INSTALAR-PRIMERO.ps1` una sola vez.
   Este comando crea la carpeta `laravel/`, instala dependencias, copia el código de la práctica,
   prepara SQLite, registra rutas, ejecuta migraciones y pruebas. **El ZIP no incluye `vendor/`.**
2. Si todavía no está ejecutándose RabbitMQ, iniciar Docker Desktop y usar
   `docker start rabbitmq-practica31`. Si el contenedor no existe, primero crear uno
   local con puertos 127.0.0.1:5672 y 127.0.0.1:15672.
3. En la primera terminal: `./INICIAR-API.ps1`
4. En la segunda terminal: `./VER-COLAS.ps1` (no iniciar consumidor todavía).
5. Importar `postman/practica31.postman_collection.json` y ejecutar las **tres peticiones válidas**.
   Guardar el `correlation_id` de una respuesta y consultar el GET.
6. Capturar los mensajes pendientes con `./VER-COLAS.ps1` y/o en http://localhost:15672.
7. En otra terminal ejecutar `./INICIAR-CONSUMIDOR.ps1`.
8. Consultar de nuevo por `correlation_id`: ahora `estado=procesada`.
9. Ejecutar la petición inválida (HTTP 422), y después la síncrona. Registrar **tiempos reales**.

## Endpoints

- `POST http://127.0.0.1:8001/api/p31/reportes` → 202, pendiente (publicación confirmada)
- `POST http://127.0.0.1:8001/api/p31/reportes/sincrono` → 200, procesada
- `GET http://127.0.0.1:8001/api/p31/reportes/{correlation_id}` → consulta del estado

Cola durable: `p31.reportes.pendientes`. Cola de errores: `p31.reportes.errores`.
Comando del consumidor: `php artisan p31:consumir-reportes --max=3`.

## Diferencias y seguridad

El productor responde 202 **cuando RabbitMQ confirma que publicó el mensaje**, no cuando termina
el consumidor. Cada mensaje contiene `message_id`, `correlation_id`, `tipo`, `ocurrido_en`
y un `payload` mínimo. El consumidor solo hace ACK después de registrar el resultado.
Los mensajes malformados se rechazan sin requeue hacia la cola de errores usando DLX.
La validación HTTP rechaza datos inválidos con 422. Se usan datos ficticios.

**No se publican** `.env`, bases SQLite ni claves; se utiliza configuración local de RabbitMQ.
Un resultado de `php artisan test` no sustituye las pruebas manuales con RabbitMQ y Postman.

## Documentación y capturas

- `docs/diagrama.md` — secuencia y comparación síncrona/asíncrona
- `docs/CAPTURAS_Y_COMPARACION.md` — lista de capturas y tabla para rellenar
- `postman/practica31.postman_collection.json` — solicitudes válidas, inválidas y consulta

La aplicación corre en HTTP local, **sin cifrado TLS de producción**. No usar datos reales.
