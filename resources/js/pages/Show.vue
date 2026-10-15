<!--
    One product or variant: who's waiting (oldest first, as they'll be
    emailed), each restock and how many were emailed, and sending now.
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, Header, Input, Panel, Select, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import StatusBadge from '../components/StatusBadge.vue';
import Stock from '../components/Stock.vue';
import icon from '../icon.js';

export default {
    components: { Button, Head, Header, Input, Link, Panel, Select, StatusBadge, Stock, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    props: {
        item: { type: Object, required: true },
        inStock: { type: Boolean, default: false },
        search: { type: String, default: null },
        status: { type: String, default: null },
        statuses: { type: Array, required: true },
        alerts: { type: Array, required: true },
        pagination: { type: Object, required: true },
        restocks: { type: Array, required: true },
        limit: { type: String, default: 'all' },
        canManage: { type: Boolean, default: false },
        canExport: { type: Boolean, default: false },
        urls: { type: Object, required: true },
    },

    data() {
        return { query: this.search ?? '', filter: this.status ?? 'all', timer: null };
    },

    computed: {
        icon: () => icon,

        title() {
            return this.item.variant ? `${this.item.title} – ${this.item.variant}` : this.item.title;
        },

        statusOptions() {
            return [{ value: 'all', label: __('All statuses') }, ...this.statuses];
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
            if (this.filter && this.filter !== 'all') params.status = this.filter;
            if (page > 1) params.page = page;
            return params;
        },

        reload() {
            router.get(this.urls.self, this.params(), { preserveState: true, replace: true });
        },

        pageUrl(page) {
            const query = new URLSearchParams(this.params(page)).toString();
            return this.urls.self + (query ? `?${query}` : '');
        },

        sendNow() {
            if (confirm(__('Email all :count people waiting now?', { count: this.item.waiting }))) {
                router.post(this.urls.send, {}, { preserveScroll: true });
            }
        },

        cancel(alert) {
            if (confirm(__('Cancel :email’s request? They won’t be emailed.', { email: alert.email }))) {
                router.post(alert.cancel_url, {}, { preserveScroll: true });
            }
        },

        remove(alert) {
            if (confirm(__('Delete :email’s request? This can’t be undone.', { email: alert.email }))) {
                router.delete(alert.delete_url, { preserveScroll: true });
            }
        },

        personUrl(alert) {
            return `${this.urls.person}?email=${encodeURIComponent(alert.email)}`;
        },
    },
};
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto max-w-6xl">
        <div class="mb-2 text-sm"><Link :href="urls.index" class="text-gray-600 hover:underline! dark:text-gray-400!">← {{ __('Back in Stock') }}</Link></div>

        <Header :title="title" :icon="icon">
            <Button v-if="item.live_url" :href="item.live_url" target="_blank" :text="__('View')" icon="external-link" />
            <Button v-if="item.edit_url" :href="item.edit_url" :text="__('Edit product')" icon="pencil" />
            <Button v-if="canExport" :href="urls.export" :text="__('Download CSV')" icon="download" />
            <Button v-if="canManage && inStock && item.waiting" variant="primary" :text="__('Email them now')" icon="mail" @click="sendNow" />
        </Header>

        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <Panel class="mb-0!">
                <div class="px-4 py-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Stock') }}</div>
                    <div class="mt-2"><Stock :item="item" /></div>
                    <div v-if="item.pending" class="mt-1 text-xs text-gray-600 dark:text-gray-400!">{{ __('Emails going out') }}</div>
                </div>
            </Panel>
            <Panel class="mb-0!">
                <div class="px-4 py-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Waiting') }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ item.waiting }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __(':count unconfirmed', { count: item.unconfirmed }) }}</div>
                </div>
            </Panel>
            <Panel class="mb-0!">
                <div class="px-4 py-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Emailed') }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ item.notified }}</div>
                </div>
            </Panel>
            <Panel class="mb-0!">
                <div class="px-4 py-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Bought after the email') }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ item.converted }}</div>
                </div>
            </Panel>
        </div>

        <p v-if="limit === 'stock'" class="mb-4 text-sm text-gray-600 dark:text-gray-400!">
            {{ __('When stock comes back, as many people are emailed as units came back, oldest request first. “Email them now” emails everyone waiting.') }}
        </p>

        <div class="mb-3 flex flex-col gap-3 sm:flex-row">
            <div class="grow">
                <Input v-model="query" icon="magnifying-glass" :placeholder="__('Search by email')" clearable :aria-label="__('Search')" />
            </div>
            <div class="sm:w-56">
                <Select v-model="filter" :options="statusOptions" option-label="label" option-value="value" :aria-label="__('Status')" />
            </div>
        </div>

        <Panel class="mb-6">
            <div v-if="alerts.length" class="overflow-x-auto">
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('Email') }}</TableColumn>
                        <TableColumn>{{ __('Status') }}</TableColumn>
                        <TableColumn>{{ __('Asked') }}</TableColumn>
                        <TableColumn>{{ __('Emailed') }}</TableColumn>
                        <TableColumn v-if="canManage" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="alert in alerts" :key="alert.id">
                            <TableCell>
                                <Link :href="personUrl(alert)" class="hover:underline!">{{ alert.email }}</Link>
                                <div v-if="alert.site !== 'default'" class="text-xs text-gray-600 dark:text-gray-400!">{{ alert.site }}</div>
                            </TableCell>
                            <TableCell><StatusBadge :status="alert.status" :label="alert.status_label" /></TableCell>
                            <TableCell class="whitespace-nowrap text-gray-600 dark:text-gray-400!" :title="alert.asked">{{ alert.asked_ago }}</TableCell>
                            <TableCell class="whitespace-nowrap text-gray-600 dark:text-gray-400!">{{ alert.notified ?? '—' }}</TableCell>
                            <TableCell v-if="canManage" class="text-right whitespace-nowrap">
                                <Button v-if="alert.open" size="sm" variant="ghost" :text="__('Cancel')" @click="cancel(alert)" />
                                <Button size="sm" variant="ghost" icon="trash" :aria-label="__('Delete')" @click="remove(alert)" />
                            </TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </div>
            <p v-else class="px-6 py-10 text-center text-gray-600 dark:text-gray-400!">{{ __('No requests match.') }}</p>
        </Panel>

        <nav v-if="pagination.last_page > 1" class="mb-6 flex items-center justify-between text-sm" :aria-label="__('Pages')">
            <Link v-if="pagination.page > 1" :href="pageUrl(pagination.page - 1)" class="hover:underline!">← {{ __('Previous') }}</Link>
            <span v-else />
            <span class="text-gray-600 dark:text-gray-400!">{{ __(':page of :last', { page: pagination.page, last: pagination.last_page }) }}</span>
            <Link v-if="pagination.page < pagination.last_page" :href="pageUrl(pagination.page + 1)" class="hover:underline!">{{ __('Next') }} →</Link>
            <span v-else />
        </nav>

        <Panel :heading="__('Restocks')">
            <div v-if="restocks.length" class="overflow-x-auto">
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('When') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Stock before') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Stock after') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Emailed') }}</TableColumn>
                        <TableColumn>{{ __('Noticed') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="restock in restocks" :key="restock.id">
                            <TableCell class="whitespace-nowrap" :title="restock.at">{{ restock.ago }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ restock.before ?? '—' }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ restock.after ?? __('Not counted') }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ restock.sent }}</TableCell>
                            <TableCell class="text-gray-600 dark:text-gray-400!">{{ restock.trigger === 'save' ? __('When saved') : __('By the scheduled check') }}</TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </div>
            <p v-else class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400!">{{ __('It hasn’t come back in stock since anyone asked.') }}</p>
        </Panel>
    </div>
</template>
