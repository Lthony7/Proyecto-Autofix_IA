<script setup lang="ts">
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { Vehiculo } from '../../types'
import { usePermissions } from '../../composables/usePermissions'

const props = defineProps<{ vehiculos: { data: Vehiculo[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null; total: number }; filters: { buscar?: string; estado?: string } }>()
const { can } = usePermissions()
const filters = reactive({ buscar: props.filters.buscar ?? '', estado: props.filters.estado ?? 'todos' })
const estados = [{ label: 'Todos', value: 'todos' }, { label: 'Activos', value: 'activo' }, { label: 'Inactivos', value: 'inactivo' }, { label: 'Archivados', value: 'archivado' }]

function buscar() { router.get(route('vehiculos.index'), { buscar: filters.buscar || undefined, estado: filters.estado === 'todos' ? undefined : filters.estado }, { preserveState: true, replace: true }) }
function cambiarEstado(vehiculo: Vehiculo) {
  const estado = vehiculo.estado === 'activo' ? 'inactivo' : 'activo'
  if (window.confirm(`¿Cambiar ${vehiculo.placa} a estado ${estado}?`)) router.patch(route('vehiculos.estado', vehiculo.id), { estado }, { preserveScroll: true })
}
</script>

<template>
  <Head title="Vehículos" />
  <UDashboardPanel id="vehiculos">
    <template #header><UDashboardNavbar title="Vehículos"><template #leading><UDashboardSidebarCollapse /></template><template #right><UButton v-if="can('vehiculos.crear')" label="Nuevo vehículo" icon="i-lucide-plus" @click="router.visit(route('vehiculos.create'))" /></template></UDashboardNavbar></template>
    <template #body>
      <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="buscar"><UInput v-model="filters.buscar" class="w-full sm:max-w-md" icon="i-lucide-search" placeholder="Placa, marca, modelo o cliente" /><USelect v-model="filters.estado" class="w-full sm:w-48" :items="estados" /><UButton type="submit" label="Filtrar" /></form>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <UCard v-for="vehiculo in vehiculos.data" :key="vehiculo.id">
          <div class="flex items-start justify-between gap-3"><div><p class="text-lg font-bold tracking-wider">{{ vehiculo.placa }}</p><p class="font-medium">{{ vehiculo.marca }} {{ vehiculo.modelo }} · {{ vehiculo.anio }}</p></div><UBadge :color="vehiculo.estado === 'activo' ? 'success' : 'neutral'" variant="subtle">{{ vehiculo.estado }}</UBadge></div>
          <div class="mt-4 space-y-1 text-sm"><p><span class="text-muted">Cliente:</span> {{ vehiculo.cliente }}</p><p><span class="text-muted">Kilometraje:</span> {{ vehiculo.kilometraje.toLocaleString('es-CO') }} km</p><p><span class="text-muted">Combustible:</span> {{ vehiculo.combustible }}</p></div>
          <template #footer><div class="flex justify-end gap-2"><UButton v-if="can('vehiculos.editar')" label="Editar" size="sm" color="neutral" variant="ghost" icon="i-lucide-pencil" @click="router.visit(route('vehiculos.edit', vehiculo.id))" /><UButton v-if="can('vehiculos.desactivar')" label="Cambiar estado" size="sm" color="neutral" variant="ghost" @click="cambiarEstado(vehiculo)" /></div></template>
        </UCard>
        <UCard v-if="!vehiculos.data.length" class="md:col-span-2 xl:col-span-3"><p class="py-8 text-center text-muted">No hay vehículos que coincidan con los filtros.</p></UCard>
      </div>
      <div class="flex flex-col items-center justify-between gap-3 sm:flex-row"><p class="text-sm text-muted">Página {{ vehiculos.current_page }} de {{ vehiculos.last_page }} · {{ vehiculos.total }} registros</p><div class="flex gap-2"><UButton label="Anterior" color="neutral" variant="outline" :disabled="!vehiculos.prev_page_url" @click="vehiculos.prev_page_url && router.visit(vehiculos.prev_page_url)" /><UButton label="Siguiente" color="neutral" variant="outline" :disabled="!vehiculos.next_page_url" @click="vehiculos.next_page_url && router.visit(vehiculos.next_page_url)" /></div></div>
    </template>
  </UDashboardPanel>
</template>
