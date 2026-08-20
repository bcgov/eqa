<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const SUPER_ADMIN = 'Super Admin';

    public const MINISTRY_ADMIN = 'Ministry Admin';

    public const INSTITUTION_ADMIN = 'Institution Admin';

    public const MINISTRY_USER = 'Ministry User';

    public const INSTITUTION_USER = 'Institution User';

    public const MINISTRY_GUEST = 'Ministry Guest';

    public const INSTITUTION_GUEST = 'Institution Guest';

    protected $fillable = ['name'];

    /** Ministry-side roles that grant access to the admin portal. */
    public const MINISTRY_ROLES = [
        self::SUPER_ADMIN,
        self::MINISTRY_ADMIN,
        self::MINISTRY_USER,
    ];

    /** Institution-side roles that grant access to the web portal. */
    public const INSTITUTION_ROLES = [
        self::SUPER_ADMIN,
        self::INSTITUTION_ADMIN,
        self::INSTITUTION_USER,
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }
}
