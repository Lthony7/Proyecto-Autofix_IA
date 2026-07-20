# AUTOFIX IA

## Arquitectura

La aplicación usa Laravel 12, PostgreSQL, Vue 3, Inertia.js y Nuxt UI. Los módulos funcionales viven en `src/{BoundedContext}` y exponen únicamente rutas web. La autorización se aplica en Laravel con `spatie/laravel-permission`; las capacidades compartidas con Inertia solo controlan visibilidad y nunca sustituyen la validación del servidor.

Bounded contexts previstos:

- `Auth`: usuarios, sesiones, roles y permisos.
- `Cliente`: clientes del taller.
- `Vehiculo`: vehículos y propiedad.
- `Taller`: mecánicos, especialidades, disponibilidad y servicios.
- `Cita`: agenda y disponibilidad.
- `OrdenTrabajo`: órdenes, asignaciones, estados y diagnóstico técnico.
- `AsistenteIA`: orientación inicial, Groq y revisión humana.
- `Inventario`: repuestos, proveedores y movimientos.
- `Pago`: pagos, comprobantes e historial.
- `Facturacion`: facturas definitivas emitidas desde órdenes cerradas.
- `Auditoria`: trazabilidad transversal.

Los contextos heredados `Categoria`, `Producto` y `Factura` conservan sus tablas para datos históricos. Sus rutas de facturación están desactivadas y no representan el inventario ni la facturación definitiva de AUTOFIX IA.

## PostgreSQL

La aplicación no usa SQLite y no incluye `public` como ruta alternativa. Todas las tablas, incluida `migrations`, se resuelven mediante `DB_SCHEMA`, cuyo valor predeterminado es `modulos`.

El usuario de despliegue debe crear el esquema una sola vez antes de migrar:

```sql
CREATE SCHEMA IF NOT EXISTS modulos AUTHORIZATION autofix_app;
```

Configure `.env` a partir de `.env.example`. El usuario de PostgreSQL necesita `USAGE` y `CREATE` sobre `modulos`, pero no necesita crear extensiones.

## Instalación

```bash
composer install
npm install
php artisan config:clear
php artisan migrate
php artisan db:seed
npm run build
```

`RolesPermisosSeeder` es idempotente. Para crear el primer administrador, defina temporalmente `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` antes de ejecutar el seeder. No existen credenciales predeterminadas y una ejecución posterior no reemplaza la contraseña. Las demás cuentas se crean mediante el flujo administrativo; el registro público está deshabilitado.

## Seguridad

- La autenticación web usa sesión y regenera el identificador al iniciar sesión.
- El cierre invalida la sesión y regenera el token CSRF.
- El login tiene límite de cinco intentos por minuto.
- Los usuarios inactivos no pueden iniciar sesión.
- Roles, permisos, pivotes y usuarios usan UUID.
- Las nuevas rutas funcionales son web; los archivos API heredados no se cargan.
- Las operaciones funcionales no ofrecen eliminación física.

## Groq

La integración se configurará mediante `GROQ_ENABLED`, `GROQ_API_KEY`, `GROQ_API_URL`, `GROQ_MODEL` y `GROQ_TIMEOUT`. La clave nunca se comparte con Inertia ni se consume desde Vue. Mientras la integración no esté habilitada se usará el modo simulado definido en el contexto `AsistenteIA`.

## Estado de implementación

### Fase 1: fundamentos

- PostgreSQL aislado en el esquema `modulos`.
- Roles Administrador, Recepcionista, Mecánico y Cliente.
- Permisos granulares y seeder idempotente.
- Autenticación web por sesión, usuarios activos y login limitado.
- Auditoría transversal base y navegación filtrada por permisos.
- Rutas funcionales exclusivamente web.

### Fase 2: clientes y vehículos

- Clientes con estados `activo`, `inactivo` y `archivado`; no existe eliminación desde rutas web.
- Documento y correo normalizados con restricciones únicas en PostgreSQL.
- Paginación y filtros ejecutados en el backend.
- Vehículos relacionados con clientes, placa normalizada única y estados no destructivos.
- Restricciones PostgreSQL para combustible y año, kilometraje no negativo y trazabilidad de usuario.
- Alcance de consulta por propiedad para usuarios con rol Cliente.
- Formularios y listados responsivos construidos con Nuxt UI e Inertia.

### Fase 3: operación del taller

- Mecánicos con estado, documento y correo únicos, especialidades y disponibilidad semanal.
- Las asignaciones y franjas omitidas se desactivan en lugar de eliminarse.
- Especialidades y servicios con estados no destructivos, duración y precio base decimal.
- Restricciones PostgreSQL para horarios, vigencia, duración, importes y estados.
- Catálogo inicial idempotente basado en los servicios descritos por AUTOFIX IA.
- Gestión protegida por permisos y acciones relevantes registradas en auditoría.

### Fase 4: citas

