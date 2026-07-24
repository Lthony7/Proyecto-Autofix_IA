<script setup lang="ts">
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

interface Usuario { id: string; name: string; email: string }
interface Registro { id: string; accion: string; recurso_tipo: string; recurso_id?: string; cambios?: Record<string, unknown>; ip?: string; created_at: string; usuario?: Usuario }
interface Pagina<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[]; total: number }

const props = defineProps<{
  registros: Pagina<Registro>
  usuarios: Usuario[]
  filtros: { buscar: string; usuario: string; desde: string; hasta: string }
}>()
const filtros = reactive({ ...props.filtros, usuario: props.filtros.usuario || 'todos' })
const abiertos = reactive(new Set<string>())

function filtrar() {
  router.get(route('auditorias.index'), {
    buscar: filtros.buscar || undefined,
    usuario: filtros.usuario === 'todos' ? undefined : filtros.usuario,
    desde: filtros.desde || undefined,
    hasta: filtros.hasta || undefined
  }, { preserveState: true, replace: true })
}

function toggle(id: string) {
  abiertos.has(id) ? abiertos.delete(id) : abiertos.add(id)
}
</script>

<template>
  <Head title="Auditoría" />
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar title="Auditoría del sistema">
        <template #leading><UDashboardSidebarCollapse /></template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div class="space-y-6">
        <div>
          <p class="text-sm text-muted">Trazabilidad transversal de operaciones</p>
          <p class="text-3xl font-semibold">{{ registros.total }} eventos</p>
        </div>
        <form class="grid gap-3 lg:grid-cols-[minmax(12rem,1fr)_16rem_11rem_11rem_auto]" @submit.prevent="filtrar">
          <UInput v-model="filtros.buscar" icon="i-lucide-search" placeholder="Acción o recurso" />
          <USelect v-model="filtros.usuario" :items="[{ label: 'Todos los usuarios', value: 'todos' }, ...usuarios.map(u => ({ label: `${u.name} · ${u.email}`, value: u.id }))]" />
          <UInput v-model="filtros.desde" type="date" />
          <UInput v-model="filtros.hasta" type="date" />
          <UButton type="submit" label="Filtrar" icon="i-lucide-list-filter" />
        </form>
        <div class="overflow-hidden rounded-lg border border-default">
          <ul v-if="registros.data.length" class="divide-y divide-default">
            <li v-for="registro in registros.data" :key="registro.id" class="p-4 sm:p-5">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <UBadge color="primary" variant="subtle">{{ registro.accion }}</UBadge>
                    <span class="font-medium">{{ registro.recurso_tipo }}</span>
                  </div>
                  <p class="mt-1 text-sm">{{ registro.usuario ? `${registro.usuario.name} · ${registro.usuario.email}` : 'Sistema o usuario eliminado' }}</p>
                  <p class="text-xs text-muted">{{ new Date(registro.created_at).toLocaleString('es-CO') }} · IP {{ registro.ip || 'no disponible' }}</p>
                  <p v-if="registro.recurso_id" class="mt-1 font-mono text-xs text-muted">{{ registro.recurso_id }}</p>
                </div>
                <UButton v-if="registro.cambios" size="xs" color="neutral" variant="ghost" :label="abiertos.has(registro.id) ? 'Ocultar cambios' : 'Ver cambios'" @click="toggle(registro.id)" />
              </div>
              <pre v-if="registro.cambios && abiertos.has(registro.id)" class="mt-4 overflow-x-auto rounded-lg bg-elevated p-4 text-xs whitespace-pre-wrap">{{ JSON.stringify(registro.cambios, null, 2) }}</pre>
            </li>
          </ul>
          <p v-else class="px-4 py-8 text-center text-muted">No hay eventos para los filtros seleccionados.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link v-for="link in registros.links" :key="link.label" :href="link.url || ''" preserve-scroll>
            <UButton :disabled="!link.url" :variant="link.active ? 'solid' : 'outline'" color="neutral" size="sm"><span v-html="link.label" /></UButton>
          </Link>
        </div>
      </div>
    </template>
  </UDashboardPanel>
</template>
