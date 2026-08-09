<script setup lang="ts">
import { computed, useId } from "vue";

interface Props {
    label?: string;
    name: string;
    error?: string;
    required?: boolean;
    hint?: string;
    id?: string;
}

const props = defineProps<Props>();
const generatedId = useId();
const fieldId = computed(
    () => props.id ?? `field-${props.name}-${generatedId}`,
);
const hintId = computed(() => `${fieldId.value}-hint`);
const errorId = computed(() => `${fieldId.value}-error`);
const describedBy = computed(
    () =>
        [props.hint ? hintId.value : null, props.error ? errorId.value : null]
            .filter(Boolean)
            .join(" ") || undefined,
);

const fieldAttrs = computed(() => ({
    id: fieldId.value,
    name: props.name,
    required: props.required || undefined,
    "aria-describedby": describedBy.value,
    "aria-invalid": props.error ? true : undefined,
}));
</script>

<template>
    <div class="space-y-2">
        <label
            v-if="label"
            :for="fieldId"
            class="block text-sm font-medium text-foreground"
        >
            {{ label }}
            <span v-if="required" aria-hidden="true" style="color: #dc2626"
                >*</span
            >
        </label>

        <slot :field-attrs="fieldAttrs" :id="fieldId" />

        <p v-if="hint" :id="hintId" class="text-sm text-muted">
            {{ hint }}
        </p>

        <p
            v-if="error"
            :id="errorId"
            class="text-sm font-medium"
            style="color: #dc2626; margin-top: 0.25rem"
            role="alert"
            aria-live="assertive"
        >
            {{ error }}
        </p>
    </div>
</template>
