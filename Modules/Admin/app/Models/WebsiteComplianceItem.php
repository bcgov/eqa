<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A Website Compliance requirement the institution answers with a link and/or a comment. */
class WebsiteComplianceItem extends Model
{
    public const APPLIES_TO_ALL = 'all';

    /** Only shown when the institution's QA is met through PTIRU Designation. */
    public const APPLIES_TO_PTIRU = 'ptiru';

    protected $table = 'website_compliance_items';

    protected $fillable = ['section_id', 'label', 'help_text', 'applies_to', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(WebsiteComplianceSection::class, 'section_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ApplicationWebCompliance::class, 'website_compliance_item_id');
    }
}
