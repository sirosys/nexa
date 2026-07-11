<?php

return [

    /*
    | Queue connection used specifically for outgoing notifications
    | (App\Notifications\*). Defaults to 'sync' for the same reason as
    | config/otp.php's queue_connection: no queue:work worker runs locally
    | in dev, so a job dispatched to the global QUEUE_CONNECTION would sit
    | in the `jobs` table forever without ever being processed.
    */
    'queue_connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'sync'),

];
