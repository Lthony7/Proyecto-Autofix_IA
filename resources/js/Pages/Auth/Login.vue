<script setup lang="ts">
import { reactive, computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import FormField from '../../components/FormField.vue'
import AutofixLogo from '../../components/AutofixLogo.vue'

// Definir que esta página no usa layout
defineOptions({
  layout: null
})

// Estado del formulario
const state = reactive({
  email: '',
  password: '',
  remember: false
})

// Obtener errores de validación del backend
const page = usePage()
const backendErrors = computed(() => page.props.errors || {})

// Convertir errores de array a string (Laravel retorna arrays)
const errors = computed(() => {
  const result: Record<string, string> = {}
  Object.keys(backendErrors.value).forEach(key => {
    const error = backendErrors.value[key]
    result[key] = Array.isArray(error) ? error[0] : error
  })
  return result
})

// Loading state
const isLoading = ref(false)

// Submit handler
const handleSubmit = () => {
  isLoading.value = true

  router.post(route('login'), state, {
    onFinish: () => {
      isLoading.value = false
    }
  })
}
</script>

<template>
  <Head title="Iniciar sesión" />
  <div class="min-h-screen flex items-center justify-center bg-background p-4">
    <div class="w-full max-w-md">
      <UCard>
        <template #header>
          <div class="flex items-center gap-3 mb-2">
            <AutofixLogo class="h-16 w-20 shrink-0" />
            <div>
              <h1 class="text-2xl font-bold">AUTOFIX</h1>
              <p class="text-sm text-muted">Ingresa tus credenciales para acceder al taller</p>
            </div>
          </div>
        </template>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <FormField label="Correo electrónico" name="email" required :error="errors.email">
            <UInput
              v-model="state.email"
              type="email"
              autocomplete="email"
              placeholder="tu@email.com"
              icon="i-lucide-mail"
              size="xl"
              class="w-full"
            />
          </FormField>

          <FormField label="Contraseña" name="password" required :error="errors.password">
            <UInput
              v-model="state.password"
              type="password"
              autocomplete="current-password"
              placeholder="••••••••"
              icon="i-lucide-lock"
              size="xl"
              class="w-full"
            />
          </FormField>

          <div class="flex items-center">
            <UCheckbox v-model="state.remember" label="Recordarme" />
          </div>

          <UButton
            type="submit"
            color="primary"
            label="Iniciar Sesión"
            :loading="isLoading"
            block
            size="xl"
          />
        </form>

        <template #footer>
          <p class="text-center text-sm text-muted">
            Las cuentas son administradas por personal autorizado.
          </p>
        </template>
      </UCard>
    </div>
  </div>
</template>
