<?php

use Livewire\Features\SupportTesting\Testable;
use Livewire\Testing\TestableLivewire;

require_once __DIR__.'/../vendor/autoload.php';

// Livewire v3 renamed TestableLivewire to Livewire\Features\SupportTesting\Testable.
// Create the legacy alias so test type hints still resolve.
if (! class_exists(TestableLivewire::class)) {
    class_alias(Testable::class, TestableLivewire::class);
}
