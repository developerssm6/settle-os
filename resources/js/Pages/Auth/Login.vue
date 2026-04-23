<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: AuthLayout });

defineProps<{ canResetPassword?: boolean; status?: string }>();

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);

function submit() {
    form.post(route('login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <VCard>
        <VCardTitle class="text-h6 font-semibold pa-6 pb-2">Sign in</VCardTitle>
        <VCardText class="pa-6 pt-2">
            <VAlert v-if="status" type="success" variant="tonal" density="compact" class="mb-4">
                {{ status }}
            </VAlert>

            <form @submit.prevent="submit">
                <VTextField
                    v-model="form.email"
                    label="Email"
                    type="email"
                    autocomplete="email"
                    autofocus
                    class="mb-3"
                    :error-messages="form.errors.email"
                />
                <VTextField
                    v-model="form.password"
                    label="Password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    class="mb-1"
                    :error-messages="form.errors.password"
                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                    @click:append-inner="showPassword = !showPassword"
                />

                <div class="d-flex align-center justify-space-between mb-4">
                    <VCheckbox v-model="form.remember" label="Remember me" density="compact" hide-details />
                    <a v-if="canResetPassword" :href="route('password.request')" class="text-sm text-primary">
                        Forgot password?
                    </a>
                </div>

                <VBtn type="submit" color="primary" block :loading="form.processing">
                    Sign in
                </VBtn>
            </form>
        </VCardText>
    </VCard>
</template>
