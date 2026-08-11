# Documento Técnico — Base de Datos del Proyecto AUTOFIX IA

Sistema de gestión para taller automotriz: clientes, vehículos, mecánicos, citas, órdenes de trabajo, inventario, facturación, pagos, historial vehicular, asistente IA y auditoría.

## Tabla de contenidos

1. [Ubicación y ejecución](#1-ubicación-y-ejecución)
2. [Consultas](#2-consultas)
3. [Inserciones y almacenamiento](#3-inserciones-y-almacenamiento)
4. [Triggers y procedimientos](#4-triggers-y-procedimientos)
5. [Relaciones](#5-relaciones)
6. [Seguridad](#6-seguridad)
7. [Flujo de datos](#7-flujo-de-datos)
8. [Ejemplos prácticos](#8-ejemplos-prácticos)
9. [Posibles mejoras futuras](#9-posibles-mejoras-futuras)

---

## 1. Ubicación y ejecución

### 1.1 Motor y configuración

- **Motor:** PostgreSQL.
- **Conexión por defecto:** `pgsql` (`config/database.php:19`).
- **Schema dedicado:** `modulos` (`config/database.php:97` → `'search_path' => env('DB_SCHEMA', 'modulos')`). Todos los objetos se crean dentro de este schema (no en `public`).
- **Driver ORM:** Laravel Eloquent (`providers.users.model` → `Src\Auth\Infrastructure\Models\UserEloquentModel`).

### 1.2 Entornos

| Entorno | Servidor | Base de datos | Conexión |
|---|---|---|---|
| Local (desarrollo) | `127.0.0.1:5432` | `autofix_ia` | usuario `postgres` (`.env` local) |
| Producción | PostgreSQL gestionado en Railway | definida por variables de entorno (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SCHEMA=modulos`) | Railway |

- **Variables relevantes (`.env`):** `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SCHEMA`, `DB_SSLMODE`.
- **Configuración adicional:** `DB_SSLMODE=prefer` (SSL cuando el servidor lo pide); `SESSION_DRIVER=database` (las sesiones se guardan en la tabla `sessions`).

### 1.3 Cómo se ejecuta / gestiona el esquema

- Las tablas se crean y modifican **exclusivamente mediante migraciones** de Laravel:
  - Migraciones base del framework en `database/migrations/` (`users`, `cache`, `jobs`, `sessions`, etc.).
  - Migraciones por módulo en `src/<Modulo>/Infrastructure/Migrations/` (44 migraciones, ~47+ tablas).
- En producción el despliegue ejecuta `php artisan migrate` (incluido el flujo `db:schema:prepare && migrate`) en el contenedor de Railway.
- No hay límite de lectura/escritura manual externa: el acceso a datos se hace desde la aplicación (web) vía Eloquent / Query Builder.

---

## 2. Consultas

Todas las lecturas se realizan desde los controladores de cada contexto usando **Eloquent (query builder de Laravel)**.

### 2.1 Patrones habituales

1. **Lectura con relaciones (eager loading):**
   ```php
   $ordenes = OrdenTrabajoEloquentModel::with([
       'cliente:id,razon_social',
       'vehiculo:id,placa,marca,modelo',
       'asignaciones' => fn ($q) => $q->where('activo', true)->with('mecanico:id,nombres,apellidos'),
   ])->paginate(10);
   ```

2. **Búsqueda (filtros dinámicos con `when`):**
   ```php
   $vehiculos = VehiculoEloquentModel::query()
       ->with('cliente:id,razon_social')
       ->visiblePara($usuario)
       ->when($buscar, fn ($q) => $q->where(fn ($sub) => $sub
           ->where('placa_normalizada', 'ilike', "%{$normalizada}%")
           ->orWhere('marca', 'ilike', "%{$buscar}%")
           ->orWhereHas('cliente', fn ($c) => $c->where('razon_social', 'ilike', "%{$buscar}%"))
       ))
       ->orderBy('placa')
       ->paginate(10)
       ->withQueryString();
   ```
   Nota: se usa `ilike` (PostgreSQL, insensible a mayúsculas) y búsqueda por `placa_normalizada` (placa sin caracteres no alfanuméricos, convertida a mayúsculas) para permitir búsquedas por placa robustas.

3. **Filtrado de visibilidad por rol** mediante `scopeVisiblePara` (ver sección 6).

4. **Consultas SQL nativas (Query Builder)** para agregados y libros contables:
   ```php
   $repuestos = DB::table('orden_repuestos as uso')
       ->whereIn('uso.orden_id', $ids)
       ->when($esCliente, fn ($q) => $q->where('uso.visible_cliente', true))
       ->orderBy('uso.created_at')
       ->get($camposRepuestos)
       ->groupBy('orden_id');
   ```

5. **Agregados por conteo de relaciones (`withCount`):**
   ```php
   VehiculoEloquentModel::withCount(['ordenes as visitas' => fn ($q) => $q->visiblePara($usuario)]);
   ```

6. **Paginación uniforme:** todas las listas del sistema usan `->paginate(10)->withQueryString()` (10 registros por página), lo que mantiene los filtros entre páginas.

### 2.2 Índices de apoyo a las consultas

Se crean índices compuestos, parciales y funcionales para las consultas frecuentes (detalle en la sección 5):

- `ordenes_estado_recibida_idx (estado, recibida_en)`
- `ordenes_cliente_recibida_idx (cliente_id, recibida_en)`
- `orden_servicios_servicio_created_idx (servicio_id, created_at)`
- `pagos_registrados_fecha_idx ON pagos (pagado_en) WHERE estado='registrado'` (parcial)
- `repuestos_stock_bajo_activo_idx ON repuestos (id) WHERE estado='activo' AND stock_actual <= stock_minimo` (parcial)
- `citas_pendientes_vencimiento_idx ON citas (fin) WHERE estado IN ('pendiente','confirmada','reprogramada')` (parcial)
- `consultas_ia_estado_fecha_idx (estado, created_at)`

---

## 3. Inserciones y almacenamiento

### 3.1 Cómo se guardan los datos

- **Modelos Eloquent**: cada alta usa `Model::create([...])` con los campos `$fillable`.
- **Transacciones**: las operaciones que tocan varias tablas se envuelven en `DB::transaction()` para garantizar atomicidad.
- **Bloqueo pesimista**: se usa `->lockForUpdate()` + `pg_advisory_xact_lock()` en operaciones concurrentes (citas, pagos, administración de usuarios).
- **UUIDs**: todas las llaves primarias son UUID (v4), generadas automáticamente por el trait `HasUuids` o `App\Traits\HasUuid`.
- **Snapshots congelados**: facturas y pagos guardan copias de datos de cliente/vehículo al momento de la operación (inhuman recursión histórica en reportes).
- **Registros históricos inmutables**: bitácoras (`orden_estado_historial`, `pago_historial`, `auditorias`, `historial_vehiculo_acciones`, etc.) solo tienen `created_at` y los modelos inmutables lanzan `LogicException` si se intenta `update`/`delete`.

### 3.2 Números consecutivos de documentos

Los números de factura (`FAC-…`), pago (`PG-…`) y comprobante (`RC-…`) se generan mediante la tabla `consecutivos_documento` y el servicio `App\Support\ConsecutivoDocumentos`:

```php
public function siguiente(string $clave, string $prefijo): string
{
    $registro = DB::table('consecutivos_documento')->where('clave', $clave)->lockForUpdate()->first();
    // ... si no existe lo crea
    $siguiente = (int) $registro->ultimo + 1;
    DB::table('consecutivos_documento')->where('clave', $clave)->update(['ultimo' => $siguiente]);
    return $registro->prefijo.'-'.str_pad((string) $siguiente, 8, '0', STR_PAD_LEFT);
}
```

Se usa dentro de la misma transacción (bloqueo con `lockForUpdate`) para evitar colisiones.

### 3.3 Ejemplo de alta transaccional (factura de orden completa)

```php
return DB::transaction(function () use ($orden, $datos, $usuarioId) {
    $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
    // ... validaciones y cálculo de subtotal/impuesto/total con BigDecimal ...
    $factura = FacturaOrdenEloquentModel::create([
        'numero' => $this->consecutivos->siguiente('factura_interna', 'FAC'),
        'orden_id' => $bloqueada->id, 'total' => (string) $total, 'estado' => 'emitida', ...
    ]);
    foreach ($lineas as $linea) FacturaOrdenLineaEloquentModel::create([...$linea, 'factura_id' => $factura->id]);
    FacturaOrdenHistorialEloquentModel::create(['factura_id' => $factura->id, 'evento' => 'emitida', ...]);
    return $factura;
});
```

---

## 4. Triggers y procedimientos

### 4.1 Estado actual

**No existen triggers ni procedimientos almacenados.** Un análisis sobre todas las migraciones confirma que no hay `CREATE TRIGGER`, `CREATE FUNCTION` ni `DO $$ BEGIN ... END $$` para automatizar lógica en el motor.

### 4.2 Automatizaciones equivalentes (nivel aplicación / motor)

La integridad y automatización se logran con:

1. **CHECK constraints en la base de datos** (aplicadas por el motor sin procedimientos):
   - Coherencia de estados (p. ej. una orden `cancelada` exige `motivo_cancelacion` y `cancelada_en`).
   - Cálculos financieros: `facturas_impuesto_calculo_check (impuesto = ROUND(base_impuesto * tasa_impuesto / 100, 2))`, `factura_lineas_calculo_check (subtotal = ROUND(cantidad * precio_unitario, 2))`.
   - Inventario: `movimientos_cantidad_check (stock_resultante = stock_anterior + cantidad)`.
   - Moneda: `moneda = 'USD'` en `facturas_orden`, `facturas_cita_legacy`, `pagos`, `pago_movimientos`.
   - Combustible, año del vehículo, horarios de disponibilidad, horas de las citas (`fin > inicio`), etc.

2. **Índices únicos parciales** que funcionan como "regla" de unicidad condicional:
   - `facturas_orden_activa_unique ON facturas_orden (orden_id) WHERE estado='emitida'` → una orden solo puede tener una factura vigente.
   - `diagnosticos_una_version_vigente_por_estado_idx ON diagnosticos_tecnicos (orden_id, estado) WHERE vigente=true` → un diagnóstico vigente por estado (borrador/confirmado).
   - `pagos_referencia_electronica_unique ON pagos (metodo, referencia) WHERE metodo IN ('tarjeta','transferencia')` → una referencia electrónica no puede repetirse.

3. **Lógica de negocio en servicios de aplicación** (Spring/JArquitectura de capas):
   - Máquina de estados de órdenes (`FlujoEstadosOrden`).
   - Registro de historial/auditoría en cada mutación.
   - Registrar eventos del vehículo (`RegistrarEventoVehiculo`) y auditoría (`RegistrarAuditoria`) dentro de la misma transacción.

4. **Locks de asesoría de PostgreSQL** para exclusiones de concurrencia:
   ```php
   DB::select('select pg_advisory_xact_lock(hashtext(?))', ['pago:'.$datos['idempotencia_clave']]);
   DB::select('select pg_advisory_xact_lock(hashtext(?))', ["cita:{$mecanicoId}:{$inicio->toDateString()}"]);
   ```

> Si se desearan triggers a futuro (p. ej. reloj automático para citas vencidas), se podrían añadir; hoy ese trabajo lo hace el comando/Job de aplicación y la migración `2026_08_09_000000` que crea el índice parcial para el barrido de citas vencidas.

---

## 5. Relaciones

### 5.1 Familias / agregados principales

```
USERS ──1:1── CLIENTES (clientes.usuario_id)
USERS ──1:1── MECANICOS (mecanicos.usuario_id)

CLIENTES ──1:N── VEHICULOS (vehiculos.cliente_id)
CLIENTES ──1:N── CITAS (citas.cliente_id)
CLIENTES ──1:N── ORDENES_TRABAJO (ordenes_trabajo.cliente_id)

VEHICULOS ──1:N── ORDENES_TRABAJO (ordenes_trabajo.vehiculo_id)
VEHICULOS ──1:N── CITAS (citas.vehiculo_id)

CITAS ──1:1── ORDENES_TRABAJO (ordenes_trabajo.cita_id, única)
CITAS ──1:1── CONSULTAS_IA (consultas_ia.cita_id, única)

MECANICOS ──N:M── ESPECIALIDADES (mecanico_especialidad)
ESPECIALIDADES ──1:N── SERVICIOS_TALLER (servicios_taller.especialidad_id)

ORDENES_TRABAJO ──1:N── ORDEN_SERVICIOS
ORDENES_TRABAJO ──1:N── ORDEN_MECANICOS
ORDENES_TRABAJO ──1:N── DIAGNOSTICOS_TECNICOS
ORDENES_TRABAJO ──1:N── ORDEN_AVANCES
ORDENES_TRABAJO ──1:N── ORDEN_REPUESTOS (inventario)
ORDENES_TRABAJO ──1:N── ORDEN_REPUESTOS_REQUERIDOS
ORDENES_TRABAJO ──1:N── ORDEN_ESTADO_HISTORIAL
ORDENES_TRABAJO ──1:1── FACTURAS_ORDEN (vigente, índice parcial único)
ORDENES_TRABAJO ──1:N── PAGOS

FACTURAS_ORDEN ──1:N── FACTURA_ORDEN_LINEAS
FACTURAS_ORDEN ──1:N── FACTURA_ORDEN_HISTORIAL
FACTURAS_ORDEN ──1:1── FACTURAS_ORDEN (reemplaza_factura_id, versionado)

PAGOS ──1:N── PAGO_HISTORIAL
PAGOS ──1:N── PAGO_MOVIMIENTOS

REPUESTOS ──1:N── MOVIMIENTOS_INVENTARIO
REPUESTOS ──1:N── ORDEN_REPUESTOS (con snapshots de código/nombre/unidad)

CONSULTAS_IA ──1:N── REVISIONES_SUGERENCIA_IA
CONSULTAS_IA ──1:N── CONSUMOS_IA
```

### 5.2 Llaves primarias y foráneas

- **Todas las tablas de negocio usan UUID** como PK (`id uuid PK`), generadas por el framework.
- Las FK están RESTRICT en las tablas de negocio (no se puede borrar un padre con hijos vivos); las FK a `users` (creado_por/actualizado_por/usuario_id) suelen ser `SET NULL`; el modelo de permisos de Spatie usa `CASCADE`.
- **Constraints únicas relevantes:** `clientes.numero_documento`, `vehiculos.placa_normalizada`, `mecanicos.numero_documento`, `servicios_taller.codigo`, `especialidades.codigo`, `repuestos.codigo`, `orden_servicios (orden_id, servicio_id)`, `diagnosticos_tecnicos (orden_id, version)`, `cita_recordatorio_entregas (cita_id, inicio_programado, canal)`, `pago_movimientos (pago_id, tipo)`.
- **Unicidades funcionales (LOWER):** `users (LOWER(email))`, `clientes (LOWER(email))`, `mecanicos (LOWER(email))` — emails únicos insensibles a mayúsculas.

### 5.3 Tablas del framework y autenticación

- `users`, `password_reset_tokens`, `sessions` (sesión por BD), `personal_access_tokens` (Sanctum, latente), `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.
- Spatie Permission: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (morphs `model_uuid` + `model_type`).

### 5.4 Columnas JSONB (datos flexibles)

`cita_estado_historial.datos_anteriores`, `factura_orden_historial.datos`, `pago_historial.datos`, `pagos.detalle_snapshot`, `pago_movimientos.metadata`, `historial_vehiculo_acciones.cambios`, `auditorias.cambios`, `consultas_ia` (`entrada`, `respuesta_original`, `meta_generacion`), `revisiones_sugerencia_ia` (`respuesta_ajustada`, `pruebas_realizadas`), `consumos_ia.meta`.

---

## 6. Seguridad

### 6.1 Autenticación

- **Guard `web`, driver `session`** (`config/auth.php`). Sesiones almacenadas en la BD (`sessions`, `SESSION_DRIVER=database`).
- Login manual con Inertia (`WebAuthController::login`) que exige `'activo' => true` y regenera la sesión contra `session fixation`.
- Rate limiting: login 5/min, registro 3/min, reset 5/min (middleware `throttle`).
- Registro público (`/register`) crea usuario + rol `Cliente` + registro `clientes` en una transacción.
- Reset de contraseña: tokens expiran (60 min), y al restablecer se revocan sesiones y tokens Sanctum del usuario.
- Middleware custom `EnsureUserIsActive`: si `activo=false`, se cierra la sesión y se redirige al login.
- **No usa** Fortify/Breeze/Jetstream; **no hay 2FA** ni verificación de email activada. El API por tokens (Sanctum) existe como código pero **no está registrado** en `api.php`.

### 6.2 Roles y permisos (Spatie)

- Se define en `database/seeders/RolesPermisosSeeder.php` el catálogo de permisos y la matriz de roles:

| Rol | Alcance abreviado |
|---|---|
| **Administrador** | Todos los permisos (acceso total) |
| **Recepcionista** | Clientes, vehículos, citas, órdenes (crear/asignar/entregar/cancelar), aprobaciones de repuestos/servicios, pagos/facturas, historial, reportes |
| **Mecánico** | Órdenes asignadas, diagnósticos, avances, repuestos (solicitar/utilizar), inventario ver/consumir, historial técnico, IA |
| **Cliente** | Solo sus vehículos, citas, órdenes, historial visible |

- Permisos se aplican en rutas con middleware `permission:...` (Spatie), con combinaciones:
  - OR: `permission:ordenes.asignar|ordenes.actualizar_estado|ordenes.entregar|ordenes.cancelar`
  - AND: `['permission:reportes.ver', 'permission:reportes.financieros']`

### 6.3 Autorización por recursos (Policies)

- `WorkOrderPolicy` regula órdenes de trabajo: `view`, `technicalWork`, `correctDiagnosis`, `mutate`, `assign`, `deliver`, `cancel`. Estados cerrados (`finalizada`, `lista_entrega`, `entregada`, `cancelada`) congelan mutaciones.
- `AsignarMecanicosRequest` y `CambiarEstadoOrdenRequest` también autorizan dentro del FormRequest.

### 6.4 Visibilidad por rol (`scopeVisiblePara`) — eje central

```php
// OrdenTrabajoEloquentModel
public function scopeVisiblePara(Builder $query, UserEloquentModel $usuario): Builder
{
    if ($usuario->can('ordenes.administrar') || $usuario->hasAnyPermission(['ordenes.crear', 'ordenes.asignar', 'ordenes.entregar', 'ordenes.cancelar'])) return $query;
    if ($usuario->can('ordenes.ver_asignadas')) return $query->whereHas('asignaciones', fn ($q) => $q->where('activo', true)->whereHas('mecanico', fn ($m) => $m->where('usuario_id', $usuario->id)->where('estado', 'activo')));
    if ($usuario->can('ordenes.ver')) return $query->whereHas('cliente', fn ($q) => $q->where('usuario_id', $usuario->id));
    return $query->whereRaw('1 = 0');
}
```

El mismo patrón se repite en `VehiculoEloquentModel`, `CitaEloquentModel` y `ConsultaIaEloquentModel` (consultas IA).

### 6.5 Datos sensibles

- Contraseñas con `Hash::make()` (bcrypt) y cast `hashed`; campos `password`/`remember_token` ocultos.
- `PagoEloquentModel` oculta `idempotencia_clave` y `solicitud_hash`.
- Auditoría append-only (`auditorias`, `historial_vehiculo_acciones`) con ~76 puntos de registro dentro de transacciones.
- Protección anti auto-sabotaje: no puedes quitarte tu rol de Administrador, desactivar tu propia cuenta ni dejar a cero el último administrador activo (con `pg_advisory_xact_lock`).

---

## 7. Flujo de datos

### 7.1 Flujo escritura (aplicación → base)

```text
Página Vue (Inertia)
   └─ router.post(route('ordenes.store'), formData)     // request HTTP (JSON/XHR)
        └─ Laravel: middleware web + auth + permission
             └─ FormRequest (validación + autorización)
                  └─ Controlador
                       └─ Servicio de aplicación (reglas de negocio)
                            └─ Eloquent Model::create() / DB::transaction()
                                 └─ PostgreSQL (schema modulos)
```

Luego del `commit`, el controlador registra auditoría/historial en la misma transacción y devuelve un redirect (`back()->with('success', ...)`).

### 7.2 Flujo lectura (base → aplicación → UI)

```text
Página Vue (Inertia) solicita url (GET /historial-vehicular)
   └─ Controlador consulta con Eloquent + scopeVisiblePara + paginación
        └─ Inertia::render('PaginaVue', $data)  // datos serializados a JSON
             └─ Vue compone la vista y monta los datos
```

### 7.3 Flujo típico de negocio completo (orden → factura → pago)

1. **Cita** agendada (`citas`).
2. **Orden de trabajo** creada (manual o desde cita) → `ordenes_trabajo` + `orden_servicios` + `orden_mecanicos` + `orden_estado_historial`.
3. Mecánica: **diagnósticos** (`diagnosticos_tecnicos`), **avances** (`orden_avances`), **repuestos** (`orden_repuestos_requeridos`, `orden_repuestos`) con sus **movimientos de inventario** (`movimientos_inventario`).
4. **Factura emitida** (`facturas_orden` + `factura_orden_lineas` + historial) usando `ConsecutivoDocumentos`.
5. **Pago registrado** (`pagos` + `pago_historial` + `pago_movimientos`) con idempotencia, totalmente pagado antes de la entrega.
6. Cada evento se refleja en `auditorias` y `historial_vehiculo_acciones`.

### 7.4 Consistencia y concurrencia

- `DB::transaction` + bloqueos `lockForUpdate` y advisory locks evitan doble agendamiento de citas, pagos duplicados, y colisiones en consecutivos.
- Índices parciales únicos refuerzan invariantes (una factura vigente por orden, un diagnóstico vigente por estado).

---

## 8. Ejemplos prácticos

### 8.1 Consulta SQL — Órdenes abiertas de un cliente activo

```sql
SELECT o.numero, o.estado, o.falla_reportada, o.kilometraje, v.placa
FROM ordenes_trabajo        o
JOIN vehiculos              v ON v.id = o.vehiculo_id
JOIN clientes               c ON c.id = o.cliente_id
WHERE c.estado = 'activo'
  AND c.usuario_id = 'REEMPLAZA_POR_UUID_DEL_USUARIO'
  AND o.estado IN ('pendiente','asignada','en_diagnostico','en_reparacion')
ORDER BY o.recibida_en DESC;
```

### 8.2 Consulta SQL — Repuestos con stock bajo (regla de negocio)

```sql
SELECT r.codigo, r.nombre, r.stock_actual, r.stock_minimo, cat.nombre AS categoria
FROM repuestos r
JOIN categorias_repuesto cat ON cat.id = r.categoria_id
WHERE r.estado = 'activo'
  AND r.stock_actual <= r.stock_minimo
ORDER BY (r.stock_actual - r.stock_minimo) ASC;
```

Está soportada por el índice parcial `repuestos_stock_bajo_activo_idx`.

### 8.3 Inserción — Crear un vehículo (con UUID automático)

```sql
INSERT INTO vehiculos
  (id, cliente_id, placa, placa_normalizada, marca, modelo, anio, color, kilometraje, combustible, estado, created_at, updated_at)
VALUES
  (gen_random_uuid(), 'UUID_CLIENTE', 'ABC-1234', 'ABC1234', 'Chevrolet', 'Aveo', 2016, 'Rojo', 57456, 'gasolina', 'activo', NOW(), NOW());
```

### 8.4 Inserción transaccional (equiv. PHP del servicio de factura)

```sql
BEGIN;
-- 1) Reservar el consecutivo (misión crítica)
UPDATE consecutivos_documento SET ultimo = ultimo + 1, updated_at = NOW()
WHERE clave = 'factura_interna'
RETURNING prefijo || '-' || lpad((ultimo)::text, 8, '0');
-- 2) Insertar la factura
INSERT INTO facturas_orden (id, numero, orden_id, subtotal, descuento, base_impuesto,
                            tasa_impuesto, impuesto, total, moneda, estado, emitida_en, emitida_por)
VALUES (gen_random_uuid(), 'FAC-00000042', 'UUID_ORDEN', 100.00, 0.00, 100.00,
        15.00, 15.00, 115.00, 'USD', 'emitida', NOW(), 'UUID_USUARIO');
COMMIT;
```

### 8.5 Índice parcial único — "una factura vigente por orden"

```sql
-- Equivale a un trigger que impide una segunda factura emitida para la misma orden
CREATE UNIQUE INDEX facturas_orden_activa_unique
  ON facturas_orden (orden_id)
  WHERE estado = 'emitida';
```

### 8.6 Actualización con advertencia y lock — desactivar un usuario (anti-sabotaje)

```sql
BEGIN;
SELECT pg_advisory_xact_lock(hashtext('autofix_admin_users'));
UPDATE users SET activo = false WHERE id = 'UUID_USUARIO' AND activo = true;
-- La regla de "último administrador activo" se valida en la capa de aplicación
COMMIT;
```

---

## 9. Posibles mejoras futuras

### Escalabilidad

1. **Lectura de réplicas**: configurar una conexión de solo lectura en `config/database.php` (p. ej. `pgsql_read`) con `DB::connection('pgsql_read')` o `read`/`write` hosts para reportes pesados.
2. **Particionado**: `pagos`, `pago_movimientos`, `auditorias` y `historial_vehiculo_acciones` son append-only y crecen; particionarlos por rango de fechas (`RANGE (created_at)`).
3. **Índices adicionales / parciales** según el patrón de consulta real: monitorear con `pg_stat_statements`).
4. **Caché**: capa Redis para catálogos de solo lectura (especialidades, servicios, repuestos) y para conteos del dashboard.

### Optimización de consultas

5. **Materializar el resumen financiero** de cada orden (tabla de proyección/`view`) para evitar recalcular por suma de servicios/repuestos/pagos en cada lectura.
6. **Reemplazar `ilike '%…%'`** por `pg_trgm` (índices `GIN trgm`) en búsquedas de placa/razón social/nombre cuando el volumen lo justifique.
7. **Eliminar código latente**: el módulo `Factura`/`Producto` legacy y la API ISO por tokens (`src/Auth/api.php`) no se utilizan; consolidarlos o depurarlos.

### Integridad y seguridad

8. **Migrar reglas de negocio a triggers/constraints** si se requiere garantía a nivel de motor (p. ej. impedir `stock_actual < 0` con triggers de seguridad extremos, hoy cubierto por lógica de aplicación + CHECK).
9. **Autenticación en dos pasos y verificación de email** (`email_verified_at` ya existe en el esquema).
10. **Auditoría de lecturas** de datos sensibles solo si el dominio lo exige (es costoso).

### Proceso

11. **`SET search_path` explícito** en cada migración o conexión para evitar errores si se ejecutan en scheme `public` (hoy el código valida `current_schema()` pero no lo fija, excepto la config).
12. **Migraciones reversibles** donde hoy se lanza excepción si no hay schema aislado, para facilitar el desarrollo en distintas bases.

---

## Anexo — Inventario de tablas por módulo

| Módulo | Tablas |
|---|---|
| Framework | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` |
| Auth | `users`, `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, `personal_access_tokens` |
| Cliente | `clientes` |
| Vehículo | `vehiculos` |
| Catálogo | `categorias`, `productos` |
| Taller | `especialidades`, `mecanicos`, `mecanico_especialidad`, `disponibilidades_mecanico`, `servicios_taller` |
| Cita | `citas`, `cita_estado_historial`, `cita_recordatorio_entregas`, `cita_repuestos_solicitados` |
| Orden de trabajo | `ordenes_trabajo`, `orden_servicios`, `orden_mecanicos`, `orden_estado_historial`, `diagnosticos_tecnicos`, `orden_avances`, `orden_repuestos_requeridos`, `orden_repuesto_requerido_historial`, `orden_servicio_historial`, `orden_cierre_tecnico_historial` |
| Inventario | `categorias_repuesto`, `proveedores`, `repuestos`, `movimientos_inventario`, `orden_repuestos` |
| Facturación | `facturas_orden`, `factura_orden_lineas`, `factura_orden_historial`, `facturas_cita_legacy`, `consecutivos_documento` |
| Factura (legacy) | `facturas`, `detalle_facturas` |
| Pago | `pagos`, `pago_historial`, `pago_movimientos` |
| Historial vehicular | `historial_vehiculo_acciones` |
| Asistente IA | `consultas_ia`, `revisiones_sugerencia_ia`, `consumos_ia` |
| Auditoría | `auditorias` |