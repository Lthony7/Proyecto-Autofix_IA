<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import { route } from "ziggy-js";
import { usePermissions } from "../../composables/usePermissions";
interface Linea {
    id: string;
    tipo: string;
    codigo?: string;
    descripcion: string;
    cantidad: string;
    precio_unitario: string;
    subtotal: string;
}
interface Historial {
    id: string;
    evento: string;
    datos?: Record<string, string>;
    created_at: string;
}
interface Factura {
    id: string;
    numero: string;
    version: number;
    reemplaza?: { id: string; numero: string };
    cliente_tipo_documento: string;
    cliente_documento: string;
    cliente_nombre: string;
    cliente_direccion: string;
    cliente_email: string;
    vehiculo_placa: string;
    subtotal: string;
    descuento: string;
    base_impuesto: string;
    tasa_impuesto: string;
    impuesto: string;
    total: string;
    moneda: string;
    estado: string;
    emitida_en: string;
    vence_en?: string;
    observaciones?: string;
    motivo_anulacion?: string;
    orden: { id: string; numero: string };
    lineas: Linea[];
    historial: Historial[];
}
const props = defineProps<{ factura: Factura }>();
const { can } = usePermissions();
const enviando = ref(false);
function dinero(valor: string) {
    return Number(valor).toLocaleString("es-CO", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
function anular() {
    const motivo = prompt("Motivo de la anulación");
    if (!motivo) return;
    router.post(
        route("facturacion.anular", props.factura.id),
        { motivo },
        { preserveScroll: true },
    );
}
function enviarCorreo() {
    router.post(
        route("facturacion.enviar", props.factura.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (enviando.value = true),
            onFinish: () => (enviando.value = false),
        },
    );
}
</script>
<template>
    <Head :title="factura.numero" /><UDashboardPanel
        ><template #header
            ><UDashboardNavbar :title="factura.numero"
                ><template #right
                    ><UBadge
                        size="lg"
                        :color="
                            factura.estado === 'emitida' ? 'success' : 'neutral'
                        "
                        >{{ factura.estado }}</UBadge
                    ></template
                ></UDashboardNavbar
            ></template
        ><template #body
            ><div class="mx-auto max-w-5xl space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link :href="route('facturacion.index')"
                        ><UButton
                            icon="i-lucide-arrow-left"
                            color="neutral"
                            variant="ghost"
                            label="Volver"
                    /></Link>
                    <div class="flex gap-2">
                        <UButton
                            :href="route('facturacion.pdf', { factura: factura.id, download: 1 })"
                            aria-label="Descargar factura en PDF"
                            label="Descargar PDF"
                            icon="i-lucide-download"
                            color="neutral"
                            variant="outline"
                        />
                        <UButton
                            v-if="can('facturas.enviar')"
                            label="Enviar por correo"
                            aria-label="Enviar factura al correo del cliente"
                            icon="i-lucide-mail"
                            color="primary"
                            variant="soft"
                            :loading="enviando"
                            :disabled="enviando"
                            @click="enviarCorreo"
                        />
                        <Link :href="route('ordenes.show', factura.orden.id)"
                            ><UButton
                                color="neutral"
                                variant="outline"
                                :label="`Ver ${factura.orden.numero}`" /></Link
                        ><UButton
                            v-if="
                                can('facturas.editar') &&
                                factura.estado === 'emitida'
                            "
                            color="error"
                            variant="soft"
                            label="Anular factura"
                            @click="anular"
                        />
                    </div>
                </div>
                <UCard
                    ><template #header
                        ><div
                            class="flex flex-col gap-4 sm:flex-row sm:justify-between"
                        >
                            <div>
                                <p class="text-sm text-muted">
                                    AUTOFIX IA · Factura interna de servicio
                                </p>
                                <h1 class="text-2xl font-semibold">
                                    {{ factura.numero }}
                                </h1>
                                <p class="text-sm text-muted">
                                    Emitida
                                    {{
                                        new Date(
                                            factura.emitida_en,
                                        ).toLocaleString("es-CO")
                                    }}
                                </p>
                                <p class="text-xs text-muted">
                                    Versión {{ factura.version }}
                                    <span v-if="factura.reemplaza"> · Reemplaza {{ factura.reemplaza.numero }}</span>
                                </p>
                            </div>
                            <div class="sm:text-right">
                                <p class="text-sm text-muted">Cliente</p>
                                <p class="font-semibold">
                                    {{ factura.cliente_nombre }}
                                </p>
                                <p class="text-sm">
                                    {{ factura.cliente_tipo_documento }}
                                    {{ factura.cliente_documento }}
                                </p>
                                <p class="text-sm text-muted">
                                    {{ factura.cliente_email }}
                                </p>
                            </div>
                        </div></template
                    >
                    <div
                        class="grid gap-4 border-b border-default pb-5 sm:grid-cols-3"
                    >
                        <div>
                            <p class="text-xs text-muted">
                                Dirección facturada
                            </p>
                            <p>{{ factura.cliente_direccion }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted">Orden y vehículo</p>
                            <p>
                                {{ factura.orden.numero }} ·
                                {{ factura.vehiculo_placa }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted">Vencimiento</p>
                            <p>
                                {{
                                    factura.vence_en
                                        ? factura.vence_en.slice(0, 10)
                                        : "Sin vencimiento"
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr
                                    class="border-b border-default text-left text-muted"
                                >
                                    <th class="py-3">Concepto</th>
                                    <th class="py-3 text-right">Cantidad</th>
                                    <th class="py-3 text-right">Precio</th>
                                    <th class="py-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="l in factura.lineas"
                                    :key="l.id"
                                    class="border-b border-default"
                                >
                                    <td class="py-3">
                                        <UBadge
                                            size="xs"
                                            color="neutral"
                                            variant="subtle"
                                            >{{ l.tipo }}</UBadge
                                        ><span class="ml-2"
                                            >{{
                                                l.codigo
                                                    ? `${l.codigo} · `
                                                    : ""
                                            }}{{ l.descripcion }}</span
                                        >
                                    </td>
                                    <td class="py-3 text-right">
                                        {{ l.cantidad }}
                                    </td>
                                    <td class="py-3 text-right">
                                        $ {{ dinero(l.precio_unitario) }}
                                    </td>
                                    <td class="py-3 text-right font-medium">
                                        $ {{ dinero(l.subtotal) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="ml-auto mt-6 max-w-sm space-y-2">
                        <div class="flex justify-between">
                            <span class="text-muted">Subtotal</span
                            ><span>$ {{ dinero(factura.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Descuento</span
                            ><span>-$ {{ dinero(factura.descuento) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Base gravable</span
                            ><span>$ {{ dinero(factura.base_impuesto) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted"
                                >Impuesto ({{ factura.tasa_impuesto }}%)</span
                            ><span>$ {{ dinero(factura.impuesto) }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t border-default pt-3 text-xl font-semibold"
                        >
                            <span>Total</span
                            ><span
                                >$ {{ dinero(factura.total) }}
                                {{ factura.moneda }}</span
                            >
                        </div>
                    </div>
                    <p
                        v-if="factura.observaciones"
                        class="mt-6 rounded-lg bg-elevated p-4 text-sm"
                    >
                        {{ factura.observaciones }}
                    </p>
                    <p class="mt-6 border-t border-default pt-4 text-center text-xs text-muted">
                        Documento de control interno del taller. No sustituye una factura electrónica DIAN.
                    </p>
                    <UAlert
                        v-if="factura.estado === 'anulada'"
                        class="mt-6"
                        color="error"
                        variant="subtle"
                        title="Factura anulada"
                        :description="factura.motivo_anulacion" /></UCard
                ><UCard
                    ><template #header
                        ><h2 class="font-semibold">
                            Historial inmutable
                        </h2></template
                    >
                    <div
                        v-for="h in factura.historial"
                        :key="h.id"
                        class="flex justify-between border-b border-default py-3 last:border-0"
                    >
                        <div>
                            <p class="font-medium capitalize">{{ h.evento }}</p>
                            <p
                                v-if="h.datos?.motivo"
                                class="text-sm text-muted"
                            >
                                {{ h.datos.motivo }}
                            </p>
                        </div>
                        <p class="text-sm text-muted">
                            {{ new Date(h.created_at).toLocaleString("es-CO") }}
                        </p>
                    </div></UCard
                >
            </div></template
        ></UDashboardPanel
    >
</template>

<style scoped>
@media print {
    :global(aside), :global(.print\:hidden) { display: none !important; }
    :global(body) { background: white !important; color: black !important; }
}
</style>
