<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import AutofixLogo from "../../components/AutofixLogo.vue";
import FormField from "../../components/FormField.vue";

const props = defineProps<{ token: string; email: string }>();
defineOptions({ layout: null });

const state = reactive({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});
const page = usePage();
const errors = computed<Record<string, string>>(
    () => page.props.errors as Record<string, string>,
);
const isLoading = ref(false);

function submit() {
    isLoading.value = true;
    router.post(route("password.update"), state, {
        onFinish: () => {
            isLoading.value = false;
        },
        onSuccess: () => {
            state.password = "";
            state.password_confirmation = "";
        },
    });
}
</script>

<template>
    <Head title="Restablecer contraseña" />
    <main
        class="min-h-screen flex items-center justify-center bg-background p-4"
    >
        <div class="w-full max-w-md">
            <UCard>
                <template #header>
                    <div class="flex items-center gap-3">
                        <AutofixLogo class="h-16 w-20 shrink-0" />
                        <div>
                            <h1 class="text-xl font-bold">Nueva contraseña</h1>
                            <p class="mt-1 text-sm text-muted">
                                Elige una contraseña segura para tu cuenta.
                            </p>
                        </div>
                    </div>
                </template>

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
                    <FormField
                        label="Nueva contraseña"
                        name="password"
                        required
                        :error="errors.password"
                        hint="Mínimo 8 caracteres, con mayúsculas, minúsculas y números."
                    >
                        <template #default="{ fieldAttrs }">
                            <UInput
                                v-bind="fieldAttrs"
                                v-model="state.password"
                                type="password"
                                autocomplete="new-password"
                                class="w-full"
                                icon="i-lucide-lock"
                                size="xl"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Confirmar nueva contraseña"
                        name="password_confirmation"
                        required
                    >
                        <template #default="{ fieldAttrs }">
                            <UInput
                                v-bind="fieldAttrs"
                                v-model="state.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="w-full"
                                icon="i-lucide-lock-keyhole"
                                size="xl"
                            />
                        </template>
                    </FormField>
                    <UButton
                        type="submit"
                        label="Restablecer contraseña"
                        icon="i-lucide-shield-check"
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
                            :padded="false"
                        />
                    </div>
                </template>
            </UCard>
        </div>
    </main>
</template>
