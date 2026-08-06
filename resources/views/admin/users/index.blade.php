<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Users &amp; Roles</h2>
            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">Add staff member</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="GET" class="flex gap-2">
                <x-text-input name="q" class="block w-full max-w-sm" placeholder="Search by name or email" :value="$search" />
                <x-secondary-button type="submit">Search</x-secondary-button>
                @if ($search)
                    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
                @endif
            </form>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">User</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Roles</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Joined</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($users as $user)
                            @php($userRoleSlugs = $user->roles->pluck('slug')->all())
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-gray-900">{{ $user->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <form method="POST" action="{{ route('admin.users.roles.update', $user) }}" class="flex flex-wrap items-center gap-3">
                                        @csrf
                                        @method('PATCH')
                                        @foreach ($manageableRoles as $role)
                                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                                <input type="checkbox" name="roles[]" value="{{ $role->slug }}" @checked(in_array($role->slug, $userRoleSlugs, true)) class="rounded border-gray-300 text-indigo-600">
                                                {{ $role->name }}
                                            </label>
                                        @endforeach
                                        <x-secondary-button type="submit">Save</x-secondary-button>
                                    </form>
                                    @foreach (array_diff($userRoleSlugs, $manageableRoles->pluck('slug')->all()) as $otherSlug)
                                        <span class="mt-1 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ ucfirst($otherSlug) }}</span>
                                    @endforeach
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-500">{{ $user->created_at->format('j M Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if ($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete {{ $user->name }}? This cannot be undone from here.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-800">Delete</button>
                                        </form>
                                    @else
                                        <span class="text-sm text-gray-400">You</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div>{{ $users->onEachSide(1)->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
