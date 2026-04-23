<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineOptions({ layout: AuthLayout });

defineProps<{ status?: string }>();

const form = useForm({});
function submit() { form.post(route('verification.send')); }
function logout() { useForm({}).post(route('logout')); }
</script>

<template>
    <VCard>
        <VCardTitle class="text-h6 font-semibold pa-6 pb-2">Verify your email</VCardTitle>
        <VCardText class="pa-6 pt-2">
            <p class="text-body-2 text-medium-emphasis mb-4">
                Thanks for signing up. Please verify your email address by clicking the link we sent you.
            </p>
            <VAlert v-if="status === 'verification-link-sent'" type="success" variant="tonal" density="compact" class="mb-4">
                A new verification link has been sent to your email address.
            </VAlert>
            <div class="d-flex flex-column gap-3">
                <VBtn color="primary" block :loading="form.processing" @click="submit">
                    Resend verification email
                </VBtn>
                <VBtn variant="text" block @click="logout">Log out</VBtn>
            </div>
        </VCardText>
    </VCard>
</template>
