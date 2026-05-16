@if(session('success'))
    <div class="fixed top-4 right-4 z-50 max-w-sm w-full">
        <x-alert type="success">{{ session('success') }}</x-alert>
    </div>
@endif

@if(session('error'))
    <div class="fixed top-4 right-4 z-50 max-w-sm w-full">
        <x-alert type="error">{{ session('error') }}</x-alert>
    </div>
@endif
