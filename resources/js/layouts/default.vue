<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import TeamsMenu from '../components/TeamsMenu.vue'
import UserMenu from '../components/UserMenu.vue'
import AutofixLogo from '../components/AutofixLogo.vue'
import { useAppConfig } from '../composables/useAppConfig'
import { useFlash } from '../composables/useFlash'
import { usePermissions } from '../composables/usePermissions'

const open = ref(false)
const appConfig = useAppConfig()
const page = usePage()
const { can, canAny } = usePermissions()
const expandedMenus = reactive(new Set<string>())

useFlash()

const navigateTo = (url: string) => {
  router.visit(url)
  open.value = false
}

const toggleMenu = (item: { label: string; url: string; active: boolean }) => {
  expandedMenus.has(item.label) ? expandedMenus.delete(item.label) : expandedMenus.add(item.label)
}

const links = computed(() => [[
  {
    label: 'Inicio',
    icon: 'i-lucide-house',
    url: '/dashboard',
    active: page.url.startsWith('/dashboard'),
    onSelect: () => navigateTo('/dashboard')
  },
  ...(can('clientes.ver') ? [{
    label: 'Clientes',
    icon: 'i-lucide-users-round',
    url: '/clientes',
    active: page.url.startsWith('/clientes'),
    onSelect: () => navigateTo('/clientes')
  }] : []),
  ...(can('vehiculos.ver') ? [{
    label: 'Vehículos',
    icon: 'i-lucide-car-front',
    url: '/vehiculos',
    active: page.url.startsWith('/vehiculos'),
    onSelect: () => navigateTo('/vehiculos')
  }] : []),
  ...(can('historial.ver') ? [{
    label: 'Historial vehicular',
    icon: 'i-lucide-notebook-tabs',
    url: '/historial-vehicular',
    active: page.url.startsWith('/historial-vehicular'),
    onSelect: () => navigateTo('/historial-vehicular')
  }] : []),
  ...(can('citas.ver') ? [{
    label: 'Citas',
    icon: 'i-lucide-calendar-days',
    url: '/citas',
    active: page.url.startsWith('/citas'),
    onSelect: () => navigateTo('/citas')
  }] : []),
  ...(can('ordenes.ver') ? [{
    label: 'Órdenes de trabajo',
    icon: 'i-lucide-clipboard-wrench',
    url: '/ordenes',
    active: page.url.startsWith('/ordenes'),
    onSelect: () => navigateTo('/ordenes')
  }] : []),
  ...(canAny(['ia.solicitar', 'ia.revisar']) ? [{
    label: 'Asistente IA',
    icon: 'i-lucide-sparkles',
    url: '/asistente-ia',
    active: page.url.startsWith('/asistente-ia'),
    onSelect: () => navigateTo('/asistente-ia')
  }] : []),
  ...(can('inventario.ver') ? [{
    label: 'Inventario',
    icon: 'i-lucide-package-search',
    url: '/inventario/catalogo-repuestos',
    active: page.url.startsWith('/inventario'),
    onSelect: () => navigateTo('/inventario'),
    children: [
      ...(can('inventario.gestionar') ? [
        { label: 'Nueva referencia', url: '/inventario/nueva-referencia' },
        { label: 'Registrar movimiento', url: '/inventario/registrar-movimiento' },
        { label: 'Catálogos auxiliares', url: '/inventario/catalogos-auxiliares' }
      ] : []),
      { label: 'Catálogo de repuestos', url: '/inventario/catalogo-repuestos' },
      { label: 'Últimos movimientos', url: '/inventario/ultimos-movimientos' }
    ]
  }] : []),
  ...(can('pagos.ver') ? [{
    label: 'Pagos',
    icon: 'i-lucide-wallet-cards',
    url: '/pagos',
    active: page.url.startsWith('/pagos'),
    onSelect: () => navigateTo('/pagos')
  }] : []),
  ...(can('usuarios.ver') ? [{
    label: 'Usuarios',
    icon: 'i-lucide-shield-user',
    url: '/usuarios',
    active: page.url.startsWith('/usuarios'),
    onSelect: () => navigateTo('/usuarios')
  }] : []),
  ...(can('auditorias.ver') ? [{
    label: 'Auditoría',
    icon: 'i-lucide-history',
    url: '/auditorias',
    active: page.url.startsWith('/auditorias'),
    onSelect: () => navigateTo('/auditorias')
  }] : []),
  ...(can('reportes.ver') ? [{
    label: 'Reportes',
    icon: 'i-lucide-chart-no-axes-combined',
    url: '/reportes/filtros',
    active: page.url.startsWith('/reportes'),
    onSelect: () => navigateTo('/reportes'),
    children: [
      { label: 'Filtros globales', url: '/reportes/filtros' },
      { label: 'Órdenes pendientes', url: '/reportes/ordenes-pendientes' },
      { label: 'Órdenes finalizadas', url: '/reportes/ordenes-finalizadas' },
      ...(can('reportes.financieros') ? [{ label: 'Ingresos por fecha', url: '/reportes/ingresos' }] : []),
      { label: 'Servicios solicitados', url: '/reportes/servicios' },
      { label: 'Repuestos utilizados', url: '/reportes/repuestos' },
      { label: 'Vehículos por cliente', url: '/reportes/vehiculos-clientes' }
    ]
  }] : []),
  ...(can('mecanicos.ver') ? [{
    label: 'Mecánicos',
    icon: 'i-lucide-hard-hat',
    url: '/mecanicos',
    active: page.url.startsWith('/mecanicos'),
    onSelect: () => navigateTo('/mecanicos')
  }] : []),
  ...(canAny(['mecanicos.ver', 'especialidades.gestionar', 'servicios.gestionar']) ? [{
    label: 'Servicios del taller',
    icon: 'i-lucide-settings',
    url: '/taller/especialidades',
    active: page.url.startsWith('/taller'),
    onSelect: () => navigateTo('/taller/catalogos'),
    children: [
      { label: 'Especialidades', url: '/taller/especialidades' },
      { label: 'Catálogo de servicios', url: '/taller/servicios' }
    ]
  }] : []),
  ...(can('facturas.ver') ? [{
    label: 'Facturación',
    icon: 'i-lucide-file-text',
    url: '/facturacion',
    active: page.url.startsWith('/facturacion'),
    onSelect: () => navigateTo('/facturacion')
  }] : [])
]])

