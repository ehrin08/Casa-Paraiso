<ul class="schedules-conflicts">
    @foreach ($conflicts as $conflict)
        <li>
            <a href="{{ $conflict['url'] }}">{{ $conflict['number'] }}</a>
            <strong>{{ $conflict['therapist'] }}</strong>
            <span>{{ $conflict['starts_at'] ? \Illuminate\Support\Carbon::parse($conflict['starts_at'], 'Asia/Manila')->format('M j, Y · g:i A') : __('Missing schedule') }}@if($conflict['ends_at']) – {{ \Illuminate\Support\Carbon::parse($conflict['ends_at'], 'Asia/Manila')->format('H:i') === '00:00' ? __('12:00 midnight') : \Illuminate\Support\Carbon::parse($conflict['ends_at'], 'Asia/Manila')->format('g:i A') }}@endif</span>
            <p>{{ $conflict['reason'] }}</p>
        </li>
    @endforeach
</ul>
