<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash as { success?: string; error?: string } | null);

const drawer = ref(true);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

watch(() => flash.value, (f) => {
    if (f?.success) { snackbarText.value = f.success; snackbarColor.value = 'success'; snackbar.value = true; }
    if (f?.error)   { snackbarText.value = f.error;   snackbarColor.value = 'error';   snackbar.value = true; }
}, { immediate: true });

const navItems = [
    { title: 'Dashboard',    icon: 'mdi-view-dashboard-outline', route: 'partner.dashboard' },
    { title: 'My Policies',  icon: 'mdi-file-document-outline',  route: 'partner.policies.index' },
    { title: 'My Payouts',   icon: 'mdi-currency-inr',           route: 'partner.payouts.index' },
    { title: 'Reports',      icon: 'mdi-chart-bar',              route: 'partner.reports.index' },
    { title: 'Profile',      icon: 'mdi-account-outline',        route: 'partner.profile.edit' },
];

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <VApp>
        <VNavigationDrawer
            v-model="drawer"
            width="240"
            :permanent="$vuetify.display.mdAndUp"
        >
            <VListItem
                prepend-icon="mdi-shield-check"
                title="SettleOS"
                subtitle="Partner Portal"
                nav
                class="py-4"
            />

            <VDivider />

            <VList density="compact" nav class="mt-2">
                <VListItem
                    v-for="item in navItems"
                    :key="item.route"
                    :prepend-icon="item.icon"
                    :title="item.title"
                    :value="item.route"
                    :active="route().current(item.route + '*')"
                    active-color="primary"
                    rounded="lg"
                    @click="router.visit(route(item.route))"
                />
            </VList>
        </VNavigationDrawer>

        <VAppBar elevation="1">
            <VAppBarNavIcon
                v-if="$vuetify.display.smAndDown"
                @click="drawer = !drawer"
            />
            <VAppBarTitle>
                <span class="text-sm text-medium-emphasis">{{ page.props.pageTitle ?? '' }}</span>
            </VAppBarTitle>
            <template #append>
                <VChip size="small" color="primary" variant="tonal" class="mr-3">Partner</VChip>
                <VMenu min-width="180">
                    <template #activator="{ props }">
                        <VBtn v-bind="props" variant="text" icon>
                            <VAvatar size="32" color="primary">
                                <span class="text-xs font-semibold text-white">
                                    {{ user?.name?.[0]?.toUpperCase() ?? 'P' }}
                                </span>
                            </VAvatar>
                        </VBtn>
                    </template>
                    <VList density="compact">
                        <VListItem :subtitle="user?.email ?? ''">
                            <template #title>
                                <span class="font-medium">{{ user?.name ?? '' }}</span>
                            </template>
                        </VListItem>
                        <VDivider />
                        <VListItem prepend-icon="mdi-logout" title="Log out" @click="logout" />
                    </VList>
                </VMenu>
            </template>
        </VAppBar>

        <VMain>
            <slot />
        </VMain>

        <VSnackbar
            v-model="snackbar"
            :color="snackbarColor"
            location="bottom right"
            :timeout="6000"
        >
            {{ snackbarText }}
            <template #actions>
                <VBtn variant="text" @click="snackbar = false">Dismiss</VBtn>
            </template>
        </VSnackbar>
    </VApp>
</template>
