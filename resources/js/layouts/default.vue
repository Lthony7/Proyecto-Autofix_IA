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
const esCliente = computed(() => ((page.props.auth as any)?.user?.roles ?? []).includes('Cliente'))
const ordenesActivas = computed(() => Number((page.props.navigation as any)?.ordenesActivas ?? 0))

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
    icon: 'i-lucide-layout-dashboard',
    url: '/dashboard',
    active: page.url.startsWith('/dashboard'),
    onSelect: () => navigateTo('/dashboard')
  },
  ...(can('clientes.ver') ? [{
    label: 'Clientes',
    icon: 'i-lucide-contact-round',
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
    label: esCliente.value ? 'Mi Historial de Servicios' : 'Historial de servicios',
    icon: 'i-lucide-wrench',
    url: esCliente.value ? '/mi-historial' : '/historial-vehicular',
    active: esCliente.value ? page.url.startsWith('/mi-historial') : page.url.startsWith('/historial-vehicular') && !page.url.startsWith('/historial-vehicular-bitacora'),
    onSelect: () => navigateTo(esCliente.value ? '/mi-historial' : '/historial-vehicular')
  }] : []),
  ...(can('historial.acciones.ver') ? [{
    label: 'Bitácora vehicular',
    icon: 'i-lucide-file-clock',
    url: '/historial-vehicular-bitacora',
    active: page.url.startsWith('/historial-vehicular-bitacora'),
    onSelect: () => navigateTo('/historial-vehicular-bitacora')
  }] : []),
  ...(can('citas.ver') ? [{
    label: 'Citas',
    icon: 'i-lucide-calendar-clock',
    url: '/citas',
    active: page.url.startsWith('/citas') && !page.url.startsWith('/citas/calendario'),
    onSelect: () => navigateTo('/citas')
  }, {
    label: 'Calendario',
    icon: 'i-lucide-calendar-days',
    url: '/citas/calendario',
    active: page.url.startsWith('/citas/calendario'),
    onSelect: () => navigateTo('/citas/calendario')
  }] : []),
  ...(can('ordenes.ver') ? [{
    label: 'Órdenes de trabajo',
    icon: 'i-lucide-clipboard-list',
    url: '/ordenes',
    active: page.url.startsWith('/ordenes'),
    badge: ordenesActivas.value,
    onSelect: () => navigateTo('/ordenes')
  }] : []),
  ...(canAny(['ia.solicitar', 'ia.revisar']) ? [{
    label: 'Asistente IA',
    icon: 'i-lucide-bot',
    url: '/asistente-ia',
    active: page.url.startsWith('/asistente-ia'),
    onSelect: () => navigateTo('/asistente-ia')
  }] : []),
  ...(can('inventario.ver') ? [{
    label: 'Inventario',
    icon: 'i-lucide-warehouse',
    url: '/inventario',
    active: page.url.startsWith('/inventario'),
    onSelect: () => navigateTo('/inventario')
  }] : []),
  ...(can('pagos.ver') ? [{
    label: 'Pagos',
    icon: 'i-lucide-badge-dollar-sign',
    url: '/pagos',
    active: page.url.startsWith('/pagos'),
    onSelect: () => navigateTo('/pagos')
  }] : []),
  ...(can('usuarios.ver') ? [{
    label: 'Usuarios',
    icon: 'i-lucide-users',
    url: '/usuarios',
    active: page.url.startsWith('/usuarios'),
    onSelect: () => navigateTo('/usuarios')
  }] : []),
  ...(can('auditorias.ver') ? [{
    label: 'Auditoría',
    icon: 'i-lucide-shield-check',
    url: '/auditorias',
    active: page.url.startsWith('/auditorias'),
    onSelect: () => navigateTo('/auditorias')
  }] : []),
  ...(can('reportes.ver') ? [{
    label: 'Reportes',
    icon: 'i-lucide-chart-column-big',
    url: '/reportes',
    active: page.url.startsWith('/reportes'),
    onSelect: () => navigateTo('/reportes')
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
    icon: 'i-lucide-settings-2',
    url: '/taller/especialidades',
    active: page.url.startsWith('/taller'),
    onSelect: () => navigateTo('/taller/catalogos'),
    children: [
      { label: 'Especialidades', icon: 'i-lucide-badge-check', url: '/taller/especialidades' },
      { label: 'Catálogo de servicios', icon: 'i-lucide-notebook-tabs', url: '/taller/servicios' }
    ]
  }] : []),
  ...(can('facturas.ver') ? [{
    label: 'Facturación',
    icon: 'i-lucide-receipt-text',
    url: '/facturacion',
    active: page.url.startsWith('/facturacion'),
    onSelect: () => navigateTo('/facturacion')
  }] : [])
]])

