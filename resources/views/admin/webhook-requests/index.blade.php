<x-layout>
    <div
        class="max-w-7xl mx-auto py-8 px-4"
        x-data="webhookRequestsTable(@js($requests))"
    >
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Webhook Requests</h1>
        </div>

        @if (session('status'))
            <div class="alert alert-success mb-4">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <select x-model="statusFilter" class="select select-bordered select-sm">
                <option value="">All statuses</option>
                <template x-for="status in statusOptions" :key="status">
                    <option :value="status" x-text="status"></option>
                </template>
            </select>

            <select x-model="providerFilter" class="select select-bordered select-sm">
                <option value="">All providers</option>
                <template x-for="provider in providerOptions" :key="provider">
                    <option :value="provider" x-text="provider"></option>
                </template>
            </select>

            <span
                class="text-sm text-base-content/60"
                x-text="`${filteredRequests.length} of ${requests.length}`"
            ></span>
        </div>

        <div class="rounded-box border border-base-300 overflow-visible mb-12">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>
                            <button type="button" @click="sortBy('provider')" class="flex items-center gap-1 font-bold cursor-pointer">
                                Provider
                                <span x-text="sortIcon('provider')"></span>
                            </button>
                        </th>
                        <th>
                            <button type="button" @click="sortBy('requester_id')" class="flex items-center gap-1 font-bold cursor-pointer">
                                Requester
                                <span x-text="sortIcon('requester_id')"></span>
                            </button>
                        </th>
                        <th>
                            <button type="button" @click="sortBy('action')" class="flex items-center gap-1 font-bold cursor-pointer">
                                Action
                                <span x-text="sortIcon('action')"></span>
                            </button>
                        </th>
                        <th>
                            <button type="button" @click="sortBy('status')" class="flex items-center gap-1 font-bold cursor-pointer">
                                Status
                                <span x-text="sortIcon('status')"></span>
                            </button>
                        </th>
                        <th>
                            <button type="button" @click="sortBy('created_at')" class="flex items-center gap-1 font-bold cursor-pointer">
                                Created
                                <span x-text="sortIcon('created_at')"></span>
                            </button>
                        </th>
                        <th class="text-right">Payload</th>
                    </tr>
                </thead>

                {{-- One <tbody> per row so the expandable detail row can sit
                     right under its parent row while x-for still tracks a
                     single root element per iteration. Multiple <tbody>
                     elements as siblings in a <table> is valid HTML. --}}
                <template x-for="row in sortedRequests" :key="row.id">
                    <tbody>
                        <tr>
                            <td><span class="badge badge-outline" x-text="row.provider"></span></td>
                            <td>
                                <span x-text="row.requester_id"></span>
                                <span class="text-xs text-base-content/50" x-text="`(${row.requester_type})`"></span>
                            </td>
                            <td x-text="row.action"></td>
                            <td>
                                <span
                                    x-text="row.status"
                                    :class="row.status === 'success'
                                        ? 'badge badge-soft badge-success'
                                        : (row.status === 'error' ? 'badge badge-soft badge-error' : 'badge badge-ghost')"
                                ></span>
                            </td>
                            <td>
                                <span :title="row.created_at" x-text="timeAgo(row.created_at)"></span>
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm btn-ghost" @click="toggleExpanded(row.id)">
                                    <span x-text="expandedId === row.id ? 'Hide' : 'View'"></span>
                                </button>
                            </td>
                        </tr>
                        <tr x-show="expandedId === row.id" x-cloak>
                            <td colspan="6" class="bg-base-200">
                                <div class="grid grid-cols-2 gap-4 p-4">
                                    <div>
                                        <div class="text-xs font-semibold text-base-content/60 mb-1">Payload in</div>
                                        <pre class="text-xs overflow-x-auto bg-base-100 rounded p-3" x-text="formatPayload(row.payload_in)"></pre>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold text-base-content/60 mb-1">Payload out</div>
                                        <pre class="text-xs overflow-x-auto bg-base-100 rounded p-3" x-text="formatPayload(row.payload_out)"></pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </template>
            </table>

            <div class="text-center py-8 text-base-content/60" x-show="sortedRequests.length === 0" x-cloak>
                No webhook requests match this filter.
            </div>
        </div>
    </div>
</x-layout>
