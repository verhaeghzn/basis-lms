<article class="sample-step">
    <div class="sample-step__head">
        <p class="sample-step__name">{{ $step['name'] ?? 'Processing step' }}</p>
        @if (! empty($step['performed_at']))
            <p class="sample-meta">{{ $step['performed_at'] }}</p>
        @endif
    </div>
    @if (! empty($step['content']))
        <p class="sample-step__body">{!! nl2br(e($step['content'])) !!}</p>
    @endif
    @if (! empty($step['description']))
        <p class="sample-meta">{!! nl2br(e($step['description'])) !!}</p>
    @endif
</article>
