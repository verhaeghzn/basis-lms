<div class="sample-composition">
    @foreach ($entries as $entry)
        <div>
            <span>{{ $entry['label'] }}</span>
            <span>{{ $entry['value'] }}</span>
        </div>
    @endforeach
</div>
