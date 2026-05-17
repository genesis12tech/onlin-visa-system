<div>
    @foreach($feeData['items'] as $item)
        <p>{{ $item['description'] }}</p>
    @endforeach
    @if($feeData['has_priority_option'])
        <p>Priority Processing</p>
    @endif
</div>
