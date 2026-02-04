<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Dispatch Extension Points as Laravel Events
    |--------------------------------------------------------------------------
    |
    | When enabled, extension points are also dispatched through Laravel's
    | event system after all handlers have run. This allows you to use
    | standard Laravel event listeners alongside extension handlers.
    |
    */
    'dispatch_as_events' => true,

    /*
    |--------------------------------------------------------------------------
    | Clear Registry on Octane Request Termination
    |--------------------------------------------------------------------------
    |
    | When running under Laravel Octane, you may want to clear the handler
    | registry between requests if handlers are registered per-request.
    |
    | For most applications, leave this disabled since handlers are typically
    | registered once in service providers.
    |
    */
    'clear_on_octane_terminate' => false,
];
