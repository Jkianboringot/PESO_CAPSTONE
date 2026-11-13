<table class="nobr">
    <thead>
        <tr>
            <th style="width:40%;">{{ $label }}</th>
            <th style="width:12%;" class="num">Count</th>
            <th style="width:10%;" class="num">Share</th>
            <th style="width:38%;">Distribution</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="num">{{ number_format($row['count']) }}</td>
                <td class="num">{{ $row['pct'] }}%</td>
                <td>
                    <div class="bar-wrap">
                        <div class="bar" style="width: {{ min(100, $row['pct']) }}%;"></div>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No records.</td></tr>
        @endforelse
    </tbody>
</table>
