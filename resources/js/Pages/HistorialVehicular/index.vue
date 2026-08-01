<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

interface Vehiculo { id:string; placa:string; marca:string; modelo:string; anio:number; kilometraje:number; estado:string; propietario?:string; visitas:number }
interface Pagina<T> { data:T[]; current_page:number; last_page:number; total:number; prev_page_url:string|null; next_page_url:string|null }
const props=defineProps<{vehiculos:Pagina<Vehiculo>;buscar:string;modoCliente:boolean}>()
const busqueda=ref(props.buscar)
const rutaIndice=props.modoCliente?'mi-historial.index':'historial-vehicular.index'
const rutaDetalle=props.modoCliente?'mi-historial.show':'historial-vehicular.show'
let temporizador:ReturnType<typeof setTimeout>
function filtrar(){router.get(route(rutaIndice),{buscar:busqueda.value||undefined},{preserveState:true,replace:true})}
watch(busqueda,()=>{clearTimeout(temporizador);temporizador=setTimeout(filtrar,350)})
</script>

<template>
  <Head :title="modoCliente?'Mi Historial de Servicios':'Historial de servicios'" />
  <UDashboardPanel>
    <template #header><UDashboardNavbar :title="modoCliente?'Mi Historial de Servicios':'Historial de servicios'"><template #leading><UDashboardSidebarCollapse /></template></UDashboardNavbar></template>
    <template #body>
      <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div><p class="text-sm text-muted">{{modoCliente?'Servicios, diagnósticos, repuestos y pagos de tus vehículos':'Reparaciones, mantenimientos, diagnósticos y costos por vehículo'}}</p><p class="text-3xl font-semibold">{{vehiculos.total}} vehículos</p></div>
          <form class="flex w-full gap-2 sm:max-w-md" @submit.prevent="filtrar"><UInput v-model="busqueda" class="w-full" icon="i-lucide-search" placeholder="Placa, marca, modelo o propietario"/><UButton type="submit" label="Buscar"/></form>
        </div>
        <ul v-if="vehiculos.data.length" class="divide-y divide-default overflow-hidden rounded-lg border border-default bg-elevated">
          <li v-for="vehiculo in vehiculos.data" :key="vehiculo.id" class="p-4 sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-3 sm:justify-start"><div><p class="text-xl font-bold tracking-wider">{{vehiculo.placa}}</p><p>{{vehiculo.marca}} {{vehiculo.modelo}} · {{vehiculo.anio}}</p></div><UBadge :color="vehiculo.estado==='activo'?'success':'neutral'">{{vehiculo.estado}}</UBadge></div>
                <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-3"><div><dt class="text-muted">Propietario</dt><dd>{{vehiculo.propietario||'Sin propietario'}}</dd></div><div><dt class="text-muted">Kilometraje actual</dt><dd>{{Number(vehiculo.kilometraje).toLocaleString('es-CO')}} km</dd></div><div><dt class="text-muted">Visitas visibles</dt><dd>{{vehiculo.visitas}}</dd></div></dl>
              </div>
              <Link :href="route(rutaDetalle,vehiculo.id)" class="self-start lg:self-center"><UButton label="Ver historial" icon="i-lucide-history" size="sm"/></Link>
            </div>
          </li>
        </ul>
        <div v-else class="rounded-lg border border-default bg-elevated"><p class="py-8 text-center text-muted">No hay vehículos disponibles.</p></div>
        <div class="flex flex-col items-center justify-between gap-3 sm:flex-row"><p class="text-sm text-muted">Página {{vehiculos.current_page}} de {{vehiculos.last_page}}</p><div class="flex gap-2"><UButton label="Anterior" color="neutral" variant="outline" :disabled="!vehiculos.prev_page_url" @click="vehiculos.prev_page_url&&router.visit(vehiculos.prev_page_url)"/><UButton label="Siguiente" color="neutral" variant="outline" :disabled="!vehiculos.next_page_url" @click="vehiculos.next_page_url&&router.visit(vehiculos.next_page_url)"/></div></div>
      </div>
    </template>
  </UDashboardPanel>
</template>
