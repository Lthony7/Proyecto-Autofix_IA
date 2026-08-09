<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { usePermissions } from '../../composables/usePermissions'

interface Cita {
  id: string
  numero: string
  cliente: string
  vehiculo: string
  servicio?: string
  mecanicoId?: string
  mecanico?: string
  motivo: string
  inicio: string
  fin: string
  estado: string
  ordenId?: string
  ordenNumero?: string
}

const props = defineProps<{
  citas: { data: Cita[]; prev_page_url: string|null; next_page_url: string|null; total: number }
  estado?: string
}>()

const { can, canAny } = usePermissions()
const filtro = ref(props.estado ?? 'todos')
const modal = ref(false)
const seleccionada = ref<Cita|null>(null)
const accion = ref<'reprogramada'|'cancelada'>('reprogramada')
const form = reactive({ fecha: '', horaInicio: '', motivo: '', observaciones: '', mecanicoId: '' })
const mecanicosDisponibles = ref<{ label:string;value:string }[]>([])
const fechasDisponibles = ref<{ value:string;horas:string[] }[]>([])
const duracionMinutos = ref(60)
const cargandoDisponibilidad = ref(false)
const errorDisponibilidad = ref('')
let solicitudDisponibilidad = 0
const errors = computed<Record<string,string>>(() => usePage().props.errors as Record<string,string>)
const opcionesFecha = computed(() => fechasDisponibles.value.map(item => ({
  value: item.value,
  label: `${new Intl.DateTimeFormat('es-CO', { weekday: 'short', day: 'numeric', month: 'short' }).format(new Date(`${item.value}T12:00:00`))} · ${item.horas.length} ${item.horas.length === 1 ? 'hora disponible' : 'horas disponibles'}`
})))
const opcionesHora = computed(() => (fechasDisponibles.value.find(item => item.value === form.fecha)?.horas ?? []).map(hora => ({
  value: hora,
  label: `${new Intl.DateTimeFormat('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date(`2000-01-01T${hora}:00`))} · Disponible (${duracionMinutos.value} min)`
})))
const estados = [{ label: 'Todos', value: 'todos' }, ...['pendiente', 'confirmada', 'reprogramada', 'vencida', 'atendida', 'cancelada'].map(value => ({ label: value, value }))]
function filtrar() {
  router.get(route('citas.index'), { estado: filtro.value === 'todos' ? undefined : filtro.value }, { preserveState: true, replace: true })
}
function cambiar(cita: Cita, estado: string) {
  if (!confirm(`¿Cambiar la cita ${cita.numero} a ${estado}?`)) return
  router.patch(route('citas.estado', cita.id), { estado }, { preserveScroll: true })
}
function convertir(cita:Cita){if(confirm(`¿Crear la orden de trabajo desde ${cita.numero}?`))router.post(route('citas.convertir-orden',cita.id))}
async function abrir(cita: Cita, tipo: 'reprogramada'|'cancelada') {
  solicitudDisponibilidad++
  seleccionada.value = cita
  accion.value = tipo
  Object.assign(form, { fecha: '', horaInicio: '', motivo: '', observaciones: '', mecanicoId: cita.mecanicoId || '' })
  mecanicosDisponibles.value = []
  fechasDisponibles.value = []
  cargandoDisponibilidad.value = false
  errorDisponibilidad.value = ''
  modal.value = true
  if (tipo === 'reprogramada') await cargarDisponibilidad(form.mecanicoId)
}
async function cargarDisponibilidad(mecanicoId = '') {
  if (!seleccionada.value) return
  const solicitud = ++solicitudDisponibilidad
  cargandoDisponibilidad.value = true
  errorDisponibilidad.value = ''
  form.fecha = ''
  form.horaInicio = ''
  try {
    const consulta = mecanicoId ? `?mecanicoId=${encodeURIComponent(mecanicoId)}` : ''
    const respuesta = await fetch(`${route('citas.disponibilidad', seleccionada.value.id)}${consulta}`, { headers: { Accept: 'application/json' } })
    const datos = await respuesta.json()
    if (solicitud !== solicitudDisponibilidad) return
    if (!respuesta.ok) throw new Error(datos.message || Object.values(datos.errors || {})[0]?.[0] || 'No fue posible consultar la disponibilidad.')
    mecanicosDisponibles.value = datos.mecanicos
    form.mecanicoId = datos.mecanicoId || ''
    duracionMinutos.value = datos.duracionMinutos
    fechasDisponibles.value = datos.fechas
  } catch (error) {
    if (solicitud !== solicitudDisponibilidad) return
    fechasDisponibles.value = []
    errorDisponibilidad.value = error instanceof Error ? error.message : 'No fue posible consultar la disponibilidad.'
  } finally {
    if (solicitud === solicitudDisponibilidad) cargandoDisponibilidad.value = false
  }
}
function seleccionarMecanico(valor: unknown) {
  form.mecanicoId = String(valor || '')
  cargarDisponibilidad(form.mecanicoId)
}
function seleccionarFecha(valor: unknown) {
  form.fecha = String(valor || '')
  form.horaInicio = ''
}
function guardarModal() {
  if (!seleccionada.value) return
  router.patch(route('citas.estado', seleccionada.value.id), { estado: accion.value, ...form }, { preserveScroll: true, onSuccess: () => { modal.value = false } })
}
function fecha(valor: string) {
  return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(valor))
}
function colorEstado(estado: string): 'error'|'success'|'warning'|'primary'|'neutral' {
  if (['cancelada', 'vencida'].includes(estado)) return 'error'
  if (estado === 'atendida') return 'success'
  if (estado === 'pendiente') return 'warning'
  return 'primary'
}
</script>

