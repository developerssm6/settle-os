<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    title: string;
    message: string;
    confirmText?: string;
    confirmLabel?: string;
    dangerous?: boolean;
}>();

const emit = defineEmits<{ confirm: [] }>();

const dialog = ref(false);
const typed = ref('');

const requiresTyping = () => !!props.confirmText;
const canConfirm = () => !requiresTyping() || typed.value === props.confirmText;

function open() { dialog.value = true; typed.value = ''; }
function close() { dialog.value = false; }
function confirm() { emit('confirm'); close(); }

defineExpose({ open });
</script>

<template>
    <VDialog v-model="dialog" max-width="480" persistent>
        <VCard>
            <VCardTitle class="pa-6 pb-2 text-subtitle-1 font-semibold d-flex align-center gap-2">
                <VIcon v-if="dangerous" icon="mdi-alert" color="error" size="20" />
                {{ title }}
            </VCardTitle>

            <VCardText class="pa-6 pt-2">
                <p class="text-body-2">{{ message }}</p>

                <VTextField
                    v-if="confirmText"
                    v-model="typed"
                    :label="`Type &quot;${confirmText}&quot; to confirm`"
                    :placeholder="confirmText"
                    class="mt-4"
                    density="compact"
                    variant="outlined"
                    hide-details
                />
            </VCardText>

            <VCardActions class="pa-4 pt-0 justify-end gap-2">
                <VBtn variant="text" @click="close">Cancel</VBtn>
                <VBtn
                    :color="dangerous ? 'error' : 'primary'"
                    :disabled="!canConfirm()"
                    @click="confirm"
                >
                    {{ confirmLabel ?? 'Confirm' }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>

    <slot :open="open" />
</template>
