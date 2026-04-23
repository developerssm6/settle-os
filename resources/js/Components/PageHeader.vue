<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string;
    breadcrumbs?: { title: string; href?: string }[];
}>();
</script>

<template>
    <div class="mb-6">
        <VBreadcrumbs
            v-if="breadcrumbs?.length"
            :items="breadcrumbs"
            density="compact"
            class="pa-0 mb-1"
        />
        <div class="d-flex align-center justify-space-between">
            <div>
                <h1 class="text-h5 font-semibold">{{ title }}</h1>
                <p v-if="subtitle" class="text-body-2 text-medium-emphasis mt-1">{{ subtitle }}</p>
            </div>
            <div v-if="$slots.actions" class="d-flex gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
