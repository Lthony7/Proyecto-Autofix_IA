<script setup lang="ts">
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { Cliente } from '../../types'
import { usePermissions } from '../../composables/usePermissions'

interface ClienteListado extends Cliente {
  estado: 'activo' | 'inactivo' | 'archivado'
  vehiculosCount: number
}

const props = defineProps<{
  customers: { data: ClienteListado[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null; total: number }
  stats: { total: number; active: number; inactive: number }
  filters: { buscar?: string; estado?: string }
}>()

const { can } = usePermissions()
const filters = reactive({ buscar: props.filters.buscar ?? '', estado: props.filters.estado ?? 'todos' })
const estados = [{ label: 'Todos', value: 'todos' }, { label: 'Activos', value: 'activo' }, { label: 'Inactivos', value: 'inactivo' }, { label: 'Archivados', value: 'archivado' }]

function buscar() {
  router.get(route('clientes.index'), { buscar: filters.buscar || undefined, estado: filters.estado === 'todos' ? undefined : filters.estado }, { preserveState: true, replace: true })
}

function cambiarEstado(cliente: ClienteListado) {
  const estado = cliente.estado === 'activo' ? 'inactivo' : 'activo'
  if (!window.confirm(`¿Deseas cambiar el estado de ${cliente.razonSocial} a ${estado}?`)) return
  router.patch(route('clientes.estado', cliente.id), { estado }, { preserveScroll: true })
}
</script>

<template>
  <Head title="Clientes" />
  <UDashboardPanel id="clientes">
    <template #header>
      <UDashboardNavbar title="Clientes">
        <template #leading><UDashboardSidebarCollapse /></template>
        <template #right>
          <UButton v-if="can('clientes.crear')" label="Nuevo cliente" icon="i-lucide-plus" @click="router.visit(route('clientes.create'))" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div class="grid gap-3 sm:grid-cols-3">
        <UCard><p class="text-sm text-muted">Total</p><p class="text-2xl font-semibold">{{ stats.total }}</p></UCard>
        <UCard><p class="text-sm text-muted">Activos</p><p class="text-2xl font-semibold text-success">{{ stats.active }}</p></UCard>
        <UCard><p class="text-sm text-muted">Inactivos o archivados</p><p class="text-2xl font-semibold">{{ stats.inactive }}</p></UCard>
      </div>

      <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="buscar">
        <UInput v-model="filters.buscar" class="w-full sm:max-w-md" icon="i-lucide-search" placeholder="Nombre, documento o correo" />
        <USelect v-model="filters.estado" class="w-full sm:w-48" :items="estados" />
        <UButton type="submit" label="Filtrar" icon="i-lucide-list-filter" />
      </form>

      <div class="overflow-x-auto rounded-lg border border-default">
        <table class="min-w-[850px] w-full text-sm">
          <thead class="bg-elevated/60 text-left"><tr><th class="p-3">Cliente</th><th class="p-3">Documento</th><th class="p-3">Contacto</th><th class="p-3">Vehículos</th><th class="p-3">Estado</th><th class="p-3 text-right">Acciones</th></tr></thead>
          <tbody>
            <tr v-for="cliente in customers.data" :key="cliente.id" class="border-t border-default">
              <td class="p-3 font-medium">{{ cliente.razonSocial }}</td>
              <td class="p-3">{{ cliente.tipoDocumento }} {{ cliente.numeroDocumento }}</td>
              <td class="p-3"><p>{{ cliente.email }}</p><p class="text-muted">{{ cliente.telefono }}</p></td>
              <td class="p-3">{{ cliente.vehiculosCount }}</td>
              <td class="p-3"><UBadge :color="cliente.estado === 'activo' ? 'success' : 'neutral'" variant="subtle">{{ cliente.estado }}</UBadge></td>
              <td class="p-3 text-right space-x-2">
                <UButton v-if="can('clientes.editar')" size="sm" color="neutral" variant="ghost" icon="i-lucide-pencil" aria-label="Editar cliente" @click="router.visit(route('clientes.edit', cliente.id))" />
                <UButton v-if="can('clientes.desactivar')" size="sm" color="neutral" variant="ghost" :icon="cliente.estado === 'activo' ? 'i-lucide-user-x' : 'i-lucide-user-check'" aria-label="Cambiar estado" @click="cambiarEstado(cliente)" />
              </td>
            </tr>
            <tr v-if="!customers.data.length"><td colspan="6" class="p-10 text-center text-muted">No hay clientes que coincidan con los filtros.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-sm text-muted">Página {{ customers.current_page }} de {{ customers.last_page }} · {{ customers.total }} registros</p>
        <div class="flex gap-2"><UButton label="Anterior" color="neutral" variant="outline" :disabled="!customers.prev_page_url" @click="customers.prev_page_url && router.visit(customers.prev_page_url)" /><UButton label="Siguiente" color="neutral" variant="outline" :disabled="!customers.next_page_url" @click="customers.next_page_url && router.visit(customers.next_page_url)" /></div>
      </div>
    </template>
  </UDashboardPanel>
</template>
