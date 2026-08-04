<script setup lang="ts">
import { reactive } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { usePermissions } from "../../composables/usePermissions";
interface Repuesto {
    id: string;
    codigo: string;
    nombre: string;
    descripcion?: string;
    unidad: string;
    stock_actual: string;
    stock_minimo: string;
    costo_referencia?: string | null;
    precio_venta: string;
    estado: string;
    categoria: { nombre: string };
    proveedor?: { nombre: string };
}
interface Movimiento {
    id: string;
    tipo: string;
    cantidad: string;
    stock_anterior: string;
    stock_resultante: string;
    costo_unitario?: string;
    motivo: string;
    created_at: string;
    movimiento_origen_id?: string;
    orden?: { id: string; numero: string };
    usuario?: { name: string };
    origen?: { tipo: string; cantidad: string };
}
interface Pagina<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}
const props = defineProps<{
    repuesto: Repuesto;
    movimientos: Pagina<Movimiento>;
    filtros: { tipo: string; desde: string; hasta: string };
}>();
const { can } = usePermissions();
const filtros = reactive({
    ...props.filtros,
    tipo: props.filtros.tipo || "todos",
});
function filtrar() {
    router.get(
        route("inventario.repuestos.show", props.repuesto.id),
        {
            tipo: filtros.tipo === "todos" ? undefined : filtros.tipo,
            desde: filtros.desde || undefined,
            hasta: filtros.hasta || undefined,
        },
        { preserveState: true, replace: true },
    );
}
function numero(v: string, d = 3) {
    return Number(v).toLocaleString("es-CO", {
        minimumFractionDigits: d,
        maximumFractionDigits: d,
    });
}
</script>
<template>
    <Head :title="repuesto.codigo" /><UDashboardPanel
        ><template #header
            ><UDashboardNavbar
                :title="`${repuesto.codigo} · ${repuesto.nombre}`"
                ><template #right
                    ><Link
                        v-if="can('inventario.gestionar')"
                        :href="route('inventario.repuestos.edit', repuesto.id)"
                        ><UButton
                            label="Editar"
                            icon="i-lucide-pencil" /></Link></template></UDashboardNavbar></template
        ><template #body
            ><div class="space-y-6">
                <div class="flex">
                    <Link :href="route('inventario.index')"
                        ><UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-arrow-left"
                            label="Volver al inventario"
                    /></Link>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <UCard
                        ><p class="text-sm text-muted">Stock actual</p>
                        <p
                            class="text-3xl font-semibold"
                            :class="
                                Number(repuesto.stock_actual) <=
                                Number(repuesto.stock_minimo)
                                    ? 'text-warning'
                                    : ''
                            "
                        >
                            {{ numero(repuesto.stock_actual) }}
                        </p>
                        <p class="text-xs text-muted">
                            {{ repuesto.unidad }}
                        </p></UCard
                    ><UCard
                        ><p class="text-sm text-muted">Stock mínimo</p>
                        <p class="text-3xl font-semibold">
                            {{ numero(repuesto.stock_minimo) }}
                        </p></UCard
                    ><UCard v-if="can('inventario.gestionar')"
                        ><p class="text-sm text-muted">Costo referencia</p>
                        <p class="text-2xl font-semibold">
                            $ {{ numero(repuesto.costo_referencia, 2) }}
                        </p></UCard
                    ><UCard
                        ><p class="text-sm text-muted">Precio venta</p>
                        <p class="text-2xl font-semibold">
                            $ {{ numero(repuesto.precio_venta, 2) }}
                        </p></UCard
                    >
                </div>
                <UCard
                    ><div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p>
                                {{ repuesto.descripcion || "Sin descripción" }}
                            </p>
                            <p class="mt-2 text-sm text-muted">
                                {{ repuesto.categoria.nombre }} ·
                                {{
                                    repuesto.proveedor?.nombre ||
                                    "Sin proveedor"
                                }}
                            </p>
                        </div>
                        <UBadge
                            :color="
                                repuesto.estado === 'activo'
                                    ? 'success'
                                    : 'neutral'
                            "
                            >{{ repuesto.estado }}</UBadge
                        >
                    </div></UCard
                >
                <section class="space-y-4">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Libro de movimientos
                            </h2>
                            <p class="text-sm text-muted">
                                {{ movimientos.total }} registros inmutables
                            </p>
                        </div>
                        <form
                            class="grid gap-2 sm:grid-cols-4"
                            @submit.prevent="filtrar"
                        >
                            <USelect
                                v-model="filtros.tipo"
                                :items="[
                                    { label: 'Todos los tipos', value: 'todos' },
                                    { label: 'Entradas', value: 'entrada' },
                                    { label: 'Salidas', value: 'salida' },
                                    { label: 'Ajustes', value: 'ajuste' },
                                    {
                                        label: 'Reversiones',
                                        value: 'reversion',
                                    },
                                ]"
                            /><UInput
                                v-model="filtros.desde"
                                type="date"
                            /><UInput
                                v-model="filtros.hasta"
                                type="date"
                            /><UButton type="submit" label="Filtrar" />
                        </form>
                    </div>
                    <div
                        class="overflow-x-auto rounded-lg border border-default"
                    >
                        <table class="w-full min-w-[900px] text-sm">
                            <thead class="bg-elevated/60 text-left">
                                <tr>
                                    <th class="p-3">Fecha</th>
                                    <th class="p-3">Tipo</th>
                                    <th class="p-3 text-right">Cantidad</th>
                                    <th class="p-3 text-right">Anterior</th>
                                    <th class="p-3 text-right">Resultante</th>
                                    <th class="p-3">Origen</th>
                                    <th class="p-3">Responsable y motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="m in movimientos.data"
                                    :key="m.id"
                                    class="border-t border-default"
                                >
                                    <td class="p-3">
                                        {{
                                            new Date(
                                                m.created_at,
                                            ).toLocaleString("es-CO")
                                        }}
                                    </td>
                                    <td class="p-3">
                                        <UBadge
                                            :color="
                                                Number(m.cantidad) > 0
                                                    ? 'success'
                                                    : 'error'
                                            "
                                            variant="subtle"
                                            >{{ m.tipo }}</UBadge
                                        >
                                    </td>
                                    <td class="p-3 text-right font-medium">
                                        {{ Number(m.cantidad) > 0 ? "+" : ""
                                        }}{{ numero(m.cantidad) }}
                                    </td>
                                    <td class="p-3 text-right">
                                        {{ numero(m.stock_anterior) }}
                                    </td>
                                    <td class="p-3 text-right">
                                        {{ numero(m.stock_resultante) }}
                                    </td>
                                    <td class="p-3">
                                        <Link
                                            v-if="m.orden"
                                            :href="
                                                route(
                                                    'ordenes.show',
                                                    m.orden.id,
                                                )
                                            "
                                            class="text-primary hover:underline"
                                            >{{ m.orden.numero }}</Link
                                        ><span
                                            v-else-if="m.origen"
                                            class="text-muted"
                                            >{{ m.origen.tipo }}
                                            {{ m.origen.cantidad }}</span
                                        ><span v-else class="text-muted"
                                            >Manual</span
                                        >
                                    </td>
                                    <td class="p-3">
                                        <p>
                                            {{ m.usuario?.name || "Sistema" }}
                                        </p>
                                        <p class="max-w-sm text-xs text-muted">
                                            {{ m.motivo }}
                                        </p>
                                    </td>
                                </tr>
                                <tr v-if="!movimientos.data.length">
                                    <td
                                        colspan="7"
                                        class="p-10 text-center text-muted"
                                    >
                                        No hay movimientos para los filtros
                                        seleccionados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-for="link in movimientos.links"
                            :key="link.label"
                            :href="link.url || ''"
                            preserve-scroll
                            ><UButton
                                :disabled="!link.url"
                                :variant="link.active ? 'solid' : 'outline'"
                                color="neutral"
                                size="sm"
                                ><span v-html="link.label" /></UButton
                        ></Link>
                    </div>
                </section></div></template
    ></UDashboardPanel>
</template>
