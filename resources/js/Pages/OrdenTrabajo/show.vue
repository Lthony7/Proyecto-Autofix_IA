<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { usePermissions } from "../../composables/usePermissions";

interface ServicioOrden {
    id: string;
    nombre: string;
    precio: string | null;
    estado: string;
    estadoAnteriorPausa?: string;
    observaciones?: string;
    origen?: string;
    trabajoRealizado?: string;
    tipoTrabajo: string;
    aprobacionEstado: string;
    tiempoTrabajadoMinutos?: number;
    resultadoPrueba?: string;
    observacionesPosteriores?: string;
    recomendacionesCliente?: string;
    iniciadoEn?: string;
    completadoEn?: string;
}
interface Orden {
    id: string;
    numero: string;
    cliente: string;
    vehiculo: string;
    estado: string;
    fallaReportada: string;
    kilometraje?: number;
    ultimaActualizacion?: string;
    fechaEstimadaFinalizacion?: string;
    observacionesEntrega?: string;
    proximoMantenimientoEn?: string;
    servicios: ServicioOrden[];
    mecanicoIds: string[];
    mecanicos: string[];
    diagnosticos: {
        id: string;
        version: number;
        diagnostico: string;
        estado: string;
        causa?: string;
        componentesAfectados?: string;
        severidad: string;
        resumenCliente: string;
        pruebasRealizadas?: string;
        recomendaciones?: string;
        notasInternas?: string;
        observacionesTecnicas?: string;
        indicacionesSeguridad?: string;
        puedeCircular: string;
        proximoMantenimientoEn?: string;
        motivoCorreccion?: string;
        autor?: string;
        confirmadoEn?: string;
        vigente: boolean;
        createdAt: string;
    }[];
    cierreTecnico: {
        tiempoTrabajadoMinutos: number;
        bloqueosTecnicos?: string;
        controlCalidadEstado: string;
        controlCalidadNotas?: string;
        pruebaRutaEstado: string;
        pruebaRutaNotas?: string;
        actualizadoEn?: string;
    } | null;
}
interface Uso {
    id: string;
    codigo: string;
    nombre: string;
    unidad: string;
    cantidad: string;
    precioUnitario: string | null;
    fuenteSuministro: string;
    facturable: boolean;
    revertido: boolean;
    createdAt: string;
}
interface Finanzas {
    servicios: string;
    repuestos: string;
    descuento: string;
    impuesto: string;
    total: string;
    pagado: string;
    saldo: string;
    estado: string;
    moneda: string;
}
interface Pago {
    id: string;
    numero: string;
    comprobante: string;
    monto: string;
    metodo: string;
    referencia?: string;
    estado: string;
    pagadoEn: string;
    motivoAnulacion?: string;
    motivoReembolso?: string;
}
interface Factura {
    id: string;
    numero: string;
    total: string;
    emitidaEn: string;
}
interface CitaDetalle {
    id: string;
    numero: string;
    estado: string;
    inicio: string;
    fin: string;
    atendidaEn?: string;
    motivo: string;
    kilometraje?: number;
    especialidad?: string;
    servicioSolicitado?: string;
    mecanicoSolicitado?: string;
    repuestosSolicitados: {
        descripcion: string;
        cantidad: string;
        observaciones?: string;
    }[];
    historial: {
        estadoAnterior?: string;
        estadoNuevo: string;
        observaciones?: string;
        fecha: string;
    }[];
}
interface Requerimiento {
    id: string;
    repuestoId?: string;
    origen: string;
    descripcion: string;
    cantidad: string;
    cantidadUsada: string;
    cantidadRestante: string;
    cumplimiento: string;
    prioridad: string;
    obligatorio: boolean;
    fuenteSuministro: string;
    motivo?: string;
    estado: string;
    stock?: string;
    unidad?: string;
    precio?: string;
    disponible: boolean;
}
interface DiagnosticoIa {
    id: string;
    estado: string;
    entrada: Record<string, any>;
    respuesta?: Record<string, any>;
    especialidad?: string;
    mecanico?: string;
    revisada: boolean;
    revision?: {
        estado: string;
        observacionesCliente?: string;
        motivoDiferencia?: string;
        pruebasRealizadas: string[];
        notasInternas?: string;
        fecha?: string;
    };
}
interface AvanceOrden {
    id: string;
    descripcion: string;
    tipo: string;
    visibilidad: string;
    estadoOrden: string;
    autor?: string;
    servicio?: string;
    createdAt: string;
    porcentaje?: number;
    fechaEstimadaFinalizacion?: string;
    notaInterna?: string;
}
interface EstadoOrdenHistorial {
    estadoAnterior?: string;
    estadoNuevo: string;
    observaciones?: string;
    autor?: string;
    createdAt: string;
}
const props = defineProps<{
    orden: Orden;
    cita: CitaDetalle | null;
    mecanicos: { label: string; value: string }[];
    serviciosCatalogo: { label: string; value: string }[];
    repuestosCatalogo: {
        id: string;
        label: string;
        stock: string;
        unidad: string;
        precio: string;
    }[];
    repuestos: { label: string; value: string }[];
    repuestosRequeridos: Requerimiento[];
    repuestosUsados: Uso[];
    avances: AvanceOrden[];
    historialEstados: EstadoOrdenHistorial[];
    finanzas: Finanzas | null;
    pagos: Pago[];
    factura: Factura | null;
    configuracionFinanciera: { tasaImpuesto: string };
    diagnosticoIa: DiagnosticoIa | null;
    capacidades: {
        trabajo: boolean;
        diagnosticar: boolean;
        consumir: boolean;
        revertirConsumo: boolean;
        avanzarEstado: boolean;
        entregar: boolean;
        cancelar: boolean;
        asignar: boolean;
        aprobarRepuestos: boolean;
        aprobarServicios: boolean;
        cerrarTecnico: boolean;
        verFinanzas: boolean;
    };
    esCliente: boolean;
}>();
const { can, canAny } = usePermissions();
const errors = computed<Record<string, string>>(
    () => usePage().props.errors as Record<string, string>,
);
const primerError = computed(() => Object.values(errors.value)[0]);
const asignacion = ref([...props.orden.mecanicoIds]);
const borradorActual = computed(() =>
    props.orden.diagnosticos.find(
        (item) => item.vigente && item.estado === "borrador",
    ),
);
const diagnosticoPublicado = computed(() =>
    props.orden.diagnosticos.find(
        (item) => item.vigente && item.estado === "confirmado",
    ),
);
const diagnosticoEditable = computed(
    () => borradorActual.value ?? diagnosticoPublicado.value,
);
const diagnosticoInicial = diagnosticoEditable.value;
const diag = reactive({
    estado: diagnosticoInicial?.estado || "borrador",
    diagnostico: diagnosticoInicial?.diagnostico || "",
    causa: diagnosticoInicial?.causa || "",
    componentesAfectados: diagnosticoInicial?.componentesAfectados || "",
    severidad: diagnosticoInicial?.severidad || "media",
    resumenCliente: diagnosticoInicial?.resumenCliente || "",
    pruebasRealizadas: diagnosticoInicial?.pruebasRealizadas || "",
    recomendaciones: diagnosticoInicial?.recomendaciones || "",
    notasInternas: diagnosticoInicial?.notasInternas || "",
    observacionesTecnicas: diagnosticoInicial?.observacionesTecnicas || "",
    indicacionesSeguridad: diagnosticoInicial?.indicacionesSeguridad || "",
    puedeCircular: diagnosticoInicial?.puedeCircular || "con_precaucion",
    proximoMantenimientoEn: diagnosticoInicial?.proximoMantenimientoEn || "",
    motivoCorreccion: "",
});
const consumo = reactive({
    repuestoId: "",
    requerimientoId: "",
    cantidad: "1.000",
    observaciones: "",
});
const nuevoServicio = reactive({ servicioId: "", motivo: "" });
const nuevoRepuesto = reactive({
    repuestoId: "",
    descripcion: "",
    cantidad: "1.000",
    motivo: "",
    prioridad: "media",
    obligatorio: true,
    fuenteSuministro: "inventario",
    unidad: "unidad",
});
const avance = reactive({
    tipo: "avance",
    descripcion: "",
    visibilidad: "cliente",
    servicioId: "",
    porcentaje: null as number | null,
    fechaEstimadaFinalizacion: "",
    notaInterna: "",
});
const cierreTecnico = reactive({
    tiempoTrabajadoMinutos:
        props.orden.cierreTecnico?.tiempoTrabajadoMinutos || 0,
    bloqueosTecnicos: props.orden.cierreTecnico?.bloqueosTecnicos || "",
    controlCalidadEstado:
        props.orden.cierreTecnico?.controlCalidadEstado || "pendiente",
    controlCalidadNotas: props.orden.cierreTecnico?.controlCalidadNotas || "",
    pruebaRutaEstado:
        props.orden.cierreTecnico?.pruebaRutaEstado || "pendiente",
    pruebaRutaNotas: props.orden.cierreTecnico?.pruebaRutaNotas || "",
    observacionesEntrega: props.orden.cierreTecnico?.observacionesEntrega || "",
    proximoMantenimientoEn:
        props.orden.cierreTecnico?.proximoMantenimientoEn || "",
});
const fechaLocal = () =>
    new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
