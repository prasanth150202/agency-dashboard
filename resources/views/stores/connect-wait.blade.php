<x-app-layout title="Connecting your store">
    <div
        x-data="storeConnect({
            statusUrl: '{{ route('stores.connect.status', ['token' => $token]) }}',
            installUrl: '{{ route('stores.connect.install', ['token' => $token]) }}',
            storesUrl: '{{ route('stores.index') }}',
        })"
        x-init="start()"
        class="mx-auto mt-10 max-w-lg rounded-2xl border border-ink-200/70 bg-white p-8 shadow-subtle"
    >
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full" :class="iconWrapClass">
                <x-lucide-loader-2 x-show="isWorking" class="h-6 w-6 animate-spin text-brix-600" />
                <x-lucide-check-circle-2 x-show="stage === 'COMPLETE'" x-cloak class="h-6 w-6 text-emerald-600" />
                <x-lucide-alert-triangle x-show="isError" x-cloak class="h-6 w-6 text-rose-600" />
            </div>

            <h2 class="mt-4 text-lg font-semibold text-ink-900" x-text="title"></h2>
            <p class="mt-1 text-sm text-ink-500" x-text="subtitle"></p>
        </div>

        <div class="mt-6 space-y-3 border-t border-ink-100 pt-6 text-left">
            <template x-for="(step, i) in steps" :key="i">
                <div class="flex items-center gap-2.5 text-sm" :class="step.state === 'done' ? 'text-emerald-600' : (step.state === 'active' ? 'font-semibold text-ink-900' : 'text-ink-400')">
                    <span class="flex h-4 w-4 shrink-0 items-center justify-center">
                        <x-lucide-check x-show="step.state === 'done'" class="h-4 w-4" />
                        <span x-show="step.state === 'active'" class="h-2 w-2 rounded-full bg-brix-600"></span>
                        <span x-show="!step.state" class="h-1.5 w-1.5 rounded-full border border-ink-300"></span>
                    </span>
                    <span x-text="step.label"></span>
                </div>
            </template>
        </div>

        <!-- Step: Installation required -->
        <div x-show="stage === 'INSTALL_REQUIRED' || stage === 'INSTALLING'" x-cloak class="mt-6 border-t border-ink-100 pt-6 text-center">
            <a
                :href="installUrl"
                x-on:click.prevent="openInstallWindow()"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
            >
                <x-lucide-external-link class="h-4 w-4" />
                <span>Install BRIX on Shopify</span>
            </a>
            <p class="mt-3 text-xs text-ink-400">
                This opens the official Shopify App Store listing in another tab.
                Once the merchant installs BRIX, this page updates on its own.
            </p>
        </div>

        <!-- Step: Agency authorization -->
        <div x-show="stage === 'AUTHORIZATION_REQUIRED'" x-cloak class="mt-6 space-y-4 border-t border-ink-100 pt-6">
            <h3 class="text-sm font-semibold text-ink-900">Connect Store to Agency</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-ink-500">Store</dt>
                    <dd class="font-medium text-ink-900" x-text="shopDomain"></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-ink-500">Shopify</dt>
                    <dd class="inline-flex items-center gap-1 font-medium text-emerald-600">
                        <x-lucide-check-circle-2 class="h-3.5 w-3.5" /> Connected
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-ink-500">Agency</dt>
                    <dd class="font-medium text-ink-900" x-text="agencyName"></dd>
                </div>
            </dl>
            <p class="text-sm text-ink-500">Allow this store to be managed from your agency dashboard.</p>
            <button
                type="button"
                x-on:click="authorizeStore()"
                x-bind:disabled="processing"
                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <x-lucide-loader-2 x-show="processing" class="h-4 w-4 animate-spin" />
                <span x-text="processing ? 'Authorizing…' : 'Authorize Store'"></span>
            </button>
        </div>

        <!-- Step: Activation -->
        <div x-show="stage === 'ACTIVATION_REQUIRED'" x-cloak class="mt-6 space-y-4 border-t border-ink-100 pt-6">
            <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm text-emerald-600"><x-lucide-check class="h-4 w-4" /> Shopify Installed</div>
                <div class="flex items-center gap-2 text-sm text-emerald-600"><x-lucide-check class="h-4 w-4" /> Shopify Authorized</div>
                <div class="flex items-center gap-2 text-sm text-emerald-600"><x-lucide-check class="h-4 w-4" /> Agency Authorized</div>
            </div>
            <button
                type="button"
                x-on:click="activateStore()"
                x-bind:disabled="processing"
                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <x-lucide-loader-2 x-show="processing" class="h-4 w-4 animate-spin" />
                <span x-text="processing ? 'Activating…' : 'Activate Store'"></span>
            </button>
        </div>

        <!-- Step: Complete -->
        <div x-show="stage === 'COMPLETE'" x-cloak class="mt-6 border-t border-ink-100 pt-6 text-center">
            <a
                :href="redirectUrl"
                class="inline-flex items-center justify-center rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
            >
                Go to Store
            </a>
        </div>

        <!-- Step: Error / expired -->
        <div x-show="isError" x-cloak class="mt-6 border-t border-ink-100 pt-6 text-center">
            <a
                :href="storesUrl"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-4 py-2.5 text-sm font-medium text-ink-700 hover:bg-ink-50"
            >
                <x-lucide-refresh-cw class="h-3.5 w-3.5" />
                Back to Stores
            </a>
        </div>

        <p x-show="errorMessage" x-cloak class="mt-3 text-center text-xs text-rose-600" x-text="errorMessage"></p>
    </div>

    @push('scripts')
    <script>
        function storeConnect({ statusUrl, installUrl, storesUrl }) {
            return {
                stage: 'CHECKING',
                title: 'Checking your BRIX connection…',
                subtitle: 'This only takes a moment.',
                shopDomain: '',
                agencyName: '',
                redirectUrl: storesUrl,
                installUrl,
                storesUrl,
                authorizeUrl: null,
                activateUrl: null,
                processing: false,
                errorMessage: '',
                timer: null,
                steps: [
                    { label: 'Store details', state: 'done' },
                    { label: 'Installation', state: 'active' },
                    { label: 'Authorization', state: '' },
                    { label: 'Activation', state: '' },
                ],
                get isWorking() {
                    return !this.isError && this.stage !== 'COMPLETE';
                },
                get isError() {
                    return ['FAILED', 'EXPIRED', 'UNINSTALLED'].includes(this.stage);
                },
                get iconWrapClass() {
                    if (this.stage === 'COMPLETE') return 'bg-emerald-50';
                    if (this.isError) return 'bg-rose-50';
                    return 'bg-brix-50';
                },
                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]').content;
                },
                // Must run synchronously inside a real click handler (the
                // "Install BRIX on Shopify" button below) — browsers block
                // window.open() calls that aren't a direct result of user
                // interaction, so this is never called from start()/x-init.
                openInstallWindow() {
                    window.open(this.installUrl, 'brix-shopify-connect');
                },
                start() {
                    this.poll();
                    this.timer = setInterval(() => this.poll(), 2500);
                },
                poll() {
                    fetch(statusUrl, { headers: { Accept: 'application/json' } })
                        .then((res) => res.json())
                        .then((body) => {
                            if (!body.success) {
                                clearInterval(this.timer);
                                this.stage = 'EXPIRED';
                                this.title = 'Your session has expired';
                                this.errorMessage = body.error || 'Please try again.';
                                return;
                            }
                            this.render(body.data);
                        })
                        .catch(() => { /* transient network error — next poll tick retries */ });
                },
                render(data) {
                    this.stage = data.stage;
                    this.shopDomain = data.shop_domain || this.shopDomain;
                    this.agencyName = data.agency_name || this.agencyName;
                    this.authorizeUrl = data.authorize_url || this.authorizeUrl;
                    this.activateUrl = data.activate_url || this.activateUrl;
                    if (data.redirect) this.redirectUrl = data.redirect;

                    const titles = {
                        INSTALL_REQUIRED: 'BRIX needs to be installed on this store',
                        INSTALLING: 'Installing BRIX…',
                        AUTHORIZATION_REQUIRED: 'Almost there',
                        ACTIVATION_REQUIRED: 'Ready to activate',
                        COMPLETE: 'Store Connected',
                        FAILED: "We couldn't connect this store",
                        UNINSTALLED: 'BRIX is no longer installed on this store',
                    };
                    this.title = titles[data.stage] || this.title;
                    this.subtitle = data.message || '';

                    const stepStates = {
                        INSTALL_REQUIRED: ['done', 'active', '', ''],
                        INSTALLING: ['done', 'active', '', ''],
                        AUTHORIZATION_REQUIRED: ['done', 'done', 'active', ''],
                        ACTIVATION_REQUIRED: ['done', 'done', 'done', 'active'],
                        COMPLETE: ['done', 'done', 'done', 'done'],
                        FAILED: ['done', '', '', ''],
                        UNINSTALLED: ['done', '', '', ''],
                    };
                    const s = stepStates[data.stage] || stepStates.INSTALL_REQUIRED;
                    this.steps.forEach((step, i) => { step.state = s[i]; });

                    if (['COMPLETE', 'FAILED', 'UNINSTALLED'].includes(data.stage)) {
                        clearInterval(this.timer);
                    }
                },
                request(url) {
                    if (this.processing) return Promise.reject();
                    this.processing = true;
                    this.errorMessage = '';

                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken(),
                        },
                    })
                        .then((res) => res.json().then((body) => ({ ok: res.ok, body })))
                        .then(({ ok, body }) => {
                            if (!ok || !body.success) {
                                this.errorMessage = body.message || 'Something went wrong. Please try again.';
                                return null;
                            }
                            return body;
                        })
                        .catch(() => {
                            this.errorMessage = 'Something went wrong. Please try again.';
                            return null;
                        })
                        .finally(() => { this.processing = false; });
                },
                authorizeStore() {
                    if (!this.authorizeUrl) return;
                    this.request(this.authorizeUrl).then((body) => { if (body) this.poll(); });
                },
                activateStore() {
                    if (!this.activateUrl) return;
                    this.request(this.activateUrl).then((body) => {
                        if (body?.data?.redirect) {
                            clearInterval(this.timer);
                            window.location.href = body.data.redirect;
                        }
                    });
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
