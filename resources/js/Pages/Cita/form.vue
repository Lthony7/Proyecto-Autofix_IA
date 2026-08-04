<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

interface Cliente {id:string;nombre:string;vehiculos:{id:string;label:string}[]}
interface Especialidad {id:string;nombre:string}
interface Servicio {id:string;especialidad_id:string;nombre:string;duracion_minutos:number}
interface Horario {dia:number;inicio:string;fin:string;vigenteDesde?:string;vigenteHasta?:string}
interface Mecanico {id:string;nombre:string;especialidadIds:string[];horarios:Horario[]}
interface Ocupacion {mecanicoId:string;fecha:string;horaInicio:string;horaFin:string}

const props=defineProps<{clientes:Cliente[];especialidades:Especialidad[];servicios:Servicio[];mecanicos:Mecanico[];ocupaciones:Ocupacion[];horizonteDias:number;prefill?:{consultaIaId:string;clienteId:string;vehiculoId:string;especialidadId?:string;mecanicoId?:string;kilometraje?:number;motivo:string}|null}>()
const state=reactive({consultaIaId:props.prefill?.consultaIaId??'',clienteId:props.prefill?.clienteId??(props.clientes.length===1?props.clientes[0].id:''),vehiculoId:props.prefill?.vehiculoId??'',especialidadId:props.prefill?.especialidadId??'',servicioId:'',mecanicoId:props.prefill?.mecanicoId??'',fecha:'',horaInicio:'',kilometraje:props.prefill?.kilometraje??null as number|null,motivo:props.prefill?.motivo??''})
const errors=computed<Record<string,string>>(()=>usePage().props.errors as Record<string,string>)
const procesando=ref(false)
const cliente=computed(()=>props.clientes.find(c=>c.id===state.clienteId))
const vehiculos=computed(()=>cliente.value?.vehiculos??[])
const servicios=computed(()=>props.servicios.filter(s=>!state.especialidadId||s.especialidad_id===state.especialidadId))
const mecanicos=computed(()=>props.mecanicos.filter(m=>!state.especialidadId||m.especialidadIds.includes(state.especialidadId)))
const mecanico=computed(()=>props.mecanicos.find(m=>m.id===state.mecanicoId))
const servicio=computed(()=>props.servicios.find(s=>s.id===state.servicioId))
const duracion=computed(()=>servicio.value?.duracion_minutos??60)
const opcionesFecha=computed(()=>{
  if(!mecanico.value)return[]
  const hoy=fechaActualBogota().fecha
  const base=new Date(`${hoy}T12:00:00`)
  return Array.from({length:props.horizonteDias},(_,i)=>{const fecha=new Date(base);fecha.setDate(base.getDate()+i);const valor=claveFecha(fecha);const cupos=slotsFecha(valor);return{valor,cupos,label:`${new Intl.DateTimeFormat('es-CO',{weekday:'short',day:'numeric',month:'short'}).format(fecha)} · ${cupos.length} ${cupos.length===1?'hora disponible':'horas disponibles'}`}}).filter(d=>d.cupos.length).map(d=>({label:d.label,value:d.valor}))
})
const opcionesHora=computed(()=>state.fecha?slotsFecha(state.fecha).map(h=>({label:`${formatoHora(h)} · Disponible (${duracion.value} min)`,value:h})):[])

watch(()=>state.clienteId,()=>{if(!vehiculos.value.some(v=>v.id===state.vehiculoId))state.vehiculoId=''})
watch(()=>state.especialidadId,()=>{if(!servicios.value.some(s=>s.id===state.servicioId))state.servicioId='';if(!mecanicos.value.some(m=>m.id===state.mecanicoId))state.mecanicoId='';if(mecanicos.value.length===1)state.mecanicoId=mecanicos.value[0].id;limpiarAgenda()})
watch(()=>state.servicioId,id=>{const s=props.servicios.find(x=>x.id===id);if(s)state.especialidadId=s.especialidad_id;limpiarAgenda()})
watch(()=>state.mecanicoId,limpiarAgenda)
watch(()=>state.fecha,()=>{state.horaInicio=''})

function limpiarAgenda(){state.fecha='';state.horaInicio=''}
function minutos(hora:string){const[h,m]=hora.split(':').map(Number);return h*60+m}
function desdeMinutos(valor:number){return`${String(Math.floor(valor/60)).padStart(2,'0')}:${String(valor%60).padStart(2,'0')}`}
function claveFecha(fecha:Date){return`${fecha.getFullYear()}-${String(fecha.getMonth()+1).padStart(2,'0')}-${String(fecha.getDate()).padStart(2,'0')}`}
function fechaActualBogota(){const partes=Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:'America/Bogota',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date()).filter(p=>p.type!=='literal').map(p=>[p.type,p.value]));return{fecha:`${partes.year}-${partes.month}-${partes.day}`,minutos:Number(partes.hour)*60+Number(partes.minute)}}
function slotsFecha(fecha:string){
  if(!mecanico.value)return[]
  const diaFecha=new Date(`${fecha}T12:00:00`);const dia=diaFecha.getDay()===0?7:diaFecha.getDay()
  const ahora=fechaActualBogota()
  const horarios=mecanico.value.horarios.filter(h=>h.dia===dia&&(!h.vigenteDesde||h.vigenteDesde<=fecha)&&(!h.vigenteHasta||h.vigenteHasta>=fecha))
  const ocupadas=props.ocupaciones.filter(o=>o.mecanicoId===mecanico.value?.id&&o.fecha===fecha)
  const resultado=new Set<string>()
  for(const horario of horarios){for(let inicio=minutos(horario.inicio);inicio+duracion.value<=minutos(horario.fin);inicio+=30){const fin=inicio+duracion.value;if(fecha===ahora.fecha&&inicio<=ahora.minutos)continue;const solapa=ocupadas.some(o=>inicio<minutos(o.horaFin)&&fin>minutos(o.horaInicio));if(!solapa)resultado.add(desdeMinutos(inicio))}}
  return [...resultado].sort()
}
function formatoHora(hora:string){return new Intl.DateTimeFormat('es-CO',{hour:'numeric',minute:'2-digit',hour12:true}).format(new Date(`2000-01-01T${hora}:00`))}
function guardar(){procesando.value=true;router.post(route('citas.store'),state,{onFinish:()=>procesando.value=false})}
</script>

