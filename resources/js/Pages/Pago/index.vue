<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
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
interface OrdenPendiente {
    id: string;
    numero: string;
    cliente: string;
    vehiculo: string;
    total: string;
    pagado: string;
    saldo: string;
}
const props = defineProps<{
    pagos: Pagina<Pago>;
    buscar: string;
    ordenesPendientes: OrdenPendiente[];
}>();
const { can } = usePermissions();
const busqueda = ref(props.buscar);
const formularioAbierto = ref(false);
const procesando = ref(false);
const fechaLocal = () => {
    const fecha = new Date();
    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());
    return fecha.toISOString().slice(0, 16);
};
const manual = reactive({
    idempotenciaClave: crypto.randomUUID(),
    ordenId: "",
    monto: "",
    metodo: "efectivo",
    referencia: "",
    observaciones: "",
    pagadoEn: fechaLocal(),
});
const ordenSeleccionada = computed(() =>
    props.ordenesPendientes.find((orden) => orden.id === manual.ordenId),
);
const errors = computed<Record<string, string>>(
    () => usePage().props.errors as Record<string, string>,
);
const opcionesOrden = computed(() =>
    props.ordenesPendientes.map((orden) => ({
        label: `${orden.numero} · ${orden.vehiculo} · saldo $ ${dinero(orden.saldo)}`,
        value: orden.id,
    })),
);
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
function registrarManual() {
    if (!manual.ordenId) return;
    procesando.value = true;
    router.post(
        route("pagos.store", manual.ordenId),
        {
            monto: manual.monto,
            idempotenciaClave: manual.idempotenciaClave,
            metodo: manual.metodo,
            referencia: manual.referencia || undefined,
            observaciones: manual.observaciones || undefined,
            pagadoEn: manual.pagadoEn,
            ver_comprobante: true,
        },
        {
            onSuccess: () => {
                manual.idempotenciaClave = crypto.randomUUID();
            },
            onFinish: () => (procesando.value = false),
        },
    );
}
function usarSaldoCompleto() {
    if (ordenSeleccionada.value) manual.monto = ordenSeleccionada.value.saldo;
}
</script>
<template>
    <Head title="Pagos" /><UDashboardPanel
        ><template #header
            ><UDashboardNavbar title="Pagos y comprobantes"
                ><template #leading><UDashboardSidebarCollapse /></template
                ><template #right
                    ><UButton
                        v-if="can('pagos.registrar')"
                        :label="
                            formularioAbierto
                                ? 'Cerrar registro'
                                : 'Registrar pago manual'
                        "
                        :icon="
                            formularioAbierto
                                ? 'i-lucide-x'
                                : 'i-lucide-receipt-text'
                        "
                        :color="formularioAbierto ? 'neutral' : 'primary'"
                        :variant="formularioAbierto ? 'outline' : 'solid'"
                        @click="
                            formularioAbierto = !formularioAbierto
                        " /></template></UDashboardNavbar></template
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
                <UCard
                    v-if="formularioAbierto"
                    class="border-primary/20 bg-primary/[0.025]"
                >
                    <template #header>
                        <div class="flex items-start gap-3">
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
                                ><UIcon
                                    name="i-lucide-hand-coins"
                                    class="size-5"
                            /></span>
                            <div>
                                <p
                                    class="font-mono text-xs font-bold uppercase tracking-[0.16em] text-primary"
                                >
                                    Registro de caja
                                </p>
                                <h2 class="mt-1 text-xl font-bold">
                                    Nuevo pago y comprobante
                                </h2>
                                <p class="mt-1 text-sm text-muted">
                                    Selecciona una orden con saldo. El sistema
                                    generará automáticamente un comprobante
                                    inmutable.
                                </p>
                            </div>
                        </div>
                    </template>
                    <form class="space-y-5" @submit.prevent="registrarManual">
                        <UFormField
                            label="Orden de trabajo"
                            required
                            :error="errors.orden"
                        >
                            <USelect
                                v-model="manual.ordenId"
                                :items="opcionesOrden"
                                class="w-full"
                                placeholder="Selecciona una orden con saldo pendiente"
                            />
                        </UFormField>

                        <div
                            v-if="ordenSeleccionada"
                            class="grid gap-3 rounded-xl border border-primary/15 bg-primary/5 p-4 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold uppercase tracking-wide text-muted"
                                >
                                    Orden
                                </p>
                                <p
                                    class="mt-1 font-mono font-bold text-primary"
                                >
                                    {{ ordenSeleccionada.numero }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-bold uppercase tracking-wide text-muted"
                                >
                                    Cliente y vehículo
                                </p>
                                <p class="mt-1 font-medium">
                                    {{ ordenSeleccionada.cliente }}
                                </p>
                                <p class="text-xs text-muted">
                                    {{ ordenSeleccionada.vehiculo }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-bold uppercase tracking-wide text-muted"
                                >
                                    Total / pagado
                                </p>
                                <p class="mt-1 font-medium">
                                    $ {{ dinero(ordenSeleccionada.total) }}
                                </p>
                                <p class="text-xs text-muted">
                                    Pagado $
                                    {{ dinero(ordenSeleccionada.pagado) }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-bold uppercase tracking-wide text-muted"
                                >
                                    Saldo pendiente
                                </p>
                                <p class="mt-1 text-xl font-black text-primary">
                                    $ {{ dinero(ordenSeleccionada.saldo) }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <UFormField
                                label="Monto"
                                required
                                :error="errors.monto"
                            >
                                <div class="flex gap-2">
                                    <UInput
                                        v-model="manual.monto"
                                        type="number"
                                        min="0.01"
                                        :max="ordenSeleccionada?.saldo"
                                        step="0.01"
                                        class="w-full"
                                        placeholder="0.00"
                                    /><UButton
                                        type="button"
                                        label="Saldo total"
                                        color="neutral"
                                        variant="outline"
                                        :disabled="!ordenSeleccionada"
                                        @click="usarSaldoCompleto"
                                    />
                                </div>
                            </UFormField>
                            <UFormField
                                label="Método de pago"
                                required
                                :error="errors.metodo"
                                ><USelect
                                    v-model="manual.metodo"
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
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                label="Fecha y hora"
                                required
                                :error="errors.pagadoEn || errors.pagado_en"
                                ><UInput
                                    v-model="manual.pagadoEn"
                                    type="datetime-local"
                                    :max="fechaLocal()"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                label="Referencia"
                                :required="['tarjeta', 'transferencia'].includes(manual.metodo)"
                                :error="errors.referencia"
                                ><UInput
                                    v-model="manual.referencia"
                                    class="w-full"
                                    placeholder="Transacción, recibo externo..."
                            /></UFormField>
                        </div>
                        <UFormField
                            label="Observaciones"
                            :error="errors.observaciones"
                            ><UTextarea
                                v-model="manual.observaciones"
                                class="w-full"
                                :rows="3"
                                placeholder="Información adicional del pago"
                        /></UFormField>
                        <UAlert
                            color="primary"
                            variant="subtle"
                            icon="i-lucide-shield-check"
                            title="Comprobante automático"
                            description="Al registrar el pago se congelarán conceptos, valores, saldo y datos financieros para conservar su evidencia histórica."
                        />
                        <div
                            class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                        >
                            <UButton
                                type="button"
                                label="Cancelar"
                                color="neutral"
                                variant="outline"
                                @click="formularioAbierto = false"
                            /><UButton
                                type="submit"
                                label="Registrar y ver comprobante"
                                icon="i-lucide-file-check-2"
                                :loading="procesando"
                                :disabled="!manual.ordenId"
                            />
                        </div>
                    </form>
                    <div
                        v-if="!ordenesPendientes.length"
                        class="mt-5 rounded-xl border border-dashed border-default p-8 text-center"
                    >
                        <UIcon
                            name="i-lucide-circle-check-big"
                            class="mx-auto size-8 text-success"
                        />
                        <p class="mt-2 font-semibold">
                            No hay saldos pendientes
                        </p>
                        <p class="text-sm text-muted">
                            Todas las órdenes visibles están pagadas o todavía
                            no tienen conceptos cobrables.
                        </p>
                    </div>
                </UCard>
                <div class="overflow-hidden rounded-lg border border-default">
                    <ul
                        v-if="pagos.data.length"
                        class="divide-y divide-default"
                    >
                        <li
                            v-for="p in pagos.data"
                            :key="p.id"
                            class="p-4 sm:p-5"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p class="font-semibold">
                                            {{ p.numero }}
                                        </p>
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
                                        :href="
                                            route('ordenes.show', p.orden.id)
                                        "
                                        class="mt-1 block text-sm text-primary hover:underline"
                                        >{{ p.orden.numero }} ·
                                        {{ p.orden.cliente.razon_social }} ·
                                        {{ p.orden.vehiculo.placa }}</Link
                                    >
                                    <p class="mt-1 text-xs text-muted">
                                        {{ p.metodo }} ·
                                        {{
                                            new Date(
                                                p.pagado_en,
                                            ).toLocaleString("es-CO")
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
                                    <p class="text-xs text-muted">
                                        {{ p.moneda }}
                                    </p>
                                    <div
                                        class="mt-2 flex flex-wrap gap-1 sm:justify-end"
                                    >
                                        <Link
                                            :href="
                                                route('pagos.comprobante', p.id)
                                            "
                                        >
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
                                            v-if="
                                                can('pagos.reembolsar') &&
                                                p.estado === 'registrado'
                                            "
                                            size="xs"
                                            color="warning"
                                            variant="ghost"
                                            label="Reembolsar"
                                            @click="reembolsar(p)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="px-4 py-8 text-center text-muted">
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
