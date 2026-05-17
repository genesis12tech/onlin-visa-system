<div>
    @foreach($documents as $document)
        <p>{{ $document->documentType->name }}</p>
        @if($document->rejection_reason)
            <p>{{ $document->rejection_reason }}</p>
        @endif
    @endforeach
</div>
