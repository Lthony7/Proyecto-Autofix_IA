<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { usePermissions } from '../../composables/usePermissions'

interface Orden {
  id: string
  numero: string
  cliente: string
  vehiculo: string
  estado: string
  recibidaEn: string
  mecanicos: string[]
}

const props = defineProps<{
  ordenes: {
    data: Orden[]
    prev_page_url: string | null
    next_page_url: string | null
    total: number
  }
  estado?: string
  resumenEstados: {
    cerradas: number
    enCurso: number
    pendientes: number
  }
}>()

const filtro = ref(props.estado ?? 'todos')
const { can } = usePermissions()
const estados = ['todos', 'pendiente', 'asignada', 'en_diagnostico', 'esperando_aprobacion', 'esperando_repuestos', 'en_reparacion', 'pausada', 'en_prueba', 'finalizada', 'lista_entrega', 'entregada', 'cancelada']

function filtrar() {
  router.get(route('ordenes.index'), { estado: filtro.value === 'todos' ? undefined : filtro.value }, { preserveState: true, replace: true })
}
</script>

<template>
  <Head title="Órdenes de trabajo" />
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar title="Órdenes de trabajo">
        <template #right>
          <UButton v-if="can('ordenes.crear')" label="Nueva orden" icon="i-lucide-plus" @click="router.visit(route('ordenes.create'))" />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="relative overflow-hidden rounded-xl border border-success/30 bg-gradient-to-br from-success/25 to-success/10 p-4 shadow-sm shadow-success/10">
          <div class="flex items-center gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-full border border-success/30 bg-success/15 text-success">
              <UIcon name="i-lucide-circle-check" class="size-6" />
            </span>
            <div>
              <p class="text-sm font-semibold text-success">Cerradas OK</p>
              <p class="font-mono text-3xl font-bold text-highlighted">{{ resumenEstados.cerradas }}</p>
            </div>
          </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-warning/30 bg-gradient-to-br from-warning/25 to-warning/10 p-4 shadow-sm shadow-warning/10">
          <div class="flex items-center gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-full border border-warning/30 bg-warning/15 text-warning">
              <UIcon name="i-lucide-wrench" class="size-6" />
            </span>
            <div>
              <p class="text-sm font-semibold text-warning">En curso</p>
              <p class="font-mono text-3xl font-bold text-highlighted">{{ resumenEstados.enCurso }}</p>
            </div>
          </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-error/30 bg-gradient-to-br from-error/25 to-error/10 p-4 shadow-sm shadow-error/10">
          <div class="flex items-center gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-full border border-error/30 bg-error/15 text-error">
              <UIcon name="i-lucide-clock-alert" class="size-6" />
            </span>
            <div>
              <p class="text-sm font-semibold text-error">Pendientes</p>
              <p class="font-mono text-3xl font-bold text-highlighted">{{ resumenEstados.pendientes }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row">
        <USelect v-model="filtro" class="w-full sm:w-52" :items="estados" />
        <UButton label="Filtrar" @click="filtrar" />
      </div>

      <ul v-if="ordenes.data.length" class="divide-y divide-default overflow-hidden rounded-lg border border-default bg-elevated">
        <li v-for="o in ordenes.data" :key="o.id">
          <Link :href="route('ordenes.show', o.id)" class="block p-4 transition hover:bg-default/50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-primary">{{ o.numero }}</p>
                <h2 class="font-semibold">{{ o.vehiculo }} · {{ o.cliente }}</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm md:grid-cols-2">
                  <div><dt class="text-muted">Recibida</dt><dd>{{ new Date(o.recibidaEn).toLocaleString('es-CO') }}</dd></div>
                  <div><dt class="text-muted">Mecánicos</dt><dd>{{ o.mecanicos.join(', ') || 'Sin asignar' }}</dd></div>
                </dl>
              </div>
              <UBadge class="self-start sm:self-center" :color="o.estado === 'cancelada' ? 'error' : o.estado === 'entregada' ? 'success' : 'primary'" variant="subtle">
                {{ o.estado.replaceAll('_', ' ') }}
              </UBadge>
            </div>
          </Link>
        </li>
      </ul>

      <div v-else class="rounded-lg border border-default bg-elevated">
        <p class="py-10 text-center text-muted">No hay órdenes para mostrar.</p>
      </div>

      <div class="flex flex-wrap justify-end gap-2">
        <UButton label="Anterior" color="neutral" variant="outline" :disabled="!ordenes.prev_page_url" @click="ordenes.prev_page_url && router.visit(ordenes.prev_page_url)" />
        <UButton label="Siguiente" color="neutral" variant="outline" :disabled="!ordenes.next_page_url" @click="ordenes.next_page_url && router.visit(ordenes.next_page_url)" />
      </div>
    </template>
  </UDashboardPanel>
</template>
