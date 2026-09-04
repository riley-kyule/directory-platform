<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Search appearance</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">Profile Meta & Menu</h2>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10" x-data="{
        template: {{ Js::from(old('profile_meta_template', $profileMetaTemplate)) }},
        items: {{ Js::from(old('navigation_items', $navigationItems)) }},
        add() { this.items.push({ label: '', url: '/' }); this.$nextTick(() => document.querySelector(`[name='navigation_items[${this.items.length - 1}][label]']`)?.focus()) },
        move(i, by) { const next = i + by; if (next < 0 || next >= this.items.length) return; [this.items[i], this.items[next]] = [this.items[next], this.items[i]] },
        preview() {
            const values = { '{profile_title}': 'Ivy', '{gender}': 'Woman', '{locality}': 'Kasarani', '{city}': 'Nairobi', '{country}': 'Kenya', '{nationality}': 'Kenyan', '{availability}': 'in-calls and outcalls', '{services}': 'massage, dinner dates', '{pronoun}': 'She' };
            return Object.entries(values).reduce((text, [token, value]) => text.split(token).join(value), this.template);
        },
    }">
        <form method="POST" action="{{ route('seo.site-presentation.update') }}" class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">@csrf @method('PATCH')
            @if (session('status'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm" role="status">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-600 text-white">✓</span>{{ session('status') }}
                </div>
            @endif

            <section class="admin-card">
                <div class="admin-card-header bg-gradient-to-r from-indigo-50 via-white to-violet-50">
                    <div class="flex gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-200" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-5m-1-9h6m0 0v6m0-6L10 14" /></svg>
                        </span>
                        <div><h3 class="text-lg font-bold text-gray-900">Dynamic profile description</h3><p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">Write it once and profile details are inserted automatically. Used for the page meta description, social shares, structured data, and shown as the opening line of each profile's "About" section.</p></div>
                    </div>
                </div>
                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,.6fr)]">
                    <div>
                        <div class="flex items-end justify-between gap-3"><label for="profile_meta_template" class="text-sm font-semibold text-gray-800">Description template</label><span class="text-xs text-gray-400"><span x-text="template.length"></span> / 1,000</span></div>
                        <textarea id="profile_meta_template" name="profile_meta_template" x-model="template" rows="7" maxlength="1000" required class="admin-field mt-2 resize-y font-mono text-sm leading-6"></textarea>
                        <x-input-error :messages="$errors->get('profile_meta_template')" class="mt-2" />
                        <div class="mt-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Available profile fields</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (['profile_title', 'gender', 'locality', 'city', 'country', 'nationality', 'availability', 'services', 'pronoun'] as $token)
                                    <button type="button" @click="template += ' {' + '{{ $token }}' + '}'" class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 font-mono text-xs font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100">{{ '{'.$token.'}' }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <aside class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-400"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Live Google preview</div>
                        <p class="mt-5 truncate text-sm text-emerald-700">{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'your-directory.com' }} › escort › ivy</p>
                        <p class="mt-1 text-xl font-medium leading-7 text-[#1a0dab]">Ivy — Kasarani, Nairobi</p>
                        <p class="mt-1 break-words text-sm leading-6 text-gray-600" x-text="preview()"></p>
                    </aside>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-4"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gray-900 text-white" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg></span><div><h3 class="text-lg font-bold text-gray-900">Public navigation</h3><p class="mt-1 text-sm text-gray-600">The same ordered menu appears on desktop and mobile.</p></div></div>
                    <button type="button" @click="add()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700"><span class="text-lg leading-none">+</span> Add menu item</button>
                </div>
                <div class="p-5 sm:p-6">
                    <div class="hidden grid-cols-[44px_1fr_1.4fr_132px] gap-3 px-3 pb-2 text-xs font-bold uppercase tracking-wider text-gray-400 sm:grid"><span>Order</span><span>Label</span><span>Destination</span><span class="text-center">Actions</span></div>
                    <div class="space-y-3">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="grid items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50/70 p-3 transition hover:border-indigo-200 hover:bg-indigo-50/40 sm:grid-cols-[44px_1fr_1.4fr_132px]">
                                <span class="grid h-9 w-9 place-items-center rounded-xl bg-white text-sm font-bold text-gray-500 shadow-sm ring-1 ring-gray-200" x-text="index + 1"></span>
                                <label class="sm:contents"><span class="text-xs font-bold uppercase tracking-wider text-gray-400 sm:hidden">Label</span><input type="text" :name="`navigation_items[${index}][label]`" x-model="item.label" maxlength="40" placeholder="e.g. Locations" required class="admin-field"></label>
                                <label class="sm:contents"><span class="text-xs font-bold uppercase tracking-wider text-gray-400 sm:hidden">Destination</span><input type="text" :name="`navigation_items[${index}][url]`" x-model="item.url" maxlength="255" placeholder="/locations" required class="admin-field font-mono text-sm"></label>
                                <div class="flex justify-end gap-1.5"><button type="button" @click="move(index, -1)" :disabled="index === 0" class="admin-icon-button" aria-label="Move up">↑</button><button type="button" @click="move(index, 1)" :disabled="index === items.length - 1" class="admin-icon-button" aria-label="Move down">↓</button><button type="button" @click="items.splice(index, 1)" class="admin-icon-button hover:!border-red-200 hover:!bg-red-50 hover:!text-red-700" aria-label="Remove">×</button></div>
                            </div>
                        </template>
                    </div>
                    <div x-show="items.length === 0" x-cloak class="rounded-2xl border-2 border-dashed border-gray-200 px-6 py-12 text-center"><p class="font-semibold text-gray-700">Your menu is empty</p><button type="button" @click="add()" class="mt-2 text-sm font-bold text-indigo-600">Add the first item</button></div>
                    <x-input-error :messages="$errors->get('navigation_items')" class="mt-3" />
                </div>
            </section>

            <div class="sticky bottom-4 z-20 flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white/95 px-5 py-4 shadow-xl shadow-gray-300/50 backdrop-blur">
                <p class="hidden text-sm text-gray-500 sm:block">Changes publish to the site immediately.</p>
                <x-primary-button class="!rounded-xl !bg-indigo-600 !px-6 !py-3 !shadow-lg !shadow-indigo-200 hover:!bg-indigo-700">Save changes</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
