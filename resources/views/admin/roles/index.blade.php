<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Roles</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @foreach ($roles as $role)
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                        @csrf
                        @method('PATCH')
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                @if ($role->is_system)
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $role->name }}</h3>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">System</span>
                                @else
                                    <x-text-input name="name" class="text-lg font-semibold" :value="$role->name" required />
                                @endif
                                <span class="text-xs text-gray-500">{{ $role->users_count }} user{{ $role->users_count === 1 ? '' : 's' }}</span>
                            </div>
                            <x-primary-button>Save</x-primary-button>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($permissionGroups as $group => $permissions)
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str($group)->replace('_', ' ')->title() }}</h4>
                                    <div class="mt-2 space-y-1">
                                        @foreach ($permissions as $permission)
                                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id))>
                                                {{ $permission->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </form>

                    @unless ($role->is_system)
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete the {{ $role->name }} role? This cannot be undone.');" class="mt-4">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-800">Delete role</button>
                        </form>
                    @endunless
                </section>
            @endforeach

            <section class="rounded-lg border-2 border-dashed border-gray-300 bg-white p-6">
                <h3 class="text-lg font-semibold text-gray-900">Add a new role</h3>
                <form method="POST" action="{{ route('admin.roles.store') }}" class="mt-4">
                    @csrf
                    <x-input-label for="new_role_name" value="Name" />
                    <x-text-input id="new_role_name" name="name" class="mt-1 block w-full max-w-sm" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($permissionGroups as $group => $permissions)
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str($group)->replace('_', ' ')->title() }}</h4>
                                <div class="mt-2 space-y-1">
                                    @foreach ($permissions as $permission)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}">
                                            {{ $permission->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4"><x-primary-button>Create role</x-primary-button></div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
