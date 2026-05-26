<x-filament-panels::page>
    <style>
        .od-stats { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; margin-bottom:1.5rem; }
        .od-body  { display:grid; grid-template-columns:1fr; gap:1rem; }
        @media(min-width:1024px) {
            .od-stats { grid-template-columns:repeat(4,1fr); }
            .od-body  { grid-template-columns:2fr 1fr; }
        }
    </style>
    @php
        $d        = $this->getMockData();
        $officer  = $d['officer'];
        $stats    = $d['stats'];
        $queue    = $d['priority_queue'];
        $team     = $d['team_workload'];
        $activity = $d['todays_activity'];

        $hour     = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        $queueUrl = \App\Filament\Officer\Resources\VisaApplications\OfficerVisaApplicationResource::getUrl('index');
    @endphp

    {{-- ── Greeting + CTA ──────────────────────────────────────────── --}}
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem;">
        <div>
            <h2 style="font-size:1.5rem; font-weight:600; letter-spacing:-0.025em; color:#fff; margin:0;">
                {{ $greeting }}, {{ $officer['name'] }} 👋
            </h2>
            <p style="margin-top:.375rem; font-size:.875rem; color:#9ca3af;">
                {{ now()->format('l, j F Y') }}
                &middot;
                You have <span style="color:#fff; font-weight:500;">{{ $stats['pending_queue']['value'] }}</span> applications awaiting review
            </p>
        </div>
        <a href="{{ $queueUrl }}"
           style="display:inline-flex; flex-shrink:0; align-items:center; gap:.5rem; border-radius:.5rem; background:rgb(var(--primary-600)); padding:.5rem 1rem; font-size:.875rem; font-weight:600; color:#fff; text-decoration:none; transition:opacity .15s;"
           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            Open Review Queue
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
        </a>
    </div>

    {{-- ── Stat Cards ───────────────────────────────────────────────── --}}
    <div class="od-stats">

        {{-- Assigned to Me --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            <div style="display:flex; align-items:flex-start; justify-content:space-between;">
                <p style="font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Assigned to Me</p>
                <span style="display:flex; width:2.25rem; height:2.25rem; flex-shrink:0; align-items:center; justify-content:center; border-radius:.5rem; background:rgba(255,255,255,.08);">
                    <svg width="20" height="20" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>
                    </svg>
                </span>
            </div>
            <p style="margin-top:.75rem; font-size:1.875rem; font-weight:700; color:#38bdf8; line-height:1;">{{ $stats['assigned_to_me']['value'] }}</p>
            <p style="margin-top:.25rem; font-size:.75rem; color:#6b7280;">{{ $stats['assigned_to_me']['label'] }}</p>
        </div>

        {{-- Approved Today --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            <div style="display:flex; align-items:flex-start; justify-content:space-between;">
                <p style="font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Approved Today</p>
                <span style="display:flex; width:2.25rem; height:2.25rem; flex-shrink:0; align-items:center; justify-content:center; border-radius:.5rem; background:rgba(16,185,129,.15);">
                    <svg width="20" height="20" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p style="margin-top:.75rem; font-size:1.875rem; font-weight:700; color:#34d399; line-height:1;">{{ $stats['approved_today']['value'] }}</p>
            <p style="margin-top:.25rem; display:flex; align-items:center; gap:.25rem; font-size:.75rem; color:#34d399;">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                </svg>
                {{ $stats['approved_today']['label'] }}
            </p>
        </div>

        {{-- Pending Queue --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            <div style="display:flex; align-items:flex-start; justify-content:space-between;">
                <p style="font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Pending Queue</p>
                <span style="display:flex; width:2.25rem; height:2.25rem; flex-shrink:0; align-items:center; justify-content:center; border-radius:.5rem; background:rgba(245,158,11,.15);">
                    <svg width="20" height="20" fill="none" stroke="#f59e0b" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p style="margin-top:.75rem; font-size:1.875rem; font-weight:700; color:#fbbf24; line-height:1;">{{ $stats['pending_queue']['value'] }}</p>
            <p style="margin-top:.25rem; font-size:.75rem; color:#6b7280;">{{ $stats['pending_queue']['label'] }}</p>
        </div>

        {{-- Avg. Decision Time --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            <div style="display:flex; align-items:flex-start; justify-content:space-between;">
                <p style="font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Avg. Decision Time</p>
                <span style="display:flex; width:2.25rem; height:2.25rem; flex-shrink:0; align-items:center; justify-content:center; border-radius:.5rem; background:rgba(20,184,166,.15);">
                    <svg width="20" height="20" fill="none" stroke="#2dd4bf" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                    </svg>
                </span>
            </div>
            <p style="margin-top:.75rem; font-size:1.875rem; font-weight:700; color:#2dd4bf; line-height:1;">{{ $stats['avg_decision_time']['value'] }}</p>
            <p style="margin-top:.25rem; display:flex; align-items:center; gap:.25rem; font-size:.75rem; color:#34d399;">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3"/>
                </svg>
                {{ $stats['avg_decision_time']['label'] }}
            </p>
        </div>

    </div>

    {{-- ── Body ─────────────────────────────────────────────────────── --}}
    <div class="od-body">

        {{-- ── Priority Queue ───────────────────────────────────────── --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm" style="overflow:hidden;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem; border-bottom:1px solid rgba(255,255,255,.08);">
                <div>
                    <h3 style="font-size:.875rem; font-weight:600; color:#fff; margin:0;">Priority Queue</h3>
                    <p style="font-size:.75rem; color:#6b7280; margin:.125rem 0 0;">Oldest unreviewed applications first</p>
                </div>
                <a href="{{ $queueUrl }}"
                   style="display:inline-flex; align-items:center; gap:.375rem; border-radius:.375rem; padding:.375rem .75rem; font-size:.75rem; font-weight:500; color:#d1d5db; border:1px solid rgba(255,255,255,.15); text-decoration:none; transition:background .15s;"
                   onmouseover="this.style.background='rgba(255,255,255,.05)'" onmouseout="this.style.background='transparent'">
                    View all
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                </a>
            </div>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                        <th style="width:3rem; padding:.75rem 1.25rem; text-align:left; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Priority</th>
                        <th style="padding:.75rem 1rem; text-align:left; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Reference</th>
                        <th style="padding:.75rem 1rem; text-align:left; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Applicant</th>
                        <th style="padding:.75rem 1rem; text-align:left; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Type</th>
                        <th style="padding:.75rem 1rem; text-align:left; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.075em; color:#6b7280;">Days</th>
                        <th style="padding:.75rem 1rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($queue as $i => $item)
                        <tr style="border-top:{{ $i > 0 ? '1px solid rgba(255,255,255,.04)' : 'none' }}; transition:background .1s;"
                            onmouseover="this.style.background='rgba(255,255,255,.03)'" onmouseout="this.style.background='transparent'">
                            <td style="padding:.875rem 1.25rem;">
                                <span style="display:inline-block; width:.625rem; height:.625rem; border-radius:50%; background:{{ $item['priority'] === 'urgent' ? '#ef4444' : '#10b981' }};"></span>
                            </td>
                            <td style="padding:.875rem 1rem;">
                                <span style="font-family:monospace; font-size:.875rem; font-weight:500; color:#5eead4;">{{ $item['reference'] }}</span>
                            </td>
                            <td style="padding:.875rem 1rem;">
                                <span style="font-size:.875rem; color:#f3f4f6;">{{ $item['applicant'] }}</span>
                            </td>
                            <td style="padding:.875rem 1rem;">
                                <span style="font-size:.875rem; color:#9ca3af;">{{ $item['type'] }}</span>
                            </td>
                            <td style="padding:.875rem 1rem;">
                                <span style="display:inline-flex; align-items:center; border-radius:.375rem; padding:.125rem .5rem; font-size:.75rem; font-weight:600;
                                    background:{{ $item['priority'] === 'urgent' ? 'rgba(239,68,68,.2)' : 'rgba(16,185,129,.2)' }};
                                    color:{{ $item['priority'] === 'urgent' ? '#f87171' : '#34d399' }};">
                                    {{ $item['days'] }}d
                                </span>
                            </td>
                            <td style="padding:.875rem 1rem; text-align:right;">
                                <a href="{{ $queueUrl }}"
                                   style="display:inline-flex; align-items:center; border-radius:.375rem; padding:.25rem .75rem; font-size:.75rem; font-weight:500; color:#d1d5db; border:1px solid rgba(255,255,255,.15); text-decoration:none; transition:background .15s;"
                                   onmouseover="this.style.background='rgba(255,255,255,.07)'" onmouseout="this.style.background='transparent'">
                                    Review
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- ── Right Sidebar ─────────────────────────────────────────── --}}
        <div style="display:flex; flex-direction:column; gap:1rem;">

            {{-- Team Workload --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm p-5">
                <h3 style="font-size:.875rem; font-weight:600; color:#fff; margin:0 0 1rem;">Team Workload</h3>
                @php $avatarColors = ['#f59e0b', '#10b981', '#ef4444', '#38bdf8']; @endphp
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    @foreach($team as $i => $member)
                        @php
                            $pct   = $member['capacity'] > 0 ? round(($member['assigned'] / $member['capacity']) * 100) : 0;
                            $barHex = $pct >= 80 ? '#ef4444' : ($pct >= 50 ? '#f59e0b' : '#10b981');
                        @endphp
                        <div>
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.5rem;">
                                <div style="display:flex; align-items:center; gap:.625rem; min-width:0;">
                                    <span style="display:inline-flex; width:2rem; height:2rem; flex-shrink:0; align-items:center; justify-content:center; border-radius:50%; font-size:.7rem; font-weight:700; color:#fff; background:{{ $avatarColors[$i % count($avatarColors)] }};">
                                        {{ $member['initials'] }}
                                    </span>
                                    <div style="min-width:0;">
                                        <p style="font-size:.875rem; font-weight:500; color:#f3f4f6; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $member['name'] }}</p>
                                        <p style="font-size:.75rem; color:#6b7280; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $member['types'] }}</p>
                                    </div>
                                </div>
                                <span style="flex-shrink:0; font-size:.75rem; font-weight:600; color:#6b7280; font-variant-numeric:tabular-nums;">{{ $member['assigned'] }}/{{ $member['capacity'] }}</span>
                            </div>
                            <div style="height:.375rem; border-radius:9999px; background:rgba(255,255,255,.08); overflow:hidden;">
                                <div style="height:100%; border-radius:9999px; background:{{ $barHex }}; width:{{ $pct }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Today's Activity --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm p-5">
                <h3 style="font-size:.875rem; font-weight:600; color:#fff; margin:0 0 1rem;">Today's Activity</h3>
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    @foreach($activity as $item)
                        <div style="display:flex; align-items:flex-start; gap:.75rem;">
                            @if($item['action'] === 'approved')
                                <span style="display:flex; width:1.5rem; height:1.5rem; flex-shrink:0; margin-top:.125rem; align-items:center; justify-content:center; border-radius:50%; background:rgba(16,185,129,.2);">
                                    <svg width="14" height="14" fill="none" stroke="#34d399" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                </span>
                            @elseif($item['action'] === 'docs_requested')
                                <span style="display:flex; width:1.5rem; height:1.5rem; flex-shrink:0; margin-top:.125rem; align-items:center; justify-content:center; border-radius:50%; background:rgba(245,158,11,.2);">
                                    <svg width="14" height="14" fill="none" stroke="#fbbf24" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>
                                    </svg>
                                </span>
                            @else
                                <span style="display:flex; width:1.5rem; height:1.5rem; flex-shrink:0; margin-top:.125rem; align-items:center; justify-content:center; border-radius:50%; background:rgba(107,114,128,.2);">
                                    <svg width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                                    </svg>
                                </span>
                            @endif
                            <div style="min-width:0;">
                                <p style="font-size:.875rem; color:#f3f4f6; margin:0;">{{ $item['label'] }}</p>
                                <p style="font-size:.75rem; color:#6b7280; margin:.125rem 0 0;">{{ $item['time_ago'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-filament-panels::page>
