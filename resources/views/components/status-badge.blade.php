@props(['status', 'size' => 'md'])
@php
    $map = [
        'active' => ['Active', 'bg-st-active/10 text-st-active ring-st-active/25', 'bg-st-active'],
        'at_risk' => ['At-Risk', 'bg-st-risk/12 text-st-risk ring-st-risk/30', 'bg-st-risk'],
        'inactive' => ['Inactive', 'bg-st-inactive/10 text-st-inactive ring-st-inactive/25', 'bg-st-inactive'],
        'new' => ['New member', 'bg-st-new/10 text-st-new ring-st-new/25', 'bg-st-new'],
        // registration states
        'registered' => ['Registered', 'bg-abyss-700/10 text-abyss-700 ring-abyss-700/20', 'bg-abyss-700'],
        'attended' => ['Attended', 'bg-st-active/10 text-st-active ring-st-active/25', 'bg-st-active'],
        'absent' => ['Absent', 'bg-st-inactive/10 text-st-inactive ring-st-inactive/25', 'bg-st-inactive'],
        'cancelled' => ['Cancelled', 'bg-glass-100 text-ink-soft ring-glass-300', 'bg-ink-soft'],
        // account states
        'pending' => ['Pending', 'bg-st-risk/12 text-st-risk ring-st-risk/30', 'bg-st-risk'],
        'approved' => ['Approved', 'bg-st-active/10 text-st-active ring-st-active/25', 'bg-st-active'],
        'rejected' => ['Rejected', 'bg-st-inactive/10 text-st-inactive ring-st-inactive/25', 'bg-st-inactive'],
        'suspended' => ['Suspended', 'bg-st-inactive/10 text-st-inactive ring-st-inactive/25', 'bg-st-inactive'],
        'draft' => ['Draft', 'bg-glass-100 text-ink-soft ring-glass-300', 'bg-ink-soft'],
        'published' => ['Published', 'bg-st-active/10 text-st-active ring-st-active/25', 'bg-st-active'],
    ];
    [$label, $classes, $dot] = $map[$status] ?? [ucfirst((string) $status), 'bg-glass-100 text-ink-soft ring-glass-300', 'bg-ink-soft'];
    $sizing = $size === 'lg' ? 'px-3.5 py-1.5 text-sm' : 'px-2.5 py-0.5 text-xs';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-full font-bold ring-1 ring-inset $classes $sizing"]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}"></span>{{ $label }}
</span>
