<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { usePermissions } from '../../composables/usePermissions'

interface Cita {
  id: string
  numero: string
  cliente: string
  vehiculo: string
  servicio?: string
  mecanico?: string
  motivo: string
  inicio: string
  fin: string
  estado: string
}

const props = defineProps<{
  citas: { data: Cita[]; prev_page_url: string|null; next_page_url: string|null; total: number }
  citasCalendario: Cita[]
  estado?: string
  mes: string
  vista?: string
}>()

const { can, canAny } = usePermissions()
const filtro = ref(props.estado ?? 'todos')
const vista = ref(props.vista === 'calendario' ? 'calendario' : 'lista')
const modal = ref(false)
const seleccionada = ref<Cita|null>(null)
const accion = ref<'reprogramada'|'cancelada'>('reprogramada')
const form = reactive({ fecha: '', horaInicio: '', motivo: '', observaciones: '', mecanicoId: '' })
const estados = [{ label: 'Todos', value: 'todos' }, ...['pendiente', 'confirmada', 'reprogramada', 'atendida', 'cancelada'].map(value => ({ label: value, value }))]
const diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']

const tituloMes = computed(() => new Intl.DateTimeFormat('es-CO', { month: 'long', year: 'numeric' }).format(new Date(`${props.mes}-01T12:00:00`)))
const diasCalendario = computed(() => {
  const [year, month] = props.mes.split('-').map(Number)
  const primerDia = new Date(year, month - 1, 1)
  const espacios = (primerDia.getDay() + 6) % 7
  const total = new Date(year, month, 0).getDate()
  return [...Array(espacios).fill(null), ...Array.from({ length: total }, (_, index) => index + 1)] as Array<number|null>
})
const citasPorDia = computed(() => {
  const agrupadas = new Map<string, Cita[]>()
  for (const cita of props.citasCalendario) {
    const key = fechaClave(new Date(cita.inicio))
    agrupadas.set(key, [...(agrupadas.get(key) ?? []), cita])
  }
  return agrupadas
})

