<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: AuthLayout });

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
const showPassword = ref(false);

function submit() {
    form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <VCard>
        <VCardTitle class="text-h6 font-semibold pa-6 pb-2">Create account</VCardTitle>
        <VCardText class="pa-6 pt-2">
            <form @submit.prevent="submit">
                <VTextField v-model="form.name" label="Full name" autocomplete="name" autofocus class="mb-3" :error-messages="form.errors.name" />
                <VTextField v-model="form.email" label="Email" type="email" autocomplete="email" class="mb-3" :error-messages="form.errors.email" />
                <VTextField
                    v-model="form.password" label="Password" :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password" class="mb-3" :error-messages="form.errors.password"
                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'" @click:append-inner="showPassword = !showPassword"
                />
                <VTextField
                    v-model="form.password_confirmation" label="Confirm password" :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password" class="mb-4" :error-messages="form.errors.password_confirmation"
                />
                <VBtn type="submit" color="primary" block :loading="form.processing">Create account</VBtn>
                <div class="text-center mt-4">
                    <a :href="route('login')" class="text-sm text-primary">Already have an account?</a>
                </div>
            </form>
        </VCardText>
    </VCard>
</template>
