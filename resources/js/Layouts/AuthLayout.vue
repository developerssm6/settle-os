<script setup lang="ts">
</script>

<template>
    <VApp>
        <VMain class="bg-background">
            <VContainer class="fill-height" fluid>
                <VRow justify="center" align="center" class="fill-height">
                    <VCol cols="12" sm="8" md="5" lg="4">
                        <!-- Brand mark -->
                        <div class="text-center mb-8">
                            <VIcon icon="mdi-shield-check" color="primary" size="48" />
                            <div class="text-h6 font-semibold mt-2">SettleOS</div>
                        </div>

                        <slot />
                    </VCol>
                </VRow>
            </VContainer>
        </VMain>
    </VApp>
</template>
