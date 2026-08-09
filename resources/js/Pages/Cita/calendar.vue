<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
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
  ordenId?: string
  ordenNumero?: string
}
interface Disponibilidad { mecanicoId:string;dia:number;inicio:string;fin:string;vigenteDesde?:string;vigenteHasta?:string }

const props = defineProps<{
  citas: Cita[]
  mecanicos: { label: string; value: string }[]
  disponibilidades: Disponibilidad[]
  vista: 'dia'|'semana'|'mes'
  fecha: string
  inicioPeriodo: string
  finPeriodo: string
  filtros: { mecanico: string; estado: string }
}>()

const { can } = usePermissions()
const filtros = reactive({ ...props.filtros })
const seleccionada = ref<Cita|null>(null)
const detalleAbierto = computed({
  get: () => seleccionada.value !== null,
  set: abierto => { if (!abierto) seleccionada.value = null }
})
const estados = [
  { label: 'Todos los estados', value: 'todos' },
  { label: 'Pendientes', value: 'pendiente' },
  { label: 'Confirmadas', value: 'confirmada' },
  { label: 'Reprogramadas', value: 'reprogramada' },
  { label: 'Vencidas', value: 'vencida' },
  { label: 'Atendidas', value: 'atendida' },
  { label: 'Canceladas', value: 'cancelada' }
]
const vistas = [
  { label: 'Día', value: 'dia', icon: 'i-lucide-calendar-range' },
  { label: 'Semana', value: 'semana', icon: 'i-lucide-columns-3' },
  { label: 'Mes', value: 'mes', icon: 'i-lucide-calendar-days' }
]

