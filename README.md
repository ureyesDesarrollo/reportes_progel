# Reportes Progel

Proyecto independiente para tableros operativos de Progel. Incluye **Votators**, **Secadores**, **Cocedores** y **Clarificador**.

## Estructura simple

- `index.php`: redirige a la selección general de reportes.
- `reports/index.php`: permite seleccionar los reportes por grupo, subgrupo y reporte.
- `reports/votators/config.php`: conexión, sensores, unidades y semáforos.
- `reports/votators/build_report.php`: consulta AVEVA y prepara los datos.
- `reports/votators/live.php`: entrega los datos para la actualización automática.
- `reports/votators/index.php`: renderiza la vista responsiva y el modo de captura.
- `reports/secadores/config.php`: sensores, unidades, conexiones y semáforos de Secadores.
- `reports/secadores/build_report.php`: consulta AVEVA y las lecturas puntuales de MySQL.
- `reports/secadores/live.php`: actualización automática sin recargar la pantalla.
- `reports/secadores/index.php`: vista responsiva de ambos túneles.
- `reports/cocedores/config.php`: sensores, campos MySQL y semáforos de Cocedores.
- `reports/cocedores/build_report.php`: consulta AVEVA y `datos_cocedores`, y prepara los nueve equipos.
- `reports/cocedores/live.php`: actualización automática sin recargar la pantalla.
- `reports/cocedores/index.php`: vista responsiva de Cocedores.
- `reports/clarificadores/config.php`: fuentes, campos y semáforos del Clarificador.
- `reports/clarificadores/build_report.php`: combina AVEVA con `datos_clarificador` y `datos_filtracion_integracion`.
- `reports/clarificadores/live.php`: actualización automática sin recargar la pantalla.
- `reports/clarificadores/index.php`: vista responsiva del Clarificador.

Secadores muestra un túnel a la vez y alterna automáticamente entre ambos. El tiempo de permanencia se configura con `SECADORES_ROTATION_MS` en milisegundos; el valor predeterminado es `60000`.

La intención es conservar un flujo directo y fácil de mantener, sin capas ni dependencias innecesarias.

## Requisitos del servidor

- Apache 2.4 con `AllowOverride All` habilitado para la carpeta del proyecto.
- PHP 8.1 o superior.
- Extensiones PHP `pdo_mysql`, `sqlsrv` y `pdo_sqlsrv`.
- Microsoft ODBC Driver 18 for SQL Server para consultar AVEVA.

No se requiere Docker ni Composer.

## Configuración

```bash
cp .env.example .env
```

Complete en `.env` las variables `AVEVA_DB_*`, `SECADORES_DB_*` y `PROCESOS_DB_*`. El archivo `.env` está excluido del repositorio y protegido por `.htaccess`.

La aplicación carga este archivo directamente al abrir cualquier reporte. Las variables configuradas en Apache o en el sistema tienen prioridad sobre `.env`.

## Instalación directa en Apache

1. Copie la carpeta completa dentro del directorio público de Apache, por ejemplo `C:\\wamp64\\www\\reportes_progel` o `/var/www/html/reportes_progel`.
2. Cree `.env` a partir de `.env.example` y complete las conexiones.
3. Confirme en `php.ini` que `pdo_mysql`, `sqlsrv` y `pdo_sqlsrv` estén habilitadas.
4. Reinicie Apache después de modificar `php.ini`.

La selección de reportes queda disponible en:

```text
http://servidor/reportes_progel/reports/
```

La raíz del proyecto redirige automáticamente a esta selección.

Para validar desde el propio servidor:

```bash
php -l reports/votators/index.php
php -l reports/secadores/index.php
php -l reports/cocedores/index.php
php -l reports/clarificadores/index.php
php -m
```

En Windows puede usar la ruta del ejecutable de PHP instalado por WampServer, por ejemplo:

```text
C:\wamp64\bin\php\php8.x.x\php.exe -l reports\votators\index.php
```

Los archivos `Dockerfile` y `docker-compose.yml` se conservan únicamente para desarrollo local opcional; el servidor no los utiliza.

Si AVEVA no está disponible o faltan variables, la aplicación continúa cargando con valores `—` y una advertencia controlada.

## Capturas de Secadores para WhatsApp

El script `scripts/enviar_secadores_whatsapp.sh` genera una imagen independiente para cada túnel usando estas rutas:

```text
/reports/secadores/?capture=1&secador=tunel_1
/reports/secadores/?capture=1&secador=tunel_2
```

Para probar las capturas sin enviar mensajes:

```bash
SECADORES_CAPTURE_ONLY=1 ./scripts/enviar_secadores_whatsapp.sh
```

De forma predeterminada se crean `tunel_1.png` y `tunel_2.png` dentro de `/tmp/secadores-whatsapp`. Para habilitar el envío configure fuera del repositorio `SERVICE_WHATSAPP_GROUP_ID` y, si el servicio lo requiere, `SERVICE_WHATSAPP_API_KEY`. El script admite compartir la imagen por ruta local, copiarla a un contenedor configurado o publicar una URL mediante `SERVICE_WHATSAPP_IMAGE_REFERENCE`.