const menuSections = computed(() => {
  const items = links.value[0]
  const definitions = [
    { id: 'principal', label: '', items: ['Inicio'] },
    { id: 'maestros', label: 'Clientes y vehículos', items: ['Clientes', 'Vehículos', 'Mecánicos', 'Servicios del taller'] },
    { id: 'operacion', label: 'Operación', items: ['Citas', 'Calendario', 'Órdenes de trabajo', 'Asistente IA'] },
    { id: 'cobro', label: 'Cobro', items: ['Facturación', 'Pagos'] },
    { id: 'gestion', label: 'Gestión', items: ['Historial de servicios', 'Mi Historial de Servicios', 'Bitácora vehicular', 'Inventario', 'Reportes'] },
    { id: 'administracion', label: 'Administración', items: ['Usuarios', 'Auditoría'] }
  ]

  return definitions.map(section => ({
    ...section,
    links: section.items.flatMap(label => {
      const item = items.find(link => link.label === label)
      return item ? [item] : []
    })
  })).filter(section => section.links.length)
})

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
      <aside class="sidebar-shell hidden h-full w-64 shrink-0 flex-col border-r border-default bg-elevated/55 shadow-xl shadow-black/5 backdrop-blur-xl md:flex">
        <div class="border-b border-default bg-gradient-to-b from-primary/5 to-transparent p-3">
          <TeamsMenu />
        </div>

        <nav class="flex-1 space-y-1.5 overflow-y-auto px-3 py-4" aria-label="Navegación principal">
          <div v-for="section in menuSections" :key="section.id" class="mb-4 last:mb-0">
            <p v-if="section.label" class="mb-1.5 px-3 font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-dimmed">{{ section.label }}</p>
            <div v-for="item in section.links" :key="item.label" class="relative">
            <button
              v-if="item.children?.length"
              type="button"
              class="group relative flex min-h-11 w-full items-center gap-2.5 overflow-hidden rounded-xl border border-transparent px-2.5 py-2 text-left text-sm font-semibold transition-all duration-200 ease-out"
              :class="item.active || expandedMenus.has(item.label) ? 'border-primary/20 bg-gradient-to-r from-primary/18 to-primary/5 text-primary shadow-sm shadow-primary/5' : 'text-muted hover:translate-x-0.5 hover:border-default hover:bg-default/70 hover:text-highlighted hover:shadow-sm'"
              :aria-expanded="expandedMenus.has(item.label)"
              @click="toggleMenu(item)"
            >
              <span v-if="item.active || expandedMenus.has(item.label)" class="absolute inset-y-2 left-0 w-0.5 rounded-full bg-primary shadow-[0_0_8px_var(--ui-primary)]"/>
              <span class="grid size-8 shrink-0 place-items-center rounded-lg border transition-colors duration-200" :class="item.active || expandedMenus.has(item.label) ? 'border-primary/15 bg-primary/12' : 'border-transparent bg-elevated group-hover:border-default group-hover:bg-default'"><UIcon :name="item.icon" class="size-[18px]" aria-hidden="true" /></span>
              <span class="flex-1">{{ item.label }}</span>
              <span class="grid size-7 place-items-center rounded-md bg-default/45"><UIcon name="i-lucide-chevron-right" class="size-3.5 transition-transform duration-300 ease-out" :class="expandedMenus.has(item.label) ? 'rotate-90' : ''" aria-hidden="true" /></span>
            </button>
            <Link
              v-else
              :href="item.url"
              class="group relative flex min-h-11 w-full items-center gap-2.5 overflow-hidden rounded-xl border border-transparent px-2.5 py-2 text-left text-sm font-semibold transition-all duration-200 ease-out"
              :class="item.active ? 'border-primary/20 bg-gradient-to-r from-primary/18 to-primary/5 text-primary shadow-sm shadow-primary/5' : 'text-muted hover:translate-x-0.5 hover:border-default hover:bg-default/70 hover:text-highlighted hover:shadow-sm'"
            >
              <span v-if="item.active" class="absolute inset-y-2 left-0 w-0.5 rounded-full bg-primary shadow-[0_0_8px_var(--ui-primary)]"/>
              <span class="grid size-8 shrink-0 place-items-center rounded-lg border transition-colors duration-200" :class="item.active ? 'border-primary/15 bg-primary/12' : 'border-transparent bg-elevated group-hover:border-default group-hover:bg-default'"><UIcon :name="item.icon" class="size-[18px]" aria-hidden="true" /></span>
              <span class="flex-1">{{ item.label }}</span>
              <span v-if="item.badge !== undefined" class="min-w-6 rounded-full bg-primary px-1.5 py-0.5 text-center font-mono text-[10px] font-bold leading-4 text-white shadow-sm shadow-primary/20">{{ item.badge }}</span>
            </Link>
            <Transition name="submenu">
              <div v-if="item.children?.length && expandedMenus.has(item.label)" class="submenu-tree relative ml-4 mt-1.5 space-y-1 pb-1 pl-7">
                <Link
                  v-for="child in item.children"
                  :key="child.url"
                  :href="child.url"
                  class="submenu-link group/child relative flex min-h-9 w-full items-center rounded-lg border border-transparent px-3 py-2 text-left text-[13px] font-medium transition-all duration-200"
                  :class="page.url.startsWith(child.url) ? 'border-primary/15 bg-primary/10 text-primary shadow-sm' : 'text-muted hover:translate-x-0.5 hover:border-default hover:bg-default/65 hover:text-highlighted'"
                >
                  <span class="tree-branch" :class="page.url.startsWith(child.url) ? 'tree-branch-active' : ''" aria-hidden="true"/>
                  <span class="mr-2 grid size-6 shrink-0 place-items-center rounded-md border transition-all" :class="page.url.startsWith(child.url) ? 'border-primary/20 bg-primary/15 text-primary shadow-sm' : 'border-default bg-elevated text-dimmed group-hover/child:border-primary/15 group-hover/child:text-primary'"><UIcon :name="child.icon" class="size-3.5" aria-hidden="true"/></span>
                  <span>{{ child.label }}</span>
                </Link>
              </div>
            </Transition>
            </div>
          </div>
        </nav>

        <div class="border-t border-default p-3">
          <UserMenu />
        </div>
      </aside>

      <UButton
        class="fixed bottom-4 left-4 z-50 rounded-xl shadow-xl shadow-black/20 md:hidden"
        label="Menú"
        icon="i-lucide-menu"
        size="lg"
        @click="open = true"
      />

      <USlideover v-model:open="open" title="AUTOFIX" description="Navegación principal" side="left">
        <template #content>
          <div class="sidebar-shell flex h-full flex-col bg-default/95 backdrop-blur-xl">
            <div class="flex items-center justify-between border-b border-default bg-gradient-to-b from-primary/5 to-transparent p-3">
              <TeamsMenu />
              <UButton icon="i-lucide-x" color="neutral" variant="ghost" aria-label="Cerrar menú" @click="open = false" />
            </div>
            <nav class="flex-1 space-y-1.5 overflow-y-auto px-3 py-4">
              <div v-for="section in menuSections" :key="section.id" class="mb-4 last:mb-0">
                <p v-if="section.label" class="mb-1.5 px-3 font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-dimmed">{{ section.label }}</p>
                <div v-for="item in section.links" :key="item.label" class="relative">
                <button
                  v-if="item.children?.length"
                  type="button"
                  class="group relative flex min-h-12 w-full items-center gap-3 overflow-hidden rounded-xl border border-transparent px-2.5 py-2 text-left text-sm font-semibold transition-all duration-200"
                  :class="item.active || expandedMenus.has(item.label) ? 'border-primary/20 bg-gradient-to-r from-primary/18 to-primary/5 text-primary shadow-sm' : 'text-muted hover:border-default hover:bg-elevated hover:text-highlighted'"
                  @click="toggleMenu(item)"
                >
                  <span v-if="item.active || expandedMenus.has(item.label)" class="absolute inset-y-2 left-0 w-0.5 rounded-full bg-primary"/>
                  <span class="grid size-9 shrink-0 place-items-center rounded-lg border" :class="item.active || expandedMenus.has(item.label) ? 'border-primary/15 bg-primary/12' : 'border-transparent bg-elevated'"><UIcon :name="item.icon" class="size-5" aria-hidden="true" /></span>
                  <span class="flex-1">{{ item.label }}</span>
                  <span class="grid size-8 place-items-center rounded-md bg-default/45"><UIcon name="i-lucide-chevron-right" class="size-4 transition-transform duration-300" :class="expandedMenus.has(item.label)?'rotate-90':''"/></span>
                </button>
                <Link
                  v-else
                  :href="item.url"
                  class="group relative flex min-h-12 w-full items-center gap-3 overflow-hidden rounded-xl border border-transparent px-2.5 py-2 text-left text-sm font-semibold transition-all duration-200"
                  :class="item.active ? 'border-primary/20 bg-gradient-to-r from-primary/18 to-primary/5 text-primary shadow-sm' : 'text-muted hover:border-default hover:bg-elevated hover:text-highlighted'"
                  @click="open = false"
                >
                  <span v-if="item.active" class="absolute inset-y-2 left-0 w-0.5 rounded-full bg-primary"/>
                   <span class="grid size-9 shrink-0 place-items-center rounded-lg border" :class="item.active ? 'border-primary/15 bg-primary/12' : 'border-transparent bg-elevated'"><UIcon :name="item.icon" class="size-5" aria-hidden="true" /></span>
                   <span class="flex-1">{{ item.label }}</span>
                   <span v-if="item.badge !== undefined" class="min-w-6 rounded-full bg-primary px-1.5 py-0.5 text-center font-mono text-[10px] font-bold leading-4 text-white shadow-sm shadow-primary/20">{{ item.badge }}</span>
                 </Link>
                <Transition name="submenu">
                  <div v-if="item.children?.length && expandedMenus.has(item.label)" class="submenu-tree relative ml-5 mt-1.5 space-y-1 pb-1 pl-7">
                    <Link v-for="child in item.children" :key="child.url" :href="child.url" class="submenu-link group/child relative flex min-h-10 w-full items-center rounded-lg border border-transparent px-3 py-2 text-left text-sm font-medium transition-all duration-200" :class="page.url.startsWith(child.url)?'border-primary/15 bg-primary/10 text-primary shadow-sm':'text-muted hover:border-default hover:bg-elevated hover:text-highlighted'" @click="open=false"><span class="tree-branch" :class="page.url.startsWith(child.url)?'tree-branch-active':''" aria-hidden="true"/><span class="mr-2 grid size-7 shrink-0 place-items-center rounded-md border" :class="page.url.startsWith(child.url)?'border-primary/20 bg-primary/15 text-primary':'border-default bg-elevated text-dimmed'"><UIcon :name="child.icon" class="size-4" aria-hidden="true"/></span><span>{{ child.label }}</span></Link>
                  </div>
                 </Transition>
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
        <div class="app-content relative z-[2] flex min-w-0 flex-1">
          <slot />
        </div>
      </div>

    </UDashboardGroup>
  </UApp>
