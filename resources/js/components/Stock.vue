<!--
    A product's stock right now: a number, "Sold out", or "Not counted".
-->
<script>
import { Badge } from '@statamic/cms/ui';

export default {
    components: { Badge },

    props: {
        item: { type: Object, required: true },
    },
};
</script>

<template>
    <Badge v-if="item.missing" :text="__('Deleted')" color="gray" size="sm" />
    <Badge v-else-if="!item.published" :text="__('Not published')" color="gray" size="sm" />
    <Badge v-else-if="item.sold_out" :text="__('Sold out')" color="red" size="sm" />
    <Badge v-else-if="!item.tracked" :text="__('In stock')" color="green" size="sm" />
    <Badge v-else :text="__(':count in stock', { count: item.stock })" color="green" size="sm" />
</template>
