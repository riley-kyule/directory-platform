<div x-data="{ open: false }">
    <!-- Mobile top bar -->
    <div class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <x-application-logo class="block h-8 w-auto fill-current text-gray-800" />
        </a>
        <button @click="open = true" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Mobile overlay -->
    <div x-show="open" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden" @click="open = false" style="display: none;"></div>

    <!-- Sidebar: off-canvas on mobile, fixed on desktop -->
    <aside
        :class="open ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-100 px-4">
            <a href="{{ route('dashboard') }}" class="flex items-center">
                <x-application-logo class="block h-8 w-auto fill-current text-gray-800" />
            </a>
            <button @click="open = false" class="p-1 text-gray-400 hover:text-gray-600 lg:hidden">
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            <div class="space-y-1">
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
            </div>

            @if (Auth::user()->account_type === \App\Enums\AccountType::Provider)
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Provider') }}</p>
                    <div class="mt-2 space-y-1">
                        <x-responsive-nav-link :href="route('onboarding.index')" :active="request()->routeIs('onboarding.*')">
                            {{ __('Provider onboarding') }}
                        </x-responsive-nav-link>
                    </div>
                </div>
            @endif

            @canany(['profiles.activate', 'moderation.view', 'verification.view', 'profiles.view-private'])
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Staff tools') }}</p>
                    <div class="mt-2 space-y-1">
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
                        @can('profiles.view-private')
                            <x-responsive-nav-link :href="route('staff.directory.index')" :active="request()->routeIs('staff.directory.*')">
                                {{ __('Manage listings') }}
                            </x-responsive-nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany

            @canany(['seo.locations', 'seo.redirects', 'seo.search-insights', 'seo.metadata'])
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('SEO') }}</p>
                    <div class="mt-2 space-y-1">
                        @can('seo.locations')
                            <x-responsive-nav-link :href="route('seo.directory.index')" :active="request()->routeIs('seo.directory.*')">
                                {{ __('Pages & Locations') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('seo.redirects')
                            <x-responsive-nav-link :href="route('seo.redirects.index')" :active="request()->routeIs('seo.redirects.*')">
                                {{ __('Redirects & Slugs') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('seo.search-insights')
                            <x-responsive-nav-link :href="route('seo.search-insights.index')" :active="request()->routeIs('seo.search-insights.*')">
                                {{ __('Search Insights') }}
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

            @canany(['settings.manage', 'system.health', 'policies.manage', 'roles.manage'])
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Administration') }}</p>
                    <div class="mt-2 space-y-1">
                        @can('roles.manage')
                            <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                                {{ __('Users & Roles') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('settings.manage')
                            <x-responsive-nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')">
                                {{ __('Admin settings') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('policies.manage')
                            <x-responsive-nav-link :href="route('admin.policies.index')" :active="request()->routeIs('admin.policies.*')">
                                {{ __('Policies') }}
                            </x-responsive-nav-link>
                        @endcan
                        @can('system.health')
                            <x-responsive-nav-link :href="route('admin.system-health')" :active="request()->routeIs('admin.system-health')">
                                {{ __('System health') }}
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
                    <x-responsive-nav-link href="{{ route('logout') }}"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </aside>
</div>
