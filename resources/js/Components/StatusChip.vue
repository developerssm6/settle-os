<script setup lang="ts">
const props = defineProps<{ status: string; size?: 'x-small' | 'small' | 'default' }>();

const config: Record<string, { color: string; icon: string; label: string }> = {
    pending:    { color: 'secondary', icon: 'mdi-clock-outline',        label: 'Pending' },
    calculated: { color: 'info',      icon: 'mdi-calculator-variant',   label: 'Calculated' },
    processed:  { color: 'success',   icon: 'mdi-check-circle-outline', label: 'Processed' },
    voided:     { color: 'error',     icon: 'mdi-cancel',               label: 'Voided' },
    active:     { color: 'success',   icon: 'mdi-check-circle-outline', label: 'Active' },
    expired:    { color: 'warning',   icon: 'mdi-clock-alert-outline',  label: 'Expired' },
    upcoming:   { color: 'info',      icon: 'mdi-clock-start',          label: 'Upcoming' },
};

const chip = config[props.status] ?? { color: 'secondary', icon: 'mdi-help-circle-outline', label: props.status };
</script>

<template>
    <VChip :color="chip.color" :size="size ?? 'small'" variant="tonal" :prepend-icon="chip.icon">
        {{ chip.label }}
    </VChip>
</template>
