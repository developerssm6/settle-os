<script setup lang="ts">
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

defineProps<{
    modelValue: string | number;
    label: string;
    errorMessages?: string | string[];
}>();

function onInput(v: string) {
    emit('update:modelValue', v.replace(/[^0-9.]/g, ''));
}
</script>

<template>
    <VTextField
        :model-value="modelValue"
        :label="label"
        suffix="%"
        :error-messages="errorMessages"
        inputmode="decimal"
        class="font-mono"
        @update:model-value="onInput"
    />
</template>
