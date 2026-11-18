<x-layouts.app title="Export log">
    <x-page-header title="Export log" subtitle="Every CSV that includes member contact details is recorded here." :back="[route('admin.settings.edit'), 'Settings']" />

    <div class="panel relative overflow-x-auto">
        <table class="table-clean min-w-[36rem]">
            <thead><tr><th>When</th><th>Who</th><th>What</th><th class="text-right">Rows</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap">{{ $log->created_at->format('j M Y, g:i A') }}</td>
                        <td>{{ $log->user?->full_name ?? 'Deleted account' }}<span class="block text-xs text-ink-soft">{{ $log->ip_address }}</span></td>
                        <td>{{ ucfirst($log->export_type) }}<span class="block text-xs text-ink-soft">{{ $log->filters }}</span></td>
                        <td class="text-right">{{ $log->row_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-10 text-center text-ink-soft">No exports yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-layouts.app>
