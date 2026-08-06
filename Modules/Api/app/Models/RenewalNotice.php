<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Api\Database\Factories\RenewalNoticeFactory;

/**
 * @property int $renewal_notice_id
 * @property int $application_id
 * @property int $contact_id
 * @property int $notice_period
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class RenewalNotice extends Model
{
    protected $table = 'renewal_notice';

    protected $primaryKey = 'renewal_notice_id';

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'contact_id',
        'notice_period',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'renewal_notice_id' => 'integer',
            'application_id' => 'integer',
            'contact_id' => 'integer',
            'notice_period' => 'integer',
            'created_at' => 'date',
        ];
    }

    protected static function newFactory(): RenewalNoticeFactory
    {
        return RenewalNoticeFactory::new();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}