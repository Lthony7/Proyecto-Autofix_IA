<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { usePermissions } from "../../composables/usePermissions";

interface ServicioOrden {
    id: string;
    nombre: string;
    precio: string;
    estado: string;
    observaciones?: string;
    origen?: string;
    trabajoRealizado?: string;
}
interface Orden {
    id: string;
    numero: string;
    cliente: string;
    vehiculo: string;
    estado: string;
    fallaReportada: string;
    kilometraje?: number;
    servicios: ServicioOrden[];
    mecanicoIds: string[];
    mecanicos: string[];
    diagnosticos: {
        id: string;
        version: number;
        diagnostico: string;
        pruebasRealizadas?: string;
        recomendaciones?: string;
        vigente: boolean;
        createdAt: string;
    }[];
}
interface Uso {
    id: string;
    codigo: string;
    nombre: string;
    unidad: string;
    cantidad: string;
    precioUnitario: string;
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
    visibilidad: string;
    estadoOrden: string;
    autor?: string;
    servicio?: string;
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
    finanzas: Finanzas | null;
    pagos: Pago[];
    factura: Factura | null;
    diagnosticoIa: DiagnosticoIa | null;
}>();
const { can, canAny } = usePermissions();
const errors = computed<Record<string, string>>(
    () => usePage().props.errors as Record<string, string>,
);
const asignacion = ref([...props.orden.mecanicoIds]);
const diag = reactive({
    diagnostico: "",
    pruebasRealizadas: "",
    recomendaciones: "",
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
});
const avance = reactive({ descripcion: "", visibilidad: "cliente", servicioId: "" });
const fechaLocal = () =>
    new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
