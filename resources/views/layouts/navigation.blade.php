<div x-data="{
        open: false,
        previousFocus: null,
        openMenu() {
            this.previousFocus = document.activeElement;
            this.open = true;
            this.$nextTick(() => this.$refs.mobileClose.focus());
        },
        closeMenu() {
            if (! this.open) return;
            this.open = false;
            this.$nextTick(() => this.previousFocus?.focus());
        },
        trapFocus(event) {
            if (! this.open || window.matchMedia('(min-width: 1024px)').matches) return;
            const focusable = [...this.$refs.sidebar.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')];
            if (! focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            if (! event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    }"
    @keydown.escape.window="closeMenu()"
    @keydown.tab.window="trapFocus($event)"
    x-effect="document.body.classList.toggle('overflow-hidden', open)">
    <!-- Mobile top bar -->
    <div class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex min-h-11 items-center" aria-label="{{ config('app.name') }} dashboard">
            <x-application-logo class="block h-8 w-auto fill-current text-gray-800" />
        </a>
        <button type="button" @click="openMenu()" :aria-expanded="open.toString()" aria-controls="authenticated-navigation" aria-label="Open navigation" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Mobile overlay -->
    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden" @click="closeMenu()" aria-hidden="true"></div>

    <!-- Sidebar: off-canvas on mobile, fixed on desktop -->
    <aside
        id="authenticated-navigation"
        x-ref="sidebar"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
        :inert="! open && ! window.matchMedia('(min-width: 1024px)').matches"
        :aria-hidden="(! open && ! window.matchMedia('(min-width: 1024px)').matches).toString()"
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-100 px-4">
            <a href="{{ route('dashboard') }}" class="flex min-h-11 items-center" aria-label="{{ config('app.name') }} dashboard">
                <x-application-logo class="block h-8 w-auto fill-current text-gray-800" />
            </a>
            <button x-ref="mobileClose" type="button" @click="closeMenu()" class="grid h-11 w-11 place-items-center rounded-md text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 lg:hidden" aria-label="Close navigation">
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        @php
            $dashboardRoute = Auth::user()->hasPermission('audit.view') ? route('admin.dashboard.index') : route('dashboard');
        @endphp
        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Account navigation">
            <div class="space-y-1">
                <x-responsive-nav-link :href="$dashboardRoute" :active="request()->routeIs(['dashboard', 'admin.dashboard.*'])">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
            </div>

            @if (Auth::user()->account_type === \App\Enums\AccountType::Provider)
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-600">{{ __('Provider') }}</p>
                    <div class="mt-2 space-y-1">
                        <x-responsive-nav-link :href="route('onboarding.index')" :active="request()->routeIs('onboarding.*')">
                            {{ __('Provider onboarding') }}
                        </x-responsive-nav-link>
                    </div>
                </div>
            @endif

            @canany(['seo.locations', 'seo.content', 'seo.redirects', 'seo.search-insights', 'seo.metadata'])
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-600">{{ __('SEO') }}</p>
                    <div class="mt-2 space-y-1">
                        @can('seo.content')
                            <x-responsive-nav-link :href="route('seo.site-presentation.edit')" :active="request()->routeIs('seo.site-presentation.*')">
                                {{ __('Profile Meta & Menu') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('seo.pages.homepage.edit')" :active="request()->routeIs('seo.pages.homepage.*')">
                                {{ __('Homepage Content') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('seo.locations')
                            <x-responsive-nav-link :href="route('seo.locations.index')" :active="request()->routeIs('seo.locations.*')">
                                {{ __('Locations') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('seo.content')
                            <x-responsive-nav-link :href="route('seo.taxonomies.index')" :active="request()->routeIs('seo.taxonomies.*')">
                                {{ __('Taxonomy options') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('seo.pages.agencies.edit')" :active="request()->routeIs('seo.pages.agencies.*')">
                                {{ __('Agency Page Content') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('seo.redirects')
                            <x-responsive-nav-link :href="route('seo.redirects.index')" :active="request()->routeIs('seo.redirects.*')">
                                {{ __('Redirections') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('seo.search-insights')
                            <x-responsive-nav-link :href="route('seo.search-insights.index')" :active="request()->routeIs('seo.search-insights.*')">
                                {{ __('Search & Conversions') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('seo.metadata')
                            <x-responsive-nav-link :href="route('seo.audit.index')" :active="request()->routeIs('seo.audit.*')">
                                {{ __('SEO Audit') }}
                            </x-responsive-nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany

            @canany(['profiles.view-private', 'profiles.create', 'profiles.activate', 'moderation.view', 'verification.view', 'settings.manage', 'reviews.view'])
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-600">{{ __('Listings') }}</p>
                    <div class="mt-2 space-y-1">
                        @can('profiles.view-private')
                            <x-responsive-nav-link :href="route('staff.directory.index')" :active="request()->routeIs('staff.directory.index')">
                                {{ __('Manage Listings') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('profiles.create')
                            <x-responsive-nav-link :href="route('staff.directory.create')" :active="request()->routeIs('staff.directory.create')">
                                {{ __('Add Listing') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('reviews.view')
                            <x-responsive-nav-link :href="route('staff.reviews.index')" :active="request()->routeIs('staff.reviews.*')">
                                {{ __('Reviews') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('profiles.activate')
                            <x-responsive-nav-link :href="route('staff.profiles.index')" :active="request()->routeIs('staff.profiles.*')">
                                {{ __('Profile reviews') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('moderation.view')
                            <x-responsive-nav-link :href="route('staff.moderation.index')" :active="request()->routeIs('staff.moderation.*')">
                                {{ __('Moderation') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('verification.view')
                            <x-responsive-nav-link :href="route('staff.verification.index')" :active="request()->routeIs('staff.verification.*')">
                                {{ __('Verification') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('settings.manage')
                            <x-responsive-nav-link :href="route('admin.settings.packages.index')" :active="request()->routeIs('admin.settings.packages.*')">
                                {{ __('Packages') }}
                            </x-responsive-nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany

            @can('roles.manage')
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-600">{{ __('Users') }}</p>
                    <div class="mt-2 space-y-1">
                        <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')">
                            {{ __('All Users') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.users.create')" :active="request()->routeIs('admin.users.create')">
                            {{ __('Add User') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')">
                            {{ __('Roles') }}
                        </x-responsive-nav-link>
                    </div>
                </div>
            @endcan

            @canany(['settings.manage', 'system.health', 'policies.manage'])
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-600">{{ __('Settings') }}</p>
                    <div class="mt-2 space-y-1">
                        @can('system.health')
                            <x-responsive-nav-link :href="route('admin.system-health')" :active="request()->routeIs('admin.system-health')">
                                {{ __('System health') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('settings.manage')
                            <x-responsive-nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.index')">
                                {{ __('Directory operation') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('admin.settings.mail.edit')" :active="request()->routeIs('admin.settings.mail.*')">
                                {{ __('Mail delivery') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('admin.settings.updates.index')" :active="request()->routeIs('admin.settings.updates.*')">
                                {{ __('Updates') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('policies.manage')
                            <x-responsive-nav-link :href="route('admin.policies.index')" :active="request()->routeIs('admin.policies.*')">
                                {{ __('Policies') }}
                            </x-responsive-nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany
        </nav>

        <div class="border-t border-gray-100 p-3">
            <div class="flex items-center gap-3 rounded-md px-2 py-2">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-800">{{ Auth::user()->name }}</p>
                    <p class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</p>
                </div>
            </div>
            <div class="mt-1 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800 focus:outline-none">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </aside>
</div>
