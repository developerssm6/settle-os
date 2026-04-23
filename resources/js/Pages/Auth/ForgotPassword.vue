<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineOptions({ layout: AuthLayout });
defineProps<{ status?: string }>();

const form = useForm({ email: '' });
function submit() { form.post(route('password.email')); }
</script>

<template>
    <VCard>
        <VCardTitle class="text-h6 font-semibold pa-6 pb-2">Reset password</VCardTitle>
        <VCardText class="pa-6 pt-2">
            <p class="text-body-2 text-medium-emphasis mb-4">
                Enter your email and we'll send you a reset link.
            </p>
            <VAlert v-if="status" type="success" variant="tonal" density="compact" class="mb-4">{{ status }}</VAlert>
            <form @submit.prevent="submit">
                <VTextField v-model="form.email" label="Email" type="email" autocomplete="email" autofocus class="mb-4" :error-messages="form.errors.email" />
                <VBtn type="submit" color="primary" block :loading="form.processing">Send reset link</VBtn>
            </form>
            <div class="text-center mt-4">
                <a :href="route('login')" class="text-sm text-primary">Back to sign in</a>
            </div>
        </VCardText>
    </VCard>
</template>
