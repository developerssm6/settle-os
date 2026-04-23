<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash as { success?: string; error?: string } | null);

const drawer = ref(true);
const rail = ref(false);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

watch(() => flash.value, (f: { success?: string; error?: string } | null) => {
    if (f?.success) { snackbarText.value = f.success; snackbarColor.value = 'success'; snackbar.value = true; }
    if (f?.error)   { snackbarText.value = f.error;   snackbarColor.value = 'error';   snackbar.value = true; }
}, { immediate: true });

const navItems = [
    { title: 'Dashboard',         icon: 'mdi-view-dashboard-outline',  route: 'admin.dashboard' },
    { title: 'Partners',          icon: 'mdi-account-group-outline',   route: 'admin.partners.index' },
    { title: 'Policies',          icon: 'mdi-file-document-outline',   route: 'admin.policies.index' },
    { title: 'Payouts',           icon: 'mdi-currency-inr',            route: 'admin.payouts.index' },
    { title: 'Commission Rates',  icon: 'mdi-percent-outline',         route: 'admin.commission-rates.index' },
    { title: 'Insurers',          icon: 'mdi-office-building-outline', route: 'admin.insurers.index' },
    { title: 'Taxonomy',          icon: 'mdi-tag-multiple-outline',    route: 'admin.taxonomy.index' },
    { title: 'Reports',           icon: 'mdi-chart-bar',               route: 'admin.reports.index' },
    { title: 'Settings',          icon: 'mdi-cog-outline',             route: 'admin.settings.index' },
];

const appEnv = import.meta.env.VITE_APP_ENV ?? 'local';
const envColor: Record<string, string> = { local: 'warning', production: 'error', staging: 'info' };

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <VApp>
        <!-- Navigation Drawer -->
        <VNavigationDrawer
            v-model="drawer"
            :rail="rail"
            width="240"
            :permanent="$vuetify.display.mdAndUp"
        >
            <VListItem
                prepend-icon="mdi-shield-check"
                title="SettleOS"
                nav
                class="py-4"
            >
                <template #append>
                    <VBtn
                        :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
                        variant="text"
                        density="compact"
                        @click="rail = !rail"
                    />
                </template>
            </VListItem>

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

        <!-- App Bar -->
        <VAppBar elevation="1">
            <VAppBarNavIcon
                v-if="$vuetify.display.smAndDown"
                @click="drawer = !drawer"
            />

            <VAppBarTitle>
                <span class="text-sm text-medium-emphasis">{{ page.props.pageTitle ?? '' }}</span>
            </VAppBarTitle>

            <template #append>
                <!-- Environment chip (hidden in production) -->
                <VChip
                    v-if="appEnv !== 'production'"
                    :color="envColor[appEnv] ?? 'warning'"
                    size="small"
                    class="mr-2"
                >
                    {{ appEnv }}
                </VChip>

                <!-- Role chip -->
                <VChip size="small" color="secondary" class="mr-3">
                    {{ user?.role ?? 'Admin' }}
                </VChip>

                <!-- User menu -->
                <VMenu min-width="180">
                    <template #activator="{ props }">
                        <VBtn v-bind="props" variant="text" icon>
                            <VAvatar size="32" color="primary">
                                <span class="text-xs font-semibold text-white">
                                    {{ user?.name?.[0]?.toUpperCase() ?? 'A' }}
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
                        <VListItem
                            prepend-icon="mdi-logout"
                            title="Log out"
                            @click="logout"
                        />
                    </VList>
                </VMenu>
            </template>
        </VAppBar>

        <!-- Main content -->
        <VMain>
            <slot />
        </VMain>

        <!-- Toast notifications -->
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
