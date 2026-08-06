<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
interface Factura{id:string;numero:string;cliente_nombre:string;cliente_documento:string;vehiculo_placa:string;subtotal:string;descuento:string;impuesto:string;total:string;moneda:string;estado:string;emitida_en:string;orden:{id:string;numero:string}}
interface Pagina<T>{data:T[];links:{url:string|null;label:string;active:boolean}[];total:number}
const props=defineProps<{facturas:Pagina<Factura>;buscar:string}>();const busqueda=ref(props.buscar)
function buscar(){router.get(route('facturacion.index'),{buscar:busqueda.value},{preserveState:true})}
function dinero(valor:string){return Number(valor).toLocaleString('es-CO',{minimumFractionDigits:2,maximumFractionDigits:2})}
</script>

<template>
  <Head title="Facturación" />
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar title="Facturación"><template #leading><UDashboardSidebarCollapse /></template></UDashboardNavbar>
    </template>
    <template #body>
      <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="text-sm text-muted">Comprobantes definitivos de órdenes finalizadas</p>
            <p class="text-3xl font-semibold">{{ facturas.total }} facturas</p>
          </div>
          <form class="flex gap-2" @submit.prevent="buscar">
            <UInput v-model="busqueda" icon="i-lucide-search" placeholder="Factura, orden, cliente o documento" class="w-full sm:w-80" />
            <UButton type="submit" label="Buscar" />
          </form>
        </div>

        <UAlert icon="i-lucide-info" color="neutral" variant="subtle" title="Las facturas se emiten únicamente desde órdenes finalizadas con servicios completados y repuestos realmente utilizados." />

        <div class="overflow-hidden rounded-lg border border-default">
          <div class="hidden grid-cols-[minmax(9rem,.8fr)_minmax(13rem,1.2fr)_minmax(12rem,1fr)_minmax(11rem,.9fr)_auto] gap-4 border-b border-default bg-elevated/50 px-4 py-3 text-xs font-medium uppercase text-muted md:grid">
            <span>Factura</span>
            <span>Cliente</span>
            <span>Orden y vehículo</span>
            <span>Total</span>
            <span class="text-right">Acciones</span>
          </div>
          <div v-for="f in facturas.data" :key="f.id" class="grid gap-4 border-b border-default px-4 py-4 last:border-b-0 md:grid-cols-[minmax(9rem,.8fr)_minmax(13rem,1.2fr)_minmax(12rem,1fr)_minmax(11rem,.9fr)_auto] md:items-center">
            <div>
              <p class="text-xs font-medium uppercase text-muted md:hidden">Factura</p>
              <p class="font-semibold">{{ f.numero }}</p>
              <UBadge :color="f.estado === 'emitida' ? 'success' : 'neutral'">{{ f.estado }}</UBadge>
            </div>
            <div>
              <p class="text-xs font-medium uppercase text-muted md:hidden">Cliente</p>
              <p>{{ f.cliente_nombre }}</p>
              <p class="text-sm text-muted">{{ f.cliente_documento }}</p>
            </div>
            <div>
              <p class="text-xs font-medium uppercase text-muted md:hidden">Orden y vehículo</p>
              <p>{{ f.orden.numero }} · {{ f.vehiculo_placa }}</p>
              <p class="text-sm text-muted">{{ new Date(f.emitida_en).toLocaleString('es-CO') }}</p>
            </div>
            <div>
              <p class="text-xs font-medium uppercase text-muted md:hidden">Total</p>
              <p class="text-lg font-semibold" :class="f.estado === 'anulada' ? 'line-through text-muted' : ''">$ {{ dinero(f.total) }}</p>
              <p class="text-xs text-muted">{{ f.moneda }} · impuesto $ {{ dinero(f.impuesto) }}</p>
            </div>
            <div class="md:text-right">
              <Link :href="route('facturacion.show', f.id)"><UButton size="sm" color="neutral" variant="outline" label="Ver detalle" /></Link>
            </div>
          </div>
          <p v-if="!facturas.data.length" class="px-4 py-12 text-center text-muted">No se encontraron facturas definitivas.</p>
        </div>

        <div class="flex flex-wrap gap-2">
          <Link v-for="link in facturas.links" :key="link.label" :href="link.url || ''" preserve-scroll>
            <UButton :disabled="!link.url" :variant="link.active ? 'solid' : 'outline'" color="neutral" size="sm"><span v-html="link.label" /></UButton>
          </Link>
        </div>
      </div>
    </template>
  </UDashboardPanel>
</template>
