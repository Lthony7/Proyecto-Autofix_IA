<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { useNow } from '@vueuse/core'
import { route } from 'ziggy-js'
import { usePermissions } from '../composables/usePermissions'

interface Metrica { label:string; value:string|number; prefix?:string; icon:string; tone:string }
interface Orden { id:string; numero:string; placa?:string; vehiculo:string; cliente?:string; falla:string; recibidaEn:string; horas:number; prioridad:string; mecanicos:string[] }
interface Etapa { estado:string; label:string; icon:string; tone:string; total:number; ordenes:Orden[] }
interface Cita { id:string; numero:string; inicio:string; placa?:string; vehiculo:string; servicio?:string; estado:string }
interface Alerta { title:string; description:string; icon:string; tone:string; url:string }
const props=defineProps<{metricas:Metrica[];etapas:Etapa[];proximasCitas:Cita[];alertas:Alerta[];usuario:{nombre:string;roles:string[]}}>()
const { can }=usePermissions()
const ahora=useNow({interval:30000})
const saludo=computed(()=>{const hora=ahora.value.getHours();return hora<12?'Buenos días':hora<19?'Buenas tardes':'Buenas noches'})
const fecha=computed(()=>ahora.value.toLocaleDateString('es-EC',{weekday:'long',day:'numeric',month:'long'}))
const hora=computed(()=>ahora.value.toLocaleTimeString('es-EC',{hour:'2-digit',minute:'2-digit'}))
const tonoMetrica=(tono:string)=>({primary:'from-primary/22 to-primary/5 text-primary',warning:'from-amber-500/20 to-amber-500/5 text-amber-500',success:'from-emerald-500/20 to-emerald-500/5 text-emerald-500',info:'from-sky-500/20 to-sky-500/5 text-sky-500'}[tono]||'from-primary/20 to-primary/5 text-primary')
const tonoEtapa=(tono:string)=>({neutral:'bg-neutral-500',info:'bg-sky-500',warning:'bg-amber-500',success:'bg-emerald-500'}[tono]||'bg-primary')
const colorPrioridad=(prioridad:string)=>prioridad==='critica'?'error':prioridad==='atencion'?'warning':'neutral'
const tiempo=(horas:number)=>horas<24?`${horas} h`:`${Math.floor(horas/24)} d ${horas%24} h`
</script>

