<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { usePermissions } from "../../composables/usePermissions";

interface Especialidad {
    id: string;
    codigo: string;
    nombre: string;
    descripcion?: string;
    estado: string;
    mecanicos_count: number;
    servicios_count: number;
}
interface Servicio {
    id: string;
    codigo: string;
    nombre: string;
    descripcion?: string;
    especialidad?: string;
    especialidadId?: string;
    duracionMinutos: number;
    precioBase: string;
    estado: string;
}
const props = defineProps<{
    vista: string;
    especialidades: Especialidad[];
    servicios: Servicio[];
}>();
const { can } = usePermissions();
const page = usePage();
const errors = computed<Record<string, string>>(
    () => page.props.errors as Record<string, string>,
);
const procesando = ref(false);
const mostrarFormularioEspecialidad = ref(false);
const mostrarFormularioServicio = ref(false);
const especialidad = reactive({ codigo: "", nombre: "", descripcion: "" });
const servicio = reactive({
    codigo: "",
    nombre: "",
    descripcion: "",
    especialidadId: "",
    duracionMinutos: 60,
    precioBase: "0.00",
});
const opciones = computed(() =>
    props.especialidades
        .filter((e) => e.estado === "activo")
        .map((e) => ({ label: e.nombre, value: e.id })),
);
const titulo = computed(() =>
    ({
        resumen: "Servicios del taller",
        especialidades: "Especialidades del taller",
        servicios: "Catálogo de servicios",
    })[props.vista] || "Servicios del taller",
);
function guardarEspecialidad() {
    procesando.value = true;
    router.post(route("especialidades.store"), especialidad, {
        preserveScroll: true,
        onSuccess: () => {
            Object.assign(especialidad, {
                codigo: "",
                nombre: "",
                descripcion: "",
            });
            mostrarFormularioEspecialidad.value = false;
        },
        onFinish: () => {
            procesando.value = false;
        },
    });
}
function guardarServicio() {
    procesando.value = true;
    router.post(route("servicios.store"), servicio, {
        preserveScroll: true,
        onSuccess: () => {
            Object.assign(servicio, {
                codigo: "",
                nombre: "",
                descripcion: "",
                especialidadId: "",
                duracionMinutos: 60,
                precioBase: "0.00",
            });
            mostrarFormularioServicio.value = false;
        },
        onFinish: () => {
            procesando.value = false;
        },
    });
}
function toggle(
    tipo: "especialidad" | "servicio",
    item: Especialidad | Servicio,
) {
    const estado = item.estado === "activo" ? "inactivo" : "activo";
    router.patch(
        route(
            `${tipo === "especialidad" ? "especialidades" : "servicios"}.estado`,
            item.id,
        ),
        { estado },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="titulo" />
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="titulo"
                ><template #leading><UDashboardSidebarCollapse /></template
            ></UDashboardNavbar>
        </template>
        <template #body>
            <div
                class="grid gap-6"
                :class="vista === 'resumen' ? 'xl:grid-cols-2' : 'grid-cols-1'"
            >
                <section
                    v-if="vista === 'resumen' || vista === 'especialidades'"
                    class="w-full space-y-4"
                >
                    <div
                        v-if="can('especialidades.gestionar')"
                        class="flex justify-end"
                    >
                        <UButton
                            label="Crear nueva especialidad"
                            icon="i-lucide-plus"
                            :color="
                                mostrarFormularioEspecialidad
                                    ? 'neutral'
                                    : 'primary'
                            "
                            :variant="
                                mostrarFormularioEspecialidad
                                    ? 'ghost'
                                    : 'solid'
                            "
                            @click="
                                mostrarFormularioEspecialidad =
                                    !mostrarFormularioEspecialidad
                            "
                        />
                    </div>
                    <UCard
                        v-if="
                            can('especialidades.gestionar') &&
                            mostrarFormularioEspecialidad
                        "
                    >
                        <template #header
                            ><h2 class="font-semibold">
                                Nueva especialidad
                            </h2></template
                        >
                        <form
                            class="grid gap-4 sm:grid-cols-2"
                            @submit.prevent="guardarEspecialidad"
                        >
                            <UFormField
                                label="Código"
                                required
                                :error="errors.codigo"
                                ><UInput
                                    v-model="especialidad.codigo"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                label="Nombre"
                                required
                                :error="errors.nombre"
                                ><UInput
                                    v-model="especialidad.nombre"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                class="sm:col-span-2"
                                label="Descripción"
                                ><UTextarea
                                    v-model="especialidad.descripcion"
                                    class="w-full"
                            /></UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton
                                    type="submit"
                                    label="Crear especialidad"
                                    :loading="procesando"
                                />
                            </div>
                        </form>
                    </UCard>

                    <h2 class="text-lg font-semibold">Especialidades</h2>
                    <div
                        class="overflow-hidden rounded-lg border border-default"
                    >
                        <div
                            class="hidden grid-cols-[minmax(10rem,1fr)_minmax(10rem,1fr)_auto] gap-4 border-b border-default bg-elevated/50 px-4 py-3 text-xs font-medium uppercase text-muted sm:grid"
                        >
                            <span>Especialidad</span>
                            <span>Uso</span>
                            <span class="text-right">Estado y acciones</span>
                        </div>
                        <div
                            v-for="e in especialidades"
                            :key="e.id"
                            class="grid gap-3 border-b border-default px-4 py-4 last:border-b-0 sm:grid-cols-[minmax(10rem,1fr)_minmax(10rem,1fr)_auto] sm:items-center"
                        >
                            <div>
                                <p
                                    class="text-xs font-medium uppercase text-muted sm:hidden"
                                >
                                    Especialidad
                                </p>
                                <p class="font-semibold">
                                    {{ e.codigo }} · {{ e.nombre }}
                                </p>
                            </div>
                            <div class="text-sm text-muted">
                                <p
                                    class="text-xs font-medium uppercase text-muted sm:hidden"
                                >
                                    Uso
                                </p>
                                <p>
                                    {{ e.mecanicos_count }} mecánicos ·
                                    {{ e.servicios_count }} servicios
                                </p>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-2 sm:justify-end"
                            >
                                <UBadge
                                    :color="
                                        e.estado === 'activo'
                                            ? 'success'
                                            : 'neutral'
                                    "
                                    >{{ e.estado }}</UBadge
                                >
                                <UButton
                                    v-if="can('especialidades.gestionar')"
                                    label="Cambiar estado"
                                    size="sm"
                                    color="neutral"
                                    variant="ghost"
                                    @click="toggle('especialidad', e)"
                                />
                            </div>
                        </div>
                        <p
                            v-if="!especialidades.length"
                            class="px-4 py-10 text-center text-muted"
                        >
                            No hay especialidades registradas.
                        </p>
                    </div>
                </section>

                <section
                    v-if="vista === 'resumen' || vista === 'servicios'"
                    class="w-full space-y-4"
                >
                    <div
                        v-if="can('servicios.gestionar')"
                        class="flex justify-end"
                    >
                        <UButton
                            label="Crear nuevo servicio"
                            icon="i-lucide-plus"
                            :color="
                                mostrarFormularioServicio
                                    ? 'neutral'
                                    : 'primary'
                            "
                            :variant="
                                mostrarFormularioServicio ? 'ghost' : 'solid'
                            "
                            @click="
                                mostrarFormularioServicio =
                                    !mostrarFormularioServicio
                            "
                        />
                    </div>
                    <UCard
                        v-if="
                            can('servicios.gestionar') &&
                            mostrarFormularioServicio
                        "
                    >
                        <template #header
                            ><h2 class="font-semibold">
                                Nuevo servicio
                            </h2></template
                        >
                        <form
                            class="grid gap-4 sm:grid-cols-2"
                            @submit.prevent="guardarServicio"
                        >
                            <UFormField label="Código" required
                                ><UInput
                                    v-model="servicio.codigo"
                                    class="w-full"
                            /></UFormField>
                            <UFormField label="Nombre" required
                                ><UInput
                                    v-model="servicio.nombre"
                                    class="w-full"
                            /></UFormField>
                            <UFormField label="Especialidad"
                                ><USelect
                                    v-model="servicio.especialidadId"
                                    class="w-full"
                                    :items="opciones"
                            /></UFormField>
                            <UFormField label="Duración (min)" required
                                ><UInput
                                    v-model.number="servicio.duracionMinutos"
                                    type="number"
                                    min="15"
                                    class="w-full"
                            /></UFormField>
                            <UFormField label="Precio base" required
                                ><UInput
                                    v-model="servicio.precioBase"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full"
                            /></UFormField>
                            <UFormField
                                class="sm:col-span-2"
                                label="Descripción"
                                ><UTextarea
                                    v-model="servicio.descripcion"
                                    class="w-full"
                            /></UFormField>
                            <div class="sm:col-span-2 text-right">
                                <UButton
                                    type="submit"
                                    label="Crear servicio"
                                    :loading="procesando"
                                />
                            </div>
                        </form>
                    </UCard>

                    <h2 class="text-lg font-semibold">Catálogo de servicios</h2>
                    <div
                        class="overflow-hidden rounded-lg border border-default"
                    >
                        <div
                            class="hidden grid-cols-[minmax(11rem,1fr)_minmax(10rem,1fr)_minmax(8rem,.7fr)_auto] gap-4 border-b border-default bg-elevated/50 px-4 py-3 text-xs font-medium uppercase text-muted sm:grid"
                        >
                            <span>Servicio</span>
                            <span>Especialidad</span>
                            <span>Duración y precio</span>
                            <span class="text-right">Estado y acciones</span>
                        </div>
                        <div
                            v-for="s in servicios"
                            :key="s.id"
                            class="grid gap-3 border-b border-default px-4 py-4 last:border-b-0 sm:grid-cols-[minmax(11rem,1fr)_minmax(10rem,1fr)_minmax(8rem,.7fr)_auto] sm:items-center"
                        >
                            <div>
                                <p
                                    class="text-xs font-medium uppercase text-muted sm:hidden"
                                >
                                    Servicio
                                </p>
                                <p class="font-semibold">
                                    {{ s.codigo }} · {{ s.nombre }}
                                </p>
                            </div>
                            <div class="text-sm">
                                <p
                                    class="text-xs font-medium uppercase text-muted sm:hidden"
                                >
                                    Especialidad
                                </p>
                                <p>{{ s.especialidad || "General" }}</p>
                            </div>
                            <div class="text-sm text-muted">
                                <p
                                    class="text-xs font-medium uppercase text-muted sm:hidden"
                                >
                                    Duración y precio
                                </p>
                                <p>
                                    {{ s.duracionMinutos }} min · $
                                    {{ s.precioBase }}
                                </p>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-2 sm:justify-end"
                            >
                                <UBadge
                                    :color="
                                        s.estado === 'activo'
                                            ? 'success'
                                            : 'neutral'
                                    "
                                    >{{ s.estado }}</UBadge
                                >
                                <UButton
                                    v-if="can('servicios.gestionar')"
                                    label="Cambiar estado"
                                    size="sm"
                                    color="neutral"
                                    variant="ghost"
                                    @click="toggle('servicio', s)"
                                />
                            </div>
                        </div>
                        <p
                            v-if="!servicios.length"
                            class="px-4 py-10 text-center text-muted"
                        >
                            No hay servicios registrados.
                        </p>
                    </div>
                </section>
            </div>
        </template>
    </UDashboardPanel>
</template>