const pago = reactive({
    idempotenciaClave: crypto.randomUUID(),
    monto: "",
    metodo: "efectivo",
    referencia: "",
    observaciones: "",
    pagadoEn: fechaLocal(),
});
const facturacion = reactive({
    descuento: "0.00",
    motivoDescuento: "",
    tasaImpuesto: props.configuracionFinanciera.tasaImpuesto,
    venceEn: "",
    observaciones: "",
});
const procesando = ref(false);
const totalRepuestos = computed(() =>
    props.repuestosUsados
        .filter((u) => !u.revertido)
        .reduce(
            (total, u) => total + Number(u.cantidad) * Number(u.precioUnitario),
            0,
        ),
);
const requerimientoConsumo = computed(() =>
    props.repuestosRequeridos.find(
        (item) => item.id === consumo.requerimientoId,
    ),
);
const progresoOrden = computed(
    () =>
        props.avances.find(
            (item) => item.porcentaje !== undefined && item.porcentaje !== null,
        )?.porcentaje ??
        (
            {
                pendiente: 5,
                asignada: 10,
                en_diagnostico: 25,
                esperando_aprobacion: 35,
                esperando_repuestos: 40,
                en_reparacion: 65,
                pausada: 50,
                en_prueba: 85,
                finalizada: 95,
                lista_entrega: 98,
                entregada: 100,
                cancelada: 0,
            } as Record<string, number>
        )[props.orden.estado] ??
        0,
);
watch(
    () => props.orden.mecanicoIds,
    (ids) => (asignacion.value = [...ids]),
);
watch(diagnosticoEditable, (item) => {
    if (item)
        Object.assign(diag, {
            estado: item.estado,
            diagnostico: item.diagnostico,
            causa: item.causa || "",
            componentesAfectados: item.componentesAfectados || "",
            severidad: item.severidad,
            resumenCliente: item.resumenCliente || "",
            pruebasRealizadas: item.pruebasRealizadas || "",
            recomendaciones: item.recomendaciones || "",
            notasInternas: item.notasInternas || "",
            observacionesTecnicas: item.observacionesTecnicas || "",
            indicacionesSeguridad: item.indicacionesSeguridad || "",
            puedeCircular: item.puedeCircular,
            proximoMantenimientoEn: item.proximoMantenimientoEn || "",
            motivoCorreccion: "",
        });
});
watch(
    () => props.orden.cierreTecnico,
    (item) => {
        if (item)
            Object.assign(cierreTecnico, {
                tiempoTrabajadoMinutos: item.tiempoTrabajadoMinutos,
                bloqueosTecnicos: item.bloqueosTecnicos || "",
                controlCalidadEstado: item.controlCalidadEstado,
                controlCalidadNotas: item.controlCalidadNotas || "",
                pruebaRutaEstado: item.pruebaRutaEstado,
                pruebaRutaNotas: item.pruebaRutaNotas || "",
                observacionesEntrega: item.observacionesEntrega || "",
                proximoMantenimientoEn: item.proximoMantenimientoEn || "",
            });
    },
);
const camposReporteIa = computed(() => {
    const entrada = props.diagnosticoIa?.entrada;
    if (!entrada) return [];
    const campos = [
        ["Síntoma principal", entrada.sintoma_principal],
        ["Sistema reportado", entrada.categoria_falla],
        ["Momento en que ocurre", entrada.momento_ocurre],
        ["Frecuencia", entrada.frecuencia?.replaceAll("_", " ")],
        ["Desde cuándo", entrada.tiempo_desde_inicio],
        ["Intensidad", entrada.intensidad],
        ["Condiciones", entrada.condiciones?.join(", ")],
        ["Ruidos, vibraciones, humo, olores o fugas", entrada.senales],
        ["Testigos del tablero", entrada.luces_tablero],
        [
            "Pérdida de potencia o dificultad de arranque",
            entrada.perdida_potencia_arranque,
        ],
        ["Códigos OBD", entrada.codigos_obd],
        ["Pruebas realizadas por el cliente", entrada.pruebas_realizadas],
        [
            "Puede circular según el cliente",
            entrada.puede_circular?.replaceAll("_", " "),
        ],
        ["Urgencia percibida", entrada.urgencia_percibida],
        ["Reparaciones recientes", entrada.reparaciones_recientes],
        ["Observaciones adicionales", entrada.observaciones],
    ];
    return campos.filter(
        ([, valor]) =>
            valor !== undefined &&
            valor !== null &&
            String(valor).trim() !== "",
    );
});

