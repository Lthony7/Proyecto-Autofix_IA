<script setup lang="ts">
import { reactive, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { usePermissions } from '../../composables/usePermissions'
interface Cita { id: string; numero: string; cliente: string; vehiculo: string; servicio?: string; mecanico?: string; motivo: string; inicio: string; fin: string; estado: string }
const props = defineProps<{ citas: { data: Cita[]; prev_page_url: string|null; next_page_url: string|null; total: number }; estado?: string }>()
const { can, canAny } = usePermissions(); const filtro = ref(props.estado ?? 'todos'); const modal = ref(false); const seleccionada = ref<Cita|null>(null); const accion = ref<'reprogramada'|'cancelada'>('reprogramada'); const form = reactive({ fecha: '', horaInicio: '', motivo: '', observaciones: '', mecanicoId: '' })
const estados = [{ label: 'Todos', value: 'todos' }, ...['pendiente','confirmada','reprogramada','atendida','cancelada'].map(x => ({ label: x, value: x }))]
function filtrar() { router.get(route('citas.index'), { estado: filtro.value === 'todos' ? undefined : filtro.value }, { preserveState: true, replace: true }) }
function cambiar(cita: Cita, estado: string) { if (!confirm(`¿Cambiar la cita ${cita.numero} a ${estado}?`)) return; router.patch(route('citas.estado', cita.id), { estado }, { preserveScroll: true }) }
function abrir(cita: Cita, tipo: 'reprogramada'|'cancelada') { seleccionada.value = cita; accion.value = tipo; Object.assign(form, { fecha: '', horaInicio: '', motivo: '', observaciones: '', mecanicoId: '' }); modal.value = true }
function guardarModal() { if (!seleccionada.value) return; router.patch(route('citas.estado', seleccionada.value.id), { estado: accion.value, ...form }, { preserveScroll: true, onSuccess: () => { modal.value = false } }) }
function fecha(valor: string) { return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(valor)) }
</script>
<template>
  <Head title="Citas" />
  <UDashboardPanel><template #header><UDashboardNavbar title="Citas"><template #right><UButton v-if="can('citas.crear')" label="Nueva cita" icon="i-lucide-plus" @click="router.visit(route('citas.create'))" /></template></UDashboardNavbar></template><template #body>
    <div class="flex flex-col gap-3 sm:flex-row"><USelect v-model="filtro" class="w-full sm:w-52" :items="estados" /><UButton label="Filtrar" @click="filtrar" /></div>
    <ul v-if="citas.data.length" class="divide-y divide-default overflow-hidden rounded-lg border border-default bg-elevated">
      <li v-for="c in citas.data" :key="c.id" class="p-4 sm:p-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3 sm:justify-start"><div><p class="text-sm font-semibold text-primary">{{ c.numero }}</p><h2 class="font-semibold">{{ c.vehiculo }}</h2><p class="text-sm text-muted">{{ c.cliente }}</p></div><UBadge :color="c.estado === 'cancelada' ? 'error' : c.estado === 'atendida' ? 'success' : 'primary'" variant="subtle">{{ c.estado }}</UBadge></div>
            <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt class="text-muted">Inicio</dt><dd>{{ fecha(c.inicio) }}</dd></div><div><dt class="text-muted">Servicio</dt><dd>{{ c.servicio || 'Por definir' }}</dd></div><div><dt class="text-muted">Mecánico</dt><dd>{{ c.mecanico || 'Sin asignar' }}</dd></div><div><dt class="text-muted">Motivo</dt><dd class="line-clamp-2">{{ c.motivo }}</dd></div></dl>
          </div>
          <div v-if="canAny(['citas.gestionar','citas.cancelar']) && !['atendida','cancelada'].includes(c.estado)" class="flex flex-wrap gap-2 xl:justify-end"><UButton v-if="c.estado !== 'confirmada' && can('citas.gestionar')" label="Confirmar" size="sm" @click="cambiar(c, 'confirmada')" /><UButton v-if="can('citas.gestionar')" label="Reprogramar" size="sm" color="neutral" variant="outline" @click="abrir(c, 'reprogramada')" /><UButton v-if="c.estado === 'confirmada' && can('citas.gestionar')" label="Atendida" size="sm" color="success" @click="cambiar(c, 'atendida')" /><UButton v-if="can('citas.cancelar')" label="Cancelar" size="sm" color="error" variant="ghost" @click="abrir(c, 'cancelada')" /></div>
        </div>
      </li>
    </ul>
    <div v-else class="rounded-lg border border-default bg-elevated"><p class="py-10 text-center text-muted">No hay citas con este estado.</p></div>
    <div class="flex flex-wrap justify-end gap-2"><UButton label="Anterior" color="neutral" variant="outline" :disabled="!citas.prev_page_url" @click="citas.prev_page_url && router.visit(citas.prev_page_url)" /><UButton label="Siguiente" color="neutral" variant="outline" :disabled="!citas.next_page_url" @click="citas.next_page_url && router.visit(citas.next_page_url)" /></div>
  </template></UDashboardPanel>
  <UModal v-model:open="modal" :title="accion === 'cancelada' ? 'Cancelar cita' : 'Reprogramar cita'" :description="seleccionada?.numero"><template #body><div v-if="accion === 'reprogramada'" class="grid gap-4 sm:grid-cols-2"><UFormField label="Nueva fecha" required><UInput v-model="form.fecha" type="date" class="w-full" /></UFormField><UFormField label="Nueva hora" required><UInput v-model="form.horaInicio" type="time" class="w-full" /></UFormField><UFormField class="sm:col-span-2" label="Observaciones"><UTextarea v-model="form.observaciones" class="w-full" /></UFormField></div><UFormField v-else label="Motivo de cancelación" required><UTextarea v-model="form.motivo" class="w-full" /></UFormField></template><template #footer><div class="flex justify-end gap-2"><UButton label="Cerrar" color="neutral" variant="outline" @click="modal = false" /><UButton :label="accion === 'cancelada' ? 'Cancelar cita' : 'Guardar cambio'" :color="accion === 'cancelada' ? 'error' : 'primary'" @click="guardarModal" /></div></template></UModal>
</template>
