<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdeaState;
use Database\Factories\IdeaFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $state
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Idea newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Idea newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Idea query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Idea whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Idea whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Idea whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Idea whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Idea whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Idea extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => IdeaState::class,
        'links' => AsArrayObject::class,
    ];

    // initial attributes set for the model
    protected $attributes = [
        'state' => IdeaState::PENDING->value,
        'links' => '[]',
    ];

    /** @use HasFactory<IdeaFactory> */
    use HasFactory, Notifiable;

    public static function getStateCounts(User $user): Collection
    {
        $statusCounts = $user->ideas()->selectRaw('state, count(*) as count')->groupBy('state')->pluck('count', 'state');
        $statusCounts['all'] = $statusCounts->sum();

        /**
        collect(IdeaState::cases())
        ->mapWithKeys(fn($state) => [
             $state->value => $statusCounts->get($state->value, 0),
        ])
             ->put('all', Auth::user()->ideas()->count());

         **/
        return $statusCounts;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Step, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(Step::class);
    }
}
