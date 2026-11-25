<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An application's copy of one Website Compliance item (text as it was when saved) with the
 * institution's link and/or comment. Later checklist changes never alter it.
 */
class ApplicationWebCompliance extends Model
{
    protected $table = 'application_web_compliances';

    protected $fillable = [
        'application_crm_id', 'website_compliance_item_id', 'section_title', 'section_intro', 'section_order',
        'item_label', 'item_help_text', 'item_order', 'url', 'comment',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_crm_id', 'crm_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(WebsiteComplianceItem::class, 'website_compliance_item_id');
    }
}
