<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds the auth `users` table (BCeID accounts) from the institution contacts
 * migrated out of Dynamics (`institution_users`). Every contact becomes a user
 * granted the "Institution User" role, so the ministry can then elevate or
 * deactivate them from the institution's Staff grid.
 *
 * Identity is the BCeID logon (institution_users.bceid_username); contacts
 * without a logon fall back to their email, then their CRM id. Because the
 * `users.email` column is UNIQUE and the legacy/test data reuses placeholder
 * emails across many contacts, a unique address is synthesised when the real
 * email is already taken. Idempotent: an existing user (matched on BCeID logon,
 * else email) is reused and never downgraded.
 */
class MigrateInstitutionContactsToUsers extends Command
{
    protected $signature = 'institution-users:migrate
        {--institution= : Limit to a single institution crm_id}';

    protected $description = 'Create user accounts (role: Institution User) for institution contacts.';

    public function handle(): int
    {
        if (! Schema::hasTable('institution_users')) {
            $this->error('institution_users table not found.');

            return self::FAILURE;
        }

        $role = Role::firstOrCreate(['name' => Role::INSTITUTION_USER]);

        $query = DB::table('institution_users');

        if ($institution = $this->option('institution')) {
            $query->where('institution_crm_id', $institution);
        }

        $contacts = $query->orderBy('full_name')->get();

        // Preload existing users for idempotency + email uniqueness.
        $existing = User::all();
        $usersByBceid = $existing->filter(fn (User $u): bool => filled($u->bceid_username))
            ->keyBy(fn (User $u): string => Str::upper((string) $u->bceid_username));
        $usersByEmail = $existing->filter(fn (User $u): bool => blank($u->bceid_username))
            ->keyBy(fn (User $u): string => Str::lower((string) $u->email));
        $usedEmails = [];
        foreach ($existing as $u) {
            $usedEmails[Str::lower((string) $u->email)] = true;
        }

        $created = 0;
        $linked = 0;
        $seen = [];
        $linkedUserIds = [];

        foreach ($contacts as $contact) {
            $bceid = $this->bceidUsername($contact);
            $email = Str::lower(trim((string) $contact->email)) ?: null;

            if ($bceid !== null) {
                $identity = 'b:'.$bceid;
            } elseif ($email !== null) {
                $identity = 'e:'.$email;
            } else {
                $identity = 'c:'.Str::lower((string) $contact->crm_id);
            }

            $user = $seen[$identity] ?? null;

            if ($user === null && $bceid !== null && $usersByBceid->has($bceid)) {
                $user = $usersByBceid->get($bceid);
            } elseif ($user === null && $bceid === null && $email !== null && $usersByEmail->has($email)) {
                $user = $usersByEmail->get($email);
            }

            if ($user === null) {
                $storedEmail = ($email !== null && ! isset($usedEmails[$email]))
                    ? $email
                    : $this->uniqueEmail((string) ($bceid ?? $contact->crm_id), $usedEmails);

                $user = User::create([
                    'guid' => Str::of((string) Str::orderedUuid())->replace('-', '')->toString(),
                    'name' => $contact->full_name
                        ?: (trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: $storedEmail),
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'email' => $storedEmail,
                    'password' => Hash::make(Str::random(40)),
                    'bceid_username' => $bceid,
                    'bceid_user_guid' => $contact->bceid_user_guid,
                    'bceid_business_guid' => $contact->bceid_business_guid,
                    'disabled' => ! $this->isActive($contact),
                ]);
                $created++;

                $usedEmails[Str::lower($storedEmail)] = true;
                if ($bceid !== null) {
                    $usersByBceid->put($bceid, $user);
                } elseif ($email !== null) {
                    $usersByEmail->put($email, $user);
                }
            }

            $seen[$identity] = $user;

            if (! isset($linkedUserIds[$user->id])) {
                if (! $user->hasAnyRole(Role::INSTITUTION_ROLES)) {
                    $user->roles()->syncWithoutDetaching([$role->id]);
                    $linked++;
                }
                $linkedUserIds[$user->id] = true;
            }
        }

        $this->info("Institution contacts migrated — users created: {$created}, roles linked: {$linked}.");

        return self::SUCCESS;
    }

    /**
     * Contact activity from the Dynamics record status (well populated), falling
     * back to the portal web-user flag.
     */
    private function isActive(object $contact): bool
    {
        $status = Str::lower(trim((string) ($contact->status ?? '')));
        if ($status === 'active') {
            return true;
        }
        if ($status === 'inactive') {
            return false;
        }

        $flag = $contact->web_user_active ?? null;

        return $flag === true || $flag === 't' || $flag === 1 || $flag === '1';
    }

    /**
     * A unique, deterministic email for a contact whose real address is already
     * taken (placeholder emails are reused across many legacy/test contacts).
     *
     * @param  array<string, bool>  $usedEmails
     */
    private function uniqueEmail(string $base, array &$usedEmails): string
    {
        $slug = Str::lower((string) preg_replace('/[^A-Za-z0-9]/', '', $base)) ?: 'user';
        $candidate = $slug.'@bceid.local';
        $n = 1;
        while (isset($usedEmails[$candidate])) {
            $n++;
            $candidate = $slug.'-'.$n.'@bceid.local';
        }

        return $candidate;
    }

    /**
     * The BCeID logon for the auth account. Prefer the real portal-profile logon
     * (institution_users.bceid_username, from eqa_portalprofile.eqa_BCeID); fall
     * back to the legacy contact web user name when a profile logon is absent.
     */
    private function bceidUsername(object $contact): ?string
    {
        $username = trim((string) ($contact->bceid_username ?? '')) ?: trim((string) ($contact->web_user_name ?? ''));

        return $username !== '' ? Str::upper($username) : null;
    }
}
