@php
    $stateLabel = match (true) {
        $program->status === 'cancelled' => 'Cancelled',
        $program->status === 'draft' => 'Draft',
        $program->hasStarted() => 'Completed',
        default => 'Upcoming',
    };
    $end = $program->ends_at;
    $sameDay = $end && $end->isSameDay($program->starts_at);
    $dateLine = $program->starts_at->format('l, j F Y');
    if ($end && ! $sameDay) {
        $dateLine .= ' to '.$end->format('l, j F Y');
    }
    $timeLine = $program->starts_at->format('g:i A').($end ? ' to '.$end->format('g:i A') : '');
    $avg = $feedback && $feedback->isNotEmpty() ? round($feedback->avg('rating'), 1) : null;
    $comments = $feedback ? $feedback->filter(fn ($f) => filled($f->comment)) : collect();
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event report: {{ $program->title }} | YCC Hub</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm 14mm 16mm; }
        .rp { font-family: var(--font-sans); color: var(--color-ink); font-size: 13px; line-height: 1.5; }
        .rp-sheet { max-width: 210mm; margin: 0 auto; background: #fff; padding: 14mm; box-shadow: 0 10px 40px rgb(10 30 36 / 0.12); }
        .rp h1, .rp h2 { font-family: var(--font-display); color: var(--color-abyss-900); }
        .rp h1 { font-size: 34px; font-weight: 800; line-height: 1.05; }
        .rp h2 { font-size: 19px; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid var(--color-abyss-900); break-after: avoid; }
        .rp table { width: 100%; border-collapse: collapse; }
        .rp th, .rp td { text-align: left; vertical-align: top; padding: 5px 8px; border: 1px solid #c9d6d1; }
        .rp thead th { background: var(--color-glass-100); font-weight: 700; font-size: 12px; }
        .rp tr { break-inside: avoid; }
        .rp .rp-kv th { width: 34%; background: var(--color-glass-50); font-weight: 600; }
        .rp .rp-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .rp .rp-muted { color: var(--color-ink-soft); }
        .rp .rp-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; border: 1px solid #c9d6d1; }
        .rp .rp-stat { padding: 8px 10px; border-right: 1px solid #c9d6d1; border-bottom: 1px solid #c9d6d1; }
        .rp .rp-stat:nth-child(4n) { border-right: 0; }
        .rp .rp-stat:nth-last-child(-n+4) { border-bottom: 0; }
        .rp .rp-stat b { display: block; font-family: var(--font-display); font-size: 26px; font-weight: 800; line-height: 1; color: var(--color-abyss-900); }
        .rp .rp-stat span { font-size: 11px; color: var(--color-ink-soft); }
        .rp .rp-two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .rp .rp-tag { display: inline-block; padding: 0 6px; border-radius: 4px; font-size: 10px; font-weight: 700; background: #e3f0ff; color: var(--color-st-new); }
        .rp .rp-note { margin-top: 10px; padding: 8px 10px; border-left: 3px solid var(--color-st-risk); background: #fff7e6; font-size: 12px; }
        .rp .rp-sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 34px; break-inside: avoid; }
        .rp .rp-line { border-top: 1px solid var(--color-ink); margin-top: 46px; padding-top: 4px; }
        .rp .rp-avoid { break-inside: avoid; }
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .rp-sheet { box-shadow: none; padding: 0; max-width: none; }
            .rp-screen-note { display: none; }
        }
    </style>
</head>
<body class="bg-glass-100">
    <div class="no-print sticky top-0 z-10 border-b border-glass-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('exco.attendance.show', $program) }}" class="btn-line btn-sm"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg> Back to attendance</a>
            <div class="flex items-center gap-2">
                <span class="hidden text-sm text-ink-soft sm:inline">Choose “Save as PDF” in the print window for a PDF.</span>
                <button type="button" onclick="window.print()" class="btn-gold btn-sm"><x-icon name="download" class="size-4" /> Print / Save as PDF</button>
            </div>
        </div>
    </div>

    <main class="rp px-0 py-6 sm:px-4 print:p-0">
        <div class="rp-sheet">

            {{-- Letterhead --}}
            <header style="display:flex; align-items:center; gap:14px; padding-bottom:12px; border-bottom:3px solid var(--color-crest-500)">
                <img src="{{ asset('images/ycc-crest-192.png') }}" alt="YCC crest" style="width:58px; height:58px">
                <div style="flex:1">
                    <p style="font-weight:700; font-size:14px; line-height:1.3">Young Conservationist Club (YCC)</p>
                    <p class="rp-muted" style="font-size:12px">Universiti Malaysia Terengganu · Sesi {{ $session }}</p>
                </div>
                <div style="text-align:right">
                    <p style="font-family:var(--font-display); font-size:22px; font-weight:800; color:var(--color-abyss-900); line-height:1">EVENT REPORT</p>
                    <p class="rp-muted" style="font-size:11px">Generated {{ now()->format('j M Y, g:i A') }}</p>
                </div>
            </header>

            <div style="margin-top:16px">
                <p style="font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--color-kelp-500)">{{ $program->categoryLabel() }}</p>
                <h1>{{ $program->title }}</h1>
            </div>

            @if (! $program->hasStarted() && $program->status !== 'cancelled')
                <p class="rp-note">This event hasn’t started yet, so the numbers below are registrations so far.</p>
            @elseif ($counts['unmarked'] > 0 && $program->status !== 'cancelled')
                <p class="rp-note rp-screen-note">{{ $counts['unmarked'] }} {{ \Illuminate\Support\Str::plural('person', $counts['unmarked']) }} on the list {{ $counts['unmarked'] === 1 ? 'is' : 'are' }} still unmarked. Mark them in Attendance first for an accurate report. (This note doesn’t print.)</p>
            @endif

            {{-- 1. Details --}}
            <h2>1. Event details</h2>
            <div style="display:grid; grid-template-columns:{{ $program->posterUrl() ? '1fr 42mm' : '1fr' }}; gap:12px; align-items:start">
                <table class="rp-kv">
                    <tr><th>Date</th><td>{{ $dateLine }}</td></tr>
                    <tr><th>Time</th><td>{{ $timeLine }}</td></tr>
                    <tr><th>Venue</th><td>{{ $program->whereLabel() }}</td></tr>
                    <tr><th>Status</th><td>{{ $stateLabel }}</td></tr>
                    <tr><th>Seats</th><td>{{ $program->max_capacity ? $program->max_capacity.' seats' : 'No limit' }}</td></tr>
                    <tr><th>Volunteer hours</th><td>{{ $hoursEach > 0 ? $num($hoursEach).' per attendee' : 'Not counted' }}</td></tr>
                    <tr><th>Counts toward activity</th><td>{{ $program->counts_for_activity ? 'Yes' : 'No' }}</td></tr>
                    <tr><th>Organised by</th><td>Young Conservationist Club (YCC), UMT</td></tr>
                </table>
                @if ($program->posterUrl())
                    <img src="{{ $program->posterUrl() }}" alt="Event poster" style="width:42mm; border:1px solid #c9d6d1; border-radius:4px">
                @endif
            </div>
            @if (filled($program->description))
                <div style="margin-top:10px">
                    <p style="font-weight:700; margin-bottom:2px">About the event</p>
                    @foreach (preg_split("/\R{2,}/", trim($program->description)) as $para)
                        <p style="margin-bottom:6px">{!! nl2br(e($para)) !!}</p>
                    @endforeach
                </div>
            @endif

            {{-- 2. Attendance --}}
            <h2>2. Attendance summary</h2>
            <div class="rp-stats rp-avoid">
                <div class="rp-stat"><b>{{ $counts['on_list'] }}</b><span>registered</span></div>
                <div class="rp-stat"><b>{{ $counts['attended'] }}</b><span>attended</span></div>
                <div class="rp-stat"><b>{{ $rate !== null ? $rate.'%' : '–' }}</b><span>attendance rate</span></div>
                <div class="rp-stat"><b>{{ $counts['absent'] }}</b><span>absent (no-show)</span></div>
                <div class="rp-stat"><b>{{ $firstTimers->count() }}</b><span>first YCC event</span></div>
                <div class="rp-stat"><b>{{ $num($hoursTotal) }}</b><span>volunteer hours in total</span></div>
                <div class="rp-stat"><b>{{ $counts['cancelled'] }}</b><span>cancelled before the day</span></div>
                <div class="rp-stat"><b>{{ $counts['unmarked'] }}</b><span>not marked</span></div>
            </div>
            <p class="rp-muted" style="margin-top:6px; font-size:12px">
                Checked in by QR: {{ $counts['qr'] }} · Marked by the MT: {{ $counts['manual'] }}.
                @if ($program->committee_count) Committee (AJK): {{ $program->committee_count }} in total. @endif
            </p>

            @if ($attended->isNotEmpty())
                <div class="rp-two rp-avoid" style="margin-top:12px">
                    <table>
                        <thead><tr><th>Attendees by team</th><th class="rp-num">People</th></tr></thead>
                        <tbody>
                            @foreach ($byTeam as $label => $n)<tr><td>{{ $label }}</td><td class="rp-num">{{ $n }}</td></tr>@endforeach
                        </tbody>
                    </table>
                    <table>
                        <thead><tr><th>Attendees by faculty</th><th class="rp-num">People</th></tr></thead>
                        <tbody>
                            @foreach ($byFaculty as $label => $n)<tr><td>{{ $label }}</td><td class="rp-num">{{ $n }}</td></tr>@endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- 3. Committee --}}
            <h2>3. Committee (AJK)</h2>
            @if ($committee->isEmpty())
                <p class="rp-muted">No committee was recorded for this event.</p>
            @else
                <table>
                    <thead><tr><th style="width:8%">No.</th><th style="width:34%">Position</th><th>Name</th><th style="width:18%">Matric no.</th></tr></thead>
                    <tbody>
                        @foreach ($committee as $i => $c)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $c->position }}</td>
                                <td>{{ $c->user?->full_name ?? 'To be decided' }}</td>
                                <td>{{ $c->user?->matric_no ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- 4. Feedback --}}
            @if ($feedback !== null)
                <h2>4. Member feedback</h2>
                @if ($feedback->isEmpty())
                    <p class="rp-muted">No feedback was given for this event.</p>
                @else
                    <div class="rp-two rp-avoid" style="align-items:start">
                        <div>
                            <p style="font-family:var(--font-display); font-size:34px; font-weight:800; line-height:1; color:var(--color-abyss-900)">{{ $num($avg) }} <span style="font-size:18px">/ 5</span></p>
                            <p class="rp-muted" style="font-size:12px">Average rating from {{ $feedback->count() }} {{ \Illuminate\Support\Str::plural('member', $feedback->count()) }}. Feedback is anonymous.</p>
                        </div>
                        <table>
                            <thead><tr><th>Rating</th><th class="rp-num">Members</th></tr></thead>
                            <tbody>
                                @foreach ([5 => 'Loved it', 4 => 'Great', 3 => 'Good', 2 => 'Okay', 1 => 'Not great'] as $stars => $word)
                                    <tr><td>{{ $stars }} {{ $stars === 1 ? 'star' : 'stars' }} ({{ $word }})</td><td class="rp-num">{{ $feedback->where('rating', $stars)->count() }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($comments->isNotEmpty())
                        <p style="font-weight:700; margin:10px 0 4px">Comments</p>
                        <ul style="list-style:disc; padding-left:18px">
                            @foreach ($comments as $f)
                                <li style="margin-bottom:3px; break-inside:avoid">“{{ $f->comment }}” <span class="rp-muted">({{ $f->rating }}/5)</span></li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            @endif

            {{-- 5. Attendees --}}
            <h2>{{ $feedback !== null ? '5' : '4' }}. Attendance list</h2>
            @if ($attended->isEmpty())
                <p class="rp-muted">Nobody has been marked as attended yet.</p>
            @else
                <table>
                    <thead>
                        <tr><th style="width:6%">No.</th><th>Name</th><th style="width:13%">Matric no.</th><th style="width:25%">Faculty</th><th style="width:14%">Team</th><th style="width:14%">Check-in</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($attended as $i => $r)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $r->user->full_name }} @if ($firstTimers->contains($r->user_id))<span class="rp-tag">FIRST EVENT</span>@endif</td>
                                <td>{{ $r->user->matric_no }}</td>
                                <td>{{ $r->user->facultyName() ?? '–' }}</td>
                                <td>{{ $r->user->teamName() ? str_replace(' Team', '', $r->user->teamName()) : '–' }}</td>
                                <td>{{ $r->checked_in_at?->format('g:i A') ?? '–' }}{{ $r->checkin_method === 'qr' ? ' (QR)' : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Sign-off --}}
            <div class="rp-sign">
                <div>
                    <p style="font-weight:700">Prepared by</p>
                    <div class="rp-line">
                        <p style="font-weight:700">{{ $preparedBy->full_name }}</p>
                        <p class="rp-muted">{{ $preparedBy->titleLabel() }}, YCC</p>
                        <p class="rp-muted">Date: {{ now()->format('j M Y') }}</p>
                    </div>
                </div>
                <div>
                    <p style="font-weight:700">Verified by</p>
                    <div class="rp-line">
                        <p style="font-weight:700">{{ $advisor ?: '______________________________' }}</p>
                        <p class="rp-muted">Penasihat, YCC</p>
                        <p class="rp-muted">Date:</p>
                    </div>
                </div>
            </div>

            <p class="rp-muted" style="margin-top:22px; font-size:10px; text-align:center">Generated by YCC Hub. Contact details are left out of this report to protect members’ personal data.</p>
        </div>
    </main>
</body>
</html>
