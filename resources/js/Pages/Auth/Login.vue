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
const status = computed(() => (page.props.flash as { success?: string })?.success)

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
  <main class="min-h-screen flex items-center justify-center bg-background p-4">
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

        <UAlert v-if="status" class="mb-4" color="success" variant="subtle" icon="i-lucide-circle-check" :description="status" role="status" />

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <FormField label="Correo electrónico" name="email" required :error="errors.email">
            <template #default="{ fieldAttrs }">
              <UInput
                v-bind="fieldAttrs"
                v-model="state.email"
                type="email"
                autocomplete="email"
                placeholder="tu@email.com"
                icon="i-lucide-mail"
                size="xl"
                class="w-full"
              />
            </template>
          </FormField>

          <FormField label="Contraseña" name="password" required :error="errors.password">
            <template #default="{ fieldAttrs }">
              <UInput
                v-bind="fieldAttrs"
                v-model="state.password"
                type="password"
                autocomplete="current-password"
                placeholder="••••••••"
                icon="i-lucide-lock"
                size="xl"
                class="w-full"
              />
            </template>
          </FormField>

          <div class="flex items-center justify-between gap-3">
            <UCheckbox v-model="state.remember" label="Recordarme" />
            <UButton :to="route('password.request')" variant="link" color="primary" label="¿Olvidaste tu contraseña?" :padded="false" />
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
          <div class="space-y-2 text-center text-sm">
            <p class="text-muted">¿Eres cliente y todavía no tienes cuenta?</p>
            <UButton :to="route('register')" variant="link" color="primary" label="Crear cuenta de cliente" :padded="false" />
          </div>
        </template>
      </UCard>
    </div>
  </main>
</template>
