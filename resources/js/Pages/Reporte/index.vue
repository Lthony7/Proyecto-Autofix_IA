<script setup lang="ts">
import { computed, reactive } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";

interface Catalogo {
    id: string;
    nombre: string;
}
const props = defineProps<{
    resumen: {
        pendientes: number;
        finalizadas: number;
        ingresos: string | null;
        servicios: number;
        repuestos: string;
    };
    ordenesPendientes: any[];
    ordenesFinalizadas: any[];
    ingresos: any[];
    serviciosSolicitados: any[];
    repuestosUtilizados: any[];
    vehiculosPorCliente: any[];
    vista: string;
    filtros: {
        desde: string;
        hasta: string;
        estado?: string;
        mecanico?: string;
        cliente?: string;
        vehiculo?: string;
        servicio?: string;
    };
    puedeVerIngresos: boolean;
    puedeExportar: boolean;
    catalogos: {
        clientes: Catalogo[];
        vehiculos: Catalogo[];
        mecanicos: Catalogo[];
        servicios: Catalogo[];
    };
}>();
const filtros = reactive({
    desde: props.filtros.desde,
    hasta: props.filtros.hasta,
    estado: props.filtros.estado || "todos",
    mecanico: props.filtros.mecanico || "todos",
    cliente: props.filtros.cliente || "todos",
    vehiculo: props.filtros.vehiculo || "todos",
    servicio: props.filtros.servicio || "todos",
});
const estados = [
    { label: "Todos los estados", value: "todos" },
    { label: "Pendiente", value: "pendiente" },
    { label: "En diagnóstico", value: "en_diagnostico" },
    { label: "En reparación", value: "en_reparacion" },
    { label: "Finalizada", value: "finalizada" },
    { label: "Entregada", value: "entregada" },
    { label: "Cancelada", value: "cancelada" },
];
const parametros = computed(() => ({
    desde: filtros.desde,
    hasta: filtros.hasta,
    estado: filtros.estado === "todos" ? undefined : filtros.estado,
    mecanico: filtros.mecanico === "todos" ? undefined : filtros.mecanico,
    cliente: filtros.cliente === "todos" ? undefined : filtros.cliente,
    vehiculo: filtros.vehiculo === "todos" ? undefined : filtros.vehiculo,
    servicio: filtros.servicio === "todos" ? undefined : filtros.servicio,
}));
const maxIngreso = computed(() =>
    Math.max(1, ...props.ingresos.map((i) => Number(i.total))),
);
const maxServicio = computed(() =>
    Math.max(
        1,
        ...props.serviciosSolicitados.map((s) => Number(s.solicitudes)),
    ),
);
const rutas: Record<string, string> = {
    resumen: "reportes.index",
    filtros: "reportes.filtros",
    "ordenes-pendientes": "reportes.ordenes-pendientes",
    "ordenes-finalizadas": "reportes.ordenes-finalizadas",
    ingresos: "reportes.ingresos",
    servicios: "reportes.servicios",
    repuestos: "reportes.repuestos",
    "vehiculos-clientes": "reportes.vehiculos-clientes",
};
const titulo = computed(
    () =>
        ({
            resumen: "Reportes administrativos",
            filtros: "Filtros de reportes",
            "ordenes-pendientes": "Órdenes pendientes",
            "ordenes-finalizadas": "Órdenes finalizadas",
            ingresos: "Ingresos por fecha",
            servicios: "Servicios más solicitados",
            repuestos: "Repuestos más utilizados",
            "vehiculos-clientes": "Vehículos atendidos por cliente",
        })[props.vista] || "Reportes administrativos",
);
function consultar() {
    router.get(
        route(rutas[props.vista] || "reportes.index"),
        parametros.value,
        { preserveState: true, replace: true },
    );
}
function exportar(tipo: string) {
    window.location.href = route("reportes.exportar", {
        ...parametros.value,
        tipo,
    });
}
function dinero(v: string | number) {
    return Number(v).toLocaleString("es-CO", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <Head :title="titulo" />
    <UDashboardPanel>
        <template #header
            ><UDashboardNavbar :title="titulo"
                ><template #leading
                    ><UDashboardSidebarCollapse /></template></UDashboardNavbar
        ></template>
        <template #body
            ><div class="space-y-7">
                <section class="w-full">
                    <UCard
                        ><template #header
                            ><div>
                                <h2 class="font-semibold">Filtros globales</h2>
                                <p class="text-sm text-muted">
                                    Los resultados y exportaciones respetan
                                    estos criterios.
                                </p>
                            </div></template
                        >
                        <form
                            class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                            @submit.prevent="consultar"
                        >
                            <UFormField label="Desde"
                                ><UInput
                                    v-model="filtros.desde"
                                    type="date"
                                    class="w-full" /></UFormField
                            ><UFormField label="Hasta"
                                ><UInput
                                    v-model="filtros.hasta"
                                    type="date"
                                    class="w-full" /></UFormField
                            ><UFormField label="Estado"
                                ><USelect
                                    v-model="filtros.estado"
                                    :items="estados"
                                    class="w-full" /></UFormField
                            ><UFormField label="Mecánico"
                                ><USelect
                                    v-model="filtros.mecanico"
                                    :items="[
                                        {
                                            label: 'Todos los mecánicos',
                                            value: 'todos',
                                        },
                                        ...catalogos.mecanicos.map((i) => ({
                                            label: i.nombre,
                                            value: i.id,
                                        })),
                                    ]"
                                    class="w-full" /></UFormField
                            ><UFormField label="Cliente"
                                ><USelect
                                    v-model="filtros.cliente"
                                    :items="[
                                        {
                                            label: 'Todos los clientes',
                                            value: 'todos',
                                        },
                                        ...catalogos.clientes.map((i) => ({
                                            label: i.nombre,
                                            value: i.id,
                                        })),
                                    ]"
                                    class="w-full" /></UFormField
                            ><UFormField label="Vehículo"
                                ><USelect
                                    v-model="filtros.vehiculo"
                                    :items="[
                                        {
                                            label: 'Todos los vehículos',
                                            value: 'todos',
                                        },
                                        ...catalogos.vehiculos.map((i) => ({
                                            label: i.nombre,
                                            value: i.id,
                                        })),
                                    ]"
                                    class="w-full" /></UFormField
                            ><UFormField label="Servicio"
                                ><USelect
                                    v-model="filtros.servicio"
                                    :items="[
                                        {
                                            label: 'Todos los servicios',
                                            value: 'todos',
                                        },
                                        ...catalogos.servicios.map((i) => ({
                                            label: i.nombre,
                                            value: i.id,
                                        })),
                                    ]"
                                    class="w-full"
                            /></UFormField>
                            <div class="flex items-end">
                                <UButton
                                    type="submit"
                                    label="Actualizar reportes"
                                    icon="i-lucide-refresh-cw"
                                    block
                                />
                            </div></form
                    ></UCard>
                </section>

                <div v-if="vista === 'resumen'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <UCard
                        ><p class="text-sm text-muted">Órdenes pendientes</p>
                        <p class="text-3xl font-semibold">
                            {{ resumen.pendientes }}
                        </p></UCard
                    ><UCard
                        ><p class="text-sm text-muted">Órdenes finalizadas</p>
                        <p class="text-3xl font-semibold">
                            {{ resumen.finalizadas }}
                        </p></UCard
                    ><UCard v-if="puedeVerIngresos"
                        ><p class="text-sm text-muted">Ingresos reales</p>
                        <p class="text-2xl font-semibold">
                            $ {{ dinero(resumen.ingresos || 0) }}
                        </p></UCard
                    ><UCard
                        ><p class="text-sm text-muted">Servicios solicitados</p>
                        <p class="text-3xl font-semibold">
                            {{ resumen.servicios }}
                        </p></UCard
                    ><UCard
                        ><p class="text-sm text-muted">Repuestos utilizados</p>
                        <p class="text-3xl font-semibold">
                            {{
                                Number(resumen.repuestos).toLocaleString(
                                    "es-CO",
                                )
                            }}
                        </p></UCard
                    >
                </div>

                <div
                    v-if="['resumen', 'ordenes-pendientes', 'ordenes-finalizadas'].includes(vista)"
                    class="grid gap-6"
                    :class="vista === 'resumen' ? 'xl:grid-cols-2' : 'grid-cols-1'"
                >
                    <section v-if="vista === 'resumen' || vista === 'ordenes-pendientes'" class="w-full">
                        <UCard
                            ><template #header
                                ><div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="font-semibold">
                                            Órdenes pendientes
                                        </h2>
                                        <p class="text-sm text-muted">
                                            Pendientes, en diagnóstico o
                                            reparación
                                        </p>
                                    </div>
                                    <UButton
                                        v-if="puedeExportar"
                                        label="CSV"
                                        size="xs"
                                        color="neutral"
                                        variant="outline"
                                        icon="i-lucide-download"
                                        @click="exportar('ordenes_pendientes')"
                                    /></div
                            ></template>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[560px] text-sm">
                                    <thead>
                                        <tr
                                            class="border-b border-default text-left"
                                        >
                                            <th class="p-2">Orden</th>
                                            <th class="p-2">Estado</th>
                                            <th class="p-2">Cliente</th>
                                            <th class="p-2">Placa</th>
                                            <th class="p-2">Ingreso</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="o in ordenesPendientes"
                                            :key="o.numero"
                                            class="border-b border-default"
                                        >
                                            <td class="p-2 font-medium">
                                                {{ o.numero }}
                                            </td>
                                            <td class="p-2">
                                                <UBadge
                                                    size="xs"
                                                    color="warning"
                                                    >{{ o.estado }}</UBadge
                                                >
                                            </td>
                                            <td class="p-2">{{ o.cliente }}</td>
                                            <td class="p-2">{{ o.placa }}</td>
                                            <td class="p-2">
                                                {{
                                                    new Date(
                                                        o.recibida_en,
                                                    ).toLocaleDateString(
                                                        "es-CO",
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p
                                    v-if="!ordenesPendientes.length"
                                    class="py-6 text-center text-muted"
                                >
                                    Sin resultados.
                                </p>
                            </div></UCard
                        >
                    </section>
                    <section v-if="vista === 'resumen' || vista === 'ordenes-finalizadas'" class="w-full">
                        <UCard
                            ><template #header
                                ><div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="font-semibold">
                                            Órdenes finalizadas
                                        </h2>
                                        <p class="text-sm text-muted">
                                            Finalizadas y entregadas
                                        </p>
                                    </div>
                                    <UButton
                                        v-if="puedeExportar"
                                        label="CSV"
                                        size="xs"
                                        color="neutral"
                                        variant="outline"
                                        icon="i-lucide-download"
                                        @click="exportar('ordenes_finalizadas')"
                                    /></div
                            ></template>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[520px] text-sm">
                                    <thead>
                                        <tr
                                            class="border-b border-default text-left"
                                        >
                                            <th class="p-2">Orden</th>
                                            <th class="p-2">Estado</th>
                                            <th class="p-2">Cliente</th>
                                            <th class="p-2">Placa</th>
                                            <th class="p-2">Finalización</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="o in ordenesFinalizadas"
                                            :key="o.numero"
                                            class="border-b border-default"
                                        >
                                            <td class="p-2 font-medium">
                                                {{ o.numero }}
                                            </td>
                                            <td class="p-2">
                                                <UBadge
                                                    size="xs"
                                                    color="success"
                                                    >{{ o.estado }}</UBadge
                                                >
                                            </td>
                                            <td class="p-2">{{ o.cliente }}</td>
                                            <td class="p-2">{{ o.placa }}</td>
                                            <td class="p-2">
                                                {{
                                                    o.finalizada_en
                                                        ? new Date(
                                                              o.finalizada_en,
                                                          ).toLocaleDateString(
                                                              "es-CO",
                                                          )
                                                        : "-"
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p
                                    v-if="!ordenesFinalizadas.length"
                                    class="py-6 text-center text-muted"
                                >
                                    Sin resultados.
                                </p>
                            </div></UCard
                        >
                    </section>
                </div>

                <section
                    v-if="puedeVerIngresos && (vista === 'resumen' || vista === 'ingresos')"
                    class="w-full"
                >
                    <UCard
                        ><template #header
                            ><div class="flex items-center justify-between">
                                <div>
                                    <h2 class="font-semibold">
                                        Ingresos por fecha
                                    </h2>
                                    <p class="text-sm text-muted">
                                        Solo pagos registrados; pagos anulados
                                        quedan excluidos
                                    </p>
                                </div>
                                <UButton
                                    v-if="puedeExportar"
                                    label="Exportar CSV"
                                    size="sm"
                                    color="neutral"
                                    variant="outline"
                                    icon="i-lucide-download"
                                    @click="exportar('ingresos')"
                                /></div
                        ></template>
                        <div class="space-y-3">
                            <div
                                v-for="i in ingresos"
                                :key="i.fecha"
                                class="grid grid-cols-[7rem_1fr_auto] items-center gap-3 text-sm"
                            >
                                <span>{{
                                    new Date(
                                        `${i.fecha}T00:00:00`,
                                    ).toLocaleDateString("es-CO")
                                }}</span>
                                <div
                                    class="h-3 overflow-hidden rounded-full bg-elevated"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary"
                                        :style="{
                                            width: `${Math.max(2, (Number(i.total) / maxIngreso) * 100)}%`,
                                        }"
                                    />
                                </div>
                                <span class="font-medium"
                                    >$ {{ dinero(i.total) }} ·
                                    {{ i.pagos }}</span
                                >
                            </div>
                            <p
                                v-if="!ingresos.length"
                                class="py-6 text-center text-muted"
                            >
                                No hubo ingresos en el periodo.
                            </p>
                        </div></UCard
                    >
                </section>

                <div
                    v-if="['resumen', 'servicios', 'repuestos'].includes(vista)"
                    class="grid gap-6"
                    :class="vista === 'resumen' ? 'xl:grid-cols-2' : 'grid-cols-1'"
                >
                    <section v-if="vista === 'resumen' || vista === 'servicios'" class="w-full">
                        <UCard
                            ><template #header
                                ><div class="flex items-center justify-between">
                                    <h2 class="font-semibold">
                                        Servicios más solicitados
                                    </h2>
                                    <UButton
                                        v-if="puedeExportar"
                                        label="CSV"
                                        size="xs"
                                        color="neutral"
                                        variant="outline"
                                        @click="exportar('servicios')"
                                    /></div
                            ></template>
                            <div class="space-y-3">
                                <div
                                    v-for="s in serviciosSolicitados"
                                    :key="s.nombre"
                                >
                                    <div
                                        class="mb-1 flex justify-between gap-3 text-sm"
                                    >
                                        <span>{{ s.nombre }}</span
                                        ><span
                                            >{{
                                                s.solicitudes
                                            }}
                                            solicitudes</span
                                        >
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-elevated"
                                    >
                                        <div
                                            class="h-full bg-primary"
                                            :style="{
                                                width: `${(Number(s.solicitudes) / maxServicio) * 100}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                                <p
                                    v-if="!serviciosSolicitados.length"
                                    class="py-6 text-center text-muted"
                                >
                                    Sin servicios.
                                </p>
                            </div></UCard
                        >
                    </section>
                    <section v-if="vista === 'resumen' || vista === 'repuestos'" class="w-full">
                        <UCard
                            ><template #header
                                ><div class="flex items-center justify-between">
                                    <h2 class="font-semibold">
                                        Repuestos más utilizados
                                    </h2>
                                    <UButton
                                        v-if="puedeExportar"
                                        label="CSV"
                                        size="xs"
                                        color="neutral"
                                        variant="outline"
                                        @click="exportar('repuestos')"
                                    /></div
                            ></template>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[480px] text-sm">
                                    <thead>
                                        <tr
                                            class="border-b border-default text-left"
                                        >
                                            <th class="p-2">Repuesto</th>
                                            <th class="p-2 text-right">
                                                Cantidad
                                            </th>
                                            <th class="p-2 text-right">
                                                Órdenes
                                            </th>
                                            <th class="p-2 text-right">
                                                Valor
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="r in repuestosUtilizados"
                                            :key="r.codigo"
                                            class="border-b border-default"
                                        >
                                            <td class="p-2">
                                                <p class="font-medium">
                                                    {{ r.codigo }} ·
                                                    {{ r.nombre }}
                                                </p>
                                                <p class="text-xs text-muted">
                                                    {{ r.unidad }}
                                                </p>
                                            </td>
                                            <td class="p-2 text-right">
                                                {{
                                                    Number(
                                                        r.cantidad,
                                                    ).toLocaleString("es-CO")
                                                }}
                                            </td>
                                            <td class="p-2 text-right">
                                                {{ r.ordenes }}
                                            </td>
                                            <td class="p-2 text-right">
                                                $ {{ dinero(r.valor) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p
                                    v-if="!repuestosUtilizados.length"
                                    class="py-6 text-center text-muted"
                                >
                                    Sin repuestos.
                                </p>
                            </div></UCard
                        >
                    </section>
                </div>

                <section
                    v-if="vista === 'resumen' || vista === 'vehiculos-clientes'"
                    class="w-full"
                >
                    <UCard
                        ><template #header
                            ><div class="flex items-center justify-between">
                                <div>
                                    <h2 class="font-semibold">
                                        Vehículos atendidos por cliente
                                    </h2>
                                    <p class="text-sm text-muted">
                                        Vehículos distintos y visitas
                                        finalizadas
                                    </p>
                                </div>
                                <UButton
                                    v-if="puedeExportar"
                                    label="Exportar CSV"
                                    size="sm"
                                    color="neutral"
                                    variant="outline"
                                    icon="i-lucide-download"
                                    @click="exportar('vehiculos_cliente')"
                                /></div
                        ></template>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[480px] text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-default text-left"
                                    >
                                        <th class="p-3">Cliente</th>
                                        <th class="p-3 text-right">
                                            Vehículos
                                        </th>
                                        <th class="p-3 text-right">
                                            Visitas finalizadas
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="c in vehiculosPorCliente"
                                        :key="c.cliente"
                                        class="border-b border-default"
                                    >
                                        <td class="p-3 font-medium">
                                            {{ c.cliente }}
                                        </td>
                                        <td class="p-3 text-right">
                                            {{ c.vehiculos }}
                                        </td>
                                        <td class="p-3 text-right">
                                            {{ c.visitas }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p
                                v-if="!vehiculosPorCliente.length"
                                class="py-6 text-center text-muted"
                            >
                                Sin resultados.
                            </p>
                        </div></UCard
                    >
                </section>
            </div></template
        >
    </UDashboardPanel>
</template>
