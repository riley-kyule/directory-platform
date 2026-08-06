<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Add staff member</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5 bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password" value="Password" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label value="Roles" />
                    <div class="mt-2 space-y-2">
                        @foreach ($manageableRoles as $role)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="roles[]" value="{{ $role->slug }}" @checked(in_array($role->slug, old('roles', []), true)) class="rounded border-gray-300 text-indigo-600">
                                {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('roles')" class="mt-2" />
                </div>
                <div class="flex items-center justify-between">
                    <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600">Cancel</a>
                    <x-primary-button>Create account</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
