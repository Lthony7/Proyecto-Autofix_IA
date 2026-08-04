<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

interface Vehiculo { id:string;label:string;kilometraje:number;detalle:{marca:string;modelo:string;anio:number;color?:string;combustible?:string} }
interface Cliente { id:string;nombre:string;vehiculos:Vehiculo[] }
const props=defineProps<{clientes:Cliente[];categorias:string[]}>()
const paso=ref(1);const procesando=ref(false);const aviso=ref('')
const errors=computed<Record<string,string>>(()=>usePage().props.errors as Record<string,string>)
const pasos=[{titulo:'Vehículo',icon:'i-lucide-car-front'},{titulo:'Falla',icon:'i-lucide-scan-search'},{titulo:'Contexto',icon:'i-lucide-clipboard-pulse'},{titulo:'Confirmar',icon:'i-lucide-sparkles'}]
const categorias=[
  {value:'electrico',label:'Sistema eléctrico',icon:'i-lucide-zap'}, {value:'motor',label:'Motor',icon:'i-lucide-gauge'},
  {value:'frenos',label:'Frenos',icon:'i-lucide-circle-dot'}, {value:'suspension',label:'Suspensión / dirección',icon:'i-lucide-move'},
  {value:'transmision',label:'Transmisión / embrague',icon:'i-lucide-settings'}, {value:'climatizacion',label:'Climatización',icon:'i-lucide-snowflake'},
  {value:'otro',label:'Otro sistema',icon:'i-lucide-wrench'}]
const condicionesDisponibles=['frio','caliente','detenido','movimiento','acelerar','frenar','girar','subida','carretera','ciudad','lluvia']
const state=reactive({clienteId:'',vehiculoId:'',kilometraje:null as number|null,categoriaFalla:'',sintomaPrincipal:'',momentoOcurre:'',frecuencia:'',tiempoDesdeInicio:'',intensidad:'moderada',condiciones:[]as string[],senales:'',lucesTablero:'',perdidaPotenciaArranque:'',codigosObd:'',pruebasRealizadas:'',puedeCircular:'si',urgenciaPercibida:'media',reparacionesRecientes:'',observaciones:''})
const cliente=computed(()=>props.clientes.find(c=>c.id===state.clienteId));const vehiculos=computed(()=>cliente.value?.vehiculos??[]);const vehiculo=computed(()=>vehiculos.value.find(v=>v.id===state.vehiculoId))
watch(()=>state.clienteId,()=>{state.vehiculoId='';state.kilometraje=null})
watch(vehiculo,v=>{if(v)state.kilometraje=v.kilometraje})
function toggleCondicion(valor:string){const indice=state.condiciones.indexOf(valor);indice>=0?state.condiciones.splice(indice,1):state.condiciones.push(valor)}
function avanzar(){aviso.value='';const faltantes=paso.value===1?!state.clienteId||!state.vehiculoId||state.kilometraje===null:paso.value===2?!state.categoriaFalla||state.sintomaPrincipal.trim().length<10||!state.momentoOcurre||!state.frecuencia||!state.tiempoDesdeInicio||!state.intensidad:paso.value===3?!state.puedeCircular||!state.urgenciaPercibida:false;if(faltantes){aviso.value='Completa los campos obligatorios de este paso antes de continuar.';return}if(paso.value<4)paso.value++;else enviar()}
function enviar(){procesando.value=true;router.post(route('ia.store'),state,{onError:()=>{aviso.value='Revisa los campos señalados antes de generar el diagnóstico.'},onFinish:()=>procesando.value=false})}
</script>

