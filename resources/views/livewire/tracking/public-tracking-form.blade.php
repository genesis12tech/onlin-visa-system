<div>
    @if($result)
        <p>{{ $result->visaType->name }}</p>
        @foreach($result->statusHistories as $history)
            <p>{{ $history->public_label }}</p>
        @endforeach
    @endif
    @if($notFound)
        <p>We could not find an application with that tracking number.</p>
    @endif
</div>
