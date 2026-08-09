# AUTOFIX IA

Sistema web para la operación de un taller automotriz único. Centraliza clientes, vehículos, citas, órdenes de trabajo, diagnóstico técnico asistido, inventario, facturación interna, pagos, historial, auditoría y reportes.

## Tecnología

- PHP 8.2 o superior y Laravel 12.
- PostgreSQL con esquema dedicado distinto de `public`.
- Vue 3, Inertia.js, Nuxt UI, Tailwind CSS y Vite.
- Spatie Permission para roles y permisos.
- Dompdf para facturas y comprobantes PDF generados en el servidor.
- Playwright para pruebas de interfaz.

## Roles

- `Administrador`: configuración, usuarios, auditoría y acceso operativo completo.
- `Recepcionista`: clientes, vehículos, citas, órdenes, facturas, pagos y envíos de documentos.
- `Mecánico`: órdenes asignadas, diagnósticos, avances, servicios y repuestos autorizados.
- `Cliente`: registro público, vehículos propios, citas, órdenes e historial propio.

Cada cuenta tiene exactamente un rol. El registro público crea exclusivamente cuentas `Cliente`; las cuentas internas se crean desde Administración.

## Instalación Local

1. Copie la configuración de ejemplo y complete PostgreSQL:

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
```

2. Prepare el esquema, migre y cargue permisos y catálogos:

```bash
php artisan db:schema:prepare
php artisan migrate
php artisan db:seed
```

3. Para crear el primer administrador, defina temporalmente `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` antes de ejecutar el seeder. El proceso es idempotente y no reemplaza una contraseña existente.

4. Inicie el entorno de desarrollo:

```bash
composer dev
```

Este comando inicia Laravel, Vite, el trabajador de colas y el programador de tareas.

## PostgreSQL

La aplicación utiliza exclusivamente `DB_CONNECTION=pgsql` y el esquema indicado por `DB_SCHEMA`, cuyo valor recomendado es `modulos`. No configure `public` ni una ruta de búsqueda con varios esquemas.

Antes de migrar se ejecuta un preflight que valida la conexión y el aislamiento:

```bash
composer migrate:safe
```

## Correo

El correo se usa para:

- Recuperación segura de contraseña.
- Recordatorios de citas próximas.
- Envío de facturas internas en PDF.
- Envío de comprobantes de pago en PDF.

En desarrollo, `MAIL_MAILER=log` escribe los mensajes en el registro. Para entrega real configure SMTP o alguno de los transportes soportados en `config/mail.php`.

Los recordatorios están desactivados inicialmente. Para habilitarlos:

```dotenv
AUTOFIX_APPOINTMENT_REMINDERS_ENABLED=true
AUTOFIX_APPOINTMENT_REMINDERS_WINDOW_MINUTES=1440
```

El comando `citas:recordar` se ejecuta cada minuto y evita duplicados por cita, fecha programada y canal. Una reprogramación genera un recordatorio independiente. No se integran aplicaciones externas ni WhatsApp.

## Colas Y Tareas

Los correos financieros y los recordatorios utilizan colas. En procesos separados pueden ejecutarse con:

```bash
php artisan queue:work --tries=3
php artisan schedule:work
```

También están disponibles:

```bash
php artisan citas:vencer
php artisan citas:recordar
```

## PDF Financiero

Las facturas y comprobantes disponen de descarga PDF y envío al correo congelado en la factura. Los documentos se generan con instantáneas históricas; no se aceptan destinatarios arbitrarios. Son comprobantes internos del taller y no constituyen facturación electrónica DIAN.

## Pruebas

Suite PHP:

```bash
composer test
```

Build de producción:

```bash
npm run build
```

Pruebas de navegador:

```bash
npx playwright install chromium
npm run test:e2e
```

Las pruebas E2E no recrean la base de datos. Para probar un inicio de sesión real puede definir `E2E_EMAIL` y `E2E_PASSWORD`; sin esas variables, el escenario autenticado se omite de forma segura.

Las operaciones que dependen de bloqueos, restricciones e índices de PostgreSQL deben probarse contra una base y esquema exclusivos de pruebas. Nunca apunte una suite destructiva a los datos de desarrollo.

La integración transaccional de recordatorios puede habilitarse con `PGSQL_INTEGRATION_DATABASE` apuntando a una base PostgreSQL preparada; la prueba revierte todos sus datos al finalizar.

## Integración IA

Groq se configura mediante las variables `GROQ_*`. Cuando está deshabilitado o falla, se utiliza un resultado simulado con el mismo contrato y advertencias. Las pruebas contra el proveedor real se realizan por separado.

## Seguridad

- Autenticación por sesión, CSRF y regeneración de sesión.
- Límite de intentos en login, registro y recuperación de contraseña.
- Cuentas inactivas sin acceso y revocación de sesiones al cambiar credenciales.
- Autorización backend por permiso, rol, propiedad y asignación activa.
- Operaciones financieras e inventario transaccionales e idempotentes.
- Historiales no destructivos y auditoría de operaciones sensibles.
- Destinatarios financieros obtenidos exclusivamente de instantáneas persistidas.

## Documentación

`AUTOFIX_IMPLEMENTATION.md` contiene el detalle técnico y las fases implementadas. `DOCUMENTATION.md` se conserva únicamente como referencia histórica del prototipo API original; sus endpoints no están cargados por la aplicación actual.
