<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: AuthLayout });

const props = defineProps<{ token: string; email: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const showPassword = ref(false);

function submit() {
    form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <VCard>
        <VCardTitle class="text-h6 font-semibold pa-6 pb-2">Set new password</VCardTitle>
        <VCardText class="pa-6 pt-2">
            <form @submit.prevent="submit">
                <VTextField v-model="form.email" label="Email" type="email" autocomplete="email" class="mb-3" :error-messages="form.errors.email" />
                <VTextField
                    v-model="form.password" label="New password" :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password" autofocus class="mb-3" :error-messages="form.errors.password"
                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'" @click:append-inner="showPassword = !showPassword"
                />
                <VTextField
                    v-model="form.password_confirmation" label="Confirm new password" :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password" class="mb-4" :error-messages="form.errors.password_confirmation"
                />
                <VBtn type="submit" color="primary" block :loading="form.processing">Reset password</VBtn>
            </form>
        </VCardText>
    </VCard>
</template>
