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

function iniciales(nombre: string) {
  return nombre.split(/\s+/).slice(0, 2).map(parte => parte[0]).join('').toUpperCase()
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
      <section class="module-hero p-5 sm:p-7">
        <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-2xl"><div class="mb-3 flex items-center gap-2 font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-primary"><span class="size-1.5 rounded-full bg-primary shadow-[0_0_8px_var(--ui-primary)]"/>Directorio del taller</div><h1 class="text-3xl font-black tracking-tight sm:text-4xl">Relaciones que mueven<br><span class="text-primary">cada servicio.</span></h1><p class="mt-3 max-w-xl text-sm leading-6 text-muted">Administra propietarios, datos de contacto y vehículos vinculados desde un directorio centralizado.</p></div>
          <div class="flex items-center gap-3 rounded-xl border border-primary/15 bg-default/65 px-4 py-3 backdrop-blur"><span class="grid size-10 place-items-center rounded-lg bg-primary/12 text-primary"><UIcon name="i-lucide-users-round" class="size-5"/></span><div><p class="text-xs uppercase tracking-wide text-muted">Cobertura actual</p><p class="font-semibold">{{stats.active}} clientes activos</p></div></div>
        </div>
      </section>

      <div class="grid gap-3 sm:grid-cols-3">
        <UCard class="metric-tile"><div class="flex items-start justify-between"><div><p class="text-xs font-bold uppercase tracking-[0.13em] text-muted">Total registrados</p><p class="mt-3 text-3xl font-black">{{ stats.total }}</p></div><span class="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary"><UIcon name="i-lucide-contact-round" class="size-5"/></span></div><div class="mt-4 h-0.5 rounded-full bg-elevated"><span class="block h-full w-2/3 rounded-full bg-primary"/></div></UCard>
        <UCard class="metric-tile"><div class="flex items-start justify-between"><div><p class="text-xs font-bold uppercase tracking-[0.13em] text-muted">Activos</p><p class="mt-3 text-3xl font-black text-success">{{ stats.active }}</p></div><span class="grid size-11 place-items-center rounded-xl bg-emerald-500/10 text-emerald-500"><UIcon name="i-lucide-user-check" class="size-5"/></span></div><div class="mt-4 h-0.5 rounded-full bg-elevated"><span class="block h-full rounded-full bg-emerald-500" :style="{width:`${stats.total?Math.max(8,(stats.active/stats.total)*100):0}%`}"/></div></UCard>
        <UCard class="metric-tile"><div class="flex items-start justify-between"><div><p class="text-xs font-bold uppercase tracking-[0.13em] text-muted">Inactivos o archivados</p><p class="mt-3 text-3xl font-black">{{ stats.inactive }}</p></div><span class="grid size-11 place-items-center rounded-xl bg-neutral-500/10 text-muted"><UIcon name="i-lucide-user-round-x" class="size-5"/></span></div><div class="mt-4 h-0.5 rounded-full bg-elevated"><span class="block h-full rounded-full bg-neutral-500" :style="{width:`${stats.inactive&&stats.total?Math.max(8,(stats.inactive/stats.total)*100):0}%`}"/></div></UCard>
      </div>

      <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="buscar">
        <UInput v-model="filters.buscar" class="w-full sm:max-w-md" icon="i-lucide-search" placeholder="Nombre, documento o correo" />
        <USelect v-model="filters.estado" class="w-full sm:w-48" :items="estados" />
        <UButton type="submit" label="Filtrar" icon="i-lucide-list-filter" />
      </form>

      <div class="overflow-x-auto rounded-xl border border-default shadow-sm">
        <table class="min-w-[850px] w-full text-sm">
          <thead class="bg-elevated/60 text-left"><tr><th class="p-4">Cliente</th><th class="p-4">Documento</th><th class="p-4">Contacto</th><th class="p-4">Vehículos</th><th class="p-4">Estado</th><th class="p-4 text-right">Acciones</th></tr></thead>
          <tbody>
            <tr v-for="cliente in customers.data" :key="cliente.id" class="border-t border-default">
              <td class="p-4"><div class="flex items-center gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl border border-primary/15 bg-primary/10 font-mono text-xs font-black text-primary">{{iniciales(cliente.razonSocial)}}</span><div><p class="font-semibold text-highlighted">{{ cliente.razonSocial }}</p><p class="text-xs text-muted">Cliente #{{cliente.id.slice(0,8).toUpperCase()}}</p></div></div></td>
              <td class="p-4"><p class="font-medium">{{ cliente.numeroDocumento }}</p><p class="text-xs uppercase text-muted">{{ cliente.tipoDocumento }}</p></td>
              <td class="p-4"><p class="font-medium">{{ cliente.email||'Sin correo' }}</p><p class="mt-0.5 flex items-center gap-1 text-xs text-muted"><UIcon name="i-lucide-phone" class="size-3"/>{{ cliente.telefono||'Sin teléfono' }}</p></td>
              <td class="p-4"><span class="inline-flex items-center gap-2 rounded-lg bg-elevated px-2.5 py-1.5 font-mono font-bold"><UIcon name="i-lucide-car-front" class="size-4 text-primary"/>{{ cliente.vehiculosCount }}</span></td>
              <td class="p-4"><UBadge :color="cliente.estado === 'activo' ? 'success' : 'neutral'" variant="subtle"><span class="mr-1 size-1.5 rounded-full bg-current"/>{{ cliente.estado }}</UBadge></td>
              <td class="p-4 text-right space-x-2">
                <UButton v-if="can('clientes.editar')" size="sm" color="neutral" variant="soft" icon="i-lucide-pencil" aria-label="Editar cliente" @click="router.visit(route('clientes.edit', cliente.id))" />
                <UButton v-if="can('clientes.desactivar')" size="sm" :color="cliente.estado === 'activo' ? 'error' : 'success'" variant="soft" :icon="cliente.estado === 'activo' ? 'i-lucide-user-x' : 'i-lucide-user-check'" aria-label="Cambiar estado" @click="cambiarEstado(cliente)" />
              </td>
            </tr>
            <tr v-if="!customers.data.length"><td colspan="6" class="p-12 text-center"><span class="mx-auto grid size-14 place-items-center rounded-full bg-primary/10 text-primary"><UIcon name="i-lucide-search-x" class="size-7"/></span><p class="mt-3 font-semibold">No encontramos clientes</p><p class="mt-1 text-sm text-muted">Prueba con otros términos o limpia los filtros aplicados.</p></td></tr>
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
