@foreach ($blocks as $block)
    <div class="sample-block">
        @if (($block['kind'] ?? null) === 'prose')
            <h3>{{ $block['label'] }}</h3>
            <p class="sample-prose-copy">{{ $block['text'] }}</p>
        @elseif (($block['kind'] ?? null) === 'table')
            <h3>{{ $block['label'] }}</h3>
            <div class="sample-table-wrap">
                <table class="sample-table">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach ($block['columns'] as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($block['table'] as $row)
                            <tr>
                                <th>{{ $row['label'] }}</th>
                                @foreach ($row['cells'] as $cell)
                                    <td @class(['sample-muted' => $cell === '—'])>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif (! empty($block['rows']))
            @if (! empty($block['label']))
                <h3>{{ $block['label'] }}</h3>
            @endif
            <dl class="sample-dl">
                @foreach ($block['rows'] as $row)
                    <dt>{{ $row['label'] }}</dt>
                    <dd>{{ $row['value'] }}</dd>
                @endforeach
            </dl>
        @endif
    </div>
@endforeach
