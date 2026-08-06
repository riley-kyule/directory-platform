<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Updates</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">The change could not be saved.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if ($deployment)
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Deployment</h3>
                    <p class="mt-1 text-sm text-gray-600">Runs <code>deploy/deploy.sh</code> on this server — the same script used from SSH, with the same atomic-release safety. A failed launch check leaves the live release untouched and serving traffic.</p>

                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Currently deployed commit</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900">{{ $deployment['current_commit'] ? substr($deployment['current_commit'], 0, 12) : 'unknown' }}</dd>
                        </div>
                        @if (session('deployment_check'))
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Latest on {{ config('deployment.branch') }}</dt>
                                <dd class="mt-1 font-mono text-sm text-gray-900">{{ substr(session('deployment_check')['remote_commit'], 0, 12) }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <form method="POST" action="{{ route('admin.settings.deployment.check') }}">
                            @csrf
                            <x-secondary-button>Check for updates</x-secondary-button>
                        </form>

                        @if (session('deployment_check'))
                            @if (session('deployment_check')['remote_commit'] !== $deployment['current_commit'])
                                <form method="POST" action="{{ route('admin.settings.deployment.deploy') }}" onsubmit="return confirm('Deploy the latest commit now? This runs migrations and activates a new release if the launch check passes.');">
                                    @csrf
                                    <x-primary-button>Deploy now</x-primary-button>
                                </form>
                                <span class="text-sm font-medium text-amber-700">An update is available.</span>
                            @else
                                <span class="text-sm text-gray-600">Already up to date.</span>
                            @endif
                        @endif
                    </div>

                    @if ($deployment['latest'])
                        @php $latest = $deployment['latest']; @endphp
                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-semibold text-gray-900">
                                Latest run
                                <span @class([
                                    'ml-2 rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-yellow-100 text-yellow-800' => in_array($latest->status, ['queued', 'running']),
                                    'bg-green-100 text-green-800' => $latest->status === 'succeeded',
                                    'bg-red-100 text-red-800' => $latest->status === 'failed',
                                ])>{{ ucfirst($latest->status) }}</span>
                            </h4>
                            <p class="mt-1 text-xs text-gray-500">
                                Triggered by {{ $latest->triggeredBy?->name ?? 'unknown' }}
                                @if ($latest->started_at) &middot; started {{ $latest->started_at->diffForHumans() }} @endif
                                @if ($latest->finished_at) &middot; finished {{ $latest->finished_at->diffForHumans() }} @endif
                            </p>
                            @if ($latest->output)
                                <pre class="mt-3 max-h-64 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100">{{ $latest->output }}</pre>
                            @endif
                        </div>
                    @endif
                </section>
            @else
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Deployment</h3>
                    <p class="mt-1 text-sm text-gray-600">Self-deploy from the admin panel is not configured on this server. Set <code>SELF_DEPLOY_ENABLED</code> and the related <code>SELF_DEPLOY_*</code> variables in <code>.env</code> to enable it — see the README's "Deploying from the admin panel" section.</p>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
