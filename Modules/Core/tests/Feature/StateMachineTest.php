<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Concerns\HasStateMachine;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Customer\Models\Customer;
use Modules\User\Models\User;

class StateMachineTestModel extends Model
{
    use HasStateMachine;

    protected $table = 'state_machine_test_model';

    protected $guarded = [];

    protected static array $transitions = [
        'draft' => ['pending_qc'],
        'pending_qc' => ['live', 'rejected'],
        'rejected' => ['pending_qc'],
        'live' => ['paused'],
        'paused' => ['live'],
        '*' => ['blocked'],
    ];

    protected array $transitionEvents = ['live' => StateMachineTestWentLive::class];
}

class StateMachineTestWentLive
{
    public function __construct(public Model $model, public ?string $from, public string $to) {}
}

beforeEach(function () {
    // A temporary table: no implicit commit, so the test transaction still rolls everything back.
    Schema::create('state_machine_test_model', function (Blueprint $table) {
        $table->temporary();
        $table->id();
        $table->string('status', 40);
        $table->timestamps();
    });
});

test('allowed transitions are accepted and others refused', function () {
    $model = StateMachineTestModel::create(['status' => 'draft']);

    expect($model->canTransitionTo('pending_qc'))->toBeTrue()
        ->and($model->canTransitionTo('live'))->toBeFalse()
        ->and($model->canTransitionTo('draft'))->toBeFalse()
        ->and($model->canTransitionTo('blocked'))->toBeTrue();
});

test('a transition updates the column and writes a history row', function () {
    $model = StateMachineTestModel::create(['status' => 'draft']);

    $model->transitionTo('pending_qc', 'Submitted for review', ['source' => 'wizard']);

    expect($model->fresh()->status)->toBe('pending_qc');

    $history = $model->statusHistories()->sole();

    expect($history)
        ->from_status->toBe('draft')
        ->to_status->toBe('pending_qc')
        ->reason->toBe('Submitted for review')
        ->meta->toBe(['source' => 'wizard'])
        ->actor_type->toBe('system')
        ->actor_id->toBeNull();
});

test('a disallowed transition throws and changes nothing', function () {
    $model = StateMachineTestModel::create(['status' => 'draft']);

    expect(fn () => $model->transitionTo('live'))->toThrow(InvalidStateTransition::class);

    expect($model->fresh()->status)->toBe('draft')
        ->and($model->statusHistories()->count())->toBe(0);
});

test('the actor is recorded with its configured type', function () {
    $model = StateMachineTestModel::create(['status' => 'pending_qc']);
    $admin = (new User)->forceFill(['id' => 7]);
    $customer = (new Customer)->forceFill(['id' => 9]);

    $model->transitionTo('rejected', 'Blurry images', actor: $admin);
    $model->transitionTo('pending_qc', actor: $customer);
    $model->transitionTo('blocked', actorType: 'courier');

    expect($model->statusHistories()->get(['actor_type', 'actor_id', 'to_status'])->toArray())->toBe([
        ['actor_type' => 'admin', 'actor_id' => 7, 'to_status' => 'rejected'],
        ['actor_type' => 'customer', 'actor_id' => 9, 'to_status' => 'pending_qc'],
        ['actor_type' => 'courier', 'actor_id' => null, 'to_status' => 'blocked'],
    ]);
});

test('the event mapped to the target state is dispatched', function () {
    Event::fake([StateMachineTestWentLive::class]);
    $model = StateMachineTestModel::create(['status' => 'pending_qc']);

    $model->transitionTo('live');

    Event::assertDispatched(StateMachineTestWentLive::class, fn ($event) => $event->from === 'pending_qc' && $event->to === 'live');
});
