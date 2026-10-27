# Guía de trabajo de Reportes Progel

## Alcance

Este proyecto es independiente de `reportes_direccion`. Actualmente contiene únicamente el reporte `reports/votators/`.

## Arquitectura

- Mantener sensores, unidades, rangos y variables de conexión en `reports/votators/config.php`.
- Mantener la consulta AVEVA en `src/AvevaRepository.php`.
- Mantener la evaluación y preparación de datos en `src/VotatorsService.php`.
- `index.php` renderiza; `live.php` entrega JSON y no duplica consultas ni reglas.
- No agregar dependencias de `secadores`, `produccion-monitoreo` ni del repositorio original.

## Seguridad y tolerancia a fallos

- Nunca versionar `.env` ni copiar credenciales al código.
- Validar nombres de tabla, timestamp y sensores antes de interpolarlos en SQL.
- Ante una falla de AVEVA, la vista debe cargar con `—` y el endpoint debe responder con un mensaje genérico.

## Verificación

```bash
docker compose config
docker compose up --build -d
docker exec reportes_progel_app php -l /var/www/html/reports/votators/index.php
docker exec reportes_progel_app php -l /var/www/html/reports/votators/live.php
curl -I http://127.0.0.1:8083/
```

