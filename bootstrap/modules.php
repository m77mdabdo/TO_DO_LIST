<?php

declare(strict_types=1);

/*
 | The modules this app runs, in boot order.
 | A module can only use modules listed before it at boot time,
 | and only through their Contract\ namespace or their events.
 */

return [
    App\Modules\Identity\IdentityModule::class,
];
