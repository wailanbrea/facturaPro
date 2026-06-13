<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Active permission slugs, resolved once per instance.
     *
     * @var array<int, string>|null
     */
    private ?array $permissionSlugs = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function fiscalProfiles(): BelongsToMany
    {
        return $this->belongsToMany(FiscalProfile::class);
    }

    /**
     * Active fiscal profiles (companies) this user may invoice with.
     *
     * If the user has no explicit assignment, every active profile is allowed
     * (backwards compatible: nobody gets locked out until assignments exist).
     *
     * @return \Illuminate\Support\Collection<int, FiscalProfile>
     */
    public function availableFiscalProfiles(): \Illuminate\Support\Collection
    {
        $assigned = $this->fiscalProfiles()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($assigned->isNotEmpty()) {
            return $assigned;
        }

        return FiscalProfile::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * IDs of the fiscal profiles this user may invoice with.
     *
     * @return array<int, int>
     */
    public function availableFiscalProfileIds(): array
    {
        return $this->availableFiscalProfiles()->pluck('id')->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionSlugs(), true);
    }

    /**
     * @param array<int, string> $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return array_intersect($permissions, $this->permissionSlugs()) !== [];
    }

    /**
     * Distinct permission slugs granted through the user's active roles.
     *
     * Memoized so repeated checks within a single request (e.g. several
     * `@can`-style calls in a Blade view, or the permission middleware)
     * resolve from one query instead of one query per permission.
     *
     * @return array<int, string>
     */
    private function permissionSlugs(): array
    {
        if ($this->permissionSlugs !== null) {
            return $this->permissionSlugs;
        }

        return $this->permissionSlugs = $this->roles()
            ->where('roles.is_active', true)
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn (Role $role): \Illuminate\Support\Collection => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    public function createdInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function updatedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'updated_by');
    }

    public function createdTechnicalReports(): HasMany
    {
        return $this->hasMany(TechnicalReport::class, 'created_by');
    }

    public function updatedTechnicalReports(): HasMany
    {
        return $this->hasMany(TechnicalReport::class, 'updated_by');
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class, 'created_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
