<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { usePermissions } from "../../../composables/usePermissions";

interface Rol { id: string; name: string }
interface Usuario { id: string; name: string; email: string; activo: boolean; roleIds: string[] }

const props = defineProps<{ usuario: Usuario | null; roles: Rol[]; esCliente: boolean }>();
const { can } = usePermissions();
const errors = computed<Record<string, string>>(() => usePage().props.errors as Record<string, string>);
const procesando = ref(false);
const state = reactive({
    name: props.usuario?.name || "",
    email: props.usuario?.email || "",
    password: "",
    password_confirmation: "",
    roleIds: [...(props.usuario?.roleIds || [])],
});
const rolSeleccionado = computed({
    get: () => state.roleIds[0] || "",
    set: (value: string) => { state.roleIds = value ? [value] : []; },
});
const opcionesRoles = computed(() => props.roles.map((rol) => ({ label: rol.name, value: rol.id })));

function guardar() {
    procesando.value = true;
    const opciones = { onFinish: () => (procesando.value = false) };
    if (props.usuario) {
        router.put(route("usuarios.update", props.usuario.id), {
            name: state.name,
            email: state.email,
            password: state.password || null,
            password_confirmation: state.password_confirmation || null,
        }, opciones);
    } else {
        router.post(route("usuarios.store"), state, opciones);
    }
}

function guardarRoles() {
    if (!props.usuario || props.esCliente) return;
    procesando.value = true;
    router.patch(route("usuarios.roles", props.usuario.id), { roleIds: state.roleIds }, {
        preserveScroll: true,
        onFinish: () => (procesando.value = false),
    });
}
</script>

<template>
    <Head :title="usuario ? 'Editar usuario' : 'Nuevo usuario'" />
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="usuario ? 'Editar usuario' : 'Nuevo usuario'">
                <template #leading><UDashboardSidebarCollapse /></template>
            </UDashboardNavbar>
        </template>
        <template #body>
            <div class="mx-auto max-w-3xl space-y-6">
                <UCard>
                    <template #header>
                        <div>
                            <h2 class="font-semibold">Datos de acceso</h2>
                            <p class="text-sm text-muted">El correo se normaliza y la contraseña nunca se registra en auditoría.</p>
                        </div>
                    </template>
                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="guardar">
                        <UFormField label="Nombre" required :error="errors.name"><UInput v-model="state.name" class="w-full" /></UFormField>
                        <UFormField label="Correo" required :error="errors.email"><UInput v-model="state.email" type="email" class="w-full" /></UFormField>
                        <UFormField :label="usuario ? 'Nueva contraseña' : 'Contraseña'" :required="!usuario" :error="errors.password"><UInput v-model="state.password" type="password" class="w-full" autocomplete="new-password" /></UFormField>
                        <UFormField label="Confirmar contraseña" :required="!usuario"><UInput v-model="state.password_confirmation" type="password" class="w-full" autocomplete="new-password" /></UFormField>
                        <UFormField v-if="!usuario" class="sm:col-span-2" label="Rol interno" required :error="errors.roleIds || errors.role_ids">
                            <USelect v-model="rolSeleccionado" :items="opcionesRoles" placeholder="Selecciona un rol" class="w-full" />
                        </UFormField>
                        <p class="sm:col-span-2 text-xs text-muted">Mínimo 12 caracteres, con mayúsculas, minúsculas, números y símbolos. En edición, déjala vacía para conservarla.</p>
                        <div class="sm:col-span-2 flex justify-end gap-2"><Link :href="route('usuarios.index')"><UButton color="neutral" variant="outline" label="Cancelar" /></Link><UButton type="submit" :label="usuario ? 'Guardar datos' : 'Crear usuario'" :loading="procesando" /></div>
                    </form>
                </UCard>

                <UCard v-if="usuario && can('roles.administrar')">
                    <template #header>
                        <div><h2 class="font-semibold">Rol</h2><p class="text-sm text-muted">Cada cuenta utiliza un único rol para mantener responsabilidades claras.</p></div>
                    </template>
                    <UAlert v-if="esCliente" color="neutral" variant="subtle" icon="i-lucide-shield-check" title="Cuenta de cliente" description="El rol Cliente proviene del registro público y no puede convertirse en una cuenta interna." />
                    <div v-else class="space-y-4">
                        <UFormField label="Rol interno" required :error="errors.roleIds || errors.role_ids"><USelect v-model="rolSeleccionado" :items="opcionesRoles" class="w-full" /></UFormField>
                        <div class="flex justify-end"><UButton label="Guardar rol" :loading="procesando" @click="guardarRoles" /></div>
                    </div>
                </UCard>
            </div>
        </template>
    </UDashboardPanel>
</template>
