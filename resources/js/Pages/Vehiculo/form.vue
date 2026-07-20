<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { Vehiculo } from '../../types'

const props = defineProps<{ vehiculo: Vehiculo | null; clientes: { label: string; value: string }[] }>()
const editando = computed(() => !!props.vehiculo)
const state = reactive({ clienteId: props.vehiculo?.clienteId ?? '', placa: props.vehiculo?.placa ?? '', marca: props.vehiculo?.marca ?? '', modelo: props.vehiculo?.modelo ?? '', anio: props.vehiculo?.anio ?? new Date().getFullYear(), color: props.vehiculo?.color ?? '', kilometraje: props.vehiculo?.kilometraje ?? 0, combustible: props.vehiculo?.combustible ?? 'gasolina', observaciones: props.vehiculo?.observaciones ?? '' })
const page = usePage()
const errors = computed<Record<string, string>>(() => page.props.errors as Record<string, string>)
const procesando = ref(false)
const combustibles = [{ label: 'Gasolina', value: 'gasolina' }, { label: 'Diésel', value: 'diesel' }, { label: 'Gas', value: 'gas' }, { label: 'Híbrido', value: 'hibrido' }, { label: 'Eléctrico', value: 'electrico' }]

function guardar() {
  procesando.value = true
  const options = { onFinish: () => { procesando.value = false } }
  if (props.vehiculo) router.put(route('vehiculos.update', props.vehiculo.id), state, options)
  else router.post(route('vehiculos.store'), state, options)
}
</script>

<template>
  <Head :title="editando ? 'Editar vehículo' : 'Nuevo vehículo'" />
  <UDashboardPanel><template #header><UDashboardNavbar :title="editando ? 'Editar vehículo' : 'Nuevo vehículo'"><template #leading><UDashboardSidebarCollapse /></template></UDashboardNavbar></template><template #body>
    <form class="mx-auto w-full max-w-5xl space-y-6" @submit.prevent="guardar">
      <UCard><template #header><div><h2 class="font-semibold">Propietario e identificación</h2><p class="text-sm text-muted">Selecciona un cliente activo e identifica el vehículo.</p></div></template><div class="grid gap-5 md:grid-cols-2"><UFormField label="Cliente" required :error="errors.clienteId"><USelect v-model="state.clienteId" class="w-full" :items="clientes" searchable /></UFormField><UFormField label="Placa" required :error="errors.placa || errors.placaNormalizada"><UInput v-model="state.placa" class="w-full uppercase" maxlength="20" /></UFormField><UFormField label="Marca" required :error="errors.marca"><UInput v-model="state.marca" class="w-full" /></UFormField><UFormField label="Modelo" required :error="errors.modelo"><UInput v-model="state.modelo" class="w-full" /></UFormField></div></UCard>
      <UCard><template #header><h2 class="font-semibold">Características y condición</h2></template><div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4"><UFormField label="Año" required :error="errors.anio"><UInput v-model.number="state.anio" type="number" class="w-full" min="1900" /></UFormField><UFormField label="Color" :error="errors.color"><UInput v-model="state.color" class="w-full" /></UFormField><UFormField label="Kilometraje" required :error="errors.kilometraje"><UInput v-model.number="state.kilometraje" type="number" class="w-full" min="0" /></UFormField><UFormField label="Combustible" required :error="errors.combustible"><USelect v-model="state.combustible" class="w-full" :items="combustibles" /></UFormField></div><UFormField class="mt-5" label="Observaciones" :error="errors.observaciones"><UTextarea v-model="state.observaciones" class="w-full" :rows="4" maxlength="2000" /></UFormField></UCard>
      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><UButton type="button" color="neutral" variant="outline" label="Cancelar" :disabled="procesando" @click="router.visit(route('vehiculos.index'))" /><UButton type="submit" label="Guardar vehículo" icon="i-lucide-save" :loading="procesando" /></div>
    </form>
  </template></UDashboardPanel>
</template>