function fechaClave(valor: Date) {
  return `${valor.getFullYear()}-${String(valor.getMonth() + 1).padStart(2, '0')}-${String(valor.getDate()).padStart(2, '0')}`
}
function claveDia(dia: number) {
  return `${props.mes}-${String(dia).padStart(2, '0')}`
}
function filtrar() {
  router.get(route('citas.index'), { estado: filtro.value === 'todos' ? undefined : filtro.value, mes: props.mes, vista: vista.value }, { preserveState: true, replace: true })
}
function cambiarMes(desplazamiento: number) {
  const fecha = new Date(`${props.mes}-01T12:00:00`)
  fecha.setMonth(fecha.getMonth() + desplazamiento)
  router.get(route('citas.index'), { estado: filtro.value === 'todos' ? undefined : filtro.value, mes: fechaClave(fecha).slice(0, 7), vista: 'calendario' }, { preserveScroll: true, replace: true })
}
function cambiarVista() {
  vista.value = vista.value === 'lista' ? 'calendario' : 'lista'
}
function cambiar(cita: Cita, estado: string) {
  if (!confirm(`¿Cambiar la cita ${cita.numero} a ${estado}?`)) return
  router.patch(route('citas.estado', cita.id), { estado }, { preserveScroll: true })
}
function abrir(cita: Cita, tipo: 'reprogramada'|'cancelada') {
  seleccionada.value = cita
  accion.value = tipo
  Object.assign(form, { fecha: '', horaInicio: '', motivo: '', observaciones: '', mecanicoId: '' })
  modal.value = true
}
function guardarModal() {
  if (!seleccionada.value) return
  router.patch(route('citas.estado', seleccionada.value.id), { estado: accion.value, ...form }, { preserveScroll: true, onSuccess: () => { modal.value = false } })
}
function fecha(valor: string) {
  return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(valor))
}
function hora(valor: string) {
  return new Intl.DateTimeFormat('es-CO', { hour: '2-digit', minute: '2-digit' }).format(new Date(valor))
}
function colorEstado(estado: string): 'error'|'success'|'warning'|'primary'|'neutral' {
  if (estado === 'cancelada') return 'error'
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
          <UButton :label="vista === 'lista' ? 'Calendario' : 'Ver lista'" :icon="vista === 'lista' ? 'i-lucide-calendar-days' : 'i-lucide-list'" color="neutral" variant="outline" @click="cambiarVista" />
          <UButton v-if="can('citas.crear')" label="Nueva cita" icon="i-lucide-plus" @click="router.visit(route('citas.create'))" />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <template v-if="vista === 'lista'">
        <div class="flex flex-col gap-3 sm:flex-row">
          <USelect v-model="filtro" class="w-full sm:w-52" :items="estados" />
          <UButton label="Filtrar" @click="filtrar" />
        </div>

        <ul v-if="citas.data.length" class="divide-y divide-default overflow-hidden rounded-lg border border-default bg-elevated">
          <li v-for="c in citas.data" :key="c.id" class="p-4 sm:p-5">
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
              <div v-if="canAny(['citas.gestionar', 'citas.cancelar']) && !['atendida', 'cancelada'].includes(c.estado)" class="flex flex-wrap gap-2 xl:justify-end">
                <UButton v-if="c.estado !== 'confirmada' && can('citas.gestionar')" label="Confirmar" size="sm" @click="cambiar(c, 'confirmada')" />
                <UButton v-if="can('citas.gestionar')" label="Reprogramar" size="sm" color="neutral" variant="outline" @click="abrir(c, 'reprogramada')" />
                <UButton v-if="c.estado === 'confirmada' && can('citas.gestionar')" label="Atendida" size="sm" color="success" @click="cambiar(c, 'atendida')" />
                <UButton v-if="can('citas.cancelar')" label="Cancelar" size="sm" color="error" variant="ghost" @click="abrir(c, 'cancelada')" />
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

      <section v-else class="overflow-hidden rounded-xl border border-default bg-elevated/80">
        <header class="flex items-center justify-between border-b border-default p-4">
          <UButton icon="i-lucide-chevron-left" color="neutral" variant="ghost" aria-label="Mes anterior" @click="cambiarMes(-1)" />
          <div class="text-center"><p class="font-mono text-xs uppercase tracking-[0.18em] text-primary">Agenda mensual</p><h2 class="mt-1 text-lg font-bold capitalize">{{ tituloMes }}</h2></div>
          <UButton icon="i-lucide-chevron-right" color="neutral" variant="ghost" aria-label="Mes siguiente" @click="cambiarMes(1)" />
        </header>
        <div class="overflow-x-auto">
          <div class="min-w-[760px]">
            <div class="grid grid-cols-7 border-b border-default bg-default/55">
              <div v-for="dia in diasSemana" :key="dia" class="p-2 text-center font-mono text-xs font-bold uppercase tracking-wider text-muted">{{ dia }}</div>
            </div>
            <div class="grid grid-cols-7">
              <div v-for="(dia, index) in diasCalendario" :key="`${dia}-${index}`" class="min-h-32 border-r border-b border-default p-2 last:border-r-0" :class="dia ? 'bg-default/25' : 'bg-elevated/25'">
                <template v-if="dia">
                  <span class="grid size-7 place-items-center rounded-full font-mono text-xs font-bold" :class="claveDia(dia) === fechaClave(new Date()) ? 'bg-primary text-white' : 'text-muted'">{{ dia }}</span>
                  <div class="mt-2 space-y-1.5">
                    <div v-for="cita in citasPorDia.get(claveDia(dia)) ?? []" :key="cita.id" class="rounded-lg border border-default bg-default/85 p-2 shadow-sm">
                      <div class="flex items-center justify-between gap-1"><span class="font-mono text-[11px] font-bold text-primary">{{ hora(cita.inicio) }}</span><span class="size-1.5 shrink-0 rounded-full" :class="cita.estado === 'cancelada' ? 'bg-error' : cita.estado === 'atendida' ? 'bg-success' : cita.estado === 'pendiente' ? 'bg-warning' : 'bg-primary'" /></div>
                      <p class="mt-1 truncate text-xs font-semibold">{{ cita.vehiculo }}</p>
                      <p class="truncate text-[11px] text-muted">{{ cita.servicio || cita.motivo }}</p>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>
      </section>
    </template>
  </UDashboardPanel>

  <UModal v-model:open="modal" :title="accion === 'cancelada' ? 'Cancelar cita' : 'Reprogramar cita'" :description="seleccionada?.numero">
    <template #body>
      <div v-if="accion === 'reprogramada'" class="grid gap-4 sm:grid-cols-2">
        <UFormField label="Nueva fecha" required><UInput v-model="form.fecha" type="date" class="w-full" /></UFormField>
        <UFormField label="Nueva hora" required><UInput v-model="form.horaInicio" type="time" class="w-full" /></UFormField>
        <UFormField class="sm:col-span-2" label="Observaciones" hint="Opcional"><UTextarea v-model="form.observaciones" class="w-full" /></UFormField>
      </div>
      <UFormField v-else label="Motivo de cancelación" required><UTextarea v-model="form.motivo" class="w-full" /></UFormField>
    </template>
    <template #footer><div class="flex justify-end gap-2"><UButton label="Cerrar" color="neutral" variant="outline" @click="modal = false" /><UButton :label="accion === 'cancelada' ? 'Cancelar cita' : 'Guardar cambio'" :color="accion === 'cancelada' ? 'error' : 'primary'" @click="guardarModal" /></div></template>
  </UModal>
</template>