const citasPorDia = computed(() => {
  const resultado = new Map<string, Cita[]>()
  for (const cita of props.citas) {
    const key = claveFecha(new Date(cita.inicio))
    resultado.set(key, [...(resultado.get(key) ?? []), cita])
  }
  return resultado
})
const diasPeriodo = computed(() => rangoFechas(props.inicioPeriodo, props.finPeriodo))
const celdasMes = computed(() => {
  const fecha = fechaLocal(props.fecha)
  const primero = new Date(fecha.getFullYear(), fecha.getMonth(), 1, 12)
  const espacios = (primero.getDay() + 6) % 7
  const total = new Date(fecha.getFullYear(), fecha.getMonth() + 1, 0).getDate()
  return [...Array(espacios).fill(null), ...Array.from({ length: total }, (_, indice) => new Date(fecha.getFullYear(), fecha.getMonth(), indice + 1, 12))] as Array<Date|null>
})
const tituloPeriodo = computed(() => {
  if (props.vista === 'dia') return formatoFecha(fechaLocal(props.fecha), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  if (props.vista === 'mes') return formatoFecha(fechaLocal(props.fecha), { month: 'long', year: 'numeric' })
  return `${formatoFecha(fechaLocal(props.inicioPeriodo), { day: 'numeric', month: 'short' })} - ${formatoFecha(fechaLocal(props.finPeriodo), { day: 'numeric', month: 'short', year: 'numeric' })}`
})
const resumen = computed(() => ({
  total: props.citas.length,
  pendientes: props.citas.filter(cita => cita.estado === 'pendiente').length,
  confirmadas: props.citas.filter(cita => ['confirmada', 'reprogramada'].includes(cita.estado)).length,
  atendidas: props.citas.filter(cita => cita.estado === 'atendida').length,
  vencidas: props.citas.filter(cita => cita.estado === 'vencida').length
}))

function fechaLocal(valor: string) {
  return new Date(`${valor}T12:00:00`)
}
function claveFecha(fecha: Date) {
  return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`
}
function rangoFechas(inicio: string, fin: string) {
  const fechas: Date[] = []
  const actual = fechaLocal(inicio)
  const ultima = fechaLocal(fin)
  while (actual <= ultima) {
    fechas.push(new Date(actual))
    actual.setDate(actual.getDate() + 1)
  }
  return fechas
}
function formatoFecha(fecha: Date, opciones: Intl.DateTimeFormatOptions) {
  return new Intl.DateTimeFormat('es-CO', opciones).format(fecha)
}
function hora(valor: string) {
  return new Intl.DateTimeFormat('es-CO', { hour: '2-digit', minute: '2-digit' }).format(new Date(valor))
}
function esHoy(fecha: Date) {
  return claveFecha(fecha) === claveFecha(new Date())
}
function horariosDia(fecha:Date){
  const clave=claveFecha(fecha)
  const dia=fecha.getDay()===0?7:fecha.getDay()
  return props.disponibilidades.filter(h=>h.dia===dia&&(filtros.mecanico==='todos'||h.mecanicoId===filtros.mecanico)&&(!h.vigenteDesde||h.vigenteDesde<=clave)&&(!h.vigenteHasta||h.vigenteHasta>=clave))
}
function disponibilidadDia(fecha:Date){
  const horarios=horariosDia(fecha)
  const mecanicosDisponibles=new Set(horarios.map(h=>h.mecanicoId)).size
  return {horarios,mecanicosDisponibles,disponible:horarios.length>0,label:filtros.mecanico==='todos'?`${mecanicosDisponibles} ${mecanicosDisponibles===1?'mecánico':'mecánicos'}`:horarios.map(h=>`${h.inicio}-${h.fin}`).join(' · ')}
}
function claseDia(fecha:Date){
  if(esHoy(fecha))return'border-primary/40 shadow-lg shadow-primary/5'
  if((citasPorDia.value.get(claveFecha(fecha))??[]).length)return'border-primary/35 bg-primary/5'
  if(disponibilidadDia(fecha).disponible)return'border-success/35 bg-success/5'
  return'border-default'
}
function consultar(cambios: Record<string, string> = {}) {
  router.get(route('citas.calendario'), {
    vista: cambios.vista ?? props.vista,
    fecha: cambios.fecha ?? props.fecha,
    mecanico: filtros.mecanico === 'todos' ? undefined : filtros.mecanico,
    estado: filtros.estado === 'todos' ? undefined : filtros.estado
  }, { preserveState: true, preserveScroll: true, replace: true })
}
function cambiarVista(vista: string) {
  consultar({ vista })
}
function moverPeriodo(direccion: number) {
  const fecha = fechaLocal(props.fecha)
  if (props.vista === 'mes') { fecha.setDate(1); fecha.setMonth(fecha.getMonth() + direccion) }
  else fecha.setDate(fecha.getDate() + direccion * (props.vista === 'semana' ? 7 : 1))
  consultar({ fecha: claveFecha(fecha) })
}
function cambiarEstadoCita(estado:string){
  if(!seleccionada.value)return
  const datos:Record<string,string>={estado}
  if(estado==='cancelada'){
    const motivo=prompt('Motivo de cancelación')?.trim()
    if(!motivo)return
    datos.motivo=motivo
  }
  if(!confirm(`¿Cambiar la cita ${seleccionada.value.numero} a ${estado.replaceAll('_',' ')}?`))return
  router.patch(route('citas.estado',seleccionada.value.id),datos,{preserveScroll:true,onSuccess:()=>{seleccionada.value=null}})
}
function colorEstado(estado: string): 'error'|'success'|'warning'|'primary'|'neutral' {
  if (['cancelada', 'vencida'].includes(estado)) return 'error'
  if (estado === 'atendida') return 'success'
  if (estado === 'pendiente') return 'warning'
  if (estado === 'reprogramada') return 'neutral'
  return 'primary'
}
function claseCita(estado: string) {
  return {
    pendiente: 'border-l-warning bg-warning/7 hover:bg-warning/12',
    confirmada: 'border-l-primary bg-primary/7 hover:bg-primary/12',
    reprogramada: 'border-l-violet-500 bg-violet-500/7 hover:bg-violet-500/12',
    atendida: 'border-l-success bg-success/7 hover:bg-success/12',
    cancelada: 'border-l-error bg-error/7 opacity-65 hover:bg-error/12',
    vencida: 'border-l-error bg-error/12 text-error hover:bg-error/18'
  }[estado] ?? 'border-l-default bg-default/70'
}
</script>

<template>
  <Head title="Calendario de citas" />
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar title="Calendario de citas">
        <template #leading><UDashboardSidebarCollapse /></template>
        <template #right>
          <Link :href="route('citas.index')"><UButton label="Ver lista" icon="i-lucide-list" color="neutral" variant="outline" /></Link>
          <Link v-if="can('citas.crear')" :href="route('citas.create')"><UButton label="Nueva cita" icon="i-lucide-calendar-plus" /></Link>
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="space-y-5">
        <section class="overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/12 via-elevated to-default shadow-lg shadow-primary/5">
          <div class="flex flex-col gap-5 p-4 lg:flex-row lg:items-center lg:justify-between lg:p-5">
            <div class="flex items-center gap-2">
              <UButton icon="i-lucide-chevron-left" color="neutral" variant="ghost" aria-label="Periodo anterior" @click="moverPeriodo(-1)" />
              <UButton label="Hoy" color="neutral" variant="outline" @click="consultar({ fecha: claveFecha(new Date()) })" />
              <UButton icon="i-lucide-chevron-right" color="neutral" variant="ghost" aria-label="Periodo siguiente" @click="moverPeriodo(1)" />
            </div>

            <div class="text-left lg:text-center">
              <p class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-primary">Planificación del taller</p>
              <h1 class="mt-1 text-xl font-black capitalize text-highlighted">{{ tituloPeriodo }}</h1>
            </div>

            <div class="flex rounded-xl border border-default bg-default/65 p-1">
              <UButton v-for="opcion in vistas" :key="opcion.value" :label="opcion.label" :icon="opcion.icon" size="sm" :color="vista === opcion.value ? 'primary' : 'neutral'" :variant="vista === opcion.value ? 'solid' : 'ghost'" @click="cambiarVista(opcion.value)" />
            </div>
          </div>

          <div class="grid gap-3 border-t border-default/70 p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_auto_auto_auto_auto_auto]">
            <USelect v-model="filtros.mecanico" :items="[{ label: 'Todos los mecánicos', value: 'todos' }, ...mecanicos]" icon="i-lucide-hard-hat" @update:model-value="consultar()" />
            <USelect v-model="filtros.estado" :items="estados" icon="i-lucide-list-filter" @update:model-value="consultar()" />
            <div class="rounded-lg border border-default bg-default/55 px-3 py-2"><p class="text-[10px] uppercase text-muted">Total</p><p class="font-mono text-lg font-bold">{{ resumen.total }}</p></div>
            <div class="rounded-lg border border-warning/20 bg-warning/5 px-3 py-2"><p class="text-[10px] uppercase text-warning">Pendientes</p><p class="font-mono text-lg font-bold">{{ resumen.pendientes }}</p></div>
            <div class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2"><p class="text-[10px] uppercase text-primary">Confirmadas</p><p class="font-mono text-lg font-bold">{{ resumen.confirmadas }}</p></div>
            <div class="rounded-lg border border-success/20 bg-success/5 px-3 py-2"><p class="text-[10px] uppercase text-success">Atendidas</p><p class="font-mono text-lg font-bold">{{ resumen.atendidas }}</p></div>
            <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2"><p class="text-[10px] uppercase text-error">Vencidas</p><p class="font-mono text-lg font-bold text-error">{{ resumen.vencidas }}</p></div>
          </div>
        </section>

        <div class="flex flex-col gap-3 rounded-xl border border-default bg-elevated/45 px-4 py-2.5 text-xs text-muted lg:flex-row lg:items-center lg:justify-between"><div class="flex flex-wrap items-center gap-4"><span class="font-semibold text-highlighted">Lectura del calendario:</span><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-success"/>Día con horario disponible</span><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-primary"/>Día con citas programadas</span><span>Sin marca: no hay horario configurado para el filtro actual</span></div><Link v-if="filtros.mecanico!=='todos'&&can('mecanicos.gestionar')" :href="route('mecanicos.edit',filtros.mecanico)"><UButton label="Configurar horario" icon="i-lucide-calendar-cog" size="xs" color="neutral" variant="outline"/></Link></div>

        <section v-if="vista === 'semana'" class="overflow-x-auto pb-2">
          <div class="grid min-w-[1080px] grid-cols-7 gap-3">
            <article v-for="dia in diasPeriodo" :key="claveFecha(dia)" class="min-h-[28rem] overflow-hidden rounded-xl border bg-elevated/75" :class="claseDia(dia)">
              <header class="border-b border-default p-3" :class="esHoy(dia) ? 'bg-primary/10' : disponibilidadDia(dia).disponible ? 'bg-success/10' : 'bg-default/45'">
                <div class="flex items-center justify-between gap-2"><p class="text-xs font-bold uppercase text-muted">{{ formatoFecha(dia, { weekday: 'short' }) }}</p><UBadge v-if="esHoy(dia)" size="xs">Hoy</UBadge><UBadge v-else-if="disponibilidadDia(dia).disponible" color="success" variant="subtle" size="xs">Disponible</UBadge></div>
                <div class="mt-1 flex items-end justify-between"><p class="font-mono text-2xl font-black">{{ dia.getDate() }}</p><span class="text-xs text-muted">{{ (citasPorDia.get(claveFecha(dia)) ?? []).length }} citas</span></div>
                <p v-if="disponibilidadDia(dia).disponible" class="mt-1 truncate text-[10px] font-medium text-success">{{ disponibilidadDia(dia).label }}</p>
              </header>
              <div class="space-y-2 p-2.5">
                <button v-for="cita in citasPorDia.get(claveFecha(dia)) ?? []" :key="cita.id" type="button" class="w-full rounded-lg border border-default border-l-[3px] p-3 text-left transition hover:-translate-y-0.5 hover:shadow-md" :class="claseCita(cita.estado)" @click="seleccionada = cita">
                  <div class="flex items-center justify-between gap-2"><span class="font-mono text-xs font-black">{{ hora(cita.inicio) }}</span><UBadge :color="colorEstado(cita.estado)" variant="subtle" size="xs">{{ cita.estado }}</UBadge></div>
                  <p class="mt-2 truncate text-sm font-bold">{{ cita.vehiculo }}</p>
                  <p class="truncate text-xs text-muted">{{ cita.servicio || 'Servicio por definir' }}</p>
                  <div class="mt-2 flex items-center gap-1.5 border-t border-default/70 pt-2 text-[11px] text-muted"><UIcon name="i-lucide-hard-hat" class="size-3"/><span class="truncate">{{ cita.mecanico || 'Sin asignar' }}</span></div>
                </button>
                <div v-if="!(citasPorDia.get(claveFecha(dia)) ?? []).length" class="grid min-h-36 place-items-center rounded-lg border border-dashed text-center" :class="disponibilidadDia(dia).disponible?'border-success/30 bg-success/5':'border-default'"><div><UIcon name="i-lucide-calendar-check" class="mx-auto size-5" :class="disponibilidadDia(dia).disponible?'text-success':'text-dimmed'"/><p class="mt-2 text-xs" :class="disponibilidadDia(dia).disponible?'font-medium text-success':'text-muted'">{{disponibilidadDia(dia).disponible?'Horario disponible':'Sin citas'}}</p></div></div>
              </div>
            </article>
          </div>
        </section>

        <section v-else-if="vista === 'mes'" class="overflow-hidden rounded-xl border border-default bg-elevated/75">
          <div class="overflow-x-auto">
            <div class="min-w-[900px]">
              <div class="grid grid-cols-7 border-b border-default bg-default/55"><div v-for="dia in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="dia" class="p-3 text-center font-mono text-xs font-bold uppercase text-muted">{{ dia }}</div></div>
              <div class="grid grid-cols-7">
                <div v-for="(dia, indice) in celdasMes" :key="`${indice}-${dia?.getDate()}`" class="min-h-36 border-r border-b border-default p-2" :class="dia ? (citasPorDia.get(claveFecha(dia))??[]).length?'bg-primary/5':disponibilidadDia(dia).disponible?'bg-success/5':'bg-default/20' : 'bg-elevated/30'">
                  <template v-if="dia">
                     <div class="flex items-center justify-between gap-2"><span class="grid size-7 place-items-center rounded-full font-mono text-xs font-bold" :class="esHoy(dia) ? 'bg-primary text-white' : 'text-muted'">{{ dia.getDate() }}</span><span v-if="(citasPorDia.get(claveFecha(dia)) ?? []).length" class="font-mono text-[10px] text-muted">{{ (citasPorDia.get(claveFecha(dia)) ?? []).length }} citas</span><span v-else-if="disponibilidadDia(dia).disponible" class="size-2 rounded-full bg-success" :title="`Disponible: ${disponibilidadDia(dia).label}`"/></div>
                    <div class="mt-1.5 space-y-1">
                      <button v-for="cita in (citasPorDia.get(claveFecha(dia)) ?? []).slice(0, 3)" :key="cita.id" type="button" class="flex w-full items-center gap-1.5 rounded-md border-l-2 px-2 py-1.5 text-left text-[11px]" :class="claseCita(cita.estado)" @click="seleccionada = cita"><span class="font-mono font-bold">{{ hora(cita.inicio) }}</span><span class="truncate">{{ cita.vehiculo }}</span></button>
                      <p v-if="(citasPorDia.get(claveFecha(dia)) ?? []).length > 3" class="px-2 text-[10px] font-medium text-primary">+ {{ (citasPorDia.get(claveFecha(dia)) ?? []).length - 3 }} más</p>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section v-else class="mx-auto max-w-5xl overflow-hidden rounded-xl border border-default bg-elevated/80">
          <header class="border-b border-default bg-default/45 p-4"><p class="font-mono text-xs font-bold uppercase tracking-wider text-primary">Agenda del día</p><h2 class="mt-1 text-xl font-bold capitalize">{{ tituloPeriodo }}</h2></header>
          <div v-if="citas.length" class="divide-y divide-default">
            <button v-for="cita in citas" :key="cita.id" type="button" class="grid w-full gap-4 p-4 text-left transition hover:bg-default/55 sm:grid-cols-[6rem_1fr_auto] sm:items-center" @click="seleccionada = cita">
              <div><p class="font-mono text-xl font-black text-primary">{{ hora(cita.inicio) }}</p><p class="text-xs text-muted">hasta {{ hora(cita.fin) }}</p></div>
              <div class="min-w-0"><p class="font-bold">{{ cita.vehiculo }}</p><p class="truncate text-sm text-muted">{{ cita.cliente }} · {{ cita.servicio || 'Servicio por definir' }}</p><p class="mt-1 truncate text-xs text-muted">{{ cita.mecanico || 'Sin mecánico asignado' }}</p></div>
              <UBadge :color="colorEstado(cita.estado)" variant="subtle">{{ cita.estado }}</UBadge>
            </button>
          </div>
          <div v-else class="grid min-h-64 place-items-center text-center"><div><UIcon name="i-lucide-calendar-check-2" class="mx-auto size-10" :class="disponibilidadDia(fechaLocal(fecha)).disponible?'text-success':'text-dimmed'"/><p class="mt-3 font-semibold">{{disponibilidadDia(fechaLocal(fecha)).disponible?'Día disponible':'Sin citas'}}</p><p class="mt-1 text-sm text-muted">{{disponibilidadDia(fechaLocal(fecha)).disponible?disponibilidadDia(fechaLocal(fecha)).label:'No hay citas ni horarios disponibles para los filtros seleccionados.'}}</p></div></div>
        </section>
      </div>
    </template>
  </UDashboardPanel>

  <UModal v-model:open="detalleAbierto" title="Detalle de la cita" :description="seleccionada?.numero">
    <template #body>
      <div v-if="seleccionada" class="space-y-4">
        <div class="flex items-start justify-between gap-3"><div><p class="text-lg font-bold">{{ seleccionada.vehiculo }}</p><p class="text-sm text-muted">{{ seleccionada.cliente }}</p></div><UBadge :color="colorEstado(seleccionada.estado)" variant="subtle">{{ seleccionada.estado }}</UBadge></div>
        <dl class="grid gap-3 rounded-xl border border-default bg-elevated/55 p-4 text-sm sm:grid-cols-2"><div><dt class="text-muted">Horario</dt><dd class="font-medium">{{ hora(seleccionada.inicio) }} - {{ hora(seleccionada.fin) }}</dd></div><div><dt class="text-muted">Mecánico</dt><dd class="font-medium">{{ seleccionada.mecanico || 'Sin asignar' }}</dd></div><div><dt class="text-muted">Servicio</dt><dd class="font-medium">{{ seleccionada.servicio || 'Por definir' }}</dd></div><div><dt class="text-muted">Número</dt><dd class="font-mono font-medium">{{ seleccionada.numero }}</dd></div></dl>
        <div><p class="text-sm text-muted">Motivo</p><p class="mt-1 text-sm leading-6">{{ seleccionada.motivo }}</p></div>
      </div>
    </template>
    <template #footer><div class="flex w-full flex-wrap justify-end gap-2"><UButton v-if="seleccionada&&['pendiente','reprogramada'].includes(seleccionada.estado)&&can('citas.gestionar')" label="Confirmar" icon="i-lucide-check" color="primary" variant="soft" @click="cambiarEstadoCita('confirmada')"/><UButton v-if="seleccionada&&['confirmada','vencida'].includes(seleccionada.estado)&&can('citas.gestionar')" label="Marcar atendida" icon="i-lucide-circle-check" color="success" variant="soft" @click="cambiarEstadoCita('atendida')"/><UButton v-if="seleccionada&&!['atendida','cancelada'].includes(seleccionada.estado)&&can('citas.cancelar')" label="Cancelar cita" icon="i-lucide-calendar-x" color="error" variant="ghost" @click="cambiarEstadoCita('cancelada')"/><UButton v-if="seleccionada?.estado==='vencida'&&can('citas.gestionar')" label="Reprogramar en lista" icon="i-lucide-calendar-sync" color="neutral" variant="outline" @click="router.visit(route('citas.index',{estado:'vencida'}))"/><UButton v-if="seleccionada?.estado==='atendida'&&!seleccionada.ordenId&&can('ordenes.crear')" label="Crear orden" icon="i-lucide-clipboard-plus" @click="router.post(route('citas.convertir-orden',seleccionada.id))"/><UButton v-if="seleccionada?.ordenId&&can('ordenes.ver')" :label="seleccionada.ordenNumero||'Ver orden'" icon="i-lucide-clipboard-list" color="neutral" variant="outline" @click="router.visit(route('ordenes.show',seleccionada.ordenId))"/><UButton label="Cerrar" color="neutral" variant="outline" @click="seleccionada = null" /></div></template>
  </UModal>
</template>
