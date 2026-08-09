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

El comando `db:schema:prepare` crea y valida el esquema configurado antes de migrar:

```bash
php artisan db:schema:prepare
php artisan migrate
```

Los comandos Artisan cuyo nombre comienza por `migrate` también ejecutan este preflight automáticamente. El proceso rechaza cualquier conexión distinta de PostgreSQL, `public`, identificadores inválidos y rutas de búsqueda que resuelvan más de un esquema. Configure `.env` a partir de `.env.example`; el usuario de despliegue necesita permiso para crear el esquema únicamente cuando infraestructura no lo haya aprovisionado. No se crean extensiones.

## Instalación

```bash
composer install
npm install
php artisan config:clear
composer migrate:safe
php artisan db:seed
npm run build
```

`RolesPermisosSeeder` es idempotente. Para crear el primer administrador, defina temporalmente `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` antes de ejecutar el seeder. No existen credenciales predeterminadas y una ejecución posterior no reemplaza la contraseña. Las cuentas internas se crean mediante el flujo administrativo; el registro público crea exclusivamente cuentas con rol Cliente.

## Seguridad

- La autenticación web usa sesión y regenera el identificador al iniciar sesión.
- El cierre invalida la sesión y regenera el token CSRF.
- El login tiene límite de cinco intentos por minuto.
- Los usuarios inactivos no pueden iniciar sesión.
- Roles, permisos, pivotes y usuarios usan UUID.
- Las nuevas rutas funcionales son web; los archivos API heredados no se cargan.
- Las operaciones funcionales no ofrecen eliminación física.

## Groq

La integración se configura mediante `GROQ_ENABLED`, `GROQ_API_KEY`, `GROQ_API_URL`, `GROQ_MODEL`, `GROQ_TIMEOUT` y `GROQ_MAX_TOKENS`. La clave nunca se comparte con Inertia ni se consume desde Vue. Mientras la integración no esté habilitada, o si el proveedor falla o devuelve una respuesta inválida, se usa el modo simulado con el mismo contrato estructurado.

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
- Estados controlados: pendiente, confirmada, reprogramada, vencida, atendida y cancelada.
- Historial inmutable de transiciones, reprogramaciones, usuario y datos anteriores.
- Duración calculada en el backend desde el servicio seleccionado.
- Validación de disponibilidad semanal y vigencia del horario del mecánico.
- El formulario ofrece durante 90 días únicamente fechas laborales con cupos y horas libres en intervalos de 30 minutos, según la duración del servicio y las citas no canceladas.
- La cita es gratuita y no crea facturas ni pagos. El servicio seleccionado y los repuestos opcionales son solicitudes sujetas a diagnóstico, disponibilidad y confirmación técnica.
- Solicitar o recomendar un repuesto no reserva ni descuenta existencias; solo el uso confirmado por el mecánico crea una salida trazable de inventario.
- Prevención de solapamientos dentro de una transacción con bloqueo asesor de PostgreSQL.
- Cancelaciones con motivo, responsable y fecha, sin eliminación física.
- Formularios y agenda responsivos mediante Inertia y Nuxt UI.

### Fase 5: órdenes y diagnóstico técnico

- Cada cita crea transaccionalmente una orden pendiente con su servicio y mecánico asignado; las citas históricas se recuperan de forma idempotente.
- Servicios de la orden conservan nombre y precio acordado como instantánea histórica.
- La orden reúne la agenda completa, entrada y recomendaciones IA, diagnóstico humano, servicios requeridos, trabajo realizado y repuestos solicitados, sugeridos, requeridos y utilizados.
- El mecánico prepara el alcance técnico y documenta el trabajo; Recepción o Administración emite la factura definitiva después de finalizar y registra el pago contra esa factura.
- Asignaciones de mecánicos trazables; las anteriores se retiran sin eliminarse.
- Estados controlados: pendiente, asignada, en diagnóstico, esperando aprobación, esperando repuestos, en reparación, pausada, en prueba, finalizada, lista para entrega, entregada y cancelada.
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

### Fase 14: pagos y facturación ampliados

