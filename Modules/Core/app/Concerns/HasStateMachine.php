<?php

declare(strict_types=1);

namespace Modules\Core\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use LogicException;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Core\Models\StatusHistory;

/**
 * Guarded status transitions with an audit row per change.
 *
 * The using model declares its graph, and optionally the column and events:
 *
 *     protected static array $transitions = [
 *         'draft' => ['pending_qc'],
 *         'pending_qc' => ['live', 'rejected'],
 *         '*' => ['blocked'],              // reachable from any state
 *     ];
 *     protected string $stateColumn = 'status';                       // default "status"
 *     protected array $transitionEvents = ['live' => ProductWentLive::class];
 *
 * Event classes are constructed as new Event($model, $from, $to).
 *
 * transitionTo() does not open a transaction: wrap it in the caller's DB::transaction()
 * together with the other writes of the same business operation.
 *
 * @mixin Model
 */
trait HasStateMachine
{
    /**
     * @return array<string, list<string>>
     */
    public static function transitions(): array
    {
        return property_exists(static::class, 'transitions') ? static::$transitions : [];
    }

    public function stateColumn(): string
    {
        return property_exists($this, 'stateColumn') ? $this->stateColumn : 'status';
    }

    public function currentState(): ?string
    {
        $state = $this->getAttribute($this->stateColumn());

        return $state instanceof BackedEnum ? (string) $state->value : ($state === null ? null : (string) $state);
    }

    public function canTransitionTo(string|BackedEnum $to): bool
    {
        $to = $to instanceof BackedEnum ? (string) $to->value : $to;
        $transitions = static::transitions();
        $allowed = array_merge($transitions[$this->currentState() ?? ''] ?? [], $transitions['*'] ?? []);

        return $to !== $this->currentState() && in_array($to, $allowed, true);
    }

    /**
     * Move to a new state, write the history row and dispatch the state's event.
     *
     * @param  array<string, mixed>  $meta
     * @param  string|null  $actorType  overrides the type derived from $actor, e.g. "courier"
     *
     * @throws InvalidStateTransition
     */
    public function transitionTo(
        string|BackedEnum $to,
        ?string $reason = null,
        array $meta = [],
        ?Model $actor = null,
        ?string $actorType = null,
    ): static {
        if (! $this->exists) {
            throw new LogicException('Save the model before changing its state.');
        }

        $to = $to instanceof BackedEnum ? (string) $to->value : $to;
        $from = $this->currentState();

        if (! $this->canTransitionTo($to)) {
            throw new InvalidStateTransition($this, $from, $to);
        }

        $this->setAttribute($this->stateColumn(), $to);
        $this->save();

        $this->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType ?? $this->statusActorType($actor),
            'actor_id' => $actor?->getKey(),
            'reason' => $reason,
            'meta' => $meta === [] ? null : $meta,
        ]);

        $event = property_exists($this, 'transitionEvents') ? ($this->transitionEvents[$to] ?? null) : null;

        if ($event !== null) {
            event(new $event($this, $from, $to));
        }

        return $this;
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'model')->orderBy('id');
    }

    protected function statusActorType(?Model $actor): string
    {
        if ($actor === null) {
            return 'system';
        }

        $types = (array) config('core.state_machine.actor_types', []);

        foreach ($types as $class => $type) {
            if ($actor instanceof $class) {
                return $type;
            }
        }

        return Str::limit(Str::snake(class_basename($actor)), 20, '');
    }
}
