@if ($paginator->hasPages())
    <nav class="mt-5 flex items-center justify-between gap-3 text-sm" aria-label="Pagination">
        <p class="text-ink-soft">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn-line btn-sm opacity-40">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-line btn-sm">Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-line btn-sm">Next</a>
            @else
                <span class="btn-line btn-sm opacity-40">Next</span>
            @endif
        </div>
    </nav>
@endif
