<?php

declare(strict_types=1);

/*
 | The modules this app runs. The order is the order their event listeners
 | run in (Workspace gives a user a workspace before LegacyImport fills it).
 | Modules only use each other through Contract\ namespaces or events.
 */

return [
    App\Modules\Identity\IdentityModule::class,
    App\Modules\Workspace\WorkspaceModule::class,
    App\Modules\Board\BoardModule::class,
    App\Modules\Access\AccessModule::class,
    App\Modules\Card\CardModule::class,
    App\Modules\Label\LabelModule::class,
    App\Modules\Checklist\ChecklistModule::class,
    App\Modules\Attachment\AttachmentModule::class,
    App\Modules\Discussion\DiscussionModule::class,
    App\Modules\Invitation\InvitationModule::class,
    App\Modules\Notification\NotificationModule::class,
    App\Modules\Audit\AuditModule::class,
    App\Modules\Activity\ActivityModule::class,
    App\Modules\LegacyImport\LegacyImportModule::class,
];
