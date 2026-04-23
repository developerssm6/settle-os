<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    modelValue: string | number;
    label: string;
    currency?: string;
    errorMessages?: string | string[];
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const symbol = computed(() => {
    const symbols: Record<string, string> = { INR: '₹', USD: '$', EUR: '€', GBP: '£', AUD: 'A$' };
    return symbols[props.currency ?? 'INR'] ?? props.currency ?? '₹';
});

function onInput(v: string) {
    // Strip everything except digits and decimal point
    emit('update:modelValue', v.replace(/[^0-9.]/g, ''));
}
</script>

<template>
    <VTextField
        :model-value="modelValue"
        :label="label"
        :prefix="symbol"
        :error-messages="errorMessages"
        inputmode="decimal"
        class="font-mono"
        @update:model-value="onInput"
    />
</template>
