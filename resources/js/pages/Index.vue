<!--
    Store → Back in Stock: what people are waiting for, most wanted first,
    with a search on product, and the last 30 days' emails and sales.
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, Header, Input, Panel, Select, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import Stock from '../components/Stock.vue';
import icon from '../icon.js';

export default {
    components: { Button, Head, Header, Input, Link, Panel, Select, Stock, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    props: {
        search: { type: String, default: null },
        show: { type: String, default: 'waiting' },
        items: { type: Array, required: true },
        pagination: { type: Object, required: true },
        stats: { type: Object, required: true },
        canExport: { type: Boolean, default: false },
        urls: { type: Object, required: true },
    },

    data() {
        return { query: this.search ?? '', filter: this.show, email: '', timer: null };
    },

    computed: {
        icon: () => icon,

        showOptions() {
            return [
                { value: 'waiting', label: __('People waiting') },
                { value: 'all', label: __('Everything asked about') },
            ];
        },

        filtered() {
            return Boolean(this.search) || this.show === 'all';
        },
    },

    watch: {
        query() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.reload(), 300);
        },

        filter() {
            this.reload();
        },
    },

    methods: {
        params(page = 1) {
            const params = {};
            if (this.query) params.search = this.query;
            if (this.filter === 'all') params.show = 'all';
            if (page > 1) params.page = page;
            return params;
        },

        reload() {
            router.get(this.urls.index, this.params(), { preserveState: true, replace: true });
        },

        pageUrl(page) {
            const query = new URLSearchParams(this.params(page)).toString();
            return this.urls.index + (query ? `?${query}` : '');
        },

        findPerson() {
            if (this.email) {
                router.get(this.urls.person, { email: this.email });
            }
        },
    },
};
</script>

<template>
    <Head :title="__('Back in Stock')" />

    <div class="mx-auto max-w-6xl">
        <Header :title="__('Back in Stock')" :icon="icon">
            <Button v-if="urls.settings" :href="urls.settings" :text="__('Settings')" icon="cog" />
            <Button v-if="canExport" :href="urls.export" :text="__('Download CSV')" icon="download" />
        </Header>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <Panel class="mb-0!">
                <div class="px-4 py-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Waiting') }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.waiting }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __(stats.products === 1 ? 'for 1 product' : 'for :count products', { count: stats.products }) }}</div>
                </div>
            </Panel>
            <Panel class="mb-0!">
                <div class="px-4 py-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Emailed') }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.notified }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('in the last 30 days') }}</div>
                </div>
            </Panel>
            <Panel class="mb-0!">
                <div class="px-4 py-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Bought after the email') }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.converted }}<span v-if="stats.rate" class="ml-2 text-base font-normal text-gray-600 dark:text-gray-400!">{{ stats.rate }}</span></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('in the last 30 days') }}</div>
                </div>
            </Panel>
        </div>

        <div class="mb-3 flex flex-col gap-3 sm:flex-row">
            <div class="grow">
                <Input v-model="query" icon="magnifying-glass" :placeholder="__('Search by product')" clearable :aria-label="__('Search')" />
            </div>
            <div class="sm:w-60">
                <Select v-model="filter" :options="showOptions" option-label="label" option-value="value" :aria-label="__('Show')" />
            </div>
        </div>

        <Panel>
            <div v-if="items.length" class="overflow-x-auto">
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('Product') }}</TableColumn>
                        <TableColumn>{{ __('Stock') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Waiting') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Unconfirmed') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Emailed') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Bought') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="item in items" :key="item.id">
                            <TableCell>
                                <Link :href="item.url" class="font-medium hover:underline!">{{ item.title }}</Link>
                                <div v-if="item.variant" class="text-sm text-gray-600 dark:text-gray-400!">{{ item.variant }}</div>
                            </TableCell>
                            <TableCell>
                                <Stock :item="item" />
                                <div v-if="item.pending" class="mt-1 text-xs text-gray-600 dark:text-gray-400!">{{ __('Emails going out') }}</div>
                            </TableCell>
                            <TableCell class="text-right text-lg font-semibold tabular-nums">{{ item.waiting }}</TableCell>
                            <TableCell class="text-right tabular-nums text-gray-600 dark:text-gray-400!">{{ item.unconfirmed }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ item.notified }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ item.converted }}</TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </div>

            <div v-else class="px-6 py-10 text-center">
                <p v-if="filtered" class="text-gray-600 dark:text-gray-400!">{{ __('Nothing matches.') }}</p>
                <template v-else>
                    <p class="font-medium">{{ __('Nobody’s waiting for anything') }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400!">{{ __('When shoppers ask to hear about a sold-out product, it shows here with how many are waiting.') }}</p>
                </template>
            </div>
        </Panel>

        <nav v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between text-sm" :aria-label="__('Pages')">
            <Link v-if="pagination.page > 1" :href="pageUrl(pagination.page - 1)" class="hover:underline!">← {{ __('Previous') }}</Link>
            <span v-else />
            <span class="text-gray-600 dark:text-gray-400!">{{ __(':page of :last', { page: pagination.page, last: pagination.last_page }) }}</span>
            <Link v-if="pagination.page < pagination.last_page" :href="pageUrl(pagination.page + 1)" class="hover:underline!">{{ __('Next') }} →</Link>
            <span v-else />
        </nav>

        <form class="mt-8 flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="findPerson">
            <label for="bis-person" class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Someone asking what you keep about them?') }}</label>
            <div class="sm:w-72"><Input id="bis-person" v-model="email" type="email" :placeholder="__('Their email address')" /></div>
            <Button type="submit" :text="__('Find')" size="sm" />
        </form>
    </div>
</template>
