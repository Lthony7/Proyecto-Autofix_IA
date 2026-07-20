<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { usePermissions } from '../../composables/usePermissions'

interface Catalogo { id: string; nombre: string; estado: string }
interface Proveedor extends Catalogo { documento: string }
interface Repuesto {
  id: string; codigo: string; nombre: string; descripcion?: string; unidad: string
  stock_actual: string; stock_minimo: string; costo_referencia: string; precio_venta: string; estado: string
  categoria: Catalogo; proveedor?: Catalogo
}
interface Movimiento {
  id: string; tipo: string; cantidad: string; stock_resultante: string; motivo: string; created_at: string
  repuesto: { codigo: string; nombre: string }
}
interface Pagina<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[]; total: number }

const props = defineProps<{ repuestos: Pagina<Repuesto>; movimientos: Movimiento[]; categorias: Catalogo[]; proveedores: Proveedor[]; filtros:{buscar:string;estado:string;categoria:string;proveedor:string;bajo:boolean};stats:{total:number;bajos:number;agotados:number} }>()
const { can } = usePermissions()
const errors = computed<Record<string, string>>(() => usePage().props.errors as Record<string, string>)
const procesando = ref(false)
const filtros = reactive({...props.filtros})
const categoria = reactive({ nombre: '', descripcion: '' })
const proveedor = reactive({ documento: '', nombre: '', contacto: '', telefono: '', email: '' })
const repuesto = reactive({ categoriaId: '', proveedorId: '', codigo: '', nombre: '', descripcion: '', unidad: 'unidad', stockMinimo: '0.000', costoReferencia: '0.00', precioVenta: '0.00' })
const movimiento = reactive({ repuestoId: '', tipo: 'entrada', cantidad: '', costoUnitario: '', motivo: '' })
const opcionesRepuestos = computed(() => props.repuestos.data.filter(p => p.estado === 'activo').map(p => ({ label: `${p.codigo} · ${p.nombre} (${p.stock_actual} ${p.unidad})`, value: p.id })))
const unidades = ['unidad', 'litro', 'metro', 'kilogramo', 'juego']

function enviar(url: string, datos: object, limpiar: () => void) {
  procesando.value = true
  router.post(url, datos, { preserveScroll: true, onSuccess: limpiar, onFinish: () => { procesando.value = false } })
}
function buscar() { router.get(route('inventario.index'), { buscar:filtros.buscar||undefined,estado:filtros.estado||undefined,categoria:filtros.categoria||undefined,proveedor:filtros.proveedor||undefined,bajo:filtros.bajo?1:undefined }, { preserveState: true, replace:true }) }
function guardarCategoria() { enviar(route('inventario.categorias.store'), categoria, () => Object.assign(categoria, { nombre: '', descripcion: '' })) }
function guardarProveedor() { enviar(route('inventario.proveedores.store'), proveedor, () => Object.assign(proveedor, { documento: '', nombre: '', contacto: '', telefono: '', email: '' })) }
function guardarRepuesto() { enviar(route('inventario.repuestos.store'), repuesto, () => Object.assign(repuesto, { categoriaId: '', proveedorId: '', codigo: '', nombre: '', descripcion: '', unidad: 'unidad', stockMinimo: '0.000', costoReferencia: '0.00', precioVenta: '0.00' })) }
function registrarMovimiento() { enviar(route('inventario.movimientos.store'), movimiento, () => Object.assign(movimiento, { repuestoId: '', tipo: 'entrada', cantidad: '', costoUnitario: '', motivo: '' })) }
function cambiarEstado(p: Repuesto, estado = p.estado === 'activo' ? 'inactivo' : 'activo') { if(!confirm(`¿Cambiar ${p.codigo} a ${estado}?`))return;router.patch(route('inventario.repuestos.estado', p.id), { estado }, { preserveScroll: true }) }
function numero(valor: string, decimales = 2) { return Number(valor).toLocaleString('es-CO', { minimumFractionDigits: decimales, maximumFractionDigits: decimales }) }
</script>

