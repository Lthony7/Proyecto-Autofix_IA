<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import TeamsMenu from '../components/TeamsMenu.vue'
import UserMenu from '../components/UserMenu.vue'
import { useAppConfig } from '../composables/useAppConfig'
import { useFlash } from '../composables/useFlash'
import { usePermissions } from '../composables/usePermissions'

const open = ref(false)
const appConfig = useAppConfig()
const page = usePage()
const { can, canAny } = usePermissions()

useFlash()

const navigateTo = (url: string) => {
  router.visit(url)
  open.value = false
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
    url: '/inventario',
    active: page.url.startsWith('/inventario'),
    onSelect: () => navigateTo('/inventario')
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
  ...(can('mecanicos.ver') ? [{
    label: 'Mecánicos',
    icon: 'i-lucide-hard-hat',
    url: '/mecanicos',
    active: page.url.startsWith('/mecanicos'),
    onSelect: () => navigateTo('/mecanicos')
  }] : []),
  ...(canAny(['especialidades.gestionar', 'servicios.gestionar']) ? [{
    label: 'Servicios del taller',
    icon: 'i-lucide-settings',
    url: '/taller/catalogos',
    active: page.url.startsWith('/taller/catalogos'),
    onSelect: () => navigateTo('/taller/catalogos')
  }] : []),
  ...(can('facturas.ver') ? [{
    label: 'Facturación',
    icon: 'i-lucide-file-text',
    url: '/facturacion',
    active: page.url.startsWith('/facturacion'),
    onSelect: () => navigateTo('/facturacion')
  }] : [])
]])

const groups = computed(() => [{
  id: 'links',
  label: 'Go to',
  items: links.value.flat()
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
          <Link
            v-for="item in links[0]"
            :key="item.label"
            :href="item.url"
            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors"
            :class="item.active ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-elevated hover:text-highlighted'"
          >
            <i :class="[item.icon, 'size-5 shrink-0']" aria-hidden="true" />
            <span>{{ item.label }}</span>
          </Link>
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

      <USlideover v-model:open="open" title="AUTOFIX IA" description="Navegación principal" side="left">
        <template #content>
          <div class="flex h-full flex-col bg-default">
            <div class="flex items-center justify-between border-b border-default p-3">
              <TeamsMenu />
              <UButton icon="i-lucide-x" color="neutral" variant="ghost" aria-label="Cerrar menú" @click="open = false" />
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
              <Link
                v-for="item in links[0]"
                :key="item.label"
                :href="item.url"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left font-medium"
                :class="item.active ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-elevated hover:text-highlighted'"
                @click="open = false"
              >
                <i :class="[item.icon, 'size-5 shrink-0']" aria-hidden="true" />
                <span>{{ item.label }}</span>
              </Link>
            </nav>
            <div class="border-t border-default p-3"><UserMenu /></div>
          </div>
        </template>
      </USlideover>

      <UDashboardSearch :groups="groups" />

      <slot />

    </UDashboardGroup>
  </UApp>
</template>
