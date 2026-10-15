<!--
    A request's status as a coloured badge.
-->
<script>
import { Badge } from '@statamic/cms/ui';

const colours = { unconfirmed: 'amber', waiting: 'blue', sending: 'blue', notified: 'green', converted: 'emerald', cancelled: 'gray' };

export default {
    components: { Badge },

    props: {
        status: { type: String, required: true },
        label: { type: String, required: true },
    },

    computed: {
        colour() {
            return colours[this.status] ?? 'default';
        },
    },
};
</script>

<template>
    <Badge :text="label" :color="colour" size="sm" />
</template>
