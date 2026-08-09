<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref } from "vue";
import AutofixLogo from "../../components/AutofixLogo.vue";
import { usePermissions } from "../../composables/usePermissions";
const props = defineProps<{
    pago: any;
    identidad: {
        clienteNombre: string;
        clienteDocumento: string;
        ordenNumero: string;
        vehiculoPlaca: string;
        vehiculoDescripcion: string;
        facturaNumero?: string;
    };
    conceptos: {
        tipo: string;
        codigo?: string;
        descripcion: string;
        cantidad: string;
        precioUnitario: string;
        subtotal: string;
    }[];
    finanzas: {
        servicios: string;
        repuestos: string;
        descuento: string;
        impuesto: string;
        total: string;
        pagado: string;
        saldo: string;
        estado: string;
        moneda: string;
    };
    factura?: {
        numero: string;
        descuento: string;
        impuesto: string;
        total: string;
    };
    reconstruido: boolean;
}>();
const { can } = usePermissions();
const enviando = ref(false);
function dinero(v: string) {
    return Number(v).toLocaleString("es-EC", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
function enviarCorreo() {
    router.post(
        route("pagos.enviar", props.pago.id),
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
    <Head :title="`Comprobante ${pago.comprobante_numero}`" />
    <UDashboardPanel>
        <template #header
            ><UDashboardNavbar title="Comprobante de pago"
                ><template #leading><UDashboardSidebarCollapse /></template
                ><template #right
                    ><div class="flex gap-2 print:hidden">
                        <Link :href="route('pagos.index')"
                            ><UButton
                                label="Volver"
                                color="neutral"
                                variant="outline"
                                icon="i-lucide-arrow-left" /></Link
                        ><UButton
                            v-if="!reconstruido"
                            :href="route('pagos.pdf', { pago: pago.id, download: 1 })"
                            aria-label="Descargar comprobante de pago en PDF"
                            label="Descargar PDF"
                            icon="i-lucide-download"
                        />
                        <UButton
                            v-if="!reconstruido && can('pagos.enviar')"
                            label="Enviar por correo"
                            aria-label="Enviar comprobante al correo del cliente"
                            icon="i-lucide-mail"
                            color="primary"
                            variant="soft"
                            :loading="enviando"
                            :disabled="enviando"
                            @click="enviarCorreo"
                        /></div></template></UDashboardNavbar
        ></template>
        <template #body
            ><div class="mx-auto max-w-4xl">
                <UAlert
                    v-if="reconstruido"
                    class="mb-4 print:hidden"
                    color="warning"
                    variant="subtle"
                    title="Comprobante histórico reconstruido"
                    description="Este pago es anterior al sistema de instantáneas; el detalle se reconstruyó con la información histórica disponible."
                />
                <article
                    class="rounded-xl border border-default bg-default p-6 shadow-sm print:border-0 print:p-0 print:shadow-none"
                >
                    <header
                        class="flex flex-col gap-5 border-b border-default pb-5 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <AutofixLogo class="h-20 w-24" />
                            <div>
                                <h1 class="text-2xl font-bold">AUTOFIX</h1>
                                <p class="text-sm text-muted">
                                    Taller automotriz
                                </p>
                            </div>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-sm text-muted">Comprobante</p>
                            <p class="text-xl font-semibold">
                                {{ pago.comprobante_numero }}
                            </p>
                            <p class="text-sm">
                                {{
                                    new Date(pago.pagado_en).toLocaleString(
                                        "es-EC",
                                    )
                                }}
                            </p>
                            <UBadge
                                class="mt-2"
                                :color="
                                    pago.estado === 'registrado'
                                        ? 'success'
                                        : 'error'
                                "
                                >{{ pago.estado }}</UBadge
                            >
                        </div>
                    </header>
                    <section
                        class="grid gap-4 border-b border-default py-5 sm:grid-cols-2"
                    >
                        <div>
                            <p
                                class="text-xs uppercase tracking-wide text-muted"
                            >
                                Cliente
                            </p>
                            <p class="font-semibold">
                                {{ identidad.clienteNombre }}
                            </p>
                            <p class="text-sm">
                                {{ identidad.clienteDocumento }}
                            </p>
                        </div>
                        <div class="sm:text-right">
                            <p
                                class="text-xs uppercase tracking-wide text-muted"
                            >
                                Orden, factura y vehículo
                            </p>
                            <p class="font-semibold">
                                {{ identidad.ordenNumero }} ·
                                {{ identidad.vehiculoPlaca }}
                            </p>
                            <p class="text-sm">
                                Factura
                                {{ identidad.facturaNumero || "histórica" }} ·
                                {{ identidad.vehiculoDescripcion }}
                            </p>
                        </div>
                    </section>
                    <section class="py-5">
                        <h2 class="mb-3 font-semibold">Detalle cobrado</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-default text-left"
                                    >
                                        <th class="py-2">Concepto</th>
                                        <th class="py-2 text-right">
                                            Cantidad
                                        </th>
                                        <th class="py-2 text-right">Precio</th>
                                        <th class="py-2 text-right">
                                            Subtotal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(c, i) in conceptos"
                                        :key="i"
                                        class="border-b border-default"
                                    >
                                        <td class="py-2">
                                            <p>{{ c.descripcion }}</p>
                                            <p class="text-xs text-muted">
                                                {{ c.tipo
                                                }}{{
                                                    c.codigo
                                                        ? " · " + c.codigo
                                                        : ""
                                                }}
                                            </p>
                                        </td>
                                        <td class="py-2 text-right">
                                            {{ c.cantidad }}
                                        </td>
                                        <td class="py-2 text-right">
                                            $ {{ dinero(c.precioUnitario) }}
                                        </td>
                                        <td class="py-2 text-right">
                                            $ {{ dinero(c.subtotal) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section
                        class="ml-auto grid max-w-sm gap-2 border-t border-default pt-4 text-sm"
                    >
                        <div class="flex justify-between">
                            <span>Servicios</span
                            ><span>$ {{ dinero(finanzas.servicios) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Repuestos</span
                            ><span>$ {{ dinero(finanzas.repuestos) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Descuento</span
                            ><span>-$ {{ dinero(finanzas.descuento) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Impuestos</span
                            ><span>$ {{ dinero(finanzas.impuesto) }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t border-default pt-2 text-base font-semibold"
                        >
                            <span>Total orden</span
                            ><span>$ {{ dinero(finanzas.total) }}</span>
                        </div>
                        <div class="flex justify-between text-primary">
                            <span>Este pago</span
                            ><span>$ {{ dinero(pago.monto) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pagado acumulado</span
                            ><span>$ {{ dinero(finanzas.pagado) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Saldo pendiente</span
                            ><span>$ {{ dinero(finanzas.saldo) }}</span>
                        </div>
                    </section>
                    <footer class="mt-8 border-t border-default pt-4 text-sm">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <p>
                                <span class="text-muted">Método:</span>
                                {{ pago.metodo }}
                            </p>
                            <p>
                                <span class="text-muted">Referencia:</span>
                                {{ pago.referencia || "No aplica" }}
                            </p>
                        </div>
                        <p v-if="pago.observaciones" class="mt-2">
                            <span class="text-muted">Observaciones:</span>
                            {{ pago.observaciones }}
                        </p>
                        <p v-if="pago.motivo_anulacion" class="mt-2 text-error">
                            Anulado: {{ pago.motivo_anulacion }}
                        </p>
                        <p
                            v-if="pago.motivo_reembolso"
                            class="mt-2 text-warning"
                        >
                            Reembolsado: {{ pago.motivo_reembolso }}
                        </p>
                        <p class="mt-6 text-center text-xs text-muted">
                            Comprobante generado por AUTOFIX. Documento de
                            control interno del taller.
                        </p>
                    </footer>
                </article>
            </div></template
        >
    </UDashboardPanel>
</template>

<style scoped>
@media print {
    :global(aside),
    :global(.print\:hidden) {
        display: none !important;
    }
    :global(body) {
        background: white !important;
        color: black !important;
    }
    article {
        color: black;
    }
}
</style>
