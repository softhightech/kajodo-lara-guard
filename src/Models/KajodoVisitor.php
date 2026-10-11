<?php

namespace Kajodo\Guard\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistent record of one visitor lookup.
 *
 * The table is the long-term memory: when a visitor IP is already stored
 * and fresh, no Kajodo API call is made at all.
 *
 * @property string $ip
 * @property string $decision
 * @property ?string $reason
 * @property ?string $verdict
 * @property ?string $country_code
 * @property ?string $located_country_code
 * @property ?string $country
 * @property ?string $city
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class KajodoVisitor extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'ip';

    protected $fillable = [
        'ip',
        'decision',
        'reason',
        'verdict',
        'country_code',
        'located_country_code',
        'country',
        'city',
    ];

    public function getTable(): string
    {
        return (string) config('kajodo-guard.table', parent::getTable() ?? 'kajodo_visitors');
    }
}
