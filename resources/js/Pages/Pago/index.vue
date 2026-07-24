<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { usePermissions } from "../../composables/usePermissions";
interface Pago {
    id: string;
    numero: string;
    comprobante_numero: string;
    monto: string;
    moneda: string;
    metodo: string;
    referencia?: string;
    estado: string;
    pagado_en: string;
    motivo_anulacion?: string;
    motivo_reembolso?: string;
    orden: {
        id: string;
        numero: string;
        cliente: { razon_social: string };
        vehiculo: { placa: string };
    };
}
interface Pagina<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}
const props = defineProps<{ pagos: Pagina<Pago>; buscar: string }>();
const { can } = usePermissions();
const busqueda = ref(props.buscar);
function buscar() {
    router.get(
        route("pagos.index"),
        { buscar: busqueda.value },
        { preserveState: true },
    );
}
function anular(pago: Pago) {
    const motivo = prompt("Motivo de la anulación");
    if (!motivo) return;
    router.post(
        route("pagos.anular", pago.id),
        { motivo },
        { preserveScroll: true },
    );
}
function reembolsar(pago: Pago) {
    const motivo = prompt("Motivo del reembolso total");
    if (!motivo) return;
    router.post(
        route("pagos.reembolsar", pago.id),
        { motivo },
        { preserveScroll: true },
    );
}
function dinero(valor: string) {
    return Number(valor).toLocaleString("es-CO", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>
<template>
    <Head title="Pagos" /><UDashboardPanel
        ><template #header
            ><UDashboardNavbar title="Pagos y comprobantes"
                ><template #leading
                    ><UDashboardSidebarCollapse /></template></UDashboardNavbar></template
        ><template #body
            ><div class="space-y-6">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p class="text-sm text-muted">
                            Historial financiero inmutable
                        </p>
                        <p class="text-3xl font-semibold">
                            {{ pagos.total }} pagos
                        </p>
                    </div>
                    <form class="flex gap-2" @submit.prevent="buscar">
                        <UInput
                            v-model="busqueda"
                            icon="i-lucide-search"
                            placeholder="Pago, orden, cliente o referencia"
                            class="w-full sm:w-80"
                        /><UButton type="submit" label="Buscar" />
                    </form>
                </div>
                <div class="overflow-hidden rounded-lg border border-default">
                    <ul v-if="pagos.data.length" class="divide-y divide-default">
                        <li v-for="p in pagos.data" :key="p.id" class="p-4 sm:p-5">
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold">{{ p.numero }}</p>
                                    <UBadge
                                        :color="
                                            p.estado === 'registrado'
                                                ? 'success'
                                                : 'neutral'
                                        "
                                        >{{ p.estado }}</UBadge
                                    >
                                </div>
                                <Link
                                    :href="route('ordenes.show', p.orden.id)"
                                    class="mt-1 block text-sm text-primary hover:underline"
                                    >{{ p.orden.numero }} ·
                                    {{ p.orden.cliente.razon_social }} ·
                                    {{ p.orden.vehiculo.placa }}</Link
                                >
                                <p class="mt-1 text-xs text-muted">
                                    {{ p.metodo }} ·
                                    {{
                                        new Date(p.pagado_en).toLocaleString(
                                            "es-CO",
                                        )
                                    }}
                                    · Comprobante {{ p.comprobante_numero }}
                                </p>
                                <p
                                    v-if="p.referencia"
                                    class="text-xs text-muted"
                                >
                                    Referencia: {{ p.referencia }}
                                </p>
                                <p
                                    v-if="p.motivo_anulacion"
                                    class="mt-1 text-xs text-error"
                                >
                                    Anulado: {{ p.motivo_anulacion }}
                                </p>
                                <p
                                    v-if="p.motivo_reembolso"
                                    class="mt-1 text-xs text-warning"
                                >
                                    Reembolsado: {{ p.motivo_reembolso }}
                                </p>
                            </div>
                            <div class="shrink-0 text-left sm:text-right">
                                <p
                                    class="text-2xl font-semibold"
                                    :class="
                                        p.estado !== 'registrado'
                                            ? 'line-through text-muted'
                                            : ''
                                    "
                                >
                                    $ {{ dinero(p.monto) }}
                                </p>
                                <p class="text-xs text-muted">{{ p.moneda }}</p>
                                <div class="mt-2 flex flex-wrap gap-1 sm:justify-end">
                                    <Link :href="route('pagos.comprobante', p.id)">
                                        <UButton
                                            size="xs"
                                            color="neutral"
                                            variant="outline"
                                            label="Ver comprobante"
                                            icon="i-lucide-receipt-text"
                                        />
                                    </Link>
                                    <UButton
                                        v-if="
                                            can('pagos.anular') &&
                                            p.estado === 'registrado'
                                        "
                                        size="xs"
                                        color="error"
                                        variant="ghost"
                                        label="Anular pago"
                                        @click="anular(p)"
                                    />
                                    <UButton
                                        v-if="can('pagos.reembolsar') && p.estado === 'registrado'"
                                        size="xs"
                                        color="warning"
                                        variant="ghost"
                                        label="Reembolsar"
                                        @click="reembolsar(p)"
                                    />
                                </div>
                            </div>
                        </div></li>
                    </ul><p v-else class="px-4 py-8 text-center text-muted">
                            No se encontraron pagos.
                        </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in pagos.links"
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
                </div></div></template
    ></UDashboardPanel>
</template>
