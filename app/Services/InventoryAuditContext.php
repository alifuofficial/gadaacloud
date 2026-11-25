<?php

namespace App\Services;

/**
 * Carries explicit stock mutation context (action_type, reference_type, reference_id, notes)
 * so WarehouseStock observers record exact audit trail attribution without brittle URL matching.
 */
class InventoryAuditContext
{
    /** @var array<int, array{action_type: string, reference_type: ?string, reference_id: ?int, notes: ?string, suppress: bool}> */
    protected static array $stack = [];

    public static function push(
        string $actionType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        bool $suppressObserver = false
    ): void {
        self::$stack[] = [
            'action_type'    => $actionType,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'notes'          => $notes,
            'suppress'       => $suppressObserver,
        ];
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    public static function current(): ?array
    {
        return empty(self::$stack) ? null : end(self::$stack);
    }

    /**
     * Execute a callback within a scoped inventory audit context.
     */
    public static function run(
        string $actionType,
        ?string $referenceType,
        ?int $referenceId,
        callable $callback,
        ?string $notes = null,
        bool $suppressObserver = false
    ): mixed {
        self::push($actionType, $referenceType, $referenceId, $notes, $suppressObserver);
        try {
            return $callback();
        } finally {
            self::pop();
        }
    }
}
