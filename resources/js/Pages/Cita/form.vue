<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

interface Cliente { id: string; nombre: string; vehiculos: { id: string; label: string }[] }
interface Especialidad { id: string; nombre: string }
interface Servicio { id: string; especialidad_id: string; nombre: string; duracion_minutos: number }
interface Mecanico { id: string; nombre: string; especialidadIds: string[]; horarios: { dia: number; inicio: string; fin: string }[] }
const props = defineProps<{ clientes: Cliente[]; especialidades: Especialidad[]; servicios: Servicio[]; mecanicos: Mecanico[]; prefill?: { consultaIaId:string;clienteId:string;vehiculoId:string;especialidadId?:string;motivo:string }|null }>()
const state = reactive({ consultaIaId:props.prefill?.consultaIaId??'', clienteId: props.prefill?.clienteId??(props.clientes.length === 1 ? props.clientes[0].id : ''), vehiculoId: props.prefill?.vehiculoId??'', especialidadId: props.prefill?.especialidadId??'', servicioId: '', mecanicoId: '', fecha: '', horaInicio: '', kilometraje: null as number|null, motivo: props.prefill?.motivo??'' })
const errors = computed<Record<string, string>>(() => usePage().props.errors as Record<string, string>); const procesando = ref(false)
const cliente = computed(() => props.clientes.find(c => c.id === state.clienteId)); const vehiculos = computed(() => cliente.value?.vehiculos ?? [])
const servicios = computed(() => props.servicios.filter(s => !state.especialidadId || s.especialidad_id === state.especialidadId))
const mecanicos = computed(() => props.mecanicos.filter(m => !state.especialidadId || m.especialidadIds.includes(state.especialidadId)))
const mecanico = computed(() => props.mecanicos.find(m => m.id === state.mecanicoId))
watch(() => state.clienteId, () => { if (!vehiculos.value.some(v => v.id === state.vehiculoId)) state.vehiculoId = '' })
watch(() => state.especialidadId, () => { if (!servicios.value.some(s => s.id === state.servicioId)) state.servicioId = ''; if (!mecanicos.value.some(m => m.id === state.mecanicoId)) state.mecanicoId = '' })
watch(() => state.servicioId, id => { const s = props.servicios.find(x => x.id === id); if (s) state.especialidadId = s.especialidad_id })
function guardar() { procesando.value = true; router.post(route('citas.store'), state, { onFinish: () => { procesando.value = false } }) }
</script>
<template>
  <Head title="Nueva cita" />
  <UDashboardPanel><template #header><UDashboardNavbar title="Nueva cita" /></template><template #body><form class="mx-auto max-w-5xl space-y-6" @submit.prevent="guardar">
    <UCard><template #header><div><h2 class="font-semibold">Cliente y vehículo</h2><p class="text-sm text-muted">Solo se muestran vehículos activos del cliente.</p></div></template><div class="grid gap-5 md:grid-cols-2"><UFormField label="Cliente" required :error="errors.clienteId"><USelect v-model="state.clienteId" class="w-full" :items="clientes.map(c => ({ label: c.nombre, value: c.id }))" /></UFormField><UFormField label="Vehículo" required :error="errors.vehiculoId"><USelect v-model="state.vehiculoId" class="w-full" :items="vehiculos.map(v => ({ label: v.label, value: v.id }))" :disabled="!state.clienteId" /></UFormField><UFormField label="Kilometraje actual" :error="errors.kilometraje"><UInput v-model.number="state.kilometraje" type="number" min="0" class="w-full" /></UFormField></div></UCard>
    <UCard><template #header><h2 class="font-semibold">Motivo y servicio</h2></template><UFormField label="Síntomas o motivo" required :error="errors.motivo"><UTextarea v-model="state.motivo" class="w-full" :rows="4" maxlength="3000" /></UFormField><div class="mt-5 grid gap-5 md:grid-cols-2"><UFormField label="Especialidad" :error="errors.especialidadId"><USelect v-model="state.especialidadId" class="w-full" :items="especialidades.map(e => ({ label: e.nombre, value: e.id }))" /></UFormField><UFormField label="Servicio sugerido" :error="errors.servicioId"><USelect v-model="state.servicioId" class="w-full" :items="servicios.map(s => ({ label: `${s.nombre} · ${s.duracion_minutos} min`, value: s.id }))" /></UFormField></div></UCard>
    <UCard><template #header><div><h2 class="font-semibold">Fecha y disponibilidad</h2><p class="text-sm text-muted">El backend confirmará horario y solapamientos antes de reservar.</p></div></template><div class="grid gap-5 md:grid-cols-3"><UFormField label="Fecha" required :error="errors.fecha || errors.inicio"><UInput v-model="state.fecha" type="date" class="w-full" /></UFormField><UFormField label="Hora de inicio" required :error="errors.horaInicio || errors.inicio"><UInput v-model="state.horaInicio" type="time" class="w-full" /></UFormField><UFormField label="Mecánico" hint="Opcional; puede asignarse después" :error="errors.mecanicoId"><USelect v-model="state.mecanicoId" class="w-full" :items="mecanicos.map(m => ({ label: m.nombre, value: m.id }))" /></UFormField></div><div v-if="mecanico" class="mt-4 rounded-lg bg-elevated p-3 text-sm"><p class="font-medium">Horario semanal de {{ mecanico.nombre }}</p><p class="mt-1 text-muted">{{ mecanico.horarios.map(h => `${['','Lun','Mar','Mié','Jue','Vie','Sáb','Dom'][h.dia]} ${h.inicio}-${h.fin}`).join(' · ') || 'Sin disponibilidad configurada' }}</p></div></UCard>
    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><UButton type="button" label="Cancelar" color="neutral" variant="outline" @click="router.visit(route('citas.index'))" /><UButton type="submit" label="Agendar cita" icon="i-lucide-calendar-plus" :loading="procesando" /></div>
  </form></template></UDashboardPanel>
</template>
