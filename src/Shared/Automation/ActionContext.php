<?php

declare(strict_types=1);

namespace App\Shared\Automation;

/**
 * Who an automation acts as and on what. Every action still goes through the
 * owning module's normal use case, so the actor's permissions are checked
 * exactly as if they had clicked it themselves.
 */
final class ActionContext
{
    public function __construct(
        /** The person whose permissions the action uses: the rule's author, or whoever pressed a button. */
        public readonly int $actorId,
        public readonly int $boardId,
        public readonly ?int $cardId,
        public readonly ?int $listId,
        /** The person whose action started the automation (the same as the actor for buttons and schedules). */
        public readonly int $triggeredBy,
        public readonly string $automationName,
        /** Unique per run, so a notice is never sent twice for the same run. */
        public readonly string $runKey,
    ) {
    }

    /** "me", "trigger" or a user id, as the person it means. */
    public function person(string $value): int
    {
        return match ($value) {
            'me'      => $this->actorId,
            'trigger' => $this->triggeredBy,
            default   => (int) $value,
        };
    }

    public function withCard(int $cardId, int $listId): self
    {
        return new self($this->actorId, $this->boardId, $cardId, $listId, $this->triggeredBy, $this->automationName, $this->runKey . ':' . $cardId);
    }

    public function withList(int $listId): self
    {
        return new self($this->actorId, $this->boardId, null, $listId, $this->triggeredBy, $this->automationName, $this->runKey . ':l' . $listId);
    }
}
