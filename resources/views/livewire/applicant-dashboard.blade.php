<div>
    {{-- Alert bar: action required --}}
    @if($actionRequiredApp)
        <x-alert-bar :application="$actionRequiredApp" class="mb-5" />
    @endif

    {{-- Welcome Banner --}}
    <div class="rounded-xl p-7 mb-6 flex items-center justify-between relative overflow-hidden"
         style="background: linear-gradient(135deg, var(--portal-teal) 0%, #0b5a4d 100%)">
        <div class="relative z-10">
            <h1 class="text-xl font-bold text-white mb-1.5" style="letter-spacing: -0.4px">
                Welcome back, {{ Str::before(auth()->user()->name, ' ') }} 👋
            </h1>
            <p class="text-sm" style="color: rgba(255,255,255,0.75)">
                @php
                    $parts = [];
                    if ($stats['underReview'] > 0) {
                        $parts[] = $stats['underReview'] . ' application' . ($stats['underReview'] !== 1 ? 's' : '') . ' under review';
                    }
                    if ($stats['pendingPayment'] > 0) {
                        $parts[] = $stats['pendingPayment'] . ' payment' . ($stats['pendingPayment'] !== 1 ? 's' : '') . ' due';
                    }
                @endphp
                {{ count($parts) > 0 ? 'You have ' . implode(' and ', $parts) . '.' : 'Everything is up to date.' }}
            </p>
        </div>
        <a href="{{ route('applications.start') }}"
           class="relative z-10 flex-shrink-0 text-sm font-bold px-5 py-2.5 rounded-lg transition-colors"
           style="background: #fff; color: var(--portal-teal)">
            + New Application
        </a>
        {{-- Decorative circles --}}
        <div class="absolute right-0 top-0 w-48 h-48 rounded-full pointer-events-none"
             style="background: rgba(255,255,255,0.06); transform: translate(30%, -30%)"></div>
        <div class="absolute right-16 bottom-0 w-32 h-32 rounded-full pointer-events-none"
             style="background: rgba(255,255,255,0.04); transform: translateY(50%)"></div>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-6">
        <x-stat-card
            :value="$stats['total']"
            label="Total Applications"
            sub="Since joined" />
        <x-stat-card
            :value="$stats['approved']"
            label="Approved"
            number-colour="text-teal-700"
            :sub="$stats['approved'] > 0 ? '↑ Active' : 'None yet'" />
        <x-stat-card
            :value="$stats['underReview']"
            label="Under Review"
            number-colour="text-amber-600"
            sub="Processing" />
        <x-stat-card
            :value="$stats['pendingPayment']"
            label="Pending Payment"
            number-colour="text-red-600"
            :sub="$stats['pendingPayment'] > 0 ? 'Action needed' : 'All clear'" />
    </div>

    {{-- Two-Column Body --}}
    <div class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-4">

        {{-- Left: Recent Applications --}}
        <div>
            <p class="text-sm font-bold mb-3.5" style="color: var(--portal-ink)">Recent Applications</p>

            @forelse($applications as $application)
                <x-application-card :application="$application" class="mb-2.5" />
            @empty
                <x-empty-state
                    icon="ti-file-certificate"
                    heading="No applications yet"
                    description="Start a new application to apply for a visa."
                >
                    <x-slot name="cta">
                        <a href="{{ route('applications.start') }}"
                           class="inline-flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg text-white"
                           style="background: var(--portal-teal)">
                            <i class="ti ti-plus" aria-hidden="true"></i>
                            New application
                        </a>
                    </x-slot>
                </x-empty-state>
            @endforelse
        </div>

        {{-- Right: Upcoming Travel + Quick Actions --}}
        <div class="flex flex-col gap-3.5">

            {{-- Upcoming Travel --}}
            <div class="bg-white border border-[var(--portal-sand-3)] rounded-xl p-5">
                <p class="text-sm font-bold mb-0.5" style="color: var(--portal-ink)">Upcoming Travel</p>
                <p class="text-xs mb-3.5" style="color: var(--portal-ink-4)">Your next approved trip</p>

                @if($upcomingTrip)
                    <div class="rounded-lg p-3.5 text-center" style="background: var(--portal-teal-soft)">
                        <p class="text-2xl font-extrabold mb-1" style="color: var(--portal-teal); letter-spacing: -1px">
                            {{ $upcomingTrip->travel_date->format('M j') }}
                        </p>
                        <p class="text-xs mb-1" style="color: var(--portal-teal-2)">
                            {{ $upcomingTrip->visaType->name }} · {{ $upcomingTrip->visaType->country->name }}
                        </p>
                        <p class="text-xs" style="color: var(--portal-ink-4)">
                            {{ (int) now()->diffInDays($upcomingTrip->travel_date, true) }} days remaining
                        </p>
                    </div>
                @else
                    <div class="rounded-lg p-4 text-center" style="background: var(--portal-sand)">
                        <p class="text-xs" style="color: var(--portal-ink-4)">No upcoming approved trips</p>
                    </div>
                @endif
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white border border-[var(--portal-sand-3)] rounded-xl p-5">
                <p class="text-sm font-bold mb-0.5" style="color: var(--portal-ink)">Quick Actions</p>
                <p class="text-xs mb-3.5" style="color: var(--portal-ink-4)">Common tasks</p>
                <div class="flex flex-col gap-2">
                    <a href="{{ route('applications.start') }}"
                       class="flex items-center justify-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-lg text-white transition-opacity hover:opacity-90"
                       style="background: var(--portal-teal)">
                        <i class="ti ti-plus text-sm" aria-hidden="true"></i>
                        Start New Application
                    </a>
                    <a href="{{ route('track') }}"
                       class="flex items-center justify-center gap-2 text-sm font-medium px-4 py-2.5 rounded-lg border transition-colors hover:bg-gray-50"
                       style="border-color: var(--portal-sand-3); color: var(--portal-ink-2)">
                        <i class="ti ti-search text-sm" aria-hidden="true"></i>
                        Track Application
                    </a>
                    <a href="{{ route('payments') }}"
                       class="flex items-center justify-center gap-2 text-sm font-medium px-4 py-2.5 rounded-lg border transition-colors hover:bg-gray-50"
                       style="border-color: var(--portal-sand-3); color: var(--portal-ink-2)">
                        <i class="ti ti-credit-card text-sm" aria-hidden="true"></i>
                        Make Payment
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