function toggle(id: string, valor: boolean) {
    if (valor && !asignacion.value.includes(id)) asignacion.value.push(id);
    if (!valor) asignacion.value = asignacion.value.filter((x) => x !== id);
}
function asignar() {
    router.patch(
        route("ordenes.asignar", props.orden.id),
        { mecanicoIds: asignacion.value },
        { preserveScroll: true },
    );
}
function estado(nuevo: string) {
    const data: any = { estado: nuevo };
    if (["cancelada", "pausada"].includes(nuevo)) {
        const m = prompt(
            nuevo === "cancelada"
                ? "Motivo de cancelación"
                : "Motivo de la pausa",
        );
        if (!m) return;
        data.motivo = m;
    }
    if (nuevo === "lista_entrega") {
        const observaciones = prompt("Observaciones para la entrega");
        if (!observaciones) return;
        data.observacionesEntrega = observaciones;
    }
    if (confirm(`¿Cambiar la orden a ${nuevo.replaceAll("_", " ")}?`))
        router.patch(route("ordenes.estado", props.orden.id), data, {
            preserveScroll: true,
        });
}
function registrarLlegada() {
    if (!props.cita || new Date(props.cita.inicio) > new Date()) return;
    if (!confirm("¿Confirmas que el vehículo llegó físicamente al taller?"))
        return;
    router.patch(
        route("citas.estado", props.cita.id),
        {
            estado: "atendida",
            observaciones: "Llegada registrada desde la orden de trabajo.",
        },
        { preserveScroll: true },
    );
}
function estadoServicio(servicio: ServicioOrden, nuevo: string) {
    const data: any = { estado: nuevo };
    if (nuevo === "cancelado") {
        const motivo = prompt("Motivo de cancelación del servicio");
        if (!motivo) return;
        data.observaciones = motivo;
    }
    if (nuevo === "completado") {
        const trabajo = prompt("Describe el trabajo realizado");
        if (!trabajo) return;
        data.trabajoRealizado = trabajo;
        const minutos = prompt("Tiempo empleado en minutos");
        if (!minutos || Number(minutos) < 1) return;
        data.tiempoTrabajadoMinutos = Number(minutos);
        data.resultadoPrueba =
            prompt("Resultado de la prueba realizada") ||
            "Sin prueba adicional";
        data.observacionesPosteriores =
            prompt("Observaciones posteriores a la reparación") || "";
        data.recomendacionesCliente =
            prompt("Recomendaciones para el cliente") || "";
    }
    router.patch(
        route("ordenes.servicios.estado", [props.orden.id, servicio.id]),
        data,
        { preserveScroll: true },
    );
}
function agregarServicio() {
    router.post(
        route("ordenes.servicios.store", props.orden.id),
        nuevoServicio,
        {
            preserveScroll: true,
            onSuccess: () =>
                Object.assign(nuevoServicio, { servicioId: "", motivo: "" }),
        },
    );
}
function registrarAvance() {
    procesando.value = true;
    router.post(route("ordenes.avances.store", props.orden.id), avance, {
        preserveScroll: true,
        onSuccess: () =>
            Object.assign(avance, {
                tipo: "avance",
                descripcion: "",
                visibilidad: "cliente",
                servicioId: "",
                porcentaje: null,
                fechaEstimadaFinalizacion: "",
                notaInterna: "",
            }),
        onFinish: () => (procesando.value = false),
    });
}
function guardarCierreTecnico() {
    procesando.value = true;
    router.patch(
        route("ordenes.cierre-tecnico", props.orden.id),
        cierreTecnico,
        {
            preserveScroll: true,
            onFinish: () => (procesando.value = false),
        },
    );
}
function seleccionarRepuesto(id: string) {
    nuevoRepuesto.repuestoId = id;
    const p = props.repuestosCatalogo.find((x) => x.id === id);
    if (p) nuevoRepuesto.descripcion = p.label;
}
function agregarRequerimiento() {
    router.post(
        route("ordenes.repuestos-requeridos.store", props.orden.id),
        nuevoRepuesto,
        {
            preserveScroll: true,
            onSuccess: () =>
                Object.assign(nuevoRepuesto, {
                    repuestoId: "",
                    descripcion: "",
                    cantidad: "1.000",
                    motivo: "",
                    prioridad: "media",
                    obligatorio: true,
                    fuenteSuministro: "inventario",
                    unidad: "unidad",
                }),
        },
    );
}
function retirarRequerimiento(item: Requerimiento) {
    const motivo = prompt("Motivo para retirar este requerimiento");
    if (motivo)
        router.patch(
            route("ordenes.repuestos-requeridos.estado", [
                props.orden.id,
                item.id,
            ]),
            { estado: "cancelado", motivo },
            { preserveScroll: true },
        );
}
function decidirRequerimiento(
    item: Requerimiento,
    estadoNuevo: "aprobado" | "no_disponible" | "no_utilizado",
) {
    const motivo = prompt(
        `Motivo para marcar como ${estadoNuevo.replaceAll("_", " ")}`,
    );
    if (!motivo) return;
    let precioUnitarioAprobado: string | undefined;
    if (estadoNuevo === "aprobado" && item.fuenteSuministro === "externo") {
        precioUnitarioAprobado =
            prompt("Precio unitario aprobado") || undefined;
        if (precioUnitarioAprobado === undefined) return;
    }
    router.patch(
        route("ordenes.repuestos-requeridos.estado", [props.orden.id, item.id]),
        { estado: estadoNuevo, motivo, precioUnitarioAprobado },
        { preserveScroll: true },
    );
}
function editarRequerimiento(item: Requerimiento) {
    const cantidad = prompt("Cantidad requerida", item.cantidad);
    if (!cantidad || Number(cantidad) <= 0) return;
    const prioridad = prompt(
        "Prioridad: baja, media, alta o critica",
        item.prioridad,
    );
    if (!prioridad || !["baja", "media", "alta", "critica"].includes(prioridad))
        return;
    const motivo = prompt("Motivo técnico actualizado", item.motivo || "");
    if (!motivo) return;
    router.patch(
        route("ordenes.repuestos-requeridos.update", [props.orden.id, item.id]),
        { cantidad, prioridad, obligatorio: item.obligatorio, motivo },
        { preserveScroll: true },
    );
}
function decidirServicio(
    item: ServicioOrden,
    estadoNuevo: "aprobado" | "rechazado",
) {
    const motivo = prompt(`Motivo para marcar el servicio como ${estadoNuevo}`);
    if (motivo)
        router.patch(
            route("ordenes.servicios.aprobacion", [props.orden.id, item.id]),
            { estado: estadoNuevo, motivo },
            { preserveScroll: true },
        );
}
function prepararUso(item: Requerimiento) {
    consumo.requerimientoId = item.id;
    consumo.repuestoId = item.repuestoId || "";
    consumo.cantidad = item.cantidadRestante;
    consumo.observaciones = `Uso confirmado para: ${item.descripcion}`;
}
function diagnosticar(estadoDiagnostico: "borrador" | "confirmado") {
    procesando.value = true;
    router.post(
        route("ordenes.diagnosticar", props.orden.id),
        { ...diag, estado: estadoDiagnostico },
        {
            preserveScroll: true,
            onFinish: () => (procesando.value = false),
        },
    );
}
function usarRepuesto() {
    if (
        !confirm(
            "¿Confirmas que esta cantidad fue realmente utilizada? Esta acción afectará inventario cuando corresponda.",
        )
    )
        return;
    procesando.value = true;
    router.post(route("ordenes.repuestos.store", props.orden.id), consumo, {
        preserveScroll: true,
        onSuccess: () =>
            Object.assign(consumo, {
                repuestoId: "",
                requerimientoId: "",
                cantidad: "1.000",
                observaciones: "",
            }),
        onFinish: () => (procesando.value = false),
    });
}
function revertir(uso: Uso) {
    const motivo = prompt("Motivo de la reversión");
    if (!motivo) return;
    if (!confirm("¿Confirmas la devolución o reversión de este uso?")) return;
    router.post(
        route("ordenes.repuestos.revertir", uso.id),
        { motivo },
        { preserveScroll: true },
    );
}
function registrarPago() {
    procesando.value = true;
    router.post(
        route("pagos.store", props.orden.id),
        { ...pago, pagadoEn: new Date(pago.pagadoEn).toISOString() },
        {
            preserveScroll: true,
            onSuccess: () =>
                Object.assign(pago, {
                    monto: "",
                    metodo: "efectivo",
                    referencia: "",
                    observaciones: "",
                    pagadoEn: fechaLocal(),
                    idempotenciaClave: crypto.randomUUID(),
                }),
            onFinish: () => (procesando.value = false),
        },
    );
}
function anularPago(item: Pago) {
    const motivo = prompt("Motivo de la anulación");
    if (!motivo) return;
    router.post(
        route("pagos.anular", item.id),
        { motivo },
        { preserveScroll: true },
    );
}
function reembolsarPago(item: Pago) {
    const motivo = prompt("Motivo del reembolso total");
    if (!motivo) return;
    router.post(
        route("pagos.reembolsar", item.id),
        { motivo },
        { preserveScroll: true },
    );
}
function emitirFactura() {
    if (!confirm("¿Emitir la factura definitiva con estos valores?")) return;
    procesando.value = true;
    router.post(route("facturacion.store", props.orden.id), facturacion, {
        preserveScroll: true,
        onFinish: () => (procesando.value = false),
    });
}
function dinero(valor: string | number | null) {
    return Number(valor).toLocaleString("es-EC", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <Head :title="orden.numero" />
    <UDashboardPanel>
        <template #header
            ><UDashboardNavbar :title="orden.numero"
                ><template #right
                    ><Link
                        v-if="
                            diagnosticoIa &&
                            canAny(['ia.solicitar', 'ia.revisar'])
                        "
                        :href="route('ia.show', diagnosticoIa.id)"
                        ><UButton
                            label="Ver IA"
                            icon="i-lucide-brain-circuit"
                            color="neutral"
                            variant="outline" /></Link
                    ><Link
                        v-if="can('citas.ver')"
                        :href="route('citas.calendario')"
                        ><UButton
                            label="Calendario"
                            icon="i-lucide-calendar-days"
                            color="neutral"
                            variant="outline" /></Link
                    ><UBadge size="lg">{{
                        orden.estado.replaceAll("_", " ")
                    }}</UBadge></template
                ></UDashboardNavbar
            ></template
        >
        <template #body>
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="order-2 space-y-6 xl:order-1 xl:col-span-2">
                    <UAlert
                        v-if="primerError"
                        color="error"
                        icon="i-lucide-circle-alert"
                        title="No se pudo completar la acción"
                        :description="primerError"
                    />
                    <UCard>
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <p
                                    class="text-xs uppercase tracking-wide text-muted"
                                >
                                    Progreso estimado
                                </p>
                                <p
                                    class="mt-1 text-2xl font-semibold text-primary"
                                >
                                    {{ progresoOrden }}%
                                </p>
                                <div
                                    class="mt-2 h-2 overflow-hidden rounded-full bg-elevated"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary transition-all"
                                        :style="{ width: `${progresoOrden}%` }"
                                    />
                                </div>
                            </div>
                            <div>
                                <p
                                    class="text-xs uppercase tracking-wide text-muted"
                                >
                                    Última actualización
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        orden.ultimaActualizacion
                                            ? new Date(
                                                  orden.ultimaActualizacion,
                                              ).toLocaleString("es-EC")
                                            : "Sin dato"
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs uppercase tracking-wide text-muted"
                                >
                                    Finalización estimada
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        orden.fechaEstimadaFinalizacion
                                            ? new Date(
                                                  orden.fechaEstimadaFinalizacion,
                                              ).toLocaleString("es-EC")
                                            : "Por definir"
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs uppercase tracking-wide text-muted"
                                >
                                    Diagnóstico
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        diagnosticoPublicado
                                            ? borradorActual
                                                ? "Confirmado · borrador pendiente"
                                                : "Confirmado"
                                            : borradorActual
                                              ? "Borrador"
                                              : "Pendiente"
                                    }}
                                </p>
                            </div>
                        </div>
                    </UCard>
                    <UCard v-if="cita"
                        ><template #header
                            ><div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="font-mono text-xs uppercase tracking-wider text-primary"
                                    >
                                        Agenda de origen
                                    </p>
                                    <h2 class="font-semibold">
                                        {{ cita.numero }}
                                    </h2>
                                </div>
                                <div
                                    class="flex flex-wrap items-center justify-end gap-2"
                                >
                                    <UButton
                                        v-if="
                                            ['confirmada', 'vencida'].includes(
                                                cita.estado,
                                            ) && can('citas.gestionar')
                                        "
                                        :label="
                                            new Date(cita.inicio) <= new Date()
                                                ? 'Registrar llegada'
                                                : `Llegada disponible ${new Date(cita.inicio).toLocaleDateString('es-EC')}`
                                        "
                                        icon="i-lucide-log-in"
                                        color="success"
                                        size="sm"
                                        :disabled="
                                            new Date(cita.inicio) > new Date()
                                        "
                                        @click="registrarLlegada"
                                    />
                                    <UBadge
                                        :color="
                                            cita.estado === 'vencida'
                                                ? 'error'
                                                : undefined
                                        "
                                        >{{ cita.estado }}</UBadge
                                    >
                                </div>
                            </div></template
                        >
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <p class="text-xs text-muted">Fecha y hora</p>
                                <p class="font-medium">
                                    {{
                                        new Date(cita.inicio).toLocaleString(
                                            "es-EC",
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">
                                    Especialidad y servicio solicitado
                                </p>
                                <p class="font-medium">
                                    {{ cita.especialidad || "Por confirmar" }} ·
                                    {{
                                        cita.servicioSolicitado ||
                                        "Sin servicio definido"
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">
                                    Mecánico solicitado
                                </p>
                                <p class="font-medium">
                                    {{
                                        cita.mecanicoSolicitado || "Sin asignar"
                                    }}
                                </p>
                            </div>
                            <div v-if="cita.atendidaEn">
                                <p class="text-xs text-muted">Llegada real</p>
                                <p class="font-medium text-success">
                                    {{
                                        new Date(
                                            cita.atendidaEn,
                                        ).toLocaleString("es-EC")
                                    }}
                                </p>
                            </div>
                        </div>
                        <UAlert
                            v-if="
                                cita.estado === 'confirmada' &&
                                new Date(cita.inicio) > new Date() &&
                                can('citas.gestionar')
                            "
                            class="mt-4"
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-clock"
                            title="La llegada aún no está disponible"
                            :description="`Podrás registrarla desde esta orden cuando comience la cita: ${new Date(cita.inicio).toLocaleString('es-EC')}.`"
                        />
                        <div class="mt-4 rounded-lg bg-elevated/50 p-4">
                            <p class="text-xs font-bold uppercase text-muted">
                                Problema descrito por el cliente
                            </p>
                            <p class="mt-2 whitespace-pre-wrap">
                                {{ cita.motivo }}
                            </p>
                            <p
                                v-if="cita.kilometraje"
                                class="mt-2 text-sm text-muted"
                            >
                                Kilometraje reportado:
                                {{
                                    Number(cita.kilometraje).toLocaleString(
                                        "es-EC",
                                    )
                                }}
                                km
                            </p>
                        </div>
                        <div
                            v-if="cita.repuestosSolicitados.length"
                            class="mt-4"
                        >
                            <p class="text-sm font-semibold">
                                Repuestos solicitados por el cliente (sin
                                reservar ni consumir)
                            </p>
                            <ul class="mt-2 space-y-2">
                                <li
                                    v-for="(r, i) in cita.repuestosSolicitados"
                                    :key="i"
                                    class="rounded-lg border border-default p-3 text-sm"
                                >
                                    <strong>{{ r.descripcion }}</strong> ·
                                    {{ r.cantidad }}
                                    <p
                                        v-if="r.observaciones"
                                        class="text-muted"
                                    >
                                        {{ r.observaciones }}
                                    </p>
                                </li>
                            </ul>
                        </div>
                        <details v-if="cita.historial.length" class="mt-4">
                            <summary
                                class="cursor-pointer text-sm text-primary"
                            >
                                Ver historial de la agenda
                            </summary>
                            <div class="mt-2 space-y-2">
                                <p
                                    v-for="(h, i) in cita.historial"
                                    :key="i"
                                    class="text-xs text-muted"
                                >
                                    {{
                                        new Date(h.fecha).toLocaleString(
                                            "es-EC",
                                        )
                                    }}
                                    · {{ h.estadoAnterior || "inicio" }} →
                                    {{ h.estadoNuevo }} ·
                                    {{ h.observaciones || "Sin observaciones" }}
                                </p>
                            </div>
                        </details></UCard
                    >
                    <UCard
                        v-if="diagnosticoIa"
                        :class="
                            diagnosticoIa.estado === 'descartada'
                                ? 'border-error/25 bg-error/5'
                                : 'border-primary/20 bg-primary/5'
                        "
                        ><template #header
                            ><div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="font-mono text-xs uppercase tracking-wider text-primary"
                                    >
                                        Diagnóstico preliminar IA
                                    </p>
                                    <h2 class="mt-1 font-semibold">
                                        {{
                                            diagnosticoIa.estado ===
                                            "descartada"
                                                ? "Descartado"
                                                : diagnosticoIa.revisada
                                                  ? "Revisado por mecánico"
                                                  : "Pendiente de revisión humana"
                                        }}
                                    </h2>
                                </div>
                                <UBadge variant="subtle">{{
                                    diagnosticoIa.estado.replaceAll("_", " ")
                                }}</UBadge>
                            </div></template
                        ><template v-if="diagnosticoIa.respuesta"
                            ><p class="leading-6">
                                {{
                                    diagnosticoIa.respuesta.resumen_cliente ||
                                    diagnosticoIa.respuesta.resumen
                                }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <UBadge v-if="diagnosticoIa.especialidad">{{
                                    diagnosticoIa.especialidad
                                }}</UBadge
                                ><UBadge
                                    v-if="diagnosticoIa.mecanico"
                                    color="success"
                                    variant="subtle"
                                    >Mecánico sugerido:
                                    {{ diagnosticoIa.mecanico }}</UBadge
                                ><UBadge color="warning" variant="subtle"
                                    >Riesgo
                                    {{
                                        diagnosticoIa.respuesta.nivel_riesgo
                                    }}</UBadge
                                ><UBadge color="neutral" variant="outline"
                                    >Circulación
                                    {{
                                        diagnosticoIa.respuesta.puede_circular
                                    }}</UBadge
                                >
                            </div>
                            <div
                                v-if="diagnosticoIa.revision"
                                class="mt-4 rounded-lg border border-success/25 bg-success/5 p-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <h3 class="font-semibold">
                                        Revisión del mecánico
                                    </h3>
                                    <UBadge color="success" variant="subtle">{{
                                        diagnosticoIa.revision.estado.replaceAll(
                                            "_",
                                            " ",
                                        )
                                    }}</UBadge>
                                </div>
                                <p
                                    v-if="
                                        diagnosticoIa.revision
                                            .observacionesCliente
                                    "
                                    class="mt-2 whitespace-pre-wrap text-sm"
                                >
                                    {{
                                        diagnosticoIa.revision
                                            .observacionesCliente
                                    }}
                                </p>
                                <p
                                    v-if="
                                        diagnosticoIa.revision.motivoDiferencia
                                    "
                                    class="mt-2 text-sm"
                                >
                                    <strong>Motivo de la corrección:</strong>
                                    {{
                                        diagnosticoIa.revision.motivoDiferencia
                                    }}
                                </p>
                                <p
                                    v-if="
                                        diagnosticoIa.revision.pruebasRealizadas
                                            .length
                                    "
                                    class="mt-2 text-sm"
                                >
                                    <strong>Pruebas realizadas:</strong>
                                    {{
                                        diagnosticoIa.revision.pruebasRealizadas.join(
                                            " · ",
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="diagnosticoIa.revision.notasInternas"
                                    class="mt-2 text-sm text-muted"
                                >
                                    <strong>Notas internas:</strong>
                                    {{ diagnosticoIa.revision.notasInternas }}
                                </p>
                            </div>
                            <UAlert
                                class="mt-4"
                                color="warning"
                                icon="i-lucide-triangle-alert"
                                title="Diagnóstico preliminar"
                                :description="
                                    diagnosticoIa.respuesta.advertencia
                                "
                            />
                            <details class="mt-4">
                                <summary
                                    class="cursor-pointer font-medium text-primary"
                                >
                                    Ver análisis IA completo
                                </summary>
                                <div class="mt-4 space-y-4 text-sm">
                                    <div>
                                        <h3 class="font-semibold">
                                            Diagnóstico técnico preliminar
                                        </h3>
                                        <p class="whitespace-pre-wrap">
                                            {{
                                                diagnosticoIa.respuesta
                                                    .diagnostico_tecnico
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold">
                                            Causas posibles
                                        </h3>
                                        <ul class="list-disc pl-5">
                                            <li
                                                v-for="(
                                                    causa, i
                                                ) in diagnosticoIa.respuesta
                                                    .posibles_causas"
                                                :key="i"
                                            >
                                                <strong>{{
                                                    causa.nombre
                                                }}</strong
                                                >: {{ causa.explicacion }} ·
                                                probabilidad
                                                {{ causa.probabilidad_o_nivel }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold">
                                            Pruebas y acciones
                                        </h3>
                                        <p>
                                            {{
                                                (
                                                    diagnosticoIa.respuesta
                                                        .pruebas_sugeridas || []
                                                ).join(" · ")
                                            }}
                                        </p>
                                        <p>
                                            {{
                                                (
                                                    diagnosticoIa.respuesta
                                                        .acciones_recomendadas ||
                                                    []
                                                ).join(" · ")
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold">
                                            Servicios sugeridos
                                        </h3>
                                        <p>
                                            {{
                                                (
                                                    diagnosticoIa.respuesta
                                                        .servicios_sugeridos ||
                                                    []
                                                ).join(" · ") || "Ninguno"
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold">
                                            Repuestos posibles, no autorizados
                                        </h3>
                                        <ul>
                                            <li
                                                v-for="(r, i) in diagnosticoIa
                                                    .respuesta
                                                    .repuestos_posibles"
                                                :key="i"
                                            >
                                                {{ r.nombre }} ·
                                                {{ r.cantidad }} ·
                                                {{ r.motivo }} · probabilidad
                                                {{ r.probabilidad_o_nivel }}
                                            </li>
                                        </ul>
                                        <p
                                            v-if="
                                                !diagnosticoIa.respuesta
                                                    .repuestos_posibles?.length
                                            "
                                            class="text-muted"
                                        >
                                            La IA no recomendó repuestos.
                                        </p>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold">
                                            Reporte completo del cliente
                                        </h3>
                                        <dl
                                            class="mt-2 grid gap-3 sm:grid-cols-2"
                                        >
                                            <div
                                                v-for="(
                                                    [etiqueta, valor], indice
                                                ) in camposReporteIa"
                                                :key="indice"
                                                class="rounded bg-elevated p-3"
                                            >
                                                <dt class="text-xs text-muted">
                                                    {{ etiqueta }}
                                                </dt>
                                                <dd
                                                    class="mt-1 whitespace-pre-wrap font-medium"
                                                >
                                                    {{ valor }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </details></template
                        >
                        <p v-else>
                            Esta sugerencia fue descartada y no debe utilizarse
                            para decidir trabajos o repuestos.
                        </p></UCard
                    >
                    <UCard
                        ><template #header
                            ><h2 class="font-semibold">
                                Vehículo y falla reportada
                            </h2></template
                        >
                        <p class="font-medium">
                            {{ orden.vehiculo }} · {{ orden.cliente }}
                        </p>
                        <p v-if="orden.kilometraje" class="text-sm text-muted">
                            {{ orden.kilometraje.toLocaleString("es-EC") }} km
                        </p>
                        <p class="mt-4 whitespace-pre-wrap">
                            {{ orden.fallaReportada }}
                        </p></UCard
                    >
                    <UCard>
                        <template #header>
                            <div>
                                <h2 class="font-semibold">
                                    Bitácora de avances
                                </h2>
                                <p class="text-sm text-muted">
                                    Actualizaciones cronológicas del diagnóstico
                                    y la reparación.
                                </p>
                            </div>
                        </template>

                        <form
                            v-if="
                                capacidades.trabajo &&
                                [
                                    'en_diagnostico',
                                    'esperando_aprobacion',
                                    'esperando_repuestos',
                                    'en_reparacion',
                                    'pausada',
                                    'en_prueba',
                                ].includes(orden.estado)
                            "
                            class="mb-5 grid gap-3 rounded-lg bg-elevated/50 p-4 sm:grid-cols-2"
                            @submit.prevent="registrarAvance"
                        >
                            <UFormField label="Tipo de actualización" required>
                                <USelect
                                    v-model="avance.tipo"
                                    :items="[
                                        { label: 'Avance', value: 'avance' },
                                        {
                                            label: 'Inspección inicial',
                                            value: 'inspeccion',
                                        },
                                        {
                                            label: 'Hallazgo',
                                            value: 'hallazgo',
                                        },
                                        {
                                            label: 'Síntoma encontrado',
                                            value: 'sintoma',
                                        },
                                        {
                                            label: 'Prueba realizada',
                                            value: 'prueba',
                                        },
                                        { label: 'Pausa', value: 'pausa' },
                                        {
                                            label: 'Recomendación',
                                            value: 'recomendacion',
                                        },
                                    ]"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                label="Servicio relacionado"
                                hint="Opcional"
                            >
                                <USelect
                                    v-model="avance.servicioId"
                                    :items="[
                                        {
                                            label: 'Avance general de la orden',
                                            value: '',
                                        },
                                        ...orden.servicios.map((s) => ({
                                            label: s.nombre,
                                            value: s.id,
                                        })),
                                    ]"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                label="Progreso aproximado (%)"
                                :error="errors.porcentaje"
                                ><UInput
                                    v-model.number="avance.porcentaje"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                label="Finalización estimada"
                                hint="Opcional"
                                ><UInput
                                    v-model="avance.fechaEstimadaFinalizacion"
                                    type="datetime-local"
                                    class="w-full"
                            /></UFormField>
                            <UFormField label="Visibilidad" required>
                                <USelect
                                    v-model="avance.visibilidad"
                                    :items="[
                                        {
                                            label: 'Visible para el cliente',
                                            value: 'cliente',
                                        },
                                        {
                                            label: 'Solo personal interno',
                                            value: 'interno',
                                        },
                                    ]"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                class="sm:col-span-2"
                                label="Nota técnica interna"
                                hint="Nunca visible para el cliente"
                                ><UTextarea
                                    v-model="avance.notaInterna"
                                    :rows="3"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                class="sm:col-span-2"
                                label="Avance realizado"
                                required
                                :error="errors.descripcion || errors.avance"
                            >
                                <UTextarea
                                    v-model="avance.descripcion"
                                    :rows="4"
                                    class="w-full"
                                    placeholder="Describe la inspección, prueba, hallazgo o trabajo realizado."
                                    required
                                />
                            </UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton
                                    type="submit"
                                    label="Registrar avance"
                                    icon="i-lucide-notebook-pen"
                                    :loading="procesando"
                                />
                            </div>
                        </form>

                        <div v-if="avances.length" class="space-y-3">
                            <article
                                v-for="item in avances"
                                :key="item.id"
                                class="rounded-lg border border-default p-4"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-2"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <UBadge variant="subtle">{{
                                            item.estadoOrden.replaceAll(
                                                "_",
                                                " ",
                                            )
                                        }}</UBadge>
                                        <UBadge
                                            color="neutral"
                                            variant="outline"
                                            >{{
                                                item.tipo.replaceAll("_", " ")
                                            }}</UBadge
                                        >
                                        <UBadge
                                            v-if="item.servicio"
                                            color="primary"
                                            variant="outline"
                                            >{{ item.servicio }}</UBadge
                                        >
                                        <UBadge
                                            v-if="
                                                item.visibilidad === 'interno'
                                            "
                                            color="neutral"
                                            variant="outline"
                                            >Interno</UBadge
                                        >
                                    </div>
                                    <span class="text-xs text-muted">{{
                                        new Date(item.createdAt).toLocaleString(
                                            "es-EC",
                                        )
                                    }}</span>
                                </div>
                                <p
                                    class="mt-3 whitespace-pre-wrap text-sm leading-6"
                                >
                                    {{ item.descripcion }}
                                </p>
                                <p
                                    v-if="
                                        item.porcentaje !== undefined &&
                                        item.porcentaje !== null
                                    "
                                    class="mt-2 text-xs text-primary"
                                >
                                    Progreso informado: {{ item.porcentaje }}%
                                </p>
                                <p
                                    v-if="item.notaInterna"
                                    class="mt-2 rounded bg-elevated p-2 text-xs"
                                >
                                    <strong>Nota interna:</strong>
                                    {{ item.notaInterna }}
                                </p>
                                <p class="mt-2 text-xs text-muted">
                                    Registrado por
                                    {{ item.autor || "Usuario no disponible" }}
                                </p>
                            </article>
                        </div>
                        <p v-else class="py-5 text-center text-sm text-muted">
                            Todavía no hay avances registrados.
                        </p>
                    </UCard>
                    <UCard
                        ><template #header
                            ><div>
                                <h2 class="font-semibold">
                                    Servicios requeridos y realizados
                                </h2>
                                <p class="text-sm text-muted">
                                    El mecánico agrega el alcance diagnosticado
                                    y documenta qué realizó.
                                </p>
                            </div></template
                        >
                        <form
                            v-if="
                                capacidades.trabajo &&
                                [
                                    'en_diagnostico',
                                    'esperando_aprobacion',
                                    'en_reparacion',
                                ].includes(orden.estado)
                            "
                            class="mb-4 grid gap-3 rounded-lg bg-elevated/50 p-4 sm:grid-cols-2"
                            @submit.prevent="agregarServicio"
                        >
                            <UFormField
                                label="Agregar servicio"
                                required
                                :error="errors.servicioId"
                                ><USelect
                                    v-model="nuevoServicio.servicioId"
                                    :items="serviciosCatalogo"
                                    class="w-full" /></UFormField
                            ><UFormField label="Motivo técnico" required
                                ><UTextarea
                                    v-model="nuevoServicio.motivo"
                                    :rows="3"
                                    class="w-full"
                                    placeholder="Resultado del diagnóstico"
                            /></UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton
                                    type="submit"
                                    label="Solicitar trabajo adicional"
                                />
                            </div>
                        </form>
                        <div
                            v-for="s in orden.servicios"
                            :key="s.id"
                            class="flex flex-col gap-3 border-b border-default py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{
                                        s.nombre
                                    }}</span
                                    ><UBadge variant="outline">{{
                                        s.origen || "manual"
                                    }}</UBadge
                                    ><UBadge
                                        :color="
                                            s.aprobacionEstado === 'aprobado'
                                                ? 'success'
                                                : s.aprobacionEstado ===
                                                    'rechazado'
                                                  ? 'error'
                                                  : 'warning'
                                        "
                                        variant="subtle"
                                        >{{
                                            s.aprobacionEstado.replaceAll(
                                                "_",
                                                " ",
                                            )
                                        }}</UBadge
                                    ><UBadge
                                        :color="
                                            s.estado === 'completado'
                                                ? 'success'
                                                : s.estado === 'cancelado'
                                                  ? 'neutral'
                                                  : s.estado === 'en_proceso'
                                                    ? 'warning'
                                                    : 'primary'
                                        "
                                        variant="subtle"
                                        >{{
                                            s.estado.replaceAll("_", " ")
                                        }}</UBadge
                                    >
                                </div>
                                <p
                                    v-if="capacidades.verFinanzas"
                                    class="text-sm text-muted"
                                >
                                    $ {{ dinero(s.precio) }}
                                </p>
                                <p
                                    v-if="s.observaciones"
                                    class="mt-1 text-xs text-muted"
                                >
                                    Motivo/notas: {{ s.observaciones }}
                                </p>
                                <p
                                    v-if="s.trabajoRealizado"
                                    class="mt-1 text-sm"
                                >
                                    <strong>Trabajo realizado:</strong>
                                    {{ s.trabajoRealizado }}
                                </p>
                                <p
                                    v-if="s.tiempoTrabajadoMinutos"
                                    class="mt-1 text-xs text-muted"
                                >
                                    Tiempo empleado:
                                    {{ s.tiempoTrabajadoMinutos }} min
                                </p>
                                <p
                                    v-if="s.resultadoPrueba"
                                    class="mt-1 text-sm"
                                >
                                    <strong>Resultado de prueba:</strong>
                                    {{ s.resultadoPrueba }}
                                </p>
                                <p
                                    v-if="s.recomendacionesCliente"
                                    class="mt-1 text-sm"
                                >
                                    <strong>Recomendación:</strong>
                                    {{ s.recomendacionesCliente }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    capacidades.aprobarServicios &&
                                    s.aprobacionEstado ===
                                        'pendiente_aprobacion'
                                "
                                class="flex gap-1"
                            >
                                <UButton
                                    size="xs"
                                    color="success"
                                    label="Aprobar"
                                    @click="decidirServicio(s, 'aprobado')"
                                />
                                <UButton
                                    size="xs"
                                    color="error"
                                    variant="soft"
                                    label="Rechazar"
                                    @click="decidirServicio(s, 'rechazado')"
                                />
                            </div>
                            <div
                                v-if="
                                    capacidades.trabajo &&
                                    [
                                        'en_diagnostico',
                                        'en_reparacion',
                                        'en_prueba',
                                    ].includes(orden.estado) &&
                                    s.aprobacionEstado === 'aprobado' &&
                                    !['completado', 'cancelado'].includes(
                                        s.estado,
                                    )
                                "
                                class="flex flex-wrap gap-1"
                            >
                                <UButton
                                    v-if="s.estado === 'pendiente'"
                                    size="xs"
                                    color="neutral"
                                    variant="outline"
                                    label="Iniciar"
                                    @click="estadoServicio(s, 'en_proceso')"
                                /><UButton
                                    size="xs"
                                    color="success"
                                    variant="soft"
                                    label="Completar"
                                    @click="estadoServicio(s, 'completado')"
                                /><UButton
                                    size="xs"
                                    color="error"
                                    variant="ghost"
                                    label="Cancelar"
                                    @click="estadoServicio(s, 'cancelado')"
                                />
                            </div>
                        </div>
                        <p
                            v-if="!orden.servicios.length"
                            class="py-4 text-center text-muted"
                        >
                            No hay servicios definidos todavía.
                        </p></UCard
                    >

                    <UCard v-if="!esCliente"
                        ><template #header
                            ><div>
                                <h2 class="font-semibold">
                                    Repuestos solicitados, recomendados o
                                    requeridos
                                </h2>
                                <p class="text-sm text-muted">
                                    Esta lista no mueve inventario. Solo
                                    “Utilizar” registra una salida real.
                                </p>
                            </div></template
                        >
                        <form
                            v-if="
                                capacidades.trabajo &&
                                [
                                    'en_diagnostico',
                                    'esperando_aprobacion',
                                    'esperando_repuestos',
                                    'en_reparacion',
                                ].includes(orden.estado)
                            "
                            class="mb-4 grid gap-3 rounded-lg bg-elevated/50 p-4 sm:grid-cols-2"
                            @submit.prevent="agregarRequerimiento"
                        >
                            <UFormField label="Fuente de suministro" required
                                ><USelect
                                    v-model="nuevoRepuesto.fuenteSuministro"
                                    :items="[
                                        {
                                            label: 'Inventario interno',
                                            value: 'inventario',
                                        },
                                        {
                                            label: 'Compra externa',
                                            value: 'externo',
                                        },
                                        {
                                            label: 'Suministrado por el cliente',
                                            value: 'cliente',
                                        },
                                    ]"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                v-if="
                                    nuevoRepuesto.fuenteSuministro ===
                                    'inventario'
                                "
                                label="Repuesto del inventario"
                                required
                                ><USelect
                                    :model-value="nuevoRepuesto.repuestoId"
                                    :items="
                                        repuestosCatalogo.map((r) => ({
                                            label: `${r.label} · stock ${r.stock} ${r.unidad}${capacidades.verFinanzas ? ` · $ ${dinero(r.precio)}` : ''}`,
                                            value: r.id,
                                        }))
                                    "
                                    class="w-full"
                                    @update:model-value="
                                        seleccionarRepuesto(
                                            String($event || ''),
                                        )
                                    " /></UFormField
                            ><UFormField v-else label="Unidad" required
                                ><UInput
                                    v-model="nuevoRepuesto.unidad"
                                    class="w-full"
                                    placeholder="unidad, litro, juego..." /></UFormField
                            ><UFormField label="Descripción" required
                                ><UTextarea
                                    v-model="nuevoRepuesto.descripcion"
                                    :rows="3"
                                    class="w-full" /></UFormField
                            ><UFormField label="Cantidad" required
                                ><UInput
                                    v-model="nuevoRepuesto.cantidad"
                                    type="number"
                                    min="0.001"
                                    step="0.001"
                                    class="w-full" /></UFormField
                            ><UFormField label="Motivo técnico" required
                                ><UTextarea
                                    v-model="nuevoRepuesto.motivo"
                                    :rows="3"
                                    class="w-full"
                            /></UFormField>
                            <UFormField label="Prioridad" required
                                ><USelect
                                    v-model="nuevoRepuesto.prioridad"
                                    :items="[
                                        { label: 'Baja', value: 'baja' },
                                        { label: 'Media', value: 'media' },
                                        { label: 'Alta', value: 'alta' },
                                        { label: 'Crítica', value: 'critica' },
                                    ]"
                                    class="w-full"
                            /></UFormField>
                            <UFormField label="Clasificación"
                                ><UCheckbox
                                    v-model="nuevoRepuesto.obligatorio"
                                    label="Obligatorio para completar el trabajo"
                            /></UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton
                                    type="submit"
                                    label="Agregar requerimiento"
                                />
                            </div>
                        </form>
                        <div
                            v-for="r in repuestosRequeridos"
                            :key="r.id"
                            class="flex flex-col gap-3 border-b border-default py-3 sm:flex-row sm:items-center sm:justify-between"
                            :class="
                                ['cancelado', 'no_utilizado'].includes(r.estado)
                                    ? 'opacity-50'
                                    : ''
                            "
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong>{{ r.descripcion }}</strong
                                    ><UBadge variant="outline">{{
                                        r.origen
                                    }}</UBadge
                                    ><UBadge variant="outline">{{
                                        r.fuenteSuministro.replaceAll("_", " ")
                                    }}</UBadge
                                    ><UBadge
                                        :color="
                                            r.prioridad === 'critica'
                                                ? 'error'
                                                : r.prioridad === 'alta'
                                                  ? 'warning'
                                                  : 'neutral'
                                        "
                                        >{{ r.prioridad }}</UBadge
                                    ><UBadge
                                        :color="
                                            r.obligatorio
                                                ? 'primary'
                                                : 'neutral'
                                        "
                                        >{{
                                            r.obligatorio
                                                ? "Obligatorio"
                                                : "Opcional"
                                        }}</UBadge
                                    ><UBadge
                                        :color="
                                            [
                                                'cancelado',
                                                'no_utilizado',
                                            ].includes(r.estado)
                                                ? 'neutral'
                                                : r.disponible
                                                  ? 'success'
                                                  : 'warning'
                                        "
                                        >{{ r.estado }}</UBadge
                                    >
                                </div>
                                <p class="text-sm">
                                    Requerido {{ r.cantidad }}
                                    {{ r.unidad || "" }} · utilizado
                                    {{ r.cantidadUsada }} · restante
                                    {{ r.cantidadRestante }}
                                    <template
                                        v-if="
                                            r.fuenteSuministro === 'inventario'
                                        "
                                        >· stock
                                        {{
                                            r.stock ?? "sin correspondencia"
                                        }}</template
                                    >
                                    <span v-if="r.precio"
                                        >· $ {{ dinero(r.precio) }} c/u</span
                                    >
                                </p>
                                <p v-if="r.motivo" class="text-xs text-muted">
                                    {{ r.motivo }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    ![
                                        'cancelado',
                                        'no_utilizado',
                                        'utilizado',
                                    ].includes(r.estado)
                                "
                                class="flex flex-wrap gap-1"
                            >
                                <UButton
                                    v-if="
                                        capacidades.aprobarRepuestos &&
                                        r.estado === 'pendiente_aprobacion'
                                    "
                                    size="xs"
                                    color="success"
                                    label="Aprobar"
                                    @click="decidirRequerimiento(r, 'aprobado')"
                                />
                                <UButton
                                    v-if="
                                        capacidades.aprobarRepuestos &&
                                        r.estado === 'pendiente_aprobacion'
                                    "
                                    size="xs"
                                    color="warning"
                                    variant="soft"
                                    label="No disponible"
                                    @click="
                                        decidirRequerimiento(r, 'no_disponible')
                                    "
                                />
                                <UButton
                                    v-if="
                                        capacidades.trabajo &&
                                        r.cantidadUsada === '0.000' &&
                                        [
                                            'pendiente_aprobacion',
                                            'aprobado',
                                            'no_disponible',
                                        ].includes(r.estado)
                                    "
                                    size="xs"
                                    color="neutral"
                                    variant="outline"
                                    label="Editar"
                                    @click="editarRequerimiento(r)"
                                />
                                <UButton
                                    v-if="
                                        capacidades.consumir &&
                                        r.estado === 'aprobado' &&
                                        r.disponible &&
                                        r.cumplimiento !== 'completo' &&
                                        [
                                            'en_diagnostico',
                                            'esperando_repuestos',
                                            'en_reparacion',
                                        ].includes(orden.estado)
                                    "
                                    size="xs"
                                    color="success"
                                    variant="soft"
                                    label="Utilizar"
                                    @click="prepararUso(r)"
                                /><UButton
                                    v-if="
                                        capacidades.trabajo &&
                                        r.estado === 'aprobado' &&
                                        r.cantidadUsada === '0.000'
                                    "
                                    size="xs"
                                    color="neutral"
                                    variant="soft"
                                    label="No utilizado"
                                    @click="
                                        decidirRequerimiento(r, 'no_utilizado')
                                    "
                                /><UButton
                                    v-if="
                                        capacidades.trabajo &&
                                        !['cancelado', 'no_utilizado'].includes(
                                            r.estado,
                                        )
                                    "
                                    size="xs"
                                    color="error"
                                    variant="ghost"
                                    label="Retirar"
                                    @click="retirarRequerimiento(r)"
                                />
                            </div>
                        </div>
                        <p
                            v-if="!repuestosRequeridos.length"
                            class="py-4 text-center text-muted"
                        >
                            No hay repuestos solicitados ni recomendados; son
                            opcionales.
                        </p></UCard
                    >

                    <UCard>
                        <template #header
                            ><div class="flex items-center justify-between">
                                <h2 class="font-semibold">
                                    Repuestos utilizados
                                </h2>
                                <p
                                    v-if="capacidades.verFinanzas"
                                    class="text-sm font-medium"
                                >
                                    Total $ {{ dinero(totalRepuestos) }}
                                </p>
                            </div></template
                        >
                        <form
                            v-if="
                                capacidades.consumir &&
                                consumo.requerimientoId &&
                                [
                                    'en_diagnostico',
                                    'esperando_repuestos',
                                    'en_reparacion',
                                ].includes(orden.estado)
                            "
                            class="mb-5 grid gap-4 rounded-lg bg-elevated/50 p-4 sm:grid-cols-2"
                            @submit.prevent="usarRepuesto"
                        >
                            <UAlert
                                v-if="consumo.requerimientoId"
                                class="sm:col-span-2"
                                color="success"
                                variant="subtle"
                                title="Uso real vinculado a un requerimiento aprobado"
                                :description="
                                    requerimientoConsumo?.fuenteSuministro ===
                                    'inventario'
                                        ? 'Al confirmar se descontará físicamente del inventario.'
                                        : 'Se registrará como utilizado sin modificar el inventario interno.'
                                "
                            />
                            <UFormField
                                v-if="
                                    requerimientoConsumo?.fuenteSuministro ===
                                    'inventario'
                                "
                                label="Repuesto"
                                required
                                :error="errors.repuestoId"
                                ><USelect
                                    v-model="consumo.repuestoId"
                                    :items="repuestos"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                label="Cantidad"
                                required
                                :error="errors.cantidad"
                                ><UInput
                                    v-model="consumo.cantidad"
                                    type="number"
                                    min="0.001"
                                    step="0.001"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                class="sm:col-span-2"
                                label="Observaciones"
                                ><UTextarea
                                    v-model="consumo.observaciones"
                                    :rows="3"
                                    class="w-full"
                            /></UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton
                                    type="submit"
                                    label="Descontar del inventario"
                                    :loading="procesando"
                                />
                            </div>
                        </form>
                        <div
                            v-for="u in repuestosUsados"
                            :key="u.id"
                            class="flex flex-col gap-2 border-b border-default py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                            :class="u.revertido ? 'opacity-55' : ''"
                        >
                            <div>
                                <p
                                    class="font-medium"
                                    :class="u.revertido ? 'line-through' : ''"
                                >
                                    {{ u.codigo }} · {{ u.nombre }}
                                    <UBadge class="ml-2" variant="outline">{{
                                        u.fuenteSuministro.replaceAll("_", " ")
                                    }}</UBadge>
                                </p>
                                <p class="text-sm text-muted">
                                    {{ u.cantidad }} {{ u.unidad }}
                                    <template v-if="capacidades.verFinanzas">
                                        · $
                                        {{ dinero(u.precioUnitario) }}
                                        c/u</template
                                    >
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <UBadge v-if="u.revertido" color="neutral"
                                    >Revertido</UBadge
                                ><UButton
                                    v-else-if="capacidades.revertirConsumo"
                                    size="xs"
                                    color="neutral"
                                    variant="ghost"
                                    label="Revertir"
                                    @click="revertir(u)"
                                />
                            </div>
                        </div>
                        <p
                            v-if="!repuestosUsados.length"
                            class="py-4 text-center text-muted"
                        >
                            Aún no se han utilizado repuestos.
                        </p>
                    </UCard>

                    <UCard v-if="finanzas">
                        <template #header
                            ><div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Estado financiero
                                    </h2>
                                    <p class="text-sm text-muted">
                                        Valores expresados en USD
                                    </p>
                                </div>
                                <UBadge
                                    :color="
                                        finanzas.estado === 'pagado'
                                            ? 'success'
                                            : finanzas.estado === 'parcial'
                                              ? 'warning'
                                              : 'neutral'
                                    "
                                    >{{ finanzas.estado }}</UBadge
                                >
                            </div></template
                        >
                        <div class="grid gap-3 sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-muted">Servicios</p>
                                <p class="font-medium">
                                    $ {{ dinero(finanzas.servicios) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Repuestos</p>
                                <p class="font-medium">
                                    $ {{ dinero(finanzas.repuestos) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Pagado</p>
                                <p class="font-medium text-success">
                                    $ {{ dinero(finanzas.pagado) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Saldo</p>
                                <p class="text-xl font-semibold">
                                    $ {{ dinero(finanzas.saldo) }}
                                </p>
                            </div>
                            <div v-if="Number(finanzas.descuento) > 0">
                                <p class="text-xs text-muted">
                                    Descuento facturado
                                </p>
                                <p class="font-medium">
                                    -$ {{ dinero(finanzas.descuento) }}
                                </p>
                            </div>
                            <div v-if="Number(finanzas.impuesto) > 0">
                                <p class="text-xs text-muted">
                                    Impuesto facturado
                                </p>
                                <p class="font-medium">
                                    $ {{ dinero(finanzas.impuesto) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Total vigente</p>
                                <p class="font-semibold">
                                    $ {{ dinero(finanzas.total) }}
                                </p>
                            </div>
                        </div>
                        <form
                            v-if="
                                can('pagos.registrar') &&
                                factura &&
                                orden.estado !== 'cancelada' &&
                                Number(finanzas.saldo) > 0
                            "
                            class="mt-5 grid gap-4 rounded-lg bg-elevated/50 p-4 sm:grid-cols-2"
                            @submit.prevent="registrarPago"
                        >
                            <UFormField
                                label="Monto"
                                required
                                :error="errors.monto"
                                ><UInput
                                    v-model="pago.monto"
                                    type="number"
                                    min="0.01"
                                    :max="finanzas.saldo"
                                    step="0.01"
                                    class="w-full" /></UFormField
                            ><UFormField label="Método" required
                                ><USelect
                                    v-model="pago.metodo"
                                    :items="[
                                        {
                                            label: 'Efectivo',
                                            value: 'efectivo',
                                        },
                                        { label: 'Tarjeta', value: 'tarjeta' },
                                        {
                                            label: 'Transferencia',
                                            value: 'transferencia',
                                        },
                                        { label: 'Otro', value: 'otro' },
                                    ]"
                                    class="w-full" /></UFormField
                            ><UFormField label="Fecha del pago" required :error="errors.pagadoEn"
                                ><UInput
                                    v-model="pago.pagadoEn"
                                    type="datetime-local"
                                    :max="fechaLocal()"
                                    class="w-full" /></UFormField
                            ><UFormField label="Referencia" :required="['tarjeta', 'transferencia'].includes(pago.metodo)" :error="errors.referencia"
                                ><UInput
                                    v-model="pago.referencia"
                                    class="w-full" /></UFormField
                            ><UFormField
                                class="sm:col-span-2"
                                label="Observaciones"
                                ><UTextarea
                                    v-model="pago.observaciones"
                                    :rows="3"
                                    class="w-full"
                            /></UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton
                                    type="submit"
                                    label="Registrar pago"
                                    :loading="procesando"
                                />
                            </div>
                        </form>
                        <div class="mt-5">
                            <h3 class="mb-2 text-sm font-semibold">
                                Historial de pagos
                            </h3>
                            <div
                                v-for="item in pagos"
                                :key="item.id"
                                class="flex flex-col gap-2 border-b border-default py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                                :class="
                                    item.estado !== 'registrado'
                                        ? 'opacity-55'
                                        : ''
                                "
                            >
                                <div>
                                    <p
                                        class="font-medium"
                                        :class="
                                            item.estado !== 'registrado'
                                                ? 'line-through'
                                                : ''
                                        "
                                    >
                                        {{ item.numero }} · $
                                        {{ dinero(item.monto) }}
                                    </p>
                                    <p class="text-xs text-muted">
                                        {{ item.metodo }} ·
                                        {{
                                            new Date(
                                                item.pagadoEn,
                                            ).toLocaleString("es-EC")
                                        }}
                                        · {{ item.comprobante }}
                                    </p>
                                    <p
                                        v-if="item.referencia"
                                        class="text-xs text-muted"
                                    >
                                        Ref. {{ item.referencia }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <UBadge
                                        :color="
                                            item.estado === 'registrado'
                                                ? 'success'
                                                : 'neutral'
                                        "
                                        >{{ item.estado }}</UBadge
                                    ><UButton
                                        v-if="
                                            can('pagos.anular') &&
                                            item.estado === 'registrado'
                                        "
                                        size="xs"
                                        color="error"
                                        variant="ghost"
                                        label="Anular"
                                        @click="anularPago(item)"
                                    /><UButton
                                        v-if="
                                            can('pagos.reembolsar') &&
                                            item.estado === 'registrado'
                                        "
                                        size="xs"
                                        color="warning"
                                        variant="ghost"
                                        label="Reembolsar"
                                        @click="reembolsarPago(item)"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="!pagos.length"
                                class="py-3 text-center text-muted"
                            >
                                Sin pagos registrados.
                            </p>
                        </div>
                    </UCard>

                    <UCard
                        v-if="
                            canAny(['facturas.ver', 'facturas.crear']) &&
                            [
                                'finalizada',
                                'lista_entrega',
                                'entregada',
                            ].includes(orden.estado)
                        "
                    >
                        <template #header
                            ><h2 class="font-semibold">
                                Facturación definitiva
                            </h2></template
                        >
                        <div
                            v-if="factura"
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="text-lg font-semibold">
                                    {{ factura.numero }}
                                </p>
                                <p class="text-sm text-muted">
                                    Emitida
                                    {{
                                        new Date(
                                            factura.emitidaEn,
                                        ).toLocaleString("es-EC")
                                    }}
                                    · Total $ {{ dinero(factura.total) }} USD
                                </p>
                            </div>
                            <Link
                                v-if="can('facturas.ver')"
                                :href="route('facturacion.show', factura.id)"
                                ><UButton
                                    label="Ver factura"
                                    icon="i-lucide-receipt-text"
                            /></Link>
                        </div>
                        <form
                            v-else-if="can('facturas.crear')"
                            class="grid gap-4 sm:grid-cols-2"
                            @submit.prevent="emitirFactura"
                        >
                            <UFormField
                                label="Descuento"
                                required
                                :error="errors.descuento"
                                ><UInput
                                    v-model="facturacion.descuento"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full"
                                    :disabled="
                                        !can('descuentos.autorizar')
                                    " /></UFormField
                            ><UFormField
                                label="Impuesto (%)"
                                required
                                :error="errors.tasaImpuesto"
                                ><UInput
                                    v-model="facturacion.tasaImpuesto"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="w-full"
                                    disabled /></UFormField
                            ><UFormField
                                v-if="
                                    can('descuentos.autorizar') &&
                                    Number(facturacion.descuento) > 0
                                "
                                label="Motivo del descuento"
                                required
                                :error="errors.motivoDescuento"
                                class="sm:col-span-2"
                                ><UTextarea
                                    v-model="facturacion.motivoDescuento"
                                    :rows="3"
                                    class="w-full"
                                    placeholder="Justificación de la autorización" /></UFormField
                            ><UFormField label="Fecha de vencimiento"
                                ><UInput
                                    v-model="facturacion.venceEn"
                                    type="date"
                                    class="w-full" /></UFormField
                            ><UFormField label="Observaciones"
                                ><UTextarea
                                    v-model="facturacion.observaciones"
                                    :rows="3"
                                    class="w-full"
                            /></UFormField>
                            <div class="sm:col-span-2">
                                <UAlert
                                    color="warning"
                                    variant="subtle"
                                    :title="
                                        can('descuentos.autorizar')
                                            ? 'La factura congela los conceptos y valores actuales de la orden.'
                                            : 'La factura se emitirá sin descuento; solo Administración puede autorizar descuentos.'
                                    "
                                />
                                <div class="mt-3 text-right">
                                    <UButton
                                        type="submit"
                                        label="Emitir factura"
                                        icon="i-lucide-file-check"
                                        :loading="procesando"
                                    />
                                </div>
                            </div>
                        </form>
                    </UCard>

                    <UCard v-if="orden.cierreTecnico">
                        <template #header>
                            <div>
                                <h2 class="font-semibold">Cierre técnico</h2>
                                <p class="text-sm text-muted">
                                    Evidencias operativas requeridas antes de
                                    finalizar la orden.
                                </p>
                            </div>
                        </template>
                        <form
                            v-if="capacidades.cerrarTecnico"
                            class="grid gap-4 sm:grid-cols-2"
                            @submit.prevent="guardarCierreTecnico"
                        >
                            <UFormField
                                label="Tiempo trabajado (minutos)"
                                required
                                :error="errors.tiempoTrabajadoMinutos"
                            >
                                <UInput
                                    v-model.number="
                                        cierreTecnico.tiempoTrabajadoMinutos
                                    "
                                    type="number"
                                    min="0"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                label="Control de calidad"
                                required
                                :error="errors.controlCalidadEstado"
                            >
                                <USelect
                                    v-model="cierreTecnico.controlCalidadEstado"
                                    :items="[
                                        {
                                            label: 'Pendiente',
                                            value: 'pendiente',
                                        },
                                        {
                                            label: 'Aprobado',
                                            value: 'aprobado',
                                        },
                                        {
                                            label: 'Rechazado',
                                            value: 'rechazado',
                                        },
                                    ]"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField label="Notas de control de calidad">
                                <UTextarea
                                    v-model="cierreTecnico.controlCalidadNotas"
                                    :rows="3"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                label="Prueba de ruta"
                                required
                                :error="errors.pruebaRutaEstado"
                            >
                                <USelect
                                    v-model="cierreTecnico.pruebaRutaEstado"
                                    :items="[
                                        {
                                            label: 'Pendiente',
                                            value: 'pendiente',
                                        },
                                        {
                                            label: 'Aprobada',
                                            value: 'aprobada',
                                        },
                                        {
                                            label: 'Con observaciones',
                                            value: 'con_observaciones',
                                        },
                                        {
                                            label: 'No aplica',
                                            value: 'no_aplica',
                                        },
                                    ]"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField label="Observaciones de prueba de ruta">
                                <UTextarea
                                    v-model="cierreTecnico.pruebaRutaNotas"
                                    :rows="3"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                label="Bloqueos técnicos activos"
                                hint="Déjalo vacío cuando todos estén resueltos"
                                :error="errors.bloqueosTecnicos"
                            >
                                <UTextarea
                                    v-model="cierreTecnico.bloqueosTecnicos"
                                    :rows="3"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField label="Próximo mantenimiento sugerido"
                                ><UInput
                                    v-model="
                                        cierreTecnico.proximoMantenimientoEn
                                    "
                                    type="date"
                                    class="w-full"
                            /></UFormField>
                            <UFormField label="Observaciones de entrega"
                                ><UTextarea
                                    v-model="cierreTecnico.observacionesEntrega"
                                    :rows="3"
                                    class="w-full"
                            /></UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton
                                    type="submit"
                                    label="Guardar cierre técnico"
                                    icon="i-lucide-clipboard-check"
                                    :loading="procesando"
                                />
                            </div>
                        </form>
                        <div v-else class="grid gap-3 text-sm sm:grid-cols-3">
                            <p>
                                <strong>Tiempo:</strong>
                                {{ orden.cierreTecnico.tiempoTrabajadoMinutos }}
                                min
                            </p>
                            <p>
                                <strong>Calidad:</strong>
                                {{ orden.cierreTecnico.controlCalidadEstado }}
                            </p>
                            <p>
                                <strong>Prueba de ruta:</strong>
                                {{
                                    orden.cierreTecnico.pruebaRutaEstado.replaceAll(
                                        "_",
                                        " ",
                                    )
                                }}
                            </p>
                            <p
                                v-if="orden.cierreTecnico.bloqueosTecnicos"
                                class="sm:col-span-3 text-error"
                            >
                                <strong>Bloqueos:</strong>
                                {{ orden.cierreTecnico.bloqueosTecnicos }}
                            </p>
                        </div>
                    </UCard>

                    <UCard
                        ><template #header
                            ><h2 class="font-semibold">
                                Diagnóstico técnico
                            </h2></template
                        >
                        <form
                            v-if="
                                capacidades.diagnosticar &&
                                orden.estado !== 'cancelada'
                            "
                            class="space-y-4"
                            @submit.prevent="diagnosticar('confirmado')"
                        >
                            <UAlert
                                color="primary"
                                variant="subtle"
                                title="Corrección con trazabilidad"
                                :description="
                                    diagnosticoEditable
                                        ? `Estás preparando la versión ${Math.max(...orden.diagnosticos.map((item) => item.version)) + 1}; las versiones actuales se conservarán en el historial.`
                                        : 'Este será el primer diagnóstico técnico de la orden.'
                                "
                            />
                            <UFormField
                                label="Diagnóstico técnico interno"
                                required
                                :error="errors.diagnostico"
                                ><UTextarea
                                    v-model="diag.diagnostico"
                                    class="w-full"
                                    :rows="5"
                            /></UFormField>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <UFormField label="Causa probable o confirmada"
                                    ><UTextarea
                                        v-model="diag.causa"
                                        class="w-full"
                                        :rows="3"
                                /></UFormField>
                                <UFormField label="Componentes afectados"
                                    ><UTextarea
                                        v-model="diag.componentesAfectados"
                                        class="w-full"
                                        :rows="3"
                                /></UFormField>
                                <UFormField label="Nivel de severidad" required
                                    ><USelect
                                        v-model="diag.severidad"
                                        :items="[
                                            { label: 'Baja', value: 'baja' },
                                            { label: 'Media', value: 'media' },
                                            { label: 'Alta', value: 'alta' },
                                            {
                                                label: 'Crítica',
                                                value: 'critica',
                                            },
                                        ]"
                                        class="w-full"
                                /></UFormField>
                                <UFormField label="¿Puede circular?" required
                                    ><USelect
                                        v-model="diag.puedeCircular"
                                        :items="[
                                            { label: 'Sí', value: 'si' },
                                            {
                                                label: 'Con precaución',
                                                value: 'con_precaucion',
                                            },
                                            { label: 'No', value: 'no' },
                                        ]"
                                        class="w-full"
                                /></UFormField>
                            </div>
                            <UFormField
                                label="Resumen para el cliente"
                                hint="Explica hallazgos y próximos pasos sin incluir notas internas."
                                required
                                :error="errors.resumenCliente"
                                ><UTextarea
                                    v-model="diag.resumenCliente"
                                    class="w-full"
                                    :rows="4" /></UFormField
                            ><UFormField label="Pruebas realizadas"
                                ><UTextarea
                                    v-model="diag.pruebasRealizadas"
                                    class="w-full" /></UFormField
                            ><UFormField label="Recomendaciones"
                                ><UTextarea
                                    v-model="diag.recomendaciones"
                                    class="w-full"
                            /></UFormField>
                            <UFormField label="Observaciones técnicas"
                                ><UTextarea
                                    v-model="diag.observacionesTecnicas"
                                    class="w-full"
                                    :rows="3"
                            /></UFormField>
                            <UFormField label="Indicaciones de seguridad"
                                ><UTextarea
                                    v-model="diag.indicacionesSeguridad"
                                    class="w-full"
                                    :rows="3"
                            /></UFormField>
                            <UFormField label="Próximo mantenimiento sugerido"
                                ><UInput
                                    v-model="diag.proximoMantenimientoEn"
                                    type="date"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                label="Notas internas"
                                hint="No son visibles para el cliente"
                            >
                                <UTextarea
                                    v-model="diag.notasInternas"
                                    class="w-full"
                                    :rows="3"
                                />
                            </UFormField>
                            <UFormField
                                v-if="
                                    [
                                        'finalizada',
                                        'lista_entrega',
                                        'entregada',
                                    ].includes(orden.estado)
                                "
                                label="Motivo de corrección posterior al cierre"
                                required
                                :error="errors.motivoCorreccion"
                                ><UTextarea
                                    v-model="diag.motivoCorreccion"
                                    class="w-full"
                                    :rows="3"
                            /></UFormField>
                            <div class="flex flex-wrap justify-end gap-2">
                                <UButton
                                    type="button"
                                    color="neutral"
                                    variant="outline"
                                    label="Guardar borrador"
                                    :loading="procesando"
                                    @click="diagnosticar('borrador')"
                                />
                                <UButton
                                    type="submit"
                                    :label="
                                        diagnosticoEditable
                                            ? 'Confirmar nueva versión'
                                            : 'Registrar diagnóstico'
                                    "
                                    :loading="procesando"
                                />
                            </div>
                        </form>
                        <div
                            v-for="d in orden.diagnosticos"
                            :key="d.id"
                            class="mt-4 rounded-lg border border-default p-4"
                        >
                            <div class="flex flex-wrap justify-between gap-2">
                                <p class="font-medium">
                                    Versión {{ d.version }} · {{ d.estado }}
                                </p>
                                <UBadge
                                    :color="d.vigente ? 'success' : 'neutral'"
                                    >{{
                                        d.vigente
                                            ? d.estado === "borrador"
                                                ? "Borrador actual"
                                                : "Publicado actual"
                                            : "Anterior"
                                    }}</UBadge
                                >
                            </div>
                            <p class="mt-3 whitespace-pre-wrap">
                                {{ d.diagnostico }}
                            </p>
                            <div class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                                <p>
                                    <strong>Severidad:</strong>
                                    {{ d.severidad }}
                                </p>
                                <p>
                                    <strong>Circulación:</strong>
                                    {{ d.puedeCircular.replaceAll("_", " ") }}
                                </p>
                                <p>
                                    <strong>Autor:</strong>
                                    {{ d.autor || "Taller" }}
                                </p>
                            </div>
                            <p v-if="d.causa" class="mt-2 text-sm">
                                <strong>Causa:</strong> {{ d.causa }}
                            </p>
                            <p
                                v-if="d.componentesAfectados"
                                class="mt-2 text-sm"
                            >
                                <strong>Componentes:</strong>
                                {{ d.componentesAfectados }}
                            </p>
                            <div
                                v-if="!esCliente && d.resumenCliente"
                                class="mt-3 rounded-md bg-elevated/50 p-3 text-sm"
                            >
                                <strong
                                    >Resumen visible para el cliente:</strong
                                >
                                <p class="mt-1 whitespace-pre-wrap">
                                    {{ d.resumenCliente }}
                                </p>
                            </div>
                            <p v-if="d.pruebasRealizadas" class="mt-2 text-sm">
                                <strong>Pruebas:</strong>
                                {{ d.pruebasRealizadas }}
                            </p>
                            <p v-if="d.recomendaciones" class="mt-2 text-sm">
                                <strong>Recomendaciones:</strong>
                                {{ d.recomendaciones }}
                            </p>
                            <p v-if="d.notasInternas" class="mt-2 text-sm">
                                <strong>Notas internas:</strong>
                                {{ d.notasInternas }}
                            </p>
                            <p
                                v-if="d.indicacionesSeguridad"
                                class="mt-2 text-sm"
                            >
                                <strong>Seguridad:</strong>
                                {{ d.indicacionesSeguridad }}
                            </p>
                            <p
                                v-if="d.motivoCorreccion"
                                class="mt-2 text-sm text-warning"
                            >
                                <strong>Motivo de corrección:</strong>
                                {{ d.motivoCorreccion }}
                            </p>
                            <p class="mt-2 text-xs text-muted">
                                {{
                                    new Date(d.createdAt).toLocaleString(
                                        "es-EC",
                                    )
                                }}
                            </p>
                        </div>
                        <p
                            v-if="!orden.diagnosticos.length"
                            class="mt-4 text-center text-muted"
                        >
                            Sin diagnósticos registrados.
                        </p></UCard
                    >
                </div>

                <aside
                    class="order-1 space-y-6 xl:order-2 xl:sticky xl:top-4 xl:self-start"
                >
                    <UCard
                        v-if="
                            capacidades.asignar ||
                            capacidades.avanzarEstado ||
                            capacidades.entregar ||
                            capacidades.cancelar
                        "
                        ><template #header
                            ><h2 class="font-semibold">
                                Cambiar estado
                            </h2></template
                        >
                        <div class="grid gap-2">
                            <UButton
                                v-if="
                                    capacidades.asignar &&
                                    orden.estado === 'pendiente'
                                "
                                label="Confirmar asignación"
                                @click="estado('asignada')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    orden.estado === 'asignada'
                                "
                                label="Iniciar diagnóstico"
                                @click="estado('en_diagnostico')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    orden.estado === 'esperando_aprobacion'
                                "
                                label="Volver a diagnóstico"
                                color="neutral"
                                variant="soft"
                                @click="estado('en_diagnostico')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    [
                                        'en_diagnostico',
                                        'esperando_aprobacion',
                                    ].includes(orden.estado)
                                "
                                label="Iniciar reparación"
                                @click="estado('en_reparacion')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    [
                                        'en_diagnostico',
                                        'en_reparacion',
                                    ].includes(orden.estado)
                                "
                                label="Esperando aprobación"
                                color="warning"
                                variant="soft"
                                @click="estado('esperando_aprobacion')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    [
                                        'en_diagnostico',
                                        'esperando_aprobacion',
                                        'en_reparacion',
                                    ].includes(orden.estado)
                                "
                                label="Esperando repuestos"
                                color="warning"
                                variant="soft"
                                @click="estado('esperando_repuestos')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    orden.estado === 'esperando_repuestos'
                                "
                                label="Retomar reparación"
                                @click="estado('en_reparacion')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    orden.estado === 'en_reparacion'
                                "
                                label="Enviar a prueba final"
                                @click="estado('en_prueba')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    orden.estado === 'en_prueba'
                                "
                                label="Devolver a reparación"
                                color="warning"
                                variant="soft"
                                @click="estado('en_reparacion')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    orden.estado === 'en_prueba'
                                "
                                label="Finalizar técnicamente"
                                color="success"
                                @click="estado('finalizada')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    [
                                        'asignada',
                                        'en_diagnostico',
                                        'esperando_aprobacion',
                                        'esperando_repuestos',
                                        'en_reparacion',
                                        'en_prueba',
                                    ].includes(orden.estado)
                                "
                                label="Pausar trabajo"
                                color="neutral"
                                variant="outline"
                                @click="estado('pausada')"
                            /><UButton
                                v-if="
                                    capacidades.avanzarEstado &&
                                    orden.estado === 'pausada' &&
                                    orden.estadoAnteriorPausa
                                "
                                label="Reanudar trabajo"
                                @click="estado(orden.estadoAnteriorPausa)"
                            /><UButton
                                v-if="
                                    capacidades.entregar &&
                                    orden.estado === 'finalizada'
                                "
                                label="Lista para entrega"
                                @click="estado('lista_entrega')"
                            /><UButton
                                v-if="
                                    capacidades.entregar &&
                                    orden.estado === 'lista_entrega'
                                "
                                label="Registrar entrega"
                                color="success"
                                @click="estado('entregada')"
                            /><UButton
                                v-if="
                                    capacidades.cancelar &&
                                    ![
                                        'entregada',
                                        'cancelada',
                                        'finalizada',
                                        'lista_entrega',
                                    ].includes(orden.estado)
                                "
                                color="error"
                                variant="soft"
                                label="Cancelar orden"
                                @click="estado('cancelada')"
                            /></div
                    ></UCard>
                    <UCard v-if="historialEstados.length">
                        <template #header
                            ><h2 class="font-semibold">
                                Historial de estados
                            </h2></template
                        >
                        <ol class="space-y-3 border-l border-default pl-4">
                            <li
                                v-for="item in historialEstados"
                                :key="`${item.createdAt}-${item.estadoNuevo}`"
                                class="relative text-sm before:absolute before:-left-[21px] before:top-1.5 before:h-2 before:w-2 before:rounded-full before:bg-primary"
                            >
                                <p class="font-medium">
                                    {{ item.estadoAnterior || "inicio" }} →
                                    {{ item.estadoNuevo }}
                                </p>
                                <p v-if="item.observaciones" class="text-muted">
                                    {{ item.observaciones }}
                                </p>
                                <p class="text-xs text-muted">
                                    {{
                                        new Date(item.createdAt).toLocaleString(
                                            "es-EC",
                                        )
                                    }}
                                    · {{ item.autor || "Taller" }}
                                </p>
                            </li>
                        </ol>
                    </UCard>
                    <UCard v-if="capacidades.asignar"
                        ><template #header
                            ><h2 class="font-semibold">
                                Mecánicos asignados
                            </h2></template
                        >
                        <div class="space-y-3">
                            <UCheckbox
                                v-for="m in mecanicos"
                                :key="m.value"
                                :model-value="asignacion.includes(m.value)"
                                :label="m.label"
                                @update:model-value="
                                    toggle(m.value, Boolean($event))
                                "
                            />
                        </div>
                        <template #footer
                            ><div class="text-right">
                                <UButton
                                    label="Guardar asignación"
                                    size="sm"
                                    @click="asignar"
                                /></div></template
                    ></UCard>
                    <UCard v-else
                        ><template #header
                            ><h2 class="font-semibold">Mecánicos</h2></template
                        >
                        <p v-for="m in orden.mecanicos" :key="m">{{ m }}</p>
                        <p v-if="!orden.mecanicos.length" class="text-muted">
                            Sin asignación.
                        </p></UCard
                    >
                </aside>
            </div>
        </template>
    </UDashboardPanel>
</template>
