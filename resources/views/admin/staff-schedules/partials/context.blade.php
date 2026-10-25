<input type="hidden" name="week" value="{{ $start->toDateString() }}">
<input type="hidden" name="expected_revision" value="{{ $revision }}">
@foreach (\Illuminate\Support\Arr::only($filters, ['q', 'staff_profile_id', 'page', 'view']) as $key => $value)
    <input type="hidden" name="return_filters[{{ $key }}]" value="{{ $value }}">
@endforeach
