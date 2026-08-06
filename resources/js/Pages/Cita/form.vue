<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";

interface Cliente {
    id: string;
    nombre: string;
    vehiculos: { id: string; label: string }[];
}
interface Especialidad {
    id: string;
    nombre: string;
}
interface Servicio {
    id: string;
    especialidad_id: string;
    nombre: string;
    duracion_minutos: number;
    precio_base: string;
}
interface Horario {
    dia: number;
    inicio: string;
    fin: string;
    vigenteDesde?: string;
    vigenteHasta?: string;
}
interface Mecanico {
    id: string;
    nombre: string;
    especialidadIds: string[];
    horarios: Horario[];
}
interface Ocupacion {
    mecanicoId: string;
    fecha: string;
    horaInicio: string;
    horaFin: string;
}
interface Repuesto {
    id: string;
    codigo: string;
    nombre: string;
    unidad: string;
    stock_actual: string;
    precio_venta: string;
}
interface ContextoIa {
    estado: string;
    entrada: Record<string, any>;
    resumen?: string;
    causas: { nombre?: string; explicacion?: string; probabilidad_o_nivel?: string }[];
    acciones: string[];
    pruebas: string[];
    servicios: string[];
    repuestos: { nombre: string; cantidad?: number; motivo?: string; probabilidad_o_nivel?: string }[];
    especialidad?: string;
    mecanico?: string;
    prioridad?: string;
    riesgo?: string;
    circulacion?: string;
    servicioCoincidente?: string;
}

const props = defineProps<{
    clientes: Cliente[];
    especialidades: Especialidad[];
    servicios: Servicio[];
    mecanicos: Mecanico[];
    repuestos: Repuesto[];
    ocupaciones: Ocupacion[];
    horizonteDias: number;
    prefill?: {
        consultaIaId: string;
        clienteId: string;
        vehiculoId: string;
        especialidadId?: string;
        mecanicoId?: string;
        servicioId?: string;
        kilometraje?: number;
        motivo: string;
        contextoIa: ContextoIa;
    } | null;
}>();
const state = reactive({
    consultaIaId: props.prefill?.consultaIaId ?? "",
    clienteId:
        props.prefill?.clienteId ??
        (props.clientes.length === 1 ? props.clientes[0].id : ""),
    vehiculoId: props.prefill?.vehiculoId ?? "",
    especialidadId: props.prefill?.especialidadId ?? "",
    servicioId: props.prefill?.servicioId ?? "",
    mecanicoId: props.prefill?.mecanicoId ?? "",
    fecha: "",
    horaInicio: "",
    kilometraje: props.prefill?.kilometraje ?? (null as number | null),
    motivo: props.prefill?.motivo ?? "",
    repuestosSolicitados: [] as {
        repuestoId: string;
        descripcion: string;
        cantidad: string;
        observaciones: string;
    }[],
});
const errors = computed<Record<string, string>>(
    () => usePage().props.errors as Record<string, string>,
);
const procesando = ref(false);
const cliente = computed(() =>
    props.clientes.find((c) => c.id === state.clienteId),
);
const vehiculos = computed(() => cliente.value?.vehiculos ?? []);
const vehiculo = computed(() =>
    vehiculos.value.find((v) => v.id === state.vehiculoId),
);
const servicios = computed(() =>
    props.servicios.filter(
        (s) =>
            !state.especialidadId || s.especialidad_id === state.especialidadId,
    ),
);
const mecanicos = computed(() =>
    props.mecanicos.filter(
        (m) =>
            !state.especialidadId ||
            m.especialidadIds.includes(state.especialidadId),
    ),
);
const mecanico = computed(() =>
    props.mecanicos.find((m) => m.id === state.mecanicoId),
);
const servicio = computed(() =>
    props.servicios.find((s) => s.id === state.servicioId),
);
const duracion = computed(() => servicio.value?.duracion_minutos ?? 60);
const camposReporteIa = computed(() => {
    const entrada = props.prefill?.contextoIa.entrada;
    if (!entrada) return [];
    const campos = [
        ["Síntoma principal", entrada.sintoma_principal],
        ["Sistema", entrada.categoria_falla],
        ["Momento en que ocurre", entrada.momento_ocurre],
        ["Frecuencia", entrada.frecuencia?.replaceAll("_", " ")],
        ["Desde cuándo", entrada.tiempo_desde_inicio],
        ["Intensidad", entrada.intensidad],
        ["Condiciones", entrada.condiciones?.join(", ")],
        ["Ruidos, vibraciones, humo, olores o fugas", entrada.senales],
        ["Testigos del tablero", entrada.luces_tablero],
        ["Pérdida de potencia o arranque", entrada.perdida_potencia_arranque],
        ["Códigos OBD", entrada.codigos_obd],
        ["Pruebas realizadas", entrada.pruebas_realizadas],
        ["Puede circular", entrada.puede_circular?.replaceAll("_", " ")],
        ["Urgencia percibida", entrada.urgencia_percibida],
        ["Reparaciones recientes", entrada.reparaciones_recientes],
        ["Observaciones", entrada.observaciones],
    ];
    return campos.filter(([, valor]) => valor !== undefined && valor !== null && String(valor).trim() !== "");
});
const opcionesFecha = computed(() => {
    if (!mecanico.value) return [];
    const hoy = fechaActualBogota().fecha;
    const base = new Date(`${hoy}T12:00:00`);
    return Array.from({ length: props.horizonteDias }, (_, i) => {
        const fecha = new Date(base);
        fecha.setDate(base.getDate() + i);
        const valor = claveFecha(fecha);
        const cupos = slotsFecha(valor);
        return {
            valor,
            cupos,
            label: `${new Intl.DateTimeFormat("es-CO", { weekday: "short", day: "numeric", month: "short" }).format(fecha)} · ${cupos.length} ${cupos.length === 1 ? "hora disponible" : "horas disponibles"}`,
        };
    })
        .filter((d) => d.cupos.length)
        .map((d) => ({ label: d.label, value: d.valor }));
});
const opcionesHora = computed(() =>
    state.fecha
        ? slotsFecha(state.fecha).map((h) => ({
              label: `${formatoHora(h)} · Disponible (${duracion.value} min)`,
              value: h,
          }))
        : [],
);