- Cada pago conserva monto, método, fecha, referencia, observaciones, responsable y número único de comprobante.
- Cada nuevo pago congela conceptos, servicios, repuestos, descuento, impuesto, total, pagado acumulado y saldo resultante para que su comprobante no cambie con operaciones posteriores.
- El comprobante presenta conceptos históricos, servicios, repuestos, descuento, impuesto, total, pago acumulado y saldo.
- La vista del comprobante dispone de estilos de impresión y permite imprimir o guardar como PDF desde el navegador sin depender de servicios externos.
- Los estados financieros se calculan como pendiente, parcial, pagado o vencido según pagos vigentes, saldo y fecha de vencimiento.
- Los pagos anulados no se eliminan ni cuentan como ingreso y su motivo permanece disponible.
- Administración puede ejecutar reembolsos totales como operaciones compensatorias; el pago pasa a `reembolsado`, conserva motivo, fecha y responsable, deja de contar como ingreso y restablece el saldo.
- Cualquier descuento exige el permiso `descuentos.autorizar`, un motivo y el usuario autorizador; los datos se guardan en la misma transacción de emisión.
- Las consultas de comprobantes financieros quedan registradas en auditoría.

Verificación manual:

1. Finalizar una orden con servicios o repuestos y emitir su factura.
2. Registrar un pago inferior al saldo y confirmar el estado parcial.
3. Abrir Pagos, seleccionar `Ver comprobante` y revisar la opción `Imprimir o guardar PDF`.
4. Comprobar que Recepción no pueda introducir descuentos y que Administración deba justificar cualquier descuento mayor que cero.

### Fase 15: historial vehicular

- `Historial de servicios` aparece para personal autorizado y `Mi Historial de Servicios` ofrece al Cliente una ruta exclusiva, siempre limitada mediante `visiblePara()` en el backend.
- La consulta agrupa cronológicamente órdenes, fechas, fallas, kilometraje, mecánicos actuales e históricos, diagnósticos versionados, trabajos, servicios, repuestos, mano de obra, pagos y factura asociada.
- Los filtros admiten búsqueda en tiempo real, rango de fechas, estado, servicio y orden cronológico, con paginación y detalle expandible.
- `Bitácora vehicular` registra automáticamente creación y edición del vehículo, cambio de propietario o estado, creación de orden, asignación de mecánicos, diagnósticos y finalización de servicios.
- Cada evento congela usuario, rol, fecha, vehículo, descripción, cambios, IP y agente de usuario. El modelo bloquea actualizaciones y eliminaciones.
- Administradores y recepcionistas consultan todos los vehículos y la bitácora; clientes solo sus vehículos y órdenes; mecánicos solo vehículos con órdenes actualmente asignadas.
- Los datos financieros se protegen mediante `historial.finanzas.ver` y nunca se entregan a mecánicos.
- Los permisos independientes son `historial.servicios.ver`, `historial.acciones.ver`, `historial.tecnico.registrar` e `historial.finanzas.ver`.
- Servicios, precios, diagnósticos, pagos anulados y consumos revertidos conservan su señal histórica en lugar de eliminarse.

Verificación manual:

1. Entrar a `Historial de servicios` y buscar por placa o propietario.
2. Abrir un vehículo con órdenes y aplicar búsqueda, fechas, estado, servicio y ordenamiento.
3. Confirmar con un usuario Cliente que no pueda abrir vehículos ajenos.
4. Confirmar con un Mecánico que solo aparezcan órdenes asignadas y no se muestren costos.
5. Crear o editar un vehículo y comprobar el evento inalterable en `Bitácora vehicular`.

### Fase 16: reportes

- `Reportes` ofrece órdenes pendientes, órdenes finalizadas, ingresos reales, servicios solicitados, repuestos utilizados y vehículos atendidos por cliente.
- Los filtros globales incluyen fechas, estado, mecánico, cliente, vehículo y servicio.
- Los ingresos usan exclusivamente pagos registrados; los pagos anulados quedan excluidos.
- Los consumos revertidos quedan excluidos del reporte de repuestos.
- Las tablas se limitan a los principales resultados y las consultas agregadas usan índices específicos para fechas, estados, servicios, repuestos y pagos.
- La exportación CSV es compatible con Excel, incluye UTF-8, neutraliza fórmulas peligrosas y registra tipo, filtros y cantidad de filas en auditoría.
- `reportes.financieros` controla los ingresos y `reportes.exportar` controla las descargas independientemente de `reportes.ver`.

Verificación manual:

1. Abrir Reportes y cambiar el rango de fechas.
2. Aplicar filtros de cliente, vehículo, mecánico o servicio.
3. Comparar el total de ingresos con pagos vigentes del mismo periodo.
4. Exportar cada sección y confirmar el evento `reporte.exportado` en Auditoría.

### Fase 17: diagnóstico IA y agenda operativa

