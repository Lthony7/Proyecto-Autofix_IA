<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
interface Cliente {
    id: string;
    nombre: string;
    vehiculos: { id: string; label: string }[];
}
const props = defineProps<{ clientes: Cliente[]; categorias: string[] }>();
const paso = ref(1),
    procesando = ref(false);
const pasos = [
    { titulo: "Paso 1", descripcion: "Vehículo" },
    { titulo: "Paso 2", descripcion: "Síntoma" },
    { titulo: "Paso 3", descripcion: "Seguridad" },
    { titulo: "Paso 4", descripcion: "Confirmación" },
];
const state = reactive({
    clienteId: "",
    vehiculoId: "",
    kilometraje: null as number | null,
    categoriaFalla: "",
    sintomaPrincipal: "",
    momentoOcurre: "",
    frecuencia: "",
    tiempoDesdeInicio: "",
    senales: "",
    lucesTablero: "",
    perdidaPotenciaArranque: "",
    puedeCircular: "si",
    urgenciaPercibida: "media",
    reparacionesRecientes: "",
    observaciones: "",
});
const errors = computed<Record<string, string>>(
    () => usePage().props.errors as Record<string, string>,
);
const vehiculos = computed(
    () => props.clientes.find((c) => c.id === state.clienteId)?.vehiculos ?? [],
);
watch(
    () => state.clienteId,
    () => (state.vehiculoId = ""),
);
function enviar() {
    procesando.value = true;
    router.post(route("ia.store"), state, {
        onFinish: () => (procesando.value = false),
    });
}
</script>
<template>
    <Head title="Nueva orientación IA" /><UDashboardPanel
        ><template #header
            ><UDashboardNavbar title="Orientación inicial con IA" /></template
        ><template #body
            ><div class="flex min-h-[calc(100vh-7rem)] w-full flex-col">
                <div class="mb-7 px-1 sm:px-6">
                    <div class="grid grid-cols-4">
                        <div
                            v-for="(item, index) in pasos"
                            :key="item.titulo"
                            class="min-w-0 text-center"
                        >
                            <p
                                class="text-xs font-bold uppercase transition-colors duration-500"
                                :class="
                                    index + 1 <= paso
                                        ? 'text-primary'
                                        : 'text-muted'
                                "
                            >
                                {{ item.titulo }}
                            </p>
                            <p
                                class="mt-0.5 truncate text-xs font-medium transition-colors duration-500 sm:text-sm"
                                :class="
                                    index + 1 <= paso
                                        ? 'text-highlighted'
                                        : 'text-muted'
                                "
                            >
                                {{ item.descripcion }}
                            </p>
                        </div>
                    </div>

                    <div class="relative mt-3">
                        <div
                            class="absolute top-1/2 right-[12.5%] left-[12.5%] h-2 -translate-y-1/2 overflow-hidden rounded-full bg-elevated"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-[width] duration-500 ease-out"
                                :style="{ width: `${((paso - 1) / 3) * 100}%` }"
                            />
                        </div>
                        <div class="relative grid grid-cols-4">
                            <div
                                v-for="n in 4"
                                :key="n"
                                class="flex justify-center"
                            >
                                <span
                                    class="size-4 rounded-full border-2 transition-all duration-500"
                                    :class="
                                        n <= paso
                                            ? 'scale-110 border-primary bg-primary shadow-sm shadow-primary/40'
                                            : 'border-muted bg-default'
                                    "
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <form
                    class="flex flex-1 flex-col"
                    @submit.prevent="paso < 4 ? paso++ : enviar()"
                >
                    <UCard v-if="paso === 1" class="w-full flex-1"
                        ><template #header
                            ><div>
                                <p class="text-sm text-primary">Paso 1 de 4</p>
                                <h2 class="text-xl font-semibold">Vehículo</h2>
                            </div></template
                        >
                        <div class="grid gap-5 md:grid-cols-2">
                            <UFormField
                                label="Cliente"
                                required
                                :error="errors.clienteId"
                                ><USelect
                                    v-model="state.clienteId"
                                    class="w-full"
                                    :items="
                                        clientes.map((c) => ({
                                            label: c.nombre,
                                            value: c.id,
                                        }))
                                    " /></UFormField
                            ><UFormField
                                label="Vehículo"
                                required
                                :error="errors.vehiculoId"
                                ><USelect
                                    v-model="state.vehiculoId"
                                    class="w-full"
                                    :items="
                                        vehiculos.map((v) => ({
                                            label: v.label,
                                            value: v.id,
                                        }))
                                    " /></UFormField
                            ><UFormField
                                label="Kilometraje actual"
                                required
                                :error="errors.kilometraje"
                                ><UInput
                                    v-model.number="state.kilometraje"
                                    type="number"
                                    min="0"
                                    class="w-full" /></UFormField
                            ><UFormField
                                label="Categoría inicial"
                                required
                                :error="errors.categoriaFalla"
                                ><USelect
                                    v-model="state.categoriaFalla"
                                    class="w-full"
                                    :items="categorias"
                            /></UFormField></div></UCard
                    ><UCard v-else-if="paso === 2" class="w-full flex-1"
                        ><template #header
                            ><div>
                                <p class="text-sm text-primary">Paso 2 de 4</p>
                                <h2 class="text-xl font-semibold">
                                    Síntoma principal
                                </h2>
                            </div></template
                        >
                        <div class="space-y-5">
                            <UFormField
                                label="¿Qué ocurre?"
                                required
                                :error="errors.sintomaPrincipal"
                                ><UTextarea
                                    v-model="state.sintomaPrincipal"
                                    class="w-full"
                                    :rows="4"
                            /></UFormField>
                            <div class="grid gap-5 md:grid-cols-3">
                                <UFormField
                                    label="Momento en que ocurre"
                                    required
                                    ><UInput
                                        v-model="state.momentoOcurre"
                                        class="w-full"
                                        placeholder="Al frenar, acelerar..." /></UFormField
                                ><UFormField label="Frecuencia" required
                                    ><UInput
                                        v-model="state.frecuencia"
                                        class="w-full"
                                        placeholder="Siempre, ocasional..." /></UFormField
                                ><UFormField label="Desde cuándo" required
                                    ><UInput
                                        v-model="state.tiempoDesdeInicio"
                                        class="w-full"
                                        placeholder="Hace 3 días"
                                /></UFormField>
                            </div></div></UCard
                    ><UCard v-else-if="paso === 3" class="w-full flex-1"
                        ><template #header
                            ><div>
                                <p class="text-sm text-primary">Paso 3 de 4</p>
                                <h2 class="text-xl font-semibold">
                                    Señales y seguridad
                                </h2>
                            </div></template
                        >
                        <div class="grid gap-5 md:grid-cols-2">
                            <UFormField
                                label="Ruidos, vibraciones, humo u olores"
                                ><UTextarea
                                    v-model="state.senales"
                                    class="w-full" /></UFormField
                            ><UFormField label="Luces del tablero"
                                ><UTextarea
                                    v-model="state.lucesTablero"
                                    class="w-full" /></UFormField
                            ><UFormField label="Pérdida de potencia o arranque"
                                ><UTextarea
                                    v-model="state.perdidaPotenciaArranque"
                                    class="w-full" /></UFormField
                            ><UFormField label="¿Puede circular?" required
                                ><USelect
                                    v-model="state.puedeCircular"
                                    class="w-full"
                                    :items="[
                                        { label: 'Sí', value: 'si' },
                                        {
                                            label: 'Con dificultad',
                                            value: 'con_dificultad',
                                        },
                                        { label: 'No', value: 'no' },
                                    ]" /></UFormField
                            ><UFormField label="Urgencia percibida" required
                                ><USelect
                                    v-model="state.urgenciaPercibida"
                                    class="w-full"
                                    :items="[
                                        'baja',
                                        'media',
                                        'alta',
                                        'critica',
                                    ]"
                            /></UFormField></div></UCard
                    ><UCard v-else class="w-full flex-1"
                        ><template #header
                            ><div>
                                <p class="text-sm text-primary">Paso 4 de 4</p>
                                <h2 class="text-xl font-semibold">
                                    Resumen editable
                                </h2>
                            </div></template
                        >
                        <div class="space-y-5">
                            <UFormField
                                label="Reparaciones recientes relacionadas"
                                ><UTextarea
                                    v-model="state.reparacionesRecientes"
                                    class="w-full" /></UFormField
                            ><UFormField label="Observaciones adicionales"
                                ><UTextarea
                                    v-model="state.observaciones"
                                    class="w-full"
                            /></UFormField>
                            <div class="rounded-lg bg-elevated p-4 text-sm">
                                <p>
                                    <strong>Categoría:</strong>
                                    {{ state.categoriaFalla }}
                                </p>
                                <p>
                                    <strong>Síntoma:</strong>
                                    {{ state.sintomaPrincipal }}
                                </p>
                                <p>
                                    <strong>Circulación:</strong>
                                    {{ state.puedeCircular }} ·
                                    <strong>Urgencia:</strong>
                                    {{ state.urgenciaPercibida }}
                                </p>
                            </div>
                            <UAlert
                                color="warning"
                                title="Importante"
                                description="La información generada por Inteligencia Artificial es únicamente una sugerencia inicial. El diagnóstico final debe ser realizado y confirmado por un mecánico autorizado."
                            /></div
                    ></UCard>
                    <div class="mt-5 flex justify-between">
                        <UButton
                            type="button"
                            color="neutral"
                            variant="outline"
                            :label="paso === 1 ? 'Cancelar' : 'Anterior'"
                            @click="
                                paso === 1
                                    ? router.visit(route('ia.index'))
                                    : paso--
                            "
                        /><UButton
                            type="submit"
                            :label="
                                paso < 4 ? 'Continuar' : 'Generar orientación'
                            "
                            :icon="paso === 4 ? 'i-lucide-sparkles' : undefined"
                            :loading="procesando"
                        />
                    </div>
                </form></div></template
    ></UDashboardPanel>
</template>
