<!--
    The dashboard's "Wanted while sold out" widget: sold-out products and
    variants with the most people waiting.
-->
<script>
import { Widget } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Widget },

    props: {
        title: { type: String, required: true },
        rows: { type: Array, required: true },
        url: { type: String, required: true },
    },

    computed: {
        icon: () => icon,
    },
};
</script>

<template>
    <Widget :title="title" :icon="icon" :href="url">
        <div class="px-4 py-3 text-sm">
            <p v-if="!rows.length" class="text-gray-500">{{ __('Nobody’s waiting for anything sold out.') }}</p>
            <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800!">
                <li v-for="row in rows" :key="row.id" class="flex items-baseline justify-between gap-3 py-1.5">
                    <a :href="row.url" class="min-w-0 truncate font-medium hover:underline!">{{ row.name }}</a>
                    <span class="shrink-0 tabular-nums text-gray-600 dark:text-gray-400!">{{ __(row.waiting === 1 ? '1 waiting' : ':count waiting', { count: row.waiting }) }}</span>
                </li>
            </ul>
        </div>
    </Widget>
</template>