const pago = reactive({
    monto: "",
    metodo: "efectivo",
    referencia: "",
    observaciones: "",
    pagadoEn: fechaLocal(),
});
const facturacion = reactive({
    descuento: "0.00",
    motivoDescuento: "",
    tasaImpuesto: "0.00",
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
        ["Pérdida de potencia o dificultad de arranque", entrada.perdida_potencia_arranque],
        ["Códigos OBD", entrada.codigos_obd],
        ["Pruebas realizadas por el cliente", entrada.pruebas_realizadas],
        ["Puede circular según el cliente", entrada.puede_circular?.replaceAll("_", " ")],
        ["Urgencia percibida", entrada.urgencia_percibida],
        ["Reparaciones recientes", entrada.reparaciones_recientes],
        ["Observaciones adicionales", entrada.observaciones],
    ];
    return campos.filter(([, valor]) => valor !== undefined && valor !== null && String(valor).trim() !== "");
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
    if (nuevo === "cancelada") {
        const m = prompt("Motivo de cancelación");
        if (!m) return;
        data.motivo = m;
    }
    if (confirm(`¿Cambiar la orden a ${nuevo.replaceAll("_", " ")}?`))
        router.patch(route("ordenes.estado", props.orden.id), data, {
            preserveScroll: true,
        });
}
function registrarLlegada() {
    if (!props.cita || new Date(props.cita.inicio) > new Date()) return;
    if (!confirm("¿Confirmas que el vehículo llegó físicamente al taller?")) return;
    router.patch(
        route("citas.estado", props.cita.id),
        { estado: "atendida", observaciones: "Llegada registrada desde la orden de trabajo." },
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
        onSuccess: () => Object.assign(avance, { descripcion: "", visibilidad: "cliente", servicioId: "" }),
        onFinish: () => (procesando.value = false),
    });
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
                }),
        },
    );
}
function retirarRequerimiento(item: Requerimiento) {
    const motivo = prompt("Motivo para retirar este requerimiento");
    if (motivo)
        router.post(
            route("ordenes.repuestos-requeridos.retirar", [
                props.orden.id,
                item.id,
            ]),
            { motivo },
            { preserveScroll: true },
        );
}
function prepararUso(item: Requerimiento) {
    consumo.requerimientoId = item.id;
    consumo.repuestoId = item.repuestoId || "";
    consumo.cantidad = item.cantidad;
    consumo.observaciones = `Uso confirmado para: ${item.descripcion}`;
}
function diagnosticar() {
    procesando.value = true;
    router.post(route("ordenes.diagnosticar", props.orden.id), diag, {
        preserveScroll: true,
        onSuccess: () =>
            Object.assign(diag, {
                diagnostico: "",
                pruebasRealizadas: "",
                recomendaciones: "",
            }),
        onFinish: () => (procesando.value = false),
    });
}
function usarRepuesto() {
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
function emitirFactura() {
    if (!confirm("¿Emitir la factura definitiva con estos valores?")) return;
    procesando.value = true;
    router.post(route("facturacion.store", props.orden.id), facturacion, {
        preserveScroll: true,
        onFinish: () => (procesando.value = false),
    });
}
function dinero(valor: string | number) {
    return Number(valor).toLocaleString("es-CO", {
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
                <div class="space-y-6 xl:col-span-2">
                    <UAlert
                        v-if="
                            errors.estado ||
                            errors.diagnostico ||
                            errors.servicio ||
                            errors.avance ||
                            errors.orden
                        "
                        color="error"
                        icon="i-lucide-circle-alert"
                        title="No se pudo completar la acción"
                        :description="
                            errors.estado ||
                            errors.diagnostico ||
                            errors.servicio ||
                            errors.avance ||
                            errors.orden
                        "
                    />
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
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <UButton
                                        v-if="cita.estado === 'confirmada' && can('citas.gestionar')"
                                        :label="new Date(cita.inicio) <= new Date() ? 'Registrar llegada' : `Llegada disponible ${new Date(cita.inicio).toLocaleDateString('es-CO')}`"
                                        icon="i-lucide-log-in"
                                        color="success"
                                        size="sm"
                                        :disabled="new Date(cita.inicio) > new Date()"
                                        @click="registrarLlegada"
                                    />
                                    <UBadge>{{ cita.estado }}</UBadge>
                                </div>
                            </div></template
                        >
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <p class="text-xs text-muted">Fecha y hora</p>
                                <p class="font-medium">
                                    {{
                                        new Date(cita.inicio).toLocaleString(
                                            "es-CO",
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
                                    {{ new Date(cita.atendidaEn).toLocaleString("es-CO") }}
                                </p>
                            </div>
                        </div>
                        <UAlert
                            v-if="cita.estado === 'confirmada' && new Date(cita.inicio) > new Date() && can('citas.gestionar')"
                            class="mt-4"
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-clock"
                            title="La llegada aún no está disponible"
                            :description="`Podrás registrarla desde esta orden cuando comience la cita: ${new Date(cita.inicio).toLocaleString('es-CO')}.`"
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
                                        "es-CO",
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
                                            "es-CO",
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
                                ><UBadge v-if="diagnosticoIa.mecanico" color="success" variant="subtle"
                                    >Mecánico sugerido: {{ diagnosticoIa.mecanico }}</UBadge
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
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="font-semibold">Revisión del mecánico</h3>
                                    <UBadge color="success" variant="subtle">{{ diagnosticoIa.revision.estado.replaceAll("_", " ") }}</UBadge>
                                </div>
                                <p v-if="diagnosticoIa.revision.observacionesCliente" class="mt-2 whitespace-pre-wrap text-sm">
                                    {{ diagnosticoIa.revision.observacionesCliente }}
                                </p>
                                <p v-if="diagnosticoIa.revision.motivoDiferencia" class="mt-2 text-sm">
                                    <strong>Motivo de la corrección:</strong> {{ diagnosticoIa.revision.motivoDiferencia }}
                                </p>
                                <p v-if="diagnosticoIa.revision.pruebasRealizadas.length" class="mt-2 text-sm">
                                    <strong>Pruebas realizadas:</strong> {{ diagnosticoIa.revision.pruebasRealizadas.join(" · ") }}
                                </p>
                                <p v-if="diagnosticoIa.revision.notasInternas" class="mt-2 text-sm text-muted">
                                    <strong>Notas internas:</strong> {{ diagnosticoIa.revision.notasInternas }}
                                </p>
                            </div>
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
                                        <h3 class="font-semibold">Reporte completo del cliente</h3>
                                        <dl class="mt-2 grid gap-3 sm:grid-cols-2">
                                            <div
                                                v-for="([etiqueta, valor], indice) in camposReporteIa"
                                                :key="indice"
                                                class="rounded bg-elevated p-3"
                                            >
                                                <dt class="text-xs text-muted">{{ etiqueta }}</dt>
                                                <dd class="mt-1 whitespace-pre-wrap font-medium">{{ valor }}</dd>
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
                            {{ orden.kilometraje.toLocaleString("es-CO") }} km
                        </p>
                        <p class="mt-4 whitespace-pre-wrap">
                            {{ orden.fallaReportada }}
                        </p></UCard
                    >
                    <UCard>
                        <template #header>
                            <div>
                                <h2 class="font-semibold">Bitácora de avances</h2>
                                <p class="text-sm text-muted">Actualizaciones cronológicas del diagnóstico y la reparación.</p>
                            </div>
                        </template>

                        <form
                            v-if="can('ordenes.avanzar') && ['en_diagnostico', 'en_reparacion'].includes(orden.estado)"
                            class="mb-5 grid gap-3 rounded-lg bg-elevated/50 p-4 sm:grid-cols-2"
                            @submit.prevent="registrarAvance"
                        >
                            <UFormField label="Servicio relacionado" hint="Opcional">
                                <USelect
                                    v-model="avance.servicioId"
                                    :items="[{ label: 'Avance general de la orden', value: '' }, ...orden.servicios.map(s => ({ label: s.nombre, value: s.id }))]"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField label="Visibilidad" required>
                                <USelect
                                    v-model="avance.visibilidad"
                                    :items="[{ label: 'Visible para el cliente', value: 'cliente' }, { label: 'Solo personal interno', value: 'interno' }]"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField class="sm:col-span-2" label="Avance realizado" required :error="errors.descripcion || errors.avance">
                                <UTextarea
                                    v-model="avance.descripcion"
                                    :rows="4"
                                    class="w-full"
                                    placeholder="Describe la inspección, prueba, hallazgo o trabajo realizado."
                                    required
                                />
                            </UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton type="submit" label="Registrar avance" icon="i-lucide-notebook-pen" :loading="procesando" />
                            </div>
                        </form>

                        <div v-if="avances.length" class="space-y-3">
                            <article v-for="item in avances" :key="item.id" class="rounded-lg border border-default p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <UBadge variant="subtle">{{ item.estadoOrden.replaceAll("_", " ") }}</UBadge>
                                        <UBadge v-if="item.servicio" color="primary" variant="outline">{{ item.servicio }}</UBadge>
                                        <UBadge v-if="item.visibilidad === 'interno'" color="neutral" variant="outline">Interno</UBadge>
                                    </div>
                                    <span class="text-xs text-muted">{{ new Date(item.createdAt).toLocaleString("es-CO") }}</span>
                                </div>
                                <p class="mt-3 whitespace-pre-wrap text-sm leading-6">{{ item.descripcion }}</p>
                                <p class="mt-2 text-xs text-muted">Registrado por {{ item.autor || "Usuario no disponible" }}</p>
                            </article>
                        </div>
                        <p v-else class="py-5 text-center text-sm text-muted">Todavía no hay avances registrados.</p>
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
                                can('ordenes.avanzar') &&
                                ['en_diagnostico', 'en_reparacion'].includes(
                                    orden.estado,
                                )
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
                                    label="Agregar al alcance"
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
                                <p class="text-sm text-muted">
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
                            </div>
                            <div
                                v-if="
                                    can('ordenes.avanzar') &&
                                    [
                                        'en_diagnostico',
                                        'en_reparacion',
                                    ].includes(orden.estado) &&
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

                    <UCard
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
                                can('ordenes.avanzar') &&
                                ['en_diagnostico', 'en_reparacion'].includes(
                                    orden.estado,
                                )
                            "
                            class="mb-4 grid gap-3 rounded-lg bg-elevated/50 p-4 sm:grid-cols-2"
                            @submit.prevent="agregarRequerimiento"
                        >
                            <UFormField
                                label="Repuesto del inventario"
                                hint="Opcional"
                                ><USelect
                                    :model-value="nuevoRepuesto.repuestoId"
                                    :items="
                                        repuestosCatalogo.map((r) => ({
                                            label: `${r.label} · stock ${r.stock} ${r.unidad} · $ ${dinero(r.precio)}`,
                                            value: r.id,
                                        }))
                                    "
                                    class="w-full"
                                    @update:model-value="
                                        seleccionarRepuesto(
                                            String($event || ''),
                                        )
                                    " /></UFormField
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
                            :class="r.estado === 'retirado' ? 'opacity-50' : ''"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong>{{ r.descripcion }}</strong
                                    ><UBadge variant="outline">{{
                                        r.origen
                                    }}</UBadge
                                    ><UBadge
                                        :color="
                                            r.estado === 'retirado'
                                                ? 'neutral'
                                                : r.disponible
                                                  ? 'success'
                                                  : 'warning'
                                        "
                                        >{{ r.estado }}</UBadge
                                    >
                                </div>
                                <p class="text-sm">
                                    Cantidad {{ r.cantidad }}
                                    {{ r.unidad || "" }} · stock
                                    {{ r.stock ?? "sin correspondencia" }}
                                    <span v-if="r.precio"
                                        >· $ {{ dinero(r.precio) }} c/u</span
                                    >
                                </p>
                                <p v-if="r.motivo" class="text-xs text-muted">
                                    {{ r.motivo }}
                                </p>
                            </div>
                            <div
                                v-if="r.estado !== 'retirado'"
                                class="flex gap-1"
                            >
                                <UButton
                                    v-if="
                                        can('inventario.consumir') &&
                                        r.repuestoId &&
                                        r.disponible &&
                                        [
                                            'en_diagnostico',
                                            'en_reparacion',
                                        ].includes(orden.estado)
                                    "
                                    size="xs"
                                    color="success"
                                    variant="soft"
                                    label="Utilizar"
                                    @click="prepararUso(r)"
                                /><UButton
                                    v-if="can('ordenes.avanzar')"
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
                                <p class="text-sm font-medium">
                                    Total $ {{ dinero(totalRepuestos) }}
                                </p>
                            </div></template
                        >
                        <form
                            v-if="
                                can('inventario.consumir') &&
                                ['en_diagnostico', 'en_reparacion'].includes(
                                    orden.estado,
                                )
                            "
                            class="mb-5 grid gap-4 rounded-lg bg-elevated/50 p-4 sm:grid-cols-2"
                            @submit.prevent="usarRepuesto"
                        >
                            <UAlert
                                v-if="consumo.requerimientoId"
                                class="sm:col-span-2"
                                color="success"
                                variant="subtle"
                                title="Uso vinculado a un requerimiento"
                                description="Al confirmar se descontará físicamente del inventario."
                            />
                            <UFormField
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
                                </p>
                                <p class="text-sm text-muted">
                                    {{ u.cantidad }} {{ u.unidad }} · $
                                    {{ dinero(u.precioUnitario) }} c/u
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <UBadge v-if="u.revertido" color="neutral"
                                    >Revertido</UBadge
                                ><UButton
                                    v-else-if="
                                        canAny([
                                            'inventario.gestionar',
                                            'inventario.consumir',
                                        ])
                                    "
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
                                        Valores expresados en COP
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
                            ><UFormField label="Fecha del pago" required
                                ><UInput
                                    v-model="pago.pagadoEn"
                                    type="datetime-local"
                                    class="w-full" /></UFormField
                            ><UFormField label="Referencia"
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
                                    item.estado === 'anulado'
                                        ? 'opacity-55'
                                        : ''
                                "
                            >
                                <div>
                                    <p
                                        class="font-medium"
                                        :class="
                                            item.estado === 'anulado'
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
                                            ).toLocaleString("es-CO")
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
                            ['finalizada', 'entregada'].includes(orden.estado)
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
                                        ).toLocaleString("es-CO")
                                    }}
                                    · Total $ {{ dinero(factura.total) }} COP
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
                                :error="errors.tasa_impuesto"
                                ><UInput
                                    v-model="facturacion.tasaImpuesto"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="w-full" /></UFormField
                            ><UFormField
                                v-if="
                                    can('descuentos.autorizar') &&
                                    Number(facturacion.descuento) > 0
                                "
                                label="Motivo del descuento"
                                required
                                :error="errors.motivo_descuento"
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

                    <UCard
                        ><template #header
                            ><h2 class="font-semibold">
                                Diagnóstico técnico
                            </h2></template
                        >
                        <form
                            v-if="
                                can('diagnosticos.registrar') &&
                                ['en_diagnostico', 'en_reparacion'].includes(
                                    orden.estado,
                                )
                            "
                            class="space-y-4"
                            @submit.prevent="diagnosticar"
                        >
                            <UFormField
                                label="Diagnóstico"
                                required
                                :error="errors.diagnostico"
                                ><UTextarea
                                    v-model="diag.diagnostico"
                                    class="w-full"
                                    :rows="5" /></UFormField
                            ><UFormField label="Pruebas realizadas"
                                ><UTextarea
                                    v-model="diag.pruebasRealizadas"
                                    class="w-full" /></UFormField
                            ><UFormField label="Recomendaciones"
                                ><UTextarea
                                    v-model="diag.recomendaciones"
                                    class="w-full"
                            /></UFormField>
                            <div class="text-right">
                                <UButton
                                    type="submit"
                                    label="Registrar nueva versión"
                                    :loading="procesando"
                                />
                            </div>
                        </form>
                        <div
                            v-for="d in orden.diagnosticos"
                            :key="d.id"
                            class="mt-4 rounded-lg border border-default p-4"
                        >
                            <div class="flex justify-between">
                                <p class="font-medium">
                                    Versión {{ d.version }}
                                </p>
                                <UBadge
                                    :color="d.vigente ? 'success' : 'neutral'"
                                    >{{
                                        d.vigente ? "Vigente" : "Anterior"
                                    }}</UBadge
                                >
                            </div>
                            <p class="mt-3 whitespace-pre-wrap">
                                {{ d.diagnostico }}
                            </p>
                            <p v-if="d.pruebasRealizadas" class="mt-2 text-sm">
                                <strong>Pruebas:</strong>
                                {{ d.pruebasRealizadas }}
                            </p>
                            <p v-if="d.recomendaciones" class="mt-2 text-sm">
                                <strong>Recomendaciones:</strong>
                                {{ d.recomendaciones }}
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

                <aside class="space-y-6">
                    <UCard
                        v-if="canAny(['ordenes.avanzar', 'ordenes.cancelar'])"
                        ><template #header
                            ><h2 class="font-semibold">
                                Cambiar estado
                            </h2></template
                        >
                        <div class="grid gap-2">
                            <UButton
                                v-if="
                                    can('ordenes.avanzar') &&
                                    orden.estado === 'pendiente'
                                "
                                label="Iniciar diagnóstico"
                                @click="estado('en_diagnostico')"
                            /><UButton
                                v-if="
                                    can('ordenes.avanzar') &&
                                    orden.estado === 'en_diagnostico'
                                "
                                label="Iniciar reparación"
                                @click="estado('en_reparacion')"
                            /><UButton
                                v-if="
                                    can('ordenes.avanzar') &&
                                    orden.estado === 'en_reparacion'
                                "
                                label="Finalizar orden"
                                @click="estado('finalizada')"
                            /><UButton
                                v-if="
                                    can('ordenes.avanzar') &&
                                    orden.estado === 'finalizada'
                                "
                                label="Marcar entregada"
                                @click="estado('entregada')"
                            /><UButton
                                v-if="
                                    can('ordenes.cancelar') &&
                                    ![
                                        'entregada',
                                        'cancelada',
                                        'finalizada',
                                    ].includes(orden.estado)
                                "
                                color="error"
                                variant="soft"
                                label="Cancelar orden"
                                @click="estado('cancelada')"
                            /></div
                    ></UCard>
                    <UCard v-if="can('ordenes.asignar')"
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