- Agenda restringida por rol y propiedad de cliente, vehículo o mecánico asignado.
- Estados controlados: pendiente, confirmada, reprogramada, atendida y cancelada.
- Historial inmutable de transiciones, reprogramaciones, usuario y datos anteriores.
- Duración calculada en el backend desde el servicio seleccionado.
- Validación de disponibilidad semanal y vigencia del horario del mecánico.
- Prevención de solapamientos dentro de una transacción con bloqueo asesor de PostgreSQL.
- Cancelaciones con motivo, responsable y fecha, sin eliminación física.
- Formularios y agenda responsivos mediante Inertia y Nuxt UI.

### Fase 5: órdenes y diagnóstico técnico

- Órdenes creadas desde recepción o convertidas una única vez desde citas atendidas.
- Servicios de la orden conservan nombre y precio acordado como instantánea histórica.
- Asignaciones de mecánicos trazables; las anteriores se retiran sin eliminarse.
- Estados controlados: pendiente, en diagnóstico, en reparación, finalizada, entregada y cancelada.
- Historial inmutable de cada transición, usuario, fecha y observaciones.
- El mecánico solo consulta y modifica órdenes con asignación activa.
- Diagnósticos técnicos versionados: una revisión nueva conserva las anteriores y marca una sola como vigente.
- Recepcionistas no tienen permiso para registrar diagnósticos técnicos.

### Fase 6: asistente de IA

- Formulario guiado en cuatro pasos con resumen editable y límites estrictos de entrada.
- Groq se consume exclusivamente desde Laravel mediante `config/services.php`.
- Prompt delimitado contra instrucciones incluidas por usuarios y sin datos personales innecesarios.
- Respuesta JSON validada con causas cualitativas, prioridad, riesgos, recomendación, especialidad y servicios.
- Modo simulado con el mismo contrato; también actúa como fallback controlado ante timeout o respuesta inválida.
- Rate limiting por usuario, deduplicación durante 24 horas y registro técnico sin secretos.
- La respuesta original es inmutable; confirmaciones, modificaciones y descartes se guardan como revisiones separadas.
- Continuidad entre consulta IA, cita y orden de trabajo sin creación automática por parte de la IA.
- La advertencia obligatoria se normaliza en el backend y se muestra en cada resultado.

### Fase 7: inventario y repuestos de órdenes

- Categorías, proveedores y repuestos con estados no destructivos y precios decimales.
- El saldo se modifica exclusivamente mediante movimientos de entrada, salida, ajuste o reversión.
- Cada movimiento conserva cantidad, saldo anterior, saldo resultante, motivo, usuario y posible orden asociada.
- Los movimientos usan transacciones y bloqueo `FOR UPDATE` sobre el repuesto para evitar saldos perdidos por concurrencia.
- PostgreSQL impide stock negativo y verifica que el saldo resultante sea igual al saldo anterior más la cantidad.
- El consumo se realiza desde órdenes en diagnóstico o reparación y solo sobre órdenes visibles para el usuario.
- `orden_repuestos` conserva cantidad y precio de venta histórico, aunque cambie después el catálogo.
- Una corrección nunca elimina el consumo: crea un movimiento compensatorio enlazado al original y marca el uso como revertido.
- El historial de movimientos y los avisos de stock mínimo se muestran en la interfaz de inventario.

### Fase 8: pagos de órdenes

- El total canónico se calcula en Laravel desde los precios históricos de servicios y repuestos; el navegador nunca define el total ni el saldo.
- Los servicios cancelados y los consumos de repuestos revertidos no forman parte del total.
- Los pagos se vinculan directamente a la orden, usan moneda `COP` y permiten efectivo, tarjeta, transferencia u otro método.
- Se admiten pagos parciales y el estado financiero derivado puede ser pendiente, parcial o pagado.
- El registro bloquea la orden con `FOR UPDATE`, recalcula el saldo dentro de la transacción e impide sobrepagos concurrentes.
- Cada pago genera números únicos de operación y comprobante, además de un evento financiero inmutable.
- La anulación exige permiso específico, motivo y bloqueo transaccional; no elimina el pago y restaura el saldo por estado.
- Una orden con pagos vigentes no puede cancelarse hasta anularlos.
- Un consumo de inventario no puede revertirse si el nuevo total quedaría por debajo del importe ya pagado.
- La consulta global de pagos y el resumen financiero de cada orden están protegidos por `pagos.ver`.
- El módulo `Factura` heredado permanece separado y no es fuente de verdad para pagos ni saldos de órdenes.

### Fase 9: facturación definitiva