<template>
  <Head title="Centro de operaciones" />
  <UDashboardPanel id="home">
    <template #header>
      <UDashboardNavbar title="Centro de operaciones">
        <template #leading><UDashboardSidebarCollapse/></template>
        <template #right><div class="hidden text-right sm:block"><p class="text-sm font-semibold capitalize">{{fecha}}</p><p class="font-mono text-xs text-primary">{{hora}} · operación en línea</p></div></template>
      </UDashboardNavbar>
    </template>

    <template #body><div class="space-y-7 pb-8">
      <section class="workshop-hero relative isolate overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/18 via-default to-elevated p-6 shadow-xl shadow-primary/5 sm:p-8">
        <div class="absolute inset-y-0 right-0 hidden w-1/2 opacity-20 lg:block"><div class="workshop-speed-lines h-full w-full"/></div>
        <div class="absolute -top-24 -right-16 size-72 rounded-full bg-primary/15 blur-3xl"/>
        <div class="relative z-10 flex flex-col gap-7 xl:flex-row xl:items-end xl:justify-between">
          <div class="max-w-3xl">
            <div class="mb-4 flex items-center gap-2"><span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-70"/><span class="relative inline-flex size-2.5 rounded-full bg-emerald-500"/></span><span class="font-mono text-xs font-bold uppercase tracking-[0.22em] text-primary">Taller operativo</span></div>
            <p class="text-sm text-muted">{{saludo}}, {{usuario.nombre}}</p>
            <h1 class="mt-1 max-w-2xl text-3xl font-black tracking-tight text-highlighted sm:text-5xl">Todo el taller.<br><span class="text-primary">Una sola vista.</span></h1>
            <p class="mt-4 max-w-xl text-sm leading-6 text-muted sm:text-base">Controla la carga de trabajo, detecta bloqueos y mueve cada vehículo hacia la entrega.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Link v-if="can('ordenes.crear')" :href="route('ordenes.create')"><UButton label="Nueva orden" icon="i-lucide-plus" size="lg"/></Link>
            <Link v-if="can('citas.crear')" :href="route('citas.create')"><UButton label="Agendar cita" icon="i-lucide-calendar-plus" size="lg" color="neutral" variant="outline"/></Link>
            <Link v-if="can('historial.ver')" :href="route(usuario.roles.includes('Cliente')?'mi-historial.index':'historial-vehicular.index')"><UButton :label="usuario.roles.includes('Cliente')?'Mi historial':'Ver historial'" icon="i-lucide-history" size="lg" color="neutral" variant="outline"/></Link>
          </div>
        </div>
      </section>

      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="metrica in metricas" :key="metrica.label" class="group relative overflow-hidden rounded-xl border border-default bg-default/85 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-lg hover:shadow-primary/5">
          <div class="absolute inset-0 bg-gradient-to-br opacity-60 transition-opacity group-hover:opacity-100" :class="tonoMetrica(metrica.tone)"/>
          <div class="relative flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{metrica.label}}</p><p class="mt-3 text-3xl font-black tracking-tight text-highlighted"><span v-if="metrica.prefix" class="mr-1 text-lg text-muted">{{metrica.prefix}}</span>{{metrica.value}}</p></div><span class="grid size-11 place-items-center rounded-xl border border-current/15 bg-default/65" :class="tonoMetrica(metrica.tone).split(' ').at(-1)"><UIcon :name="metrica.icon" class="size-5"/></span></div>
          <div class="relative mt-5 h-0.5 overflow-hidden rounded-full bg-elevated"><span class="block h-full w-1/3 bg-primary transition-all duration-500 group-hover:w-full"/></div>
        </article>
      </section>

      <section>
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-primary">Flujo del taller</p><h2 class="mt-1 text-2xl font-bold">Órdenes en movimiento</h2></div><Link v-if="can('ordenes.ver')" :href="route('ordenes.index')" class="self-start"><UButton label="Ver todas las órdenes" icon="i-lucide-arrow-up-right" color="neutral" variant="ghost"/></Link></div>
        <div class="grid auto-cols-[88%] grid-flow-col gap-4 overflow-x-auto pb-3 sm:auto-cols-[48%] xl:grid-flow-row xl:grid-cols-4 xl:overflow-visible">
          <article v-for="etapa in etapas" :key="etapa.estado" class="min-h-[22rem] rounded-xl border border-default bg-elevated/45 p-3">
            <header class="mb-3 flex items-center justify-between px-1 py-1"><div class="flex items-center gap-2"><span class="size-2 rounded-full shadow-[0_0_12px_currentColor]" :class="tonoEtapa(etapa.tone)"/><UIcon :name="etapa.icon" class="size-4 text-muted"/><h3 class="text-sm font-bold">{{etapa.label}}</h3></div><span class="grid size-7 place-items-center rounded-full bg-default font-mono text-xs font-bold">{{etapa.total}}</span></header>
            <div class="space-y-3">
              <Link v-for="orden in etapa.ordenes" :key="orden.id" :href="route('ordenes.show',orden.id)" class="group block rounded-lg border border-default bg-default/90 p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                <div class="flex items-start justify-between gap-2"><div><p class="font-mono text-xs font-bold text-primary">{{orden.numero}}</p><p class="mt-1 text-xl font-black tracking-widest">{{orden.placa||'SIN PLACA'}}</p></div><UBadge :color="colorPrioridad(orden.prioridad)" variant="subtle" size="xs">{{tiempo(orden.horas)}}</UBadge></div>
                <p class="mt-1 truncate text-sm font-medium">{{orden.vehiculo}}</p><p class="truncate text-xs text-muted">{{orden.cliente}}</p>
                <p class="mt-3 line-clamp-2 text-xs leading-5 text-muted">{{orden.falla}}</p>
                <div class="mt-4 flex items-center justify-between border-t border-default pt-3"><div class="flex min-w-0 items-center gap-1.5 text-xs text-muted"><UIcon name="i-lucide-hard-hat" class="size-3.5 shrink-0"/><span class="truncate">{{orden.mecanicos.join(', ')||'Sin asignar'}}</span></div><UIcon name="i-lucide-arrow-right" class="size-4 text-dimmed transition group-hover:translate-x-1 group-hover:text-primary"/></div>
              </Link>
              <div v-if="!etapa.ordenes.length" class="grid min-h-44 place-items-center rounded-lg border border-dashed border-default bg-default/35 p-5 text-center"><div><UIcon :name="etapa.icon" class="mx-auto size-7 text-dimmed"/><p class="mt-2 text-sm font-medium">Etapa despejada</p><p class="mt-1 text-xs text-muted">No hay órdenes en este estado.</p></div></div>
              <p v-if="etapa.total>etapa.ordenes.length" class="text-center text-xs text-muted">+ {{etapa.total-etapa.ordenes.length}} órdenes adicionales</p>
            </div>
          </article>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-[1.35fr_1fr]">
        <UCard class="bg-default/85"><template #header><div class="flex items-center justify-between"><div><p class="font-mono text-xs font-bold uppercase tracking-[0.18em] text-primary">Agenda</p><h2 class="mt-1 text-lg font-bold">Próximas citas</h2></div><Link v-if="can('citas.ver')" :href="route('citas.index')"><UButton icon="i-lucide-arrow-up-right" color="neutral" variant="ghost" aria-label="Ver citas"/></Link></div></template>
          <div v-if="proximasCitas.length" class="divide-y divide-default"><Link v-for="cita in proximasCitas" :key="cita.id" :href="route('citas.index')" class="group flex items-center gap-4 py-3 first:pt-0 last:pb-0"><div class="w-14 shrink-0 rounded-lg bg-primary/10 p-2 text-center"><p class="font-mono text-sm font-black text-primary">{{new Date(cita.inicio).toLocaleTimeString('es-EC',{hour:'2-digit',minute:'2-digit'})}}</p><p class="text-[10px] uppercase text-muted">{{new Date(cita.inicio).toLocaleDateString('es-EC',{day:'2-digit',month:'short'})}}</p></div><div class="min-w-0 flex-1"><div class="flex items-center gap-2"><p class="font-bold tracking-wider">{{cita.placa}}</p><UBadge size="xs" variant="subtle">{{cita.estado}}</UBadge></div><p class="truncate text-sm">{{cita.vehiculo}}</p><p class="truncate text-xs text-muted">{{cita.servicio||'Servicio por definir'}}</p></div><UIcon name="i-lucide-chevron-right" class="size-4 text-dimmed transition group-hover:translate-x-1 group-hover:text-primary"/></Link></div>
          <div v-else class="py-10 text-center"><UIcon name="i-lucide-calendar-check" class="mx-auto size-8 text-dimmed"/><p class="mt-3 font-medium">Agenda despejada</p><p class="text-sm text-muted">No hay citas próximas visibles.</p></div>
        </UCard>

        <UCard class="bg-default/85"><template #header><div><p class="font-mono text-xs font-bold uppercase tracking-[0.18em] text-primary">Atención requerida</p><h2 class="mt-1 text-lg font-bold">Alertas operativas</h2></div></template>
          <div v-if="alertas.length" class="space-y-3"><Link v-for="alerta in alertas" :key="alerta.title" :href="alerta.url" class="group flex gap-3 rounded-lg border border-default bg-elevated/55 p-3 transition hover:border-primary/30 hover:bg-elevated"><span class="grid size-10 shrink-0 place-items-center rounded-lg" :class="alerta.tone==='error'?'bg-red-500/10 text-red-500':'bg-amber-500/10 text-amber-500'"><UIcon :name="alerta.icon" class="size-5"/></span><div class="min-w-0 flex-1"><p class="text-sm font-semibold">{{alerta.title}}</p><p class="mt-0.5 text-xs leading-5 text-muted">{{alerta.description}}</p></div><UIcon name="i-lucide-arrow-up-right" class="size-4 text-dimmed transition group-hover:text-primary"/></Link></div>
          <div v-else class="py-10 text-center"><span class="mx-auto grid size-12 place-items-center rounded-full bg-emerald-500/10 text-emerald-500"><UIcon name="i-lucide-shield-check" class="size-6"/></span><p class="mt-3 font-medium">Operación estable</p><p class="text-sm text-muted">No hay alertas que requieran atención.</p></div>
        </UCard>
      </section>
    </div></template>
  </UDashboardPanel>
</template>

<style scoped>
.workshop-hero::after{position:absolute;inset:0;content:"";background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:28px 28px;mask-image:linear-gradient(to right,black,transparent 75%);pointer-events:none}.workshop-speed-lines{background:repeating-linear-gradient(-18deg,transparent 0 24px,color-mix(in srgb,var(--ui-primary) 28%,transparent) 25px 27px,transparent 28px 50px);transform:skewX(-8deg)}
</style>
