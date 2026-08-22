<?php

namespace App\Models;

use App\Search\SearchOutcome;
use Database\Factories\AiSearchLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 * @property string $question
 * @property string|null $interpretation
 * @property array<string, mixed>|null $query_json
 * @property string|null $compiled_sql
 * @property int|null $row_count
 * @property int $attempts
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $estimated_cost_cents
 * @property SearchOutcome $outcome
 * @property int $duration_ms
 * @property Carbon|null $created_at
 * @property-read User $user
 * @property-read Company|null $company
 */
#[Fillable([
    'user_id',
    'company_id',
    'question',
    'interpretation',
    'query_json',
    'compiled_sql',
    'row_count',
    'attempts',
    'input_tokens',
    'output_tokens',
    'estimated_cost_cents',
    'outcome',
    'duration_ms',
])]
class AiSearchLog extends Model
{
    /** @use HasFactory<AiSearchLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempts' => 0,
        'input_tokens' => 0,
        'output_tokens' => 0,
        'estimated_cost_cents' => 0,
        'duration_ms' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query_json' => 'array',
            'row_count' => 'integer',
            'attempts' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_cents' => 'integer',
            'outcome' => SearchOutcome::class,
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