<template>
  <Head title="Nuevo diagnóstico IA"/>
  <UDashboardPanel>
    <template #header><UDashboardNavbar title="Nuevo diagnóstico IA"/></template>
    <template #body><div class="mx-auto w-full max-w-6xl space-y-5">
      <UAlert color="warning" icon="i-lucide-triangle-alert" title="Sugerencia preliminar con revisión humana obligatoria" description="La IA organizará hipótesis y pruebas usando el vehículo y su historial. No autoriza reparaciones, repuestos, costos ni circulación."/>
      <div class="grid grid-cols-4 gap-2">
        <button v-for="(item,index) in pasos" :key="item.titulo" type="button" class="relative rounded-xl border p-3 text-left transition" :class="index+1===paso?'border-primary/40 bg-primary/10 text-primary':index+1<paso?'border-success/25 bg-success/5 text-success':'border-default bg-elevated/55 text-muted'" @click="index+1<paso&&(paso=index+1)"><div class="flex items-center gap-2"><span class="grid size-7 place-items-center rounded-full bg-default font-mono text-xs font-bold">{{index+1}}</span><UIcon :name="item.icon" class="hidden size-4 sm:block"/></div><p class="mt-2 hidden text-xs font-bold sm:block">{{item.titulo}}</p><span v-if="index<3" class="absolute top-1/2 -right-2.5 z-10 h-px w-3 bg-default"/></button>
      </div>
      <UAlert v-if="aviso" color="error" icon="i-lucide-circle-alert" title="Información incompleta" :description="aviso"/>

      <form @submit.prevent="avanzar">
        <Transition name="submenu" mode="out-in">
          <UCard v-if="paso===1" key="paso1"><template #header><div><div class="flex items-center gap-2"><span class="grid size-7 place-items-center rounded-full bg-primary/15 font-mono text-xs font-bold text-primary">1</span><h2 class="font-bold">Cliente y vehículo</h2></div><p class="mt-1 text-sm text-muted">El contexto conocido se carga automáticamente y no vuelve a solicitarse.</p></div></template>
            <div class="grid gap-5 md:grid-cols-2"><UFormField label="Cliente" required :error="errors.clienteId"><USelect v-model="state.clienteId" :items="clientes.map(c=>({label:c.nombre,value:c.id}))" class="w-full"/></UFormField><UFormField label="Vehículo" required :error="errors.vehiculoId"><USelect v-model="state.vehiculoId" :items="vehiculos.map(v=>({label:v.label,value:v.id}))" :disabled="!state.clienteId" class="w-full"/></UFormField><UFormField label="Kilometraje de ingreso" required :hint="vehiculo?`Último registrado: ${vehiculo.kilometraje.toLocaleString('es-CO')} km`:undefined" :error="errors.kilometraje"><UInput v-model.number="state.kilometraje" type="number" :min="vehiculo?.kilometraje??0" max="9999999" class="w-full"/></UFormField></div>
            <div v-if="vehiculo" class="mt-5 grid gap-3 rounded-xl border border-default bg-elevated/55 p-4 sm:grid-cols-4"><div><p class="text-[10px] uppercase text-muted">Marca</p><p class="font-semibold">{{vehiculo.detalle.marca}}</p></div><div><p class="text-[10px] uppercase text-muted">Modelo</p><p class="font-semibold">{{vehiculo.detalle.modelo}}</p></div><div><p class="text-[10px] uppercase text-muted">Año</p><p class="font-semibold">{{vehiculo.detalle.anio}}</p></div><div><p class="text-[10px] uppercase text-muted">Combustible</p><p class="font-semibold">{{vehiculo.detalle.combustible||'No registrado'}}</p></div></div>
          </UCard>

          <UCard v-else-if="paso===2" key="paso2"><template #header><div><div class="flex items-center gap-2"><span class="grid size-7 place-items-center rounded-full bg-primary/15 font-mono text-xs font-bold text-primary">2</span><h2 class="font-bold">¿Qué falla presenta?</h2></div><p class="mt-1 text-sm text-muted">Describe hechos observables. La IA separará el reporte de sus inferencias.</p></div></template>
            <UFormField label="Sistema o tipo de falla" required :error="errors.categoriaFalla||errors.categoria_falla"><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"><button v-for="item in categorias" :key="item.value" type="button" class="rounded-xl border p-3 text-left transition" :class="state.categoriaFalla===item.value?'border-primary bg-primary/10 text-primary shadow-sm':'border-default bg-default/45 hover:border-primary/30'" @click="state.categoriaFalla=item.value"><UIcon :name="item.icon" class="size-5"/><p class="mt-2 text-sm font-semibold">{{item.label}}</p></button></div></UFormField>
            <UFormField class="mt-5" label="Reporte de la falla" required hint="Mínimo 10 caracteres. Indica qué sucede y cómo se manifiesta." :error="errors.sintomaPrincipal||errors.sintoma_principal"><UTextarea v-model="state.sintomaPrincipal" rows="4" minlength="10" maxlength="1500" class="w-full"/></UFormField>
            <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-4"><UFormField label="Momento en que ocurre" required><UInput v-model="state.momentoOcurre" placeholder="Al arrancar, frenar..." class="w-full"/></UFormField><UFormField label="Frecuencia" required><USelect v-model="state.frecuencia" :items="[{label:'Primera vez',value:'primera_vez'},{label:'Ocasional',value:'ocasional'},{label:'Intermitente',value:'intermitente'},{label:'Frecuente',value:'frecuente'},{label:'Permanente',value:'permanente'}]" class="w-full"/></UFormField><UFormField label="Desde cuándo" required><UInput v-model="state.tiempoDesdeInicio" placeholder="Hace 3 días" class="w-full"/></UFormField><UFormField label="Intensidad" required><USelect v-model="state.intensidad" :items="['leve','moderada','severa']" class="w-full"/></UFormField></div>
          </UCard>

          <UCard v-else-if="paso===3" key="paso3"><template #header><div><div class="flex items-center gap-2"><span class="grid size-7 place-items-center rounded-full bg-primary/15 font-mono text-xs font-bold text-primary">3</span><h2 class="font-bold">Síntomas, condiciones y seguridad</h2></div><p class="mt-1 text-sm text-muted">Los detalles opcionales mejoran la certeza sin convertirla en diagnóstico definitivo.</p></div></template>
            <UFormField label="Condiciones en que aparece" hint="Selecciona todas las que correspondan"><div class="flex flex-wrap gap-2"><UButton v-for="condicion in condicionesDisponibles" :key="condicion" type="button" size="sm" :variant="state.condiciones.includes(condicion)?'solid':'outline'" :color="state.condiciones.includes(condicion)?'primary':'neutral'" :label="condicion" @click="toggleCondicion(condicion)"/></div></UFormField>
            <div class="mt-5 grid gap-4 md:grid-cols-2"><UFormField label="Ruidos, vibraciones, humo, olores o fugas" hint="Opcional"><UTextarea v-model="state.senales" rows="3" class="w-full"/></UFormField><UFormField label="Luces o testigos del tablero" hint="Opcional"><UTextarea v-model="state.lucesTablero" rows="3" class="w-full"/></UFormField><UFormField label="Pérdida de potencia o dificultad de arranque" hint="Opcional"><UTextarea v-model="state.perdidaPotenciaArranque" rows="3" class="w-full"/></UFormField><UFormField label="Códigos OBD" hint="Opcional; copia los códigos exactos"><UTextarea v-model="state.codigosObd" rows="3" placeholder="Ej. P0300, P0171" class="w-full"/></UFormField><UFormField class="md:col-span-2" label="Pruebas ya realizadas y resultados" hint="Opcional"><UTextarea v-model="state.pruebasRealizadas" rows="3" class="w-full"/></UFormField></div>
            <div class="mt-5 grid gap-4 md:grid-cols-2"><UFormField label="¿Puede circular?" required><USelect v-model="state.puedeCircular" :items="[{label:'Sí, sin cambios evidentes',value:'si'},{label:'Solo con precaución',value:'con_dificultad'},{label:'No debe circular',value:'no'}]" class="w-full"/></UFormField><UFormField label="Urgencia percibida" required><USelect v-model="state.urgenciaPercibida" :items="['baja','media','alta','critica']" class="w-full"/></UFormField></div>
          </UCard>

          <UCard v-else key="paso4"><template #header><div><div class="flex items-center gap-2"><span class="grid size-7 place-items-center rounded-full bg-primary/15 font-mono text-xs font-bold text-primary">4</span><h2 class="font-bold">Revisar y generar</h2></div><p class="mt-1 text-sm text-muted">Confirma la información que se usará junto con el historial técnico autorizado.</p></div></template>
            <div class="grid gap-4 md:grid-cols-2"><UFormField label="Reparaciones o piezas recientes" hint="Opcional"><UTextarea v-model="state.reparacionesRecientes" rows="3" class="w-full"/></UFormField><UFormField label="Notas adicionales" hint="Opcional"><UTextarea v-model="state.observaciones" rows="3" class="w-full"/></UFormField></div>
            <div class="mt-5 grid gap-3 rounded-xl border border-default bg-elevated/55 p-4 sm:grid-cols-2 lg:grid-cols-4"><div><p class="text-[10px] uppercase text-muted">Vehículo</p><p class="font-semibold">{{vehiculo?.label}}</p></div><div><p class="text-[10px] uppercase text-muted">Sistema</p><p class="font-semibold">{{categorias.find(c=>c.value===state.categoriaFalla)?.label}}</p></div><div><p class="text-[10px] uppercase text-muted">Frecuencia</p><p class="font-semibold">{{state.frecuencia.replaceAll('_',' ')}}</p></div><div><p class="text-[10px] uppercase text-muted">Seguridad</p><p class="font-semibold">{{state.puedeCircular.replaceAll('_',' ')}}</p></div><div class="sm:col-span-2 lg:col-span-4"><p class="text-[10px] uppercase text-muted">Reporte</p><p class="mt-1 text-sm">{{state.sintomaPrincipal}}</p></div></div>
            <UAlert class="mt-5" color="warning" icon="i-lucide-shield-alert" title="Confirmación humana obligatoria" description="Se guardará una versión estructurada y auditable. Ningún servicio, repuesto, cita o costo será confirmado automáticamente."/>
          </UCard>
        </Transition>
        <div class="mt-5 flex flex-col-reverse justify-between gap-3 sm:flex-row"><UButton type="button" color="neutral" variant="outline" :label="paso===1?'Cancelar':'Anterior'" @click="paso===1?router.visit(route('ia.index')):paso--"/><UButton type="submit" :label="paso<4?'Continuar':'Generar diagnóstico preliminar'" :icon="paso===4?'i-lucide-sparkles':'i-lucide-arrow-right'" :loading="procesando"/></div>
      </form>
    </div></template>
  </UDashboardPanel>
</template>