<template>
  <Head title="Citas" />
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar title="Citas">
        <template #right>
          <UButton label="Calendario" icon="i-lucide-calendar-days" color="neutral" variant="outline" @click="router.visit(route('citas.calendario'))" />
          <UButton v-if="can('citas.crear')" label="Nueva cita" icon="i-lucide-plus" @click="router.visit(route('citas.create'))" />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
        <div class="flex flex-col gap-3 sm:flex-row">
          <USelect v-model="filtro" class="w-full sm:w-52" :items="estados" />
          <UButton label="Filtrar" @click="filtrar" />
        </div>

        <ul v-if="citas.data.length" class="divide-y divide-default overflow-hidden rounded-lg border border-default bg-elevated">
          <li v-for="c in citas.data" :key="c.id" class="border-l-4 p-4 sm:p-5" :class="c.estado === 'vencida' ? 'border-l-error bg-error/7' : 'border-l-transparent'">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-3 sm:justify-start">
                  <div><p class="text-sm font-semibold text-primary">{{ c.numero }}</p><h2 class="font-semibold">{{ c.vehiculo }}</h2><p class="text-sm text-muted">{{ c.cliente }}</p></div>
                  <UBadge :color="colorEstado(c.estado)" variant="subtle">{{ c.estado }}</UBadge>
                </div>
                <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                  <div><dt class="text-muted">Inicio</dt><dd>{{ fecha(c.inicio) }}</dd></div>
                  <div><dt class="text-muted">Servicio</dt><dd>{{ c.servicio || 'Por definir' }}</dd></div>
                  <div><dt class="text-muted">Mecánico</dt><dd>{{ c.mecanico || 'Sin asignar' }}</dd></div>
                  <div><dt class="text-muted">Motivo</dt><dd class="line-clamp-2">{{ c.motivo }}</dd></div>
                </dl>
              </div>
              <div class="flex flex-wrap gap-2 xl:justify-end">
                <template v-if="canAny(['citas.gestionar', 'citas.cancelar']) && !['atendida', 'cancelada'].includes(c.estado)"><UButton v-if="['pendiente', 'reprogramada'].includes(c.estado) && can('citas.gestionar')" label="Confirmar" size="sm" @click="cambiar(c, 'confirmada')" /><UButton v-if="can('citas.gestionar')" label="Reprogramar" size="sm" color="neutral" variant="outline" @click="abrir(c, 'reprogramada')" /><UButton v-if="['confirmada', 'vencida'].includes(c.estado) && can('citas.gestionar')" label="Atendida" size="sm" color="success" @click="cambiar(c, 'atendida')" /><UButton v-if="can('citas.cancelar')" label="Cancelar" size="sm" color="error" variant="ghost" @click="abrir(c, 'cancelada')" /></template>
                <UButton v-if="c.estado==='atendida'&&!c.ordenId&&can('ordenes.crear')" label="Crear orden" icon="i-lucide-clipboard-plus" size="sm" @click="convertir(c)"/>
                <UButton v-if="c.ordenId&&can('ordenes.ver')" :label="c.ordenNumero||'Ver orden'" icon="i-lucide-clipboard-list" size="sm" color="neutral" variant="outline" @click="router.visit(route('ordenes.show',c.ordenId))"/>
              </div>
            </div>
          </li>
        </ul>
        <div v-else class="rounded-lg border border-default bg-elevated"><p class="py-10 text-center text-muted">No hay citas con este estado.</p></div>
        <div class="flex flex-wrap justify-end gap-2">
          <UButton label="Anterior" color="neutral" variant="outline" :disabled="!citas.prev_page_url" @click="citas.prev_page_url && router.visit(citas.prev_page_url)" />
          <UButton label="Siguiente" color="neutral" variant="outline" :disabled="!citas.next_page_url" @click="citas.next_page_url && router.visit(citas.next_page_url)" />
        </div>
    </template>
  </UDashboardPanel>

  <UModal v-model:open="modal" :title="accion === 'cancelada' ? 'Cancelar cita' : 'Reprogramar cita'" :description="seleccionada?.numero">
    <template #body>
      <div v-if="accion === 'reprogramada'" class="grid gap-4 sm:grid-cols-2">
        <UFormField class="sm:col-span-2" label="Mecánico encargado" required :error="errors.mecanicoId||errors.mecanico_id"><USelect :model-value="form.mecanicoId" :items="mecanicosDisponibles" :loading="cargandoDisponibilidad" class="w-full" @update:model-value="seleccionarMecanico"/></UFormField>
        <UFormField label="Día disponible" required :error="errors.fecha||errors.inicio"><USelect :model-value="form.fecha" :items="opcionesFecha" :disabled="!form.mecanicoId || cargandoDisponibilidad" placeholder="Selecciona un día" class="w-full" @update:model-value="seleccionarFecha" /></UFormField>
        <UFormField label="Hora disponible" required :error="errors.horaInicio||errors.inicio"><USelect v-model="form.horaInicio" :items="opcionesHora" :disabled="!form.fecha || cargandoDisponibilidad" placeholder="Selecciona una hora" class="w-full" /></UFormField>
        <UAlert v-if="errorDisponibilidad" class="sm:col-span-2" color="error" icon="i-lucide-circle-alert" title="No se pudo cargar la agenda" :description="errorDisponibilidad" />
        <UAlert v-else-if="form.mecanicoId && !cargandoDisponibilidad && !fechasDisponibles.length" class="sm:col-span-2" color="warning" icon="i-lucide-calendar-x" title="Sin cupos disponibles" description="Este mecánico no tiene franjas libres dentro de los próximos 90 días." />
        <UFormField class="sm:col-span-2" label="Observaciones" hint="Opcional" :error="errors.observaciones"><UTextarea v-model="form.observaciones" class="w-full" /></UFormField>
      </div>
      <UFormField v-else label="Motivo de cancelación" required :error="errors.motivo"><UTextarea v-model="form.motivo" class="w-full" /></UFormField>
    </template>
    <template #footer><div class="flex justify-end gap-2"><UButton label="Cerrar" color="neutral" variant="outline" @click="modal = false" /><UButton :label="accion === 'cancelada' ? 'Cancelar cita' : 'Guardar cambio'" :color="accion === 'cancelada' ? 'error' : 'primary'" :loading="cargandoDisponibilidad" :disabled="accion === 'reprogramada' && (!form.mecanicoId || !form.fecha || !form.horaInicio)" @click="guardarModal" /></div></template>
  </UModal>

</template>