<template>
  <Head title="Nueva cita"/>
  <UDashboardPanel><template #header><UDashboardNavbar title="Nueva cita"/></template><template #body><form class="mx-auto max-w-5xl space-y-6" @submit.prevent="guardar">
    <UCard><template #header><div><h2 class="font-semibold">Cliente y vehículo</h2><p class="text-sm text-muted">Solo se muestran vehículos activos del cliente.</p></div></template><div class="grid gap-5 md:grid-cols-2"><UFormField label="Cliente" required :error="errors.clienteId||errors.cliente_id"><USelect v-model="state.clienteId" class="w-full" :items="clientes.map(c=>({label:c.nombre,value:c.id}))" required/></UFormField><UFormField label="Vehículo" required :error="errors.vehiculoId||errors.vehiculo_id"><USelect v-model="state.vehiculoId" class="w-full" :items="vehiculos.map(v=>({label:v.label,value:v.id}))" :disabled="!state.clienteId" required/></UFormField><UFormField label="Kilometraje actual" hint="Opcional" :error="errors.kilometraje"><UInput v-model.number="state.kilometraje" type="number" min="0" max="9999999" class="w-full"/></UFormField></div></UCard>

    <UCard><template #header><h2 class="font-semibold">Motivo y servicio</h2></template><UFormField label="Síntomas o motivo" required :error="errors.motivo"><UTextarea v-model="state.motivo" class="w-full" :rows="4" minlength="10" maxlength="3000" required/></UFormField><div class="mt-5 grid gap-5 md:grid-cols-2"><UFormField label="Especialidad" required :error="errors.especialidadId||errors.especialidad_id"><USelect v-model="state.especialidadId" class="w-full" :items="especialidades.map(e=>({label:e.nombre,value:e.id}))" required/></UFormField><UFormField label="Servicio sugerido" hint="Opcional; sin servicio se reserva 60 min" :error="errors.servicioId||errors.servicio_id"><USelect v-model="state.servicioId" class="w-full" :items="servicios.map(s=>({label:`${s.nombre} · ${s.duracion_minutos} min`,value:s.id}))"/></UFormField></div></UCard>

    <UCard><template #header><div><h2 class="font-semibold">Turno y disponibilidad</h2><p class="text-sm text-muted">Solo aparecen fechas laborales y horas sin cruces con otras citas. Los cupos se calculan en intervalos de 30 minutos.</p></div></template><div class="grid gap-5 md:grid-cols-3"><UFormField label="Mecánico" required hint="Según la especialidad" :error="errors.mecanicoId||errors.mecanico_id"><USelect v-model="state.mecanicoId" class="w-full" :items="mecanicos.map(m=>({label:m.nombre,value:m.id}))" :disabled="!state.especialidadId" required/></UFormField><UFormField label="Fecha laboral disponible" required :error="errors.fecha||errors.inicio"><USelect v-model="state.fecha" class="w-full" :items="opcionesFecha" :disabled="!state.mecanicoId||!opcionesFecha.length" :placeholder="!state.mecanicoId?'Selecciona un mecánico':opcionesFecha.length?'Selecciona una fecha':'Sin fechas disponibles'" required/></UFormField><UFormField label="Hora disponible" required :error="errors.horaInicio||errors.inicio"><USelect v-model="state.horaInicio" class="w-full" :items="opcionesHora" :disabled="!state.fecha||!opcionesHora.length" placeholder="Selecciona una hora" required/></UFormField></div><div v-if="mecanico" class="mt-4 rounded-xl border border-success/20 bg-success/5 p-4 text-sm"><div class="flex items-center gap-2"><UIcon name="i-lucide-calendar-check" class="text-success"/><p class="font-semibold">Jornada semanal de {{mecanico.nombre}}</p></div><p class="mt-2 text-muted">{{mecanico.horarios.map(h=>`${['','Lun','Mar','Mié','Jue','Vie','Sáb','Dom'][h.dia]} ${h.inicio}-${h.fin}`).join(' · ')||'Sin horario laboral configurado'}}</p><p v-if="state.fecha&&state.horaInicio" class="mt-2 font-medium text-success">Reserva: {{state.fecha}} a las {{formatoHora(state.horaInicio)}} · duración {{duracion}} minutos.</p></div><UAlert v-if="state.mecanicoId&&!opcionesFecha.length" class="mt-4" color="warning" icon="i-lucide-calendar-x" title="No hay cupos en los próximos días" description="Revisa el horario laboral del mecánico o selecciona otro profesional compatible."/></UCard>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><UButton type="button" label="Cancelar" color="neutral" variant="outline" @click="router.visit(route('citas.index'))"/><UButton type="submit" label="Agendar cita" icon="i-lucide-calendar-plus" :loading="procesando" :disabled="!state.fecha||!state.horaInicio||!state.mecanicoId"/></div>
  </form></template></UDashboardPanel>
</template>
