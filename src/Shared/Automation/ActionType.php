<?php

declare(strict_types=1);

namespace App\Shared\Automation;

use Closure;

/**
 * One thing an automation can do, offered by the module that owns it
 * (Card moves cards, Label adds labels, Notification sends notices...).
 *
 * $param says what the action needs, so the automation page can offer the
 * right choices without knowing the module:
 *   none   - nothing ("Mark the card complete")
 *   list   - a list id on the board
 *   label  - a label id on the board
 *   person - "me" (whoever the automation acts as), "trigger" (the person
 *            whose action started it) or a user id
 *   days   - a whole number of days
 *   text   - free text (a comment, a message, checklist lines)
 *
 * $target is "card" for actions on one card, or "list" for actions on a
 * whole list (sorting). The runner is called with the context and the value.
 */
final class ActionType
{
    public const PARAMS = ['none', 'list', 'label', 'person', 'days', 'text'];

    /** @param Closure(ActionContext, string): void $run */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $param,
        public readonly Closure $run,
        public readonly string $target = 'card',
    ) {
    }
}
