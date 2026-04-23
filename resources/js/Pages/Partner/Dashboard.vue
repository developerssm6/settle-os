<script setup lang="ts">
import PartnerLayout from '@/Layouts/PartnerLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineOptions({ layout: PartnerLayout });

defineProps<{
    stats: {
        policies_this_month: number;
        commission_this_month: string;
        unpaid_commission: string;
        tds_ytd: string;
    };
}>();
</script>

<template>
    <VContainer fluid class="pa-6">
        <PageHeader title="Dashboard" />

        <VRow class="mb-6">
            <VCol cols="12" sm="6" lg="3">
                <VCard>
                    <VCardText>
                        <div class="text-caption text-medium-emphasis mb-1">Policies this month</div>
                        <div class="text-h5 font-mono font-semibold">{{ stats.policies_this_month }}</div>
                    </VCardText>
                    <VCardActions class="pt-0">
                        <VBtn variant="text" size="small" :href="route('partner.policies.index')">View all</VBtn>
                    </VCardActions>
                </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
                <VCard>
                    <VCardText>
                        <div class="text-caption text-medium-emphasis mb-1">Commission this month</div>
                        <div class="text-h5 font-mono font-semibold">{{ stats.commission_this_month }}</div>
                    </VCardText>
                </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
                <VCard>
                    <VCardText>
                        <div class="text-caption text-medium-emphasis mb-1">Unpaid to me</div>
                        <div class="text-h5 font-mono font-semibold text-warning">{{ stats.unpaid_commission }}</div>
                    </VCardText>
                    <VCardActions class="pt-0">
                        <VBtn variant="text" size="small" :href="route('partner.payouts.index')">View payouts</VBtn>
                    </VCardActions>
                </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
                <VCard>
                    <VCardText>
                        <div class="text-caption text-medium-emphasis mb-1">TDS (YTD)</div>
                        <div class="text-h5 font-mono font-semibold">{{ stats.tds_ytd }}</div>
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>
    </VContainer>
</template>