watch(
    () => state.clienteId,
    () => {
        if (!vehiculos.value.some((v) => v.id === state.vehiculoId))
            state.vehiculoId = "";
    },
);
watch(
    () => state.especialidadId,
    () => {
        if (!servicios.value.some((s) => s.id === state.servicioId))
            state.servicioId = "";
        if (!mecanicos.value.some((m) => m.id === state.mecanicoId))
            state.mecanicoId = "";
        if (mecanicos.value.length === 1)
            state.mecanicoId = mecanicos.value[0].id;
        limpiarAgenda();
    },
);
watch(
    () => state.servicioId,
    (id) => {
        const s = props.servicios.find((x) => x.id === id);
        if (s) state.especialidadId = s.especialidad_id;
        limpiarAgenda();
    },
);
watch(() => state.mecanicoId, limpiarAgenda);
watch(
    () => state.fecha,
    () => {
        state.horaInicio = "";
    },
);

function limpiarAgenda() {
    state.fecha = "";
    state.horaInicio = "";
}
function minutos(hora: string) {
    const [h, m] = hora.split(":").map(Number);
    return h * 60 + m;
}
function desdeMinutos(valor: number) {
    return `${String(Math.floor(valor / 60)).padStart(2, "0")}:${String(valor % 60).padStart(2, "0")}`;
}
function claveFecha(fecha: Date) {
    return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, "0")}-${String(fecha.getDate()).padStart(2, "0")}`;
}
function fechaActualBogota() {
    const partes = Object.fromEntries(
        new Intl.DateTimeFormat("en-CA", {
            timeZone: "America/Bogota",
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hourCycle: "h23",
        })
            .formatToParts(new Date())
            .filter((p) => p.type !== "literal")
            .map((p) => [p.type, p.value]),
    );
    return {
        fecha: `${partes.year}-${partes.month}-${partes.day}`,
        minutos: Number(partes.hour) * 60 + Number(partes.minute),
    };
}
function slotsFecha(fecha: string) {
    if (!mecanico.value) return [];
    const diaFecha = new Date(`${fecha}T12:00:00`);
    const dia = diaFecha.getDay() === 0 ? 7 : diaFecha.getDay();
    const ahora = fechaActualBogota();
    const horarios = mecanico.value.horarios.filter(
        (h) =>
            h.dia === dia &&
            (!h.vigenteDesde || h.vigenteDesde <= fecha) &&
            (!h.vigenteHasta || h.vigenteHasta >= fecha),
    );
    const ocupadas = props.ocupaciones.filter(
        (o) => o.mecanicoId === mecanico.value?.id && o.fecha === fecha,
    );
    const resultado = new Set<string>();
    for (const horario of horarios) {
        for (
            let inicio = minutos(horario.inicio);
            inicio + duracion.value <= minutos(horario.fin);
            inicio += 30
        ) {
            const fin = inicio + duracion.value;
            if (fecha === ahora.fecha && inicio <= ahora.minutos) continue;
            const solapa = ocupadas.some(
                (o) =>
                    inicio < minutos(o.horaFin) && fin > minutos(o.horaInicio),
            );
            if (!solapa) resultado.add(desdeMinutos(inicio));
        }
    }
    return [...resultado].sort();
}
function formatoHora(hora: string) {
    return new Intl.DateTimeFormat("es-CO", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
    }).format(new Date(`2000-01-01T${hora}:00`));
}
function dinero(valor: string | number) {
    return Number(valor).toLocaleString("es-CO", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
function agregarRepuesto() {
    state.repuestosSolicitados.push({
        repuestoId: "",
        descripcion: "",
        cantidad: "1.000",
        observaciones: "",
    });
}
function seleccionarRepuesto(indice: number, id: string) {
    const repuesto = props.repuestos.find((r) => r.id === id);
    state.repuestosSolicitados[indice].repuestoId = id;
    state.repuestosSolicitados[indice].descripcion = repuesto
        ? `${repuesto.codigo} · ${repuesto.nombre}`
        : "";
}
function guardar() {
    procesando.value = true;
    router.post(route("citas.store"), state, {
        onFinish: () => (procesando.value = false),
    });
}
</script>

<template>
    <Head title="Nueva cita" />
    <UDashboardPanel
        ><template #header><UDashboardNavbar title="Nueva cita" /></template
        ><template #body
            ><form
                class="mx-auto max-w-5xl space-y-6"
                @submit.prevent="guardar"
            >
                <UCard v-if="prefill?.contextoIa" class="border-primary/25 bg-primary/5">
                    <template #header>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-mono text-xs uppercase tracking-wider text-primary">Datos importados del asistente IA</p>
                                <h2 class="mt-1 font-semibold">La cita ya contiene el reporte completo del cliente</h2>
                                <p class="mt-1 text-sm text-muted">Revisa la fecha y el horario. No necesitas volver a escribir la información de la falla.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <UBadge variant="subtle">{{ prefill.contextoIa.estado.replaceAll("_", " ") }}</UBadge>
                                <UBadge v-if="prefill.contextoIa.prioridad" color="warning" variant="subtle">Prioridad {{ prefill.contextoIa.prioridad }}</UBadge>
                                <UBadge v-if="prefill.contextoIa.riesgo" color="neutral" variant="outline">Riesgo {{ prefill.contextoIa.riesgo }}</UBadge>
                            </div>
                        </div>
                    </template>

                    <UAlert
                        color="warning"
                        variant="subtle"
                        icon="i-lucide-triangle-alert"
                        title="Orientación preliminar"
                        description="Las causas, servicios y repuestos sugeridos ayudan a preparar la cita, pero el mecánico debe verificarlos antes de autorizar trabajos o consumir inventario."
                    />

                    <div class="mt-5 grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
                        <section>
                            <h3 class="font-semibold">Información reportada</h3>
                            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div v-for="([etiqueta, valor], indice) in camposReporteIa" :key="indice" class="rounded-lg bg-elevated/70 p-3">
                                    <dt class="text-xs text-muted">{{ etiqueta }}</dt>
                                    <dd class="mt-1 whitespace-pre-wrap text-sm font-medium">{{ valor }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="space-y-4">
                            <div class="rounded-lg border border-default p-4">
                                <h3 class="font-semibold">Resumen para la atención</h3>
                                <p class="mt-2 whitespace-pre-wrap text-sm leading-6">{{ prefill.contextoIa.resumen || "Pendiente de conclusión." }}</p>
                            </div>
                            <div class="rounded-lg border border-default p-4">
                                <h3 class="font-semibold">Asignación recomendada</h3>
                                <p class="mt-2 text-sm"><strong>Especialidad:</strong> {{ prefill.contextoIa.especialidad || "Por confirmar" }}</p>
                                <p class="mt-1 text-sm"><strong>Mecánico:</strong> {{ prefill.contextoIa.mecanico || "Sin candidato disponible" }}</p>
                                <p v-if="prefill.contextoIa.servicioCoincidente" class="mt-1 text-sm text-success"><strong>Servicio preseleccionado:</strong> {{ prefill.contextoIa.servicioCoincidente }}</p>
                            </div>
                        </section>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        <section class="rounded-lg border border-default p-4">
                            <h3 class="font-semibold">Acciones y pruebas recomendadas</h3>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                <li v-for="accion in prefill.contextoIa.acciones" :key="accion">{{ accion }}</li>
                                <li v-for="prueba in prefill.contextoIa.pruebas" :key="prueba">Prueba: {{ prueba }}</li>
                            </ul>
                            <p v-if="!prefill.contextoIa.acciones.length && !prefill.contextoIa.pruebas.length" class="mt-2 text-sm text-muted">Sin recomendaciones adicionales.</p>
                        </section>
                        <section class="rounded-lg border border-default p-4">
                            <h3 class="font-semibold">Servicios y repuestos posibles</h3>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <UBadge v-for="servicioSugerido in prefill.contextoIa.servicios" :key="servicioSugerido" color="primary" variant="subtle">{{ servicioSugerido }}</UBadge>
                            </div>
                            <ul v-if="prefill.contextoIa.repuestos.length" class="mt-3 space-y-2 text-sm">
                                <li v-for="repuestoSugerido in prefill.contextoIa.repuestos" :key="repuestoSugerido.nombre">
                                    <strong>{{ repuestoSugerido.nombre }}</strong> · {{ repuestoSugerido.cantidad || 1 }}
                                    <span class="text-muted"> · {{ repuestoSugerido.motivo }}</span>
                                </li>
                            </ul>
                        </section>
                    </div>
                </UCard>

                <UCard
                    ><template #header
                        ><div>
                            <h2 class="font-semibold">Cliente y vehículo</h2>
                            <p class="text-sm text-muted">
                                Solo se muestran vehículos activos del cliente.
                            </p>
                        </div></template
                    >
                    <div class="grid gap-5 md:grid-cols-2">
                        <UFormField
                            label="Cliente"
                            required
                            :error="errors.clienteId || errors.cliente_id"
                            ><USelect
                                v-model="state.clienteId"
                                class="w-full"
                                :items="
                                    clientes.map((c) => ({
                                        label: c.nombre,
                                        value: c.id,
                                    }))
                                "
                                :disabled="!!prefill"
                                required /></UFormField
                        ><UFormField
                            label="Vehículo"
                            required
                            :error="errors.vehiculoId || errors.vehiculo_id"
                            ><USelect
                                v-model="state.vehiculoId"
                                class="w-full"
                                :items="
                                    vehiculos.map((v) => ({
                                        label: v.label,
                                        value: v.id,
                                    }))
                                "
                                :disabled="!!prefill || !state.clienteId"
                                required /></UFormField
                        ><UFormField
                            label="Kilometraje actual"
                            hint="Opcional"
                            :error="errors.kilometraje"
                            ><UInput
                                v-model.number="state.kilometraje"
                                type="number"
                                min="0"
                                max="9999999"
                                class="w-full"
                        /></UFormField></div
                ></UCard>

                <UCard
                    ><template #header
                        ><h2 class="font-semibold">
                            Motivo y servicio
                        </h2></template
                    ><UFormField
                        label="Síntomas o motivo"
                        required
                        :error="errors.motivo"
                        ><UTextarea
                            v-model="state.motivo"
                            class="w-full"
                            :rows="4"
                            required
                    /></UFormField>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <UFormField
                            label="Especialidad"
                            required
                            :error="
                                errors.especialidadId || errors.especialidad_id
                            "
                            ><USelect
                                v-model="state.especialidadId"
                                class="w-full"
                                :items="
                                    especialidades.map((e) => ({
                                        label: e.nombre,
                                        value: e.id,
                                    }))
                                "
                                required /></UFormField
                        ><UFormField
                            label="Servicio sugerido"
                            hint="Opcional; sin servicio se reserva 60 min"
                            :error="errors.servicioId || errors.servicio_id"
                            ><USelect
                                v-model="state.servicioId"
                                class="w-full"
                                :items="
                                    servicios.map((s) => ({
                                        label: `${s.nombre} · ${s.duracion_minutos} min`,
                                        value: s.id,
                                    }))
                                "
                        /></UFormField></div
                ></UCard>

                <UCard
                    ><template #header
                        ><div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="font-semibold">
                                    Repuestos solicitados
                                </h2>
                                <p class="text-sm text-muted">
                                    Opcional. Solicitar no reserva, descuenta ni
                                    factura inventario; el mecánico confirmará
                                    si son adecuados.
                                </p>
                            </div>
                            <UButton
                                type="button"
                                label="Solicitar repuesto"
                                icon="i-lucide-plus"
                                size="sm"
                                color="neutral"
                                variant="outline"
                                @click="agregarRepuesto"
                            /></div
                    ></template>
                    <div
                        v-if="state.repuestosSolicitados.length"
                        class="space-y-4"
                    >
                        <div
                            v-for="(item, i) in state.repuestosSolicitados"
                            :key="i"
                            class="grid gap-3 rounded-xl border border-default p-4 md:grid-cols-[1.5fr_.5fr_1fr_auto]"
                        >
                            <UFormField label="Repuesto del catálogo"
                                ><USelect
                                    :model-value="item.repuestoId"
                                    class="w-full"
                                    :items="
                                        repuestos.map((r) => ({
                                            label: `${r.codigo} · ${r.nombre} · stock ${r.stock_actual} ${r.unidad} · $ ${dinero(r.precio_venta)}`,
                                            value: r.id,
                                        }))
                                    "
                                    placeholder="Selecciona o describe abajo"
                                    @update:model-value="
                                        seleccionarRepuesto(
                                            i,
                                            String($event || ''),
                                        )
                                    " /></UFormField
                            ><UFormField
                                label="Cantidad"
                                :error="
                                    errors[
                                        `repuestos_solicitados.${i}.cantidad`
                                    ]
                                "
                                ><UInput
                                    v-model="item.cantidad"
                                    type="number"
                                    min="0.001"
                                    step="0.001"
                                    class="w-full" /></UFormField
                            ><UFormField
                                label="Descripción o solicitud"
                                :error="
                                    errors[
                                        `repuestos_solicitados.${i}.descripcion`
                                    ]
                                "
                                ><UTextarea
                                    v-model="item.descripcion"
                                    :rows="3"
                                    class="w-full"
                                    placeholder="Ej. pastillas delanteras" /></UFormField
                            ><UButton
                                type="button"
                                icon="i-lucide-trash-2"
                                color="error"
                                variant="ghost"
                                class="self-end"
                                aria-label="Quitar repuesto"
                                @click="state.repuestosSolicitados.splice(i, 1)"
                            /><UFormField
                                class="md:col-span-4"
                                label="Observaciones"
                                hint="Opcional"
                                ><UTextarea
                                    v-model="item.observaciones"
                                    :rows="3"
                                    class="w-full"
                                    placeholder="Marca preferida, síntoma relacionado u otra indicación"
                            /></UFormField>
                        </div>
                    </div>
                    <p v-else class="py-4 text-center text-muted">
                        No solicitaste repuestos. La cita continúa siendo
                        gratuita.
                    </p>
                    <UAlert
                        class="mt-4"
                        color="neutral"
                        variant="subtle"
                        title="La cita no genera cobro"
                        description="El precio final solo se determina después del diagnóstico, la autorización y el uso real de servicios o repuestos."
                /></UCard>

                <UCard
                    ><template #header
                        ><div>
                            <h2 class="font-semibold">
                                Turno y disponibilidad
                            </h2>
                            <p class="text-sm text-muted">
                                Solo aparecen fechas laborales y horas sin
                                cruces con otras citas. Los cupos se calculan en
                                intervalos de 30 minutos.
                            </p>
                        </div></template
                    >
                    <div class="grid gap-5 md:grid-cols-3">
                        <UFormField
                            label="Mecánico"
                            required
                            hint="Según la especialidad"
                            :error="errors.mecanicoId || errors.mecanico_id"
                            ><USelect
                                v-model="state.mecanicoId"
                                class="w-full"
                                :items="
                                    mecanicos.map((m) => ({
                                        label: m.nombre,
                                        value: m.id,
                                    }))
                                "
                                :disabled="!state.especialidadId"
                                required /></UFormField
                        ><UFormField
                            label="Fecha laboral disponible"
                            required
                            :error="errors.fecha || errors.inicio"
                            ><USelect
                                v-model="state.fecha"
                                class="w-full"
                                :items="opcionesFecha"
                                :disabled="
                                    !state.mecanicoId || !opcionesFecha.length
                                "
                                :placeholder="
                                    !state.mecanicoId
                                        ? 'Selecciona un mecánico'
                                        : opcionesFecha.length
                                          ? 'Selecciona una fecha'
                                          : 'Sin fechas disponibles'
                                "
                                required /></UFormField
                        ><UFormField
                            label="Hora disponible"
                            required
                            :error="errors.horaInicio || errors.inicio"
                            ><USelect
                                v-model="state.horaInicio"
                                class="w-full"
                                :items="opcionesHora"
                                :disabled="!state.fecha || !opcionesHora.length"
                                placeholder="Selecciona una hora"
                                required
                        /></UFormField>
                    </div>
                    <div
                        v-if="mecanico"
                        class="mt-4 rounded-xl border border-success/20 bg-success/5 p-4 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <UIcon
                                name="i-lucide-calendar-check"
                                class="text-success"
                            />
                            <p class="font-semibold">
                                Jornada semanal de {{ mecanico.nombre }}
                            </p>
                        </div>
                        <p class="mt-2 text-muted">
                            {{
                                mecanico.horarios
                                    .map(
                                        (h) =>
                                            `${["", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"][h.dia]} ${h.inicio}-${h.fin}`,
                                    )
                                    .join(" · ") ||
                                "Sin horario laboral configurado"
                            }}
                        </p>
                        <p
                            v-if="state.fecha && state.horaInicio"
                            class="mt-2 font-medium text-success"
                        >
                            Reserva: {{ state.fecha }} a las
                            {{ formatoHora(state.horaInicio) }} · duración
                            {{ duracion }} minutos.
                        </p>
                    </div>
                    <UAlert
                        v-if="state.mecanicoId && !opcionesFecha.length"
                        class="mt-4"
                        color="warning"
                        icon="i-lucide-calendar-x"
                        title="No hay cupos en los próximos días"
                        description="Revisa el horario laboral del mecánico o selecciona otro profesional compatible."
                /></UCard>

                <div
                    class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <UButton
                        type="button"
                        label="Cancelar"
                        color="neutral"
                        variant="outline"
                        @click="router.visit(route('citas.index'))"
                    /><UButton
                        type="submit"
                        label="Agendar cita"
                        icon="i-lucide-calendar-plus"
                        :loading="procesando"
                        :disabled="
                            !state.fecha ||
                            !state.horaInicio ||
                            !state.mecanicoId
                        "
                    />
                </div></form></template
    ></UDashboardPanel>
</template>