<template>
  <Head title="Inventario" />
  <UDashboardPanel>
    <template #header><UDashboardNavbar title="Inventario de repuestos"><template #leading><UDashboardSidebarCollapse /></template></UDashboardNavbar></template>
    <template #body>
      <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
          <UCard><p class="text-sm text-muted">Referencias totales</p><p class="mt-1 text-3xl font-semibold">{{ stats.total }}</p></UCard>
          <UCard><p class="text-sm text-muted">Bajo stock global</p><p class="mt-1 text-3xl font-semibold" :class="stats.bajos ? 'text-warning' : 'text-success'">{{ stats.bajos }}</p></UCard>
          <UCard><p class="text-sm text-muted">Sin existencias</p><p class="mt-1 text-3xl font-semibold" :class="stats.agotados ? 'text-error' : ''">{{ stats.agotados }}</p></UCard>
        </div>

        <div v-if="can('inventario.gestionar')" class="grid gap-6 xl:grid-cols-2">
          <UCard>
            <template #header><div><h2 class="font-semibold">Nueva referencia</h2><p class="text-sm text-muted">El saldo inicial se registra después como una entrada.</p></div></template>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="guardarRepuesto">
              <UFormField label="Código" required :error="errors.codigo"><UInput v-model="repuesto.codigo" class="w-full" /></UFormField>
              <UFormField label="Nombre" required :error="errors.nombre"><UInput v-model="repuesto.nombre" class="w-full" /></UFormField>
              <UFormField label="Categoría" required :error="errors.categoriaId"><USelect v-model="repuesto.categoriaId" :items="categorias.filter(c=>c.estado==='activo').map(c => ({ label: c.nombre, value: c.id }))" class="w-full" /></UFormField>
              <UFormField label="Proveedor"><USelect v-model="repuesto.proveedorId" :items="proveedores.filter(p=>p.estado==='activo').map(p => ({ label: p.nombre, value: p.id }))" class="w-full" /></UFormField>
              <UFormField label="Unidad" required><USelect v-model="repuesto.unidad" :items="unidades" class="w-full" /></UFormField>
              <UFormField label="Stock mínimo" required :error="errors.stockMinimo"><UInput v-model="repuesto.stockMinimo" type="number" min="0" step="0.001" class="w-full" /></UFormField>
              <UFormField label="Costo de referencia" required><UInput v-model="repuesto.costoReferencia" type="number" min="0" step="0.01" class="w-full" /></UFormField>
              <UFormField label="Precio de venta" required><UInput v-model="repuesto.precioVenta" type="number" min="0" step="0.01" class="w-full" /></UFormField>
              <UFormField class="sm:col-span-2" label="Descripción"><UTextarea v-model="repuesto.descripcion" class="w-full" /></UFormField>
              <div class="sm:col-span-2 text-right"><UButton type="submit" label="Crear repuesto" :loading="procesando" /></div>
            </form>
          </UCard>

          <UCard>
            <template #header><div><h2 class="font-semibold">Registrar movimiento</h2><p class="text-sm text-muted">Las salidas de órdenes se registran desde la orden de trabajo.</p></div></template>
            <form class="space-y-4" @submit.prevent="registrarMovimiento">
              <UFormField label="Repuesto" required :error="errors.repuestoId"><USelect v-model="movimiento.repuestoId" :items="opcionesRepuestos" class="w-full" /></UFormField>
              <div class="grid gap-4 sm:grid-cols-2">
                <UFormField label="Tipo" required><USelect v-model="movimiento.tipo" :items="[{ label: 'Entrada', value: 'entrada' }, { label: 'Ajuste', value: 'ajuste' }]" class="w-full" /></UFormField>
                <UFormField label="Cantidad" required :error="errors.cantidad"><UInput v-model="movimiento.cantidad" type="number" step="0.001" class="w-full" /></UFormField>
              </div>
              <UFormField label="Costo unitario"><UInput v-model="movimiento.costoUnitario" type="number" min="0" step="0.01" class="w-full" /></UFormField>
              <UFormField label="Motivo" required :error="errors.motivo"><UTextarea v-model="movimiento.motivo" class="w-full" placeholder="Compra, corrección por conteo físico..." /></UFormField>
              <div class="text-right"><UButton type="submit" label="Aplicar movimiento" :loading="procesando" /></div>
            </form>
          </UCard>
        </div>

        <UCard v-if="can('inventario.gestionar')">
          <template #header><div class="flex items-center justify-between"><h2 class="font-semibold">Catálogos auxiliares</h2><Link :href="route('inventario.catalogos')"><UButton size="sm" color="neutral" variant="outline" label="Administrar catálogos"/></Link></div></template>
          <div class="grid gap-6 lg:grid-cols-2">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="guardarCategoria">
              <UFormField label="Nueva categoría" required><UInput v-model="categoria.nombre" class="w-full" /></UFormField>
              <UFormField label="Descripción"><UInput v-model="categoria.descripcion" class="w-full" /></UFormField>
              <div class="sm:col-span-2 text-right"><UButton type="submit" size="sm" color="neutral" label="Agregar categoría" /></div>
            </form>
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="guardarProveedor">
              <UFormField label="Documento" required><UInput v-model="proveedor.documento" class="w-full" /></UFormField>
              <UFormField label="Proveedor" required><UInput v-model="proveedor.nombre" class="w-full" /></UFormField>
              <UFormField label="Contacto"><UInput v-model="proveedor.contacto" class="w-full" /></UFormField>
              <UFormField label="Teléfono"><UInput v-model="proveedor.telefono" class="w-full" /></UFormField>
              <UFormField label="Correo"><UInput v-model="proveedor.email" type="email" class="w-full" /></UFormField>
              <div class="self-end text-right"><UButton type="submit" size="sm" color="neutral" label="Agregar proveedor" /></div>
            </form>
          </div>
        </UCard>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
          <section class="space-y-4">
            <div><h2 class="mb-3 text-lg font-semibold">Catálogo</h2><form class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="buscar"><UInput v-model="filtros.buscar" icon="i-lucide-search" placeholder="Código o nombre"/><USelect v-model="filtros.estado" :items="[{label:'Todos los estados',value:''},{label:'Activos',value:'activo'},{label:'Inactivos',value:'inactivo'},{label:'Archivados',value:'archivado'}]"/><USelect v-model="filtros.categoria" :items="[{label:'Todas las categorías',value:''},...categorias.map(c=>({label:c.nombre,value:c.id}))]"/><USelect v-model="filtros.proveedor" :items="[{label:'Todos los proveedores',value:''},...proveedores.map(p=>({label:p.nombre,value:p.id}))]"/><UCheckbox v-model="filtros.bajo" label="Solo bajo mínimo"/><UButton type="submit" label="Aplicar filtros" icon="i-lucide-list-filter"/></form></div>
            <UCard v-for="p in repuestos.data" :key="p.id">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div><div class="flex flex-wrap items-center gap-2"><Link :href="route('inventario.repuestos.show',p.id)" class="font-semibold text-primary hover:underline">{{ p.codigo }} · {{ p.nombre }}</Link><UBadge :color="p.estado === 'activo' ? 'success' : 'neutral'">{{ p.estado }}</UBadge><UBadge v-if="Number(p.stock_actual) <= Number(p.stock_minimo)" color="warning" variant="subtle">Stock bajo</UBadge></div><p class="mt-1 text-sm text-muted">{{ p.categoria.nombre }} · {{ p.proveedor?.nombre || 'Sin proveedor' }} · {{ p.unidad }}</p><p v-if="p.descripcion" class="mt-2 text-sm">{{ p.descripcion }}</p></div>
                <div class="shrink-0 text-left sm:text-right"><p class="text-2xl font-semibold">{{ numero(p.stock_actual, 3) }}</p><p class="text-xs text-muted">mínimo {{ numero(p.stock_minimo, 3) }}</p><p class="mt-2 text-sm">Venta $ {{ numero(p.precio_venta) }}</p><div v-if="can('inventario.gestionar')" class="mt-2 flex flex-wrap justify-end gap-1"><Link :href="route('inventario.repuestos.edit',p.id)"><UButton size="xs" color="neutral" variant="ghost" label="Editar"/></Link><UButton size="xs" color="neutral" variant="ghost" :label="p.estado==='activo'?'Desactivar':'Activar'" @click="cambiarEstado(p)"/><UButton v-if="p.estado!=='archivado'" size="xs" color="error" variant="ghost" label="Archivar" @click="cambiarEstado(p,'archivado')"/></div></div>
              </div>
            </UCard>
            <UCard v-if="!repuestos.data.length"><p class="py-6 text-center text-muted">No hay repuestos para mostrar.</p></UCard>
            <div class="flex flex-wrap gap-2"><Link v-for="link in repuestos.links" :key="link.label" :href="link.url || ''" preserve-scroll><UButton :disabled="!link.url" :variant="link.active ? 'solid' : 'outline'" color="neutral" size="sm"><span v-html="link.label" /></UButton></Link></div>
          </section>

          <section><h2 class="mb-4 text-lg font-semibold">Últimos movimientos</h2><UCard><div v-for="m in movimientos" :key="m.id" class="border-b border-default py-3 first:pt-0 last:border-0 last:pb-0"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-medium">{{ m.repuesto.codigo }} · {{ m.repuesto.nombre }}</p><p class="mt-1 text-xs text-muted">{{ m.motivo }}</p><p class="mt-1 text-xs text-muted">{{new Date(m.created_at).toLocaleString('es-CO')}}</p></div><div class="text-right"><UBadge :color="Number(m.cantidad) > 0 ? 'success' : 'error'">{{ Number(m.cantidad) > 0 ? '+' : '' }}{{ numero(m.cantidad, 3) }}</UBadge><p class="mt-1 text-xs text-muted">saldo {{ numero(m.stock_resultante, 3) }}</p></div></div></div><p v-if="!movimientos.length" class="text-center text-muted">Sin movimientos.</p></UCard></section>
        </div>
      </div>
    </template>
  </UDashboardPanel>
</template>