- Las facturas se emiten únicamente desde órdenes finalizadas o entregadas y solo puede existir una vigente por orden.
- Servicios y repuestos se copian como líneas históricas con descripción, cantidad, precio unitario y subtotal.
- Los datos fiscales básicos del cliente y la placa del vehículo también quedan congelados al emitir.
- Subtotal, descuento, base gravable, impuesto y total se calculan con precisión decimal exclusivamente en Laravel.
- La tasa de impuesto y el descuento se validan en backend; el total no puede quedar por debajo de los pagos vigentes.
- Después de emitir, el saldo de pagos utiliza el total fiscal de la factura como fuente de verdad.
- Una factura con pagos vigentes no puede anularse y una orden facturada no permite revertir consumos de inventario.
- La anulación conserva cabecera, líneas e historial y permite emitir posteriormente una nueva factura corregida.
- El módulo heredado `Factura` ya no expone rutas web, evitando fuentes paralelas de facturación.

### Fase 10: administración y auditoría

- Los administradores pueden crear usuarios, actualizar nombre, correo y contraseña, asignar roles y cambiar el estado de las cuentas.
- Las contraseñas administrativas exigen al menos doce caracteres, mayúsculas, minúsculas, números y símbolos.
- Los correos se normalizan en minúsculas y PostgreSQL aplica unicidad sin distinguir mayúsculas.
- No existe eliminación física de usuarios; la desactivación revoca sesiones, tokens y credenciales persistentes.
- Un middleware global expulsa cualquier sesión cuyo usuario haya sido desactivado después de iniciar sesión.
- Las operaciones usan un bloqueo asesor para impedir que cambios concurrentes dejen el sistema sin administrador activo.
- Un administrador no puede desactivar su propia cuenta ni retirar su propio rol de Administrador.
- Los roles se asignan en una operación separada protegida por `roles.administrar`; no se conceden permisos directos por usuario.
- Creación, actualización, cambio de contraseña, roles y estado generan eventos de auditoría sin registrar contraseñas ni hashes.
- La auditoría global está paginada y permite filtrar por acción, recurso, usuario y rango de fechas.
- El permiso `auditorias.ver` está asignado únicamente al rol Administrador y no reutiliza el permiso de historial funcional.

### Fase 11: cierre de inventario

- Los repuestos pueden editar código, descripción, categoría, proveedor, unidad, mínimos y precios sin exponer el saldo en el payload.
- `stock_actual` ya no admite asignación masiva; el servicio de movimientos lo actualiza explícitamente bajo bloqueo.
- La unidad no puede cambiar después del primer movimiento, evitando reinterpretar cantidades históricas.
- Categorías y proveedores permiten creación, edición, activación, desactivación y archivo sin eliminación física.
- Las operaciones de categorías y proveedores generan auditoría, igual que los repuestos y movimientos.
- El catálogo incorpora filtros por texto, estado, categoría, proveedor y nivel bajo de inventario.
- Las alertas de stock mínimo y agotados se calculan globalmente en PostgreSQL sobre referencias activas.
- Cada repuesto tiene una vista de detalle con saldo, mínimos, costos, precio y libro paginado de movimientos.
- El libro puede filtrarse por tipo y rango de fechas, y muestra saldos anterior/resultante, orden, responsable, motivo y movimiento de origen.
- El consumo bloquea la orden antes de validar su estado y bloquea el repuesto antes de capturar sus precios históricos.

### Fase 12: cierre operativo de órdenes

- Cada servicio de una orden avanza de pendiente a en proceso, completado o cancelado mediante transiciones controladas.
- Los servicios completados y cancelados son terminales y conservan las observaciones registradas.
- Los mecánicos solo pueden cambiar servicios de órdenes donde mantienen una asignación activa.
- Una orden no puede finalizar mientras tenga servicios pendientes o en proceso.
- Para finalizar debe existir al menos un servicio completado o un repuesto utilizado y no revertido.
- Una orden no puede cancelarse mientras conserve pagos vigentes o consumos de repuestos sin revertir.
- La entrega exige una factura definitiva vigente y saldo financiero exactamente igual a cero.
- Cambios de servicio, estado de orden, pagos, inventario y facturación se serializan mediante el bloqueo de la orden.
- Cada transición de servicio genera un evento de auditoría con estado anterior y nuevo.

### Fase 13: optimización del frontend

- Vite separa Vue, Vue Router, Inertia, Ziggy, Nuxt UI, VueUse, Iconify, internacionalización y notificaciones en chunks estables.
- El archivo principal de la aplicación bajó de aproximadamente 771 KB a 24 KB antes de compresión.
- Nuxt UI y Reka UI permanecen juntos porque forman un ciclo de runtime; su chunk independiente puede reutilizarse desde la caché aunque cambie código de negocio.
- Las dependencias pesadas del dashboard, incluidas gráficas y tablas, siguen cargándose mediante el chunk dinámico de esa página.
- Axios dejó de cargarse globalmente porque ninguna funcionalidad lo utiliza; Inertia mantiene su propio transporte HTTP.
- Se retiraron del arranque los adaptadores globales de compatibilidad que no tenían consumidores.
- El límite de advertencia se fija en 600 KB para reflejar el tamaño esperado del runtime UI agrupado, sin ocultar crecimientos inesperados en otros chunks.