</template>

<style scoped>
.sidebar-shell {
  background-image: radial-gradient(circle at 15% 0%, color-mix(in srgb, var(--ui-primary) 7%, transparent), transparent 18rem);
}

.submenu-tree::before {
  position: absolute;
  top: 0;
  bottom: 1.15rem;
  left: .7rem;
  width: 1px;
  content: '';
  background: linear-gradient(to bottom, color-mix(in srgb, var(--ui-primary) 55%, var(--ui-border)), var(--ui-border));
}

.tree-branch {
  position: absolute;
  top: 50%;
  left: -1.05rem;
  width: 1.05rem;
  height: .75rem;
  border-top: 1px solid var(--ui-border);
  border-left: 1px solid transparent;
  border-radius: 0 .4rem 0 0;
  transition: border-color 200ms ease, filter 200ms ease;
}

.tree-branch::after {
  position: absolute;
  top: -3px;
  right: -1px;
  width: 5px;
  height: 5px;
  content: '';
  border-top: 1px solid currentColor;
  border-right: 1px solid currentColor;
  color: var(--ui-text-dimmed);
  transform: rotate(45deg);
}

.submenu-link:hover .tree-branch,
.tree-branch-active {
  border-top-color: var(--ui-primary);
  filter: drop-shadow(0 0 3px color-mix(in srgb, var(--ui-primary) 60%, transparent));
}

.tree-branch-active::after,
.submenu-link:hover .tree-branch::after {
  color: var(--ui-primary);
}

.submenu-enter-active,
.submenu-leave-active {
  overflow: hidden;
  transition: max-height 320ms cubic-bezier(.22, 1, .36, 1), opacity 220ms ease, transform 280ms ease;
}

.submenu-enter-from,
.submenu-leave-to {
  max-height: 0;
  opacity: 0;
  transform: translateY(-.4rem);
}

.submenu-enter-to,
.submenu-leave-from {
  max-height: 36rem;
  opacity: 1;
  transform: translateY(0);
}

@media (prefers-reduced-motion: reduce) {
  .submenu-enter-active,
  .submenu-leave-active {
    transition-duration: 1ms;
  }
}
</style>
