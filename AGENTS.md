# Guía de trabajo de Reportes Progel

## Regla principal de mantenimiento

- Elegir siempre la solución más simple, explícita y fácil de leer que resuelva el cambio solicitado.
- Evitar clases, servicios, repositorios, patrones de diseño y abstracciones para lógica que sólo usa este reporte.
- No crear capas “por si se necesitan después”. Una abstracción nueva debe tener una necesidad real y más de un consumidor.
- Preferir funciones pequeñas, nombres descriptivos y un flujo directo: configuración, consulta/preparación, vista o JSON.
- Documentar en español únicamente las decisiones o pasos que no resulten evidentes por el propio código.
- Mantener cada cambio acotado; no reformatear ni reorganizar archivos ajenos a la solicitud.
- Estas reglas aplican a todos los cambios futuros de este proyecto.

## Alcance

Este proyecto es independiente de `reportes_direccion`. Contiene los reportes `reports/votators/`, `reports/secadores/`, `reports/cocedores/`, `reports/clarificadores/` y `reports/concentradores/`.

## Arquitectura

- `index.php` redirige a la selección general en `reports/`.
- `reports/index.php` es el selector de grupos, subgrupos y reportes.
- `reports/votators/config.php` contiene conexión, sensores, unidades y rangos.
- `reports/votators/build_report.php` consulta AVEVA, evalúa las lecturas y prepara el reporte.
- `reports/votators/index.php` renderiza la vista.
- `reports/votators/live.php` reutiliza `build_report.php` y entrega el JSON de actualización.
- Secadores conserva la misma estructura simple dentro de `reports/secadores/`.
- Cocedores conserva la misma estructura simple dentro de `reports/cocedores/` y combina AVEVA con `progel_procesos.datos_cocedores`.
- Clarificador conserva la misma estructura simple dentro de `reports/clarificadores/` y combina AVEVA con `progel_procesos.datos_clarificador`.
- Secadores muestra un túnel por pantalla y rota entre túneles según `SECADORES_ROTATION_MS`.
- No agregar dependencias de `reportes_direccion`, `produccion-monitoreo` ni del repositorio original.

## Escala responsiva de reportes

- Todos los reportes cargan `assets/css/report-responsive.css` después de sus estilos locales.
- El contenedor principal de cada reporte lleva el atributo `data-report-responsive`.
- Los reportes nuevos deben seguir ambas reglas para heredar la escala común de pantallas grandes.
- Para elementos nuevos reutilizables se pueden usar `report-scale-title`, `report-scale-label` y `report-scale-value`; si el reporte tiene una estructura propia, agregar sus selectores al archivo compartido.
- La ampliación de alta resolución se aplica desde 1600 px de ancho y más de 1200 px de alto. Full HD conserva una escala compacta para que el tablero completo permanezca visible.
- El archivo compartido no modifica `capture-mode`; cada reporte conserva su composición específica para capturas.

## Seguridad y tolerancia a fallos

- Nunca versionar `.env` ni copiar credenciales al código, documentación o mensajes.
- El servidor ejecuta Apache/PHP directamente y no dispone de Docker.
- `config/environment.php` carga `.env`; las variables del sistema o de Apache tienen prioridad.
- Mantener `.htaccess` para impedir el acceso web a archivos ocultos como `.env`.
- Validar nombres de tabla, timestamp y sensores antes de interpolarlos en SQL.
- Ante una falla de AVEVA, la vista debe cargar con `—` y el endpoint debe responder con un mensaje genérico.

## Verificación

```bash
php -l config/environment.php
php -l reports/votators/config.php
php -l reports/votators/build_report.php
php -l reports/votators/index.php
php -l reports/votators/live.php
php -l reports/secadores/config.php
php -l reports/secadores/build_report.php
php -l reports/secadores/index.php
php -l reports/secadores/live.php
php -l reports/cocedores/config.php
php -l reports/cocedores/build_report.php
php -l reports/cocedores/index.php
php -l reports/cocedores/live.php
php -l reports/clarificadores/config.php
php -l reports/clarificadores/build_report.php
php -l reports/clarificadores/index.php
php -l reports/clarificadores/live.php
php -l index.php
php -l reports/index.php
curl -I http://127.0.0.1/reportes_progel/
```

Si el equipo de desarrollo no tiene PHP instalado localmente, puede usarse el contenedor sólo para validar; esto no cambia el despliegue directo del servidor.
