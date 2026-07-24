<script setup lang="ts">
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
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
      <ul v-if="vehiculos.data.length" class="divide-y divide-default overflow-hidden rounded-lg border border-default bg-elevated">
        <li v-for="vehiculo in vehiculos.data" :key="vehiculo.id" class="p-4 sm:p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-3 sm:justify-start">
                <div><p class="text-lg font-bold tracking-wider">{{ vehiculo.placa }}</p><p class="font-medium">{{ vehiculo.marca }} {{ vehiculo.modelo }} · {{ vehiculo.anio }}</p></div>
                <UBadge :color="vehiculo.estado === 'activo' ? 'success' : 'neutral'" variant="subtle">{{ vehiculo.estado }}</UBadge>
              </div>
              <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                <div><dt class="text-muted">Cliente</dt><dd>{{ vehiculo.cliente }}</dd></div>
                <div><dt class="text-muted">Kilometraje</dt><dd>{{ vehiculo.kilometraje.toLocaleString('es-CO') }} km</dd></div>
                <div><dt class="text-muted">Combustible</dt><dd>{{ vehiculo.combustible }}</dd></div>
              </dl>
            </div>
            <div class="flex flex-wrap gap-2 lg:justify-end"><Link v-if="can('historial.ver')" :href="route('historial-vehicular.show',vehiculo.id)"><UButton label="Historial" size="sm" color="neutral" variant="ghost" icon="i-lucide-history"/></Link><UButton v-if="can('vehiculos.editar')" label="Editar" size="sm" color="neutral" variant="ghost" icon="i-lucide-pencil" @click="router.visit(route('vehiculos.edit', vehiculo.id))" /><UButton v-if="can('vehiculos.desactivar')" label="Cambiar estado" size="sm" color="neutral" variant="ghost" @click="cambiarEstado(vehiculo)" /></div>
          </div>
        </li>
      </ul>
      <div v-else class="rounded-lg border border-default bg-elevated"><p class="py-8 text-center text-muted">No hay vehículos que coincidan con los filtros.</p></div>
      <div class="flex flex-col items-center justify-between gap-3 sm:flex-row"><p class="text-sm text-muted">Página {{ vehiculos.current_page }} de {{ vehiculos.last_page }} · {{ vehiculos.total }} registros</p><div class="flex gap-2"><UButton label="Anterior" color="neutral" variant="outline" :disabled="!vehiculos.prev_page_url" @click="vehiculos.prev_page_url && router.visit(vehiculos.prev_page_url)" /><UButton label="Siguiente" color="neutral" variant="outline" :disabled="!vehiculos.next_page_url" @click="vehiculos.next_page_url && router.visit(vehiculos.next_page_url)" /></div></div>
    </template>
  </UDashboardPanel>
</template>
