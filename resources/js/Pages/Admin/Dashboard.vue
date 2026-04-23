<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineOptions({ layout: AdminLayout });

defineProps<{
    stats: {
        policies_this_month: number;
        commission_calculated: string;
        pending_payouts: number;
        active_partners: number;
    };
}>();
</script>

<template>
    <VContainer fluid class="pa-6">
        <PageHeader title="Dashboard" />

        <!-- KPI cards -->
        <VRow class="mb-6">
            <VCol cols="12" sm="6" lg="3">
                <VCard>
                    <VCardText>
                        <div class="text-caption text-medium-emphasis mb-1">Policies this month</div>
                        <div class="text-h5 font-mono font-semibold">{{ stats.policies_this_month }}</div>
                    </VCardText>
                    <VCardActions class="pt-0">
                        <VBtn variant="text" size="small" :href="route('admin.policies.index')">View all</VBtn>
                    </VCardActions>
                </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
                <VCard>
                    <VCardText>
                        <div class="text-caption text-medium-emphasis mb-1">Commission calculated</div>
                        <div class="text-h5 font-mono font-semibold">{{ stats.commission_calculated }}</div>
                    </VCardText>
                    <VCardActions class="pt-0">
                        <VBtn variant="text" size="small" :href="route('admin.payouts.index')">View payouts</VBtn>
                    </VCardActions>
                </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
                <VCard>
                    <VCardText>
                        <div class="text-caption text-medium-emphasis mb-1">Pending payouts</div>
                        <div class="text-h5 font-mono font-semibold">{{ stats.pending_payouts }}</div>
                    </VCardText>
                    <VCardActions class="pt-0">
                        <VBtn variant="text" size="small" :href="route('admin.payouts.index', { status: 'calculated' })">
                            Process now
                        </VBtn>
                    </VCardActions>
                </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
                <VCard>
                    <VCardText>
                        <div class="text-caption text-medium-emphasis mb-1">Active partners</div>
                        <div class="text-h5 font-mono font-semibold">{{ stats.active_partners }}</div>
                    </VCardText>
                    <VCardActions class="pt-0">
                        <VBtn variant="text" size="small" :href="route('admin.partners.index')">View all</VBtn>
                    </VCardActions>
                </VCard>
            </VCol>
        </VRow>

        <!-- Placeholder for activity feed — wired up in M3 -->
        <VRow>
            <VCol cols="12">
                <VCard>
                    <VCardTitle class="text-subtitle-1 pa-4 pb-2">Recent activity</VCardTitle>
                    <VCardText>
                        <VAlert type="info" variant="tonal" density="compact">
                            Activity feed will be available once policies and payouts are created.
                        </VAlert>
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>
    </VContainer>
</template>
