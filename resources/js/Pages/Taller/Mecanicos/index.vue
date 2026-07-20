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
  <UDashboardPanel><template #header><UDashboardNavbar title="Mecánicos"><template #leading><UDashboardSidebarCollapse /></template><template #right><UButton v-if="can('mecanicos.gestionar')" label="Nuevo mecánico" icon="i-lucide-plus" @click="router.visit(route('mecanicos.create'))" /></template></UDashboardNavbar></template><template #body>
    <form class="flex gap-3" @submit.prevent="filtrar"><UInput v-model="buscar" class="w-full max-w-md" icon="i-lucide-search" placeholder="Nombre o documento" /><UButton type="submit" label="Buscar" /></form>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><UCard v-for="m in mecanicos.data" :key="m.id"><div class="flex justify-between gap-3"><div><h2 class="font-semibold">{{ m.nombres }} {{ m.apellidos }}</h2><p class="text-sm text-muted">{{ m.tipoDocumento }} {{ m.numeroDocumento }}</p></div><UBadge :color="m.estado === 'activo' ? 'success' : 'neutral'">{{ m.estado }}</UBadge></div><div class="mt-4 space-y-2 text-sm"><p>{{ m.email }} · {{ m.telefono }}</p><div class="flex flex-wrap gap-1"><UBadge v-for="e in m.especialidades" :key="e" color="primary" variant="subtle">{{ e }}</UBadge></div><p class="text-muted">{{ m.horarios.length }} franjas disponibles</p></div><template #footer><div v-if="can('mecanicos.gestionar')" class="flex justify-end gap-2"><UButton label="Editar" size="sm" color="neutral" variant="ghost" @click="router.visit(route('mecanicos.edit', m.id))" /><UButton label="Cambiar estado" size="sm" color="neutral" variant="ghost" @click="estado(m)" /></div></template></UCard><UCard v-if="!mecanicos.data.length" class="md:col-span-2 xl:col-span-3"><p class="py-8 text-center text-muted">No hay mecánicos registrados.</p></UCard></div>
    <div class="flex justify-end gap-2"><UButton label="Anterior" color="neutral" variant="outline" :disabled="!mecanicos.prev_page_url" @click="mecanicos.prev_page_url && router.visit(mecanicos.prev_page_url)" /><UButton label="Siguiente" color="neutral" variant="outline" :disabled="!mecanicos.next_page_url" @click="mecanicos.next_page_url && router.visit(mecanicos.next_page_url)" /></div>
  </template></UDashboardPanel>
</template>
