<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A heading of the Website Compliance checklist (Maintenance > Website Compliance). */
class WebsiteComplianceSection extends Model
{
    protected $table = 'website_compliance_sections';

    protected $fillable = ['title', 'intro', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(WebsiteComplianceItem::class, 'section_id')->orderBy('sort_order')->orderBy('id');
    }
}