watch(() => page.url, () => {
  for (const item of links.value[0]) if (item.children?.length && item.active) expandedMenus.add(item.label)
}, { immediate: true })

const groups = computed(() => [{
  id: 'links',
  label: 'Ir a',
  items: links.value.flat().map(({ children, ...item }) => item)
}])
</script>

<template>
  <UApp :primary="appConfig.ui.colors.primary" :neutral="appConfig.ui.colors.neutral">
    <UDashboardGroup unit="rem">
      <aside class="hidden h-full w-64 shrink-0 flex-col border-r border-default bg-elevated/40 md:flex">
        <div class="border-b border-default p-3">
          <TeamsMenu />
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Navegación principal">
          <div v-for="item in links[0]" :key="item.label">
            <button
              v-if="item.children?.length"
              type="button"
              class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors"
              :class="item.active || expandedMenus.has(item.label) ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-elevated hover:text-highlighted'"
              :aria-expanded="expandedMenus.has(item.label)"
              @click="toggleMenu(item)"
            >
              <i :class="[item.icon, 'size-5 shrink-0']" aria-hidden="true" />
              <span class="flex-1">{{ item.label }}</span>
              <i :class="[expandedMenus.has(item.label) ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right', 'size-4']" aria-hidden="true" />
            </button>
            <Link
              v-else
              :href="item.url"
              class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors"
              :class="item.active ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-elevated hover:text-highlighted'"
            >
              <i :class="[item.icon, 'size-5 shrink-0']" aria-hidden="true" />
              <span>{{ item.label }}</span>
            </Link>
            <div v-if="item.children?.length && expandedMenus.has(item.label)" class="ml-5 mt-1 space-y-1 border-l border-default pl-3">
              <Link
                v-for="child in item.children"
                :key="child.url"
                :href="child.url"
                class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-xs transition-colors"
                :class="page.url.startsWith(child.url) ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-elevated hover:text-highlighted'"
              >
                <i class="i-lucide-corner-down-right -ml-4 size-3.5 shrink-0 text-dimmed" aria-hidden="true" />
                <span>{{ child.label }}</span>
              </Link>
            </div>
          </div>
        </nav>

        <div class="border-t border-default p-3">
          <UserMenu />
        </div>
      </aside>

      <UButton
        class="fixed bottom-4 left-4 z-50 md:hidden"
        label="Menú"
        icon="i-lucide-menu"
        size="lg"
        @click="open = true"
      />

      <USlideover v-model:open="open" title="AUTOFIX" description="Navegación principal" side="left">
        <template #content>
          <div class="flex h-full flex-col bg-default">
            <div class="flex items-center justify-between border-b border-default p-3">
              <TeamsMenu />
              <UButton icon="i-lucide-x" color="neutral" variant="ghost" aria-label="Cerrar menú" @click="open = false" />
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
              <div v-for="item in links[0]" :key="item.label">
                <button
                  v-if="item.children?.length"
                  type="button"
                  class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left font-medium"
                  :class="item.active || expandedMenus.has(item.label) ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-elevated hover:text-highlighted'"
                  @click="toggleMenu(item)"
                >
                  <i :class="[item.icon, 'size-5 shrink-0']" aria-hidden="true" />
                  <span class="flex-1">{{ item.label }}</span>
                  <i :class="[expandedMenus.has(item.label) ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right', 'size-4']" />
                </button>
                <Link
                  v-else
                  :href="item.url"
                  class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left font-medium"
                  :class="item.active ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-elevated hover:text-highlighted'"
                  @click="open = false"
                >
                  <i :class="[item.icon, 'size-5 shrink-0']" aria-hidden="true" />
                  <span>{{ item.label }}</span>
                </Link>
                <div v-if="item.children?.length && expandedMenus.has(item.label)" class="ml-5 mt-1 space-y-1 border-l border-default pl-3">
                  <Link v-for="child in item.children" :key="child.url" :href="child.url" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-muted hover:bg-elevated hover:text-highlighted" :class="page.url.startsWith(child.url)?'bg-primary/10 text-primary':''" @click="open=false"><i class="i-lucide-corner-down-right -ml-4 size-3.5 shrink-0 text-dimmed" aria-hidden="true"/><span>{{ child.label }}</span></Link>
                </div>
              </div>
            </nav>
            <div class="border-t border-default p-3"><UserMenu /></div>
          </div>
        </template>
      </USlideover>

      <UDashboardSearch :groups="groups" />

      <div class="relative flex min-w-0 flex-1 overflow-hidden">
        <div
          class="pointer-events-none fixed inset-y-0 right-0 left-0 z-[1] flex items-center justify-center overflow-hidden md:left-64"
          aria-hidden="true"
        >
          <AutofixLogo
            class="h-auto w-[min(82vw,56rem)] opacity-[0.045] dark:opacity-[0.065]"
          />
        </div>
        <div class="relative z-[2] flex min-w-0 flex-1">
          <slot />
        </div>
      </div>

    </UDashboardGroup>
  </UApp>
</template>
