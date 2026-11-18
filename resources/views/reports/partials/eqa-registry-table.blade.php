<table>
    <thead>
        <tr>
            <th class="num" style="width: 16pt"></th>
            <th style="width: 166pt">Institution Name</th>
            <th style="width: 130pt">Address 1</th>
            <th style="width: 114pt">Website</th>
            <th style="width: 61pt">{!! str_replace(' ', '<br>', e($dateLabel)) !!}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="num">{{ $row['number'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="address">@foreach ($row['address'] as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</td>
                <td class="website">@if ($row['website_url'])<a href="{{ $row['website_url'] }}">@foreach ($row['website_lines'] as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</a>@endif</td>
                <td class="date">{{ $row['date'] }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No institutions to list.</td></tr>
        @endforelse
    </tbody>
</table>
