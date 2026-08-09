<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AutofixLogo from "../../components/AutofixLogo.vue";
import FormField from "../../components/FormField.vue";

defineOptions({ layout: null });

const state = reactive({ email: "" });
const page = usePage();
const errors = computed<Record<string, string>>(
    () => page.props.errors as Record<string, string>,
);
const status = computed(
    () => (page.props.flash as { success?: string })?.success,
);
const isLoading = ref(false);

function submit() {
    isLoading.value = true;
    router.post(route("password.email"), state, {
        preserveScroll: true,
        onFinish: () => {
            isLoading.value = false;
        },
    });
}
</script>

<template>
    <Head title="Recuperar contraseña" />
    <main
        class="min-h-screen flex items-center justify-center bg-background p-4"
    >
        <div class="w-full max-w-md">
            <UCard>
                <template #header>
                    <div class="flex items-center gap-3">
                        <AutofixLogo class="h-16 w-20 shrink-0" />
                        <div>
                            <h1 class="text-xl font-bold">
                                Recuperar contraseña
                            </h1>
                            <p class="mt-1 text-sm text-muted">
                                Te enviaremos un enlace seguro si tu cuenta está
                                activa.
                            </p>
                        </div>
                    </div>
                </template>

                <UAlert
                    v-if="status"
                    class="mb-4"
                    color="success"
                    variant="subtle"
                    icon="i-lucide-circle-check"
                    :description="status"
                    role="status"
                />

                <form class="space-y-5" @submit.prevent="submit">
                    <FormField
                        label="Correo electrónico"
                        name="email"
                        required
                        :error="errors.email"
                    >
                        <template #default="{ fieldAttrs }">
                            <UInput
                                v-bind="fieldAttrs"
                                v-model="state.email"
                                type="email"
                                autocomplete="email"
                                maxlength="254"
                                class="w-full"
                                icon="i-lucide-mail"
                                size="xl"
                            />
                        </template>
                    </FormField>
                    <UButton
                        type="submit"
                        label="Enviar enlace de recuperación"
                        icon="i-lucide-send"
                        size="xl"
                        block
                        :loading="isLoading"
                    />
                </form>

                <template #footer>
                    <div class="text-center">
                        <UButton
                            :to="route('login')"
                            variant="link"
                            label="Volver a iniciar sesión"
                            icon="i-lucide-arrow-left"
                            :padded="false"
                        />
                    </div>
                </template>
            </UCard>
        </div>
    </main>
</template>
