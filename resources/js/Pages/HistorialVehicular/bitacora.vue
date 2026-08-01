<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

interface Opcion { label:string; value:string }
interface Registro {
  id:string; fecha:string; accion:string; accionLabel:string; descripcion:string; cambios?:Record<string,unknown>;
  ip?:string; rol:string; usuario?:{nombre:string;email:string}; vehiculo:{id:string;placa:string;descripcion:string}
}
interface Pagina<T> { data:T[]; current_page:number; last_page:number; total:number; prev_page_url:string|null; next_page_url:string|null }
const props=defineProps<{registros:Pagina<Registro>;vehiculos:Opcion[];usuarios:Opcion[];acciones:Opcion[];filtros:Record<string,string>}>()
const buscar=ref(props.filtros.buscar||'')
const filtros=reactive({vehiculo:props.filtros.vehiculo||'todos',usuario:props.filtros.usuario||'todos',accion:props.filtros.accion||'todos',desde:props.filtros.desde||'',hasta:props.filtros.hasta||''})
let temporizador:ReturnType<typeof setTimeout>
function aplicar(){router.get(route('historial-vehicular.bitacora'),{buscar:buscar.value||undefined,vehiculo:filtros.vehiculo==='todos'?undefined:filtros.vehiculo,usuario:filtros.usuario==='todos'?undefined:filtros.usuario,accion:filtros.accion==='todos'?undefined:filtros.accion,desde:filtros.desde||undefined,hasta:filtros.hasta||undefined},{preserveState:true,replace:true})}
watch(buscar,()=>{clearTimeout(temporizador);temporizador=setTimeout(aplicar,350)})
function limpiar(){buscar.value='';Object.assign(filtros,{vehiculo:'todos',usuario:'todos',accion:'todos',desde:'',hasta:''});aplicar()}
</script>

<template>
  <Head title="Bitácora vehicular" />
  <UDashboardPanel>
    <template #header><UDashboardNavbar title="Bitácora de acciones"><template #leading><UDashboardSidebarCollapse/></template><template #right><Link :href="route('historial-vehicular.index')"><UButton label="Volver" icon="i-lucide-arrow-left" color="neutral" variant="outline"/></Link></template></UDashboardNavbar></template>
    <template #body><div class="space-y-5">
      <div><p class="text-sm text-muted">Registro automático e inalterable de operaciones sobre vehículos</p><p class="text-3xl font-semibold">{{registros.total}} eventos</p></div>
      <form class="grid gap-3 rounded-lg border border-default bg-default/80 p-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="aplicar">
        <UInput v-model="buscar" icon="i-lucide-search" placeholder="Buscar en tiempo real" class="w-full"/>
        <USelect v-model="filtros.vehiculo" :items="[{label:'Todos los vehículos',value:'todos'},...vehiculos]" class="w-full"/>
        <USelect v-model="filtros.usuario" :items="[{label:'Todos los usuarios',value:'todos'},...usuarios]" class="w-full"/>
        <USelect v-model="filtros.accion" :items="[{label:'Todas las acciones',value:'todos'},...acciones]" class="w-full"/>
        <UInput v-model="filtros.desde" type="date" class="w-full"/>
        <UInput v-model="filtros.hasta" type="date" class="w-full"/>
        <div class="flex gap-2 md:col-span-2"><UButton type="submit" label="Aplicar filtros" icon="i-lucide-list-filter"/><UButton type="button" label="Limpiar" color="neutral" variant="outline" @click="limpiar"/></div>
      </form>
      <div class="overflow-x-auto rounded-lg border border-default bg-default/85">
        <table class="w-full min-w-[1050px] text-sm"><thead class="bg-elevated/80 text-left"><tr><th class="p-3">Fecha</th><th class="p-3">Vehículo</th><th class="p-3">Acción</th><th class="p-3">Usuario y rol</th><th class="p-3">Detalle</th><th class="p-3">IP</th></tr></thead>
          <tbody><tr v-for="registro in registros.data" :key="registro.id" class="border-t border-default align-top"><td class="whitespace-nowrap p-3">{{new Date(registro.fecha).toLocaleString('es-CO')}}</td><td class="p-3"><Link :href="route('historial-vehicular.show',registro.vehiculo.id)" class="font-semibold text-primary hover:underline">{{registro.vehiculo.placa}}</Link><p class="text-xs text-muted">{{registro.vehiculo.descripcion}}</p></td><td class="p-3"><UBadge variant="subtle">{{registro.accionLabel}}</UBadge></td><td class="p-3"><p class="font-medium">{{registro.usuario?.nombre||'Sistema'}}</p><p class="text-xs text-muted">{{registro.rol}}</p></td><td class="max-w-md p-3"><p>{{registro.descripcion}}</p><details v-if="registro.cambios" class="mt-2"><summary class="cursor-pointer text-xs text-primary">Ver cambios</summary><pre class="mt-2 max-w-md whitespace-pre-wrap rounded bg-elevated p-2 text-xs">{{JSON.stringify(registro.cambios,null,2)}}</pre></details></td><td class="p-3 text-muted">{{registro.ip||'No disponible'}}</td></tr></tbody>
        </table><p v-if="!registros.data.length" class="py-10 text-center text-muted">No existen eventos para los filtros seleccionados.</p>
      </div>
      <div class="flex flex-col items-center justify-between gap-3 sm:flex-row"><p class="text-sm text-muted">Página {{registros.current_page}} de {{registros.last_page}}</p><div class="flex gap-2"><UButton label="Anterior" color="neutral" variant="outline" :disabled="!registros.prev_page_url" @click="registros.prev_page_url&&router.visit(registros.prev_page_url)"/><UButton label="Siguiente" color="neutral" variant="outline" :disabled="!registros.next_page_url" @click="registros.next_page_url&&router.visit(registros.next_page_url)"/></div></div>
    </div></template>
  </UDashboardPanel>
</template>
