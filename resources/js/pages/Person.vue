<!--
    Everything kept about one email address: for answering someone who asks
    what's held about them (download), or asks to be forgotten (delete).
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, Header, Input, Panel, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import StatusBadge from '../components/StatusBadge.vue';
import icon from '../icon.js';

export default {
    components: { Button, Head, Header, Input, Link, Panel, StatusBadge, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    props: {
        email: { type: String, default: null },
        alerts: { type: Array, required: true },
        canManage: { type: Boolean, default: false },
        canExport: { type: Boolean, default: false },
        urls: { type: Object, required: true },
    },

    data() {
        return { query: this.email ?? '' };
    },

    computed: {
        icon: () => icon,
    },

    methods: {
        find() {
            router.get(this.urls.self, this.query ? { email: this.query } : {});
        },

        forget() {
            if (confirm(__('Delete everything kept about :email? This can’t be undone.', { email: this.email }))) {
                router.post(this.urls.forget, { email: this.email });
            }
        },
    },
};
</script>

<template>
    <Head :title="__('Find someone')" />

    <div class="mx-auto max-w-5xl">
        <div class="mb-2 text-sm"><Link :href="urls.index" class="text-gray-600 hover:underline! dark:text-gray-400!">← {{ __('Back in Stock') }}</Link></div>

        <Header :title="email ?? __('Find someone')" :icon="icon">
            <Button v-if="email && alerts.length && canExport" :href="urls.download" :text="__('Download their data')" icon="download" />
            <Button v-if="email && alerts.length && canManage" variant="danger" :text="__('Delete everything')" @click="forget" />
        </Header>

        <form class="mb-6 flex flex-col gap-2 sm:flex-row" @submit.prevent="find">
            <div class="grow"><Input v-model="query" type="email" icon="magnifying-glass" :placeholder="__('Email address')" :aria-label="__('Email address')" /></div>
            <Button type="submit" :text="__('Find')" />
        </form>

        <Panel v-if="email">
            <div v-if="alerts.length" class="overflow-x-auto">
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('Product') }}</TableColumn>
                        <TableColumn>{{ __('Status') }}</TableColumn>
                        <TableColumn>{{ __('Asked') }}</TableColumn>
                        <TableColumn>{{ __('Confirmed') }}</TableColumn>
                        <TableColumn>{{ __('Emailed') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="alert in alerts" :key="alert.id">
                            <TableCell><Link :href="alert.item_url" class="hover:underline!">{{ alert.product }}</Link></TableCell>
                            <TableCell><StatusBadge :status="alert.status" :label="alert.status_label" /></TableCell>
                            <TableCell class="whitespace-nowrap text-gray-600 dark:text-gray-400!">{{ alert.asked }}</TableCell>
                            <TableCell class="whitespace-nowrap text-gray-600 dark:text-gray-400!">{{ alert.confirmed ?? '—' }}</TableCell>
                            <TableCell class="whitespace-nowrap text-gray-600 dark:text-gray-400!">{{ alert.notified ?? '—' }}</TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </div>
            <p v-else class="px-6 py-10 text-center text-gray-600 dark:text-gray-400!">{{ __('Nothing is kept about that address.') }}</p>
        </Panel>

        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400!">{{ __('Only requests are kept: the address, what was asked about, when, and the consent wording agreed to. Addresses that unsubscribe are kept as a one-way hash, so they aren’t emailed again.') }}</p>
    </div>
</template>
