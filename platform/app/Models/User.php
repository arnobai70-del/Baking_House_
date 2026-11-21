<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Enums\StaffPermission;
use App\Models\Concerns\HasCrmRecords;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret', 'mfa_last_timestep'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasCrmRecords, HasFactory, Notifiable;

    /**
     * Permission names resolved once per request.
     *
     * @var array<string, true>|null
     */
    private ?array $permissionCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'datetime',
            'mfa_last_timestep' => 'integer',
        ];
    }

    /**
     * Enrolled = a confirmed secret exists. This is NOT proof of authentication;
     * a code must still be verified in the current session (App\Services\Mfa\MfaSession).
     */
    public function hasMfaEnrolled(): bool
    {
        return $this->mfa_confirmed_at !== null && $this->mfa_secret !== null;
    }

    /**
     * Saved delivery addresses (retail checkout), default first.
     *
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->orderBy('label');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot('role')->withTimestamps();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->loadMissing('roles')->roles->contains('name', $roleName);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin);
    }

    public function isStaff(): bool
    {
        return $this->hasPermission(StaffPermission::AccessAdmin);
    }

    public function hasPermission(StaffPermission $permission): bool
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = $this->roles()
                ->with('permissions:id,name')
                ->get()
                ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
                ->mapWithKeys(fn (string $name) => [$name => true])
                ->all();
        }

        return isset($this->permissionCache[$permission->value]);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    /**
     * Approved organizations of one type that this user belongs to.
     *
     * @return Collection<int, Organization>
     */
    public function approvedOrganizations(OrganizationType $type): Collection
    {
        return $this->organizations()
            ->where('type', $type->value)
            ->where('status', OrganizationStatus::Approved->value)
            ->get();
    }

    public function roleIn(Organization $organization): ?OrganizationRole
    {
        $membership = $this->organizations()->whereKey($organization->getKey())->first();

        return $membership ? OrganizationRole::from($membership->pivot->role) : null;
    }
}