- El diagnóstico IA usa el contrato versionado `diagnostico.v2` y conserva prompt, esquema, respuesta original, respuesta cruda y metadatos de generación.
- El resultado separa resumen para cliente, análisis técnico, hipótesis con evidencias, pruebas, riesgo, circulación, tiempos, herramientas, servicios y posibles repuestos.
- Las respuestas inválidas del proveedor activan un fallback controlado que conserva la advertencia y nunca autoriza reparaciones, consumos o circulación.
- La sugerencia de mecánico se calcula con especialidad, horario, órdenes activas y citas futuras; teléfonos, cargas y notas internas solo se entregan a revisores autorizados.
- Confirmaciones, modificaciones y descartes crean revisiones versionadas; una modificación se presenta como respuesta vigente sin alterar la respuesta original.
- El descarte exige motivo y la prioridad corregida se refleja en listados y resúmenes operativos.
- La consulta puede vincularse a una cita y posteriormente a una orden sin duplicar registros; ambas vinculaciones se revalidan bajo bloqueo transaccional.
- La creación de citas valida en el servidor la relación cliente-vehículo y la compatibilidad entre especialidad, servicio y mecánico.
- El calendario independiente ofrece vistas Día, Semana y Mes, filtros por mecánico y estado, navegación por periodos y detalle de citas.
- La orden muestra el diagnóstico IA vigente, su revisión humana y accesos condicionados por permisos a IA y calendario.
- El mecánico asignado a la cita o a la orden puede revisar la consulta; una sugerencia inicial deja de conceder acceso cuando existe una asignación operativa distinta.
- Una orden no puede entrar en reparación, completar servicios ni consumir repuestos sin diagnóstico técnico humano vigente; si tiene IA vinculada, esta debe estar confirmada o modificada.
- Las citas se interpretan en `America/Bogota`, validan disponibilidad nuevamente al confirmar y no pueden marcarse atendidas antes de comenzar o sin mecánico.
- La orden existe desde que se agenda la cita y aparece de inmediato al mecánico asignado; cualquier reintento abre la misma orden sin duplicarla.
- Después de entregar el vehículo no se permite anular o reembolsar pagos ni anular la factura, conservando el cierre financiero verificado.
- Inventario presenta conteos reales de referencias normales, bajas y agotadas, además de movimientos con saldo anterior y resultante.
- Los movimientos solo exponen responsable a gestores; una orden asociada solo se muestra a usuarios que pueden consultarla.
- Los índices operativos cubren alertas de stock, proveedor/estado y libro de movimientos por repuesto, tipo y fecha.
- Inventario aparece como una única opción de navegación y concentra globos verde/amarillo/rojo, filtros, catálogo, detalle, edición, entradas, ajustes, trazabilidad y accesos a Diagnóstico IA y Reportes.
- Cada referencia permite consultar su libro de movimientos, editar sus datos, registrar stock y cambiar de estado sin eliminación física.
- Reportes aparece como una única opción y reúne en una vista dinámica órdenes, ingresos, inventario, estados IA, servicios, repuestos y vehículos por cliente.
- Los filtros rápidos y globales actualizan todas las métricas; cada exportación CSV conserva exactamente el periodo y criterios seleccionados.

Verificación manual:

1. Generar un diagnóstico con Groq deshabilitado y confirmar que el fallback muestre riesgo, circulación y advertencia.
2. Modificar y descartar diagnósticos con un mecánico; comprobar que la respuesta original permanezca intacta y el descarte exija motivo.
3. Agendar una cita desde IA, solicitar opcionalmente un repuesto y verificar que la orden aparezca inmediatamente al mecánico sin factura ni movimiento de inventario.
4. Abrir la orden y confirmar que muestre la respuesta humana vigente y no una corrección interna obsoleta.
5. Registrar entradas y salidas de inventario y revisar tarjetas, saldos y visibilidad de la orden asociada según el rol.

### Fase 18: comunicaciones, documentos y accesibilidad

- La recuperación de contraseña usa el broker de Laravel, respuestas contra enumeración de cuentas, enlaces con vencimiento y revocación de sesiones y tokens al completar el cambio.
- Los recordatorios de citas por correo son configurables, se seleccionan en `America/Bogota` y conservan un registro idempotente por cita, fecha programada y canal.
- Facturas y comprobantes se generan como PDF en el servidor desde instantáneas financieras y pueden enviarse únicamente al correo congelado en la factura.
- Los permisos `facturas.enviar` y `pagos.enviar` separan la consulta del envío externo de documentos.
- Playwright cubre autenticación pública, protección de rutas y un escenario autenticado opcional mediante credenciales de prueba.
- Los campos compartidos vinculan etiquetas, ayudas y errores; el layout incluye salto al contenido, región principal, foco visible y estado accesible de navegación.
- WhatsApp y otros proveedores externos no forman parte del alcance actual; las pruebas reales de Groq se ejecutan por separado.
