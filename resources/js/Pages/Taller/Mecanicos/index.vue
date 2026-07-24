<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { usePermissions } from '../../../composables/usePermissions'

interface Mecanico { id: string; nombres: string; apellidos: string; tipoDocumento: string; numeroDocumento: string; telefono: string; email: string; estado: string; especialidades: string[]; horarios: unknown[] }
const props = defineProps<{ mecanicos: { data: Mecanico[]; prev_page_url: string | null; next_page_url: string | null; total: number }; buscar?: string }>()
const buscar = ref(props.buscar ?? '')
const { can } = usePermissions()
function filtrar() { router.get(route('mecanicos.index'), { buscar: buscar.value || undefined }, { preserveState: true, replace: true }) }
function estado(m: Mecanico) { const nuevo = m.estado === 'activo' ? 'inactivo' : 'activo'; if (confirm(`¿Cambiar a ${nuevo} a ${m.nombres} ${m.apellidos}?`)) router.patch(route('mecanicos.estado', m.id), { estado: nuevo }, { preserveScroll: true }) }
</script>

<template>
  <Head title="Mecánicos" />
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar title="Mecánicos">
        <template #leading><UDashboardSidebarCollapse /></template>
        <template #right><UButton v-if="can('mecanicos.gestionar')" label="Nuevo mecánico" icon="i-lucide-plus" @click="router.visit(route('mecanicos.create'))" /></template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <form class="flex gap-3" @submit.prevent="filtrar">
        <UInput v-model="buscar" class="w-full max-w-md" icon="i-lucide-search" placeholder="Nombre o documento" />
        <UButton type="submit" label="Buscar" />
      </form>

      <div class="overflow-hidden rounded-lg border border-default">
        <div class="hidden grid-cols-[minmax(12rem,1.2fr)_minmax(12rem,1fr)_minmax(12rem,1.2fr)_minmax(10rem,1fr)_auto] gap-4 border-b border-default bg-elevated/50 px-4 py-3 text-xs font-medium uppercase text-muted md:grid">
          <span>Mecánico</span>
          <span>Contacto</span>
          <span>Especialidades</span>
          <span>Disponibilidad</span>
          <span class="text-right">Estado y acciones</span>
        </div>
        <div
          v-for="m in mecanicos.data"
          :key="m.id"
          class="grid gap-4 border-b border-default px-4 py-4 last:border-b-0 md:grid-cols-[minmax(12rem,1.2fr)_minmax(12rem,1fr)_minmax(12rem,1.2fr)_minmax(10rem,1fr)_auto] md:items-center"
        >
          <div>
            <p class="text-xs font-medium uppercase text-muted md:hidden">Mecánico</p>
            <p class="font-semibold">{{ m.nombres }} {{ m.apellidos }}</p>
            <p class="text-sm text-muted">{{ m.tipoDocumento }} {{ m.numeroDocumento }}</p>
          </div>
          <div class="text-sm">
            <p class="text-xs font-medium uppercase text-muted md:hidden">Contacto</p>
            <p class="break-all">{{ m.email }}</p>
            <p class="text-muted">{{ m.telefono }}</p>
          </div>
          <div>
            <p class="mb-1 text-xs font-medium uppercase text-muted md:hidden">Especialidades</p>
            <div class="flex flex-wrap gap-1">
              <UBadge v-for="e in m.especialidades" :key="e" color="primary" variant="subtle">{{ e }}</UBadge>
              <span v-if="!m.especialidades.length" class="text-sm text-muted">Sin especialidades</span>
            </div>
          </div>
          <div class="text-sm">
            <p class="text-xs font-medium uppercase text-muted md:hidden">Disponibilidad</p>
            <p>{{ m.horarios.length }} franjas disponibles</p>
          </div>
          <div class="flex flex-wrap items-center gap-2 md:max-w-44 md:justify-end">
            <UBadge :color="m.estado === 'activo' ? 'success' : 'neutral'">{{ m.estado }}</UBadge>
            <template v-if="can('mecanicos.gestionar')">
              <UButton label="Editar" size="sm" color="neutral" variant="ghost" @click="router.visit(route('mecanicos.edit', m.id))" />
              <UButton label="Cambiar estado" size="sm" color="neutral" variant="ghost" @click="estado(m)" />
            </template>
          </div>
        </div>
        <p v-if="!mecanicos.data.length" class="px-4 py-12 text-center text-muted">No hay mecánicos registrados.</p>
      </div>

      <div class="flex justify-end gap-2">
        <UButton label="Anterior" color="neutral" variant="outline" :disabled="!mecanicos.prev_page_url" @click="mecanicos.prev_page_url && router.visit(mecanicos.prev_page_url)" />
        <UButton label="Siguiente" color="neutral" variant="outline" :disabled="!mecanicos.next_page_url" @click="mecanicos.next_page_url && router.visit(mecanicos.next_page_url)" />
      </div>
    </template>
  </UDashboardPanel>
</template>
