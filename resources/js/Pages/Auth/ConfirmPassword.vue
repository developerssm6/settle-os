<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: AuthLayout });

const form = useForm({ password: '' });
const showPassword = ref(false);

function submit() {
    form.post(route('password.confirm'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <VCard>
        <VCardTitle class="text-h6 font-semibold pa-6 pb-2">Confirm password</VCardTitle>
        <VCardText class="pa-6 pt-2">
            <p class="text-body-2 text-medium-emphasis mb-4">
                This is a secure area. Please confirm your password to continue.
            </p>
            <form @submit.prevent="submit">
                <VTextField
                    v-model="form.password" label="Password" :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password" autofocus class="mb-4" :error-messages="form.errors.password"
                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'" @click:append-inner="showPassword = !showPassword"
                />
                <VBtn type="submit" color="primary" block :loading="form.processing">Confirm</VBtn>
            </form>
        </VCardText>
    </VCard>
</template>
