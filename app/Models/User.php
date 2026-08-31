<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements FilamentUser, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
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
        'role',
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

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canUseSemphony();
    }

    public function canUseSemphony(): bool
    {
        $email = Str::lower($this->email);

        return $this->isAdmin()
            || str_ends_with($email, '@tue.nl')
            || str_ends_with($email, '@rozenlicht.nl')
            || str_ends_with($email, '@student.tue.nl');
    }

    public function canAttachSemphonySample(Sample $sample): bool
    {
        return $this->canUseSemphony()
            && ($this->isAdmin() || $this->semphonyAuthorizedSamples()->whereKey($sample->getKey())->exists());
    }

    public function starredSourceMaterials(): BelongsToMany
    {
        return $this->belongsToMany(SourceMaterial::class, 'source_material_user')->withTimestamps();
    }

    public function starredSamples(): BelongsToMany
    {
        return $this->belongsToMany(Sample::class, 'sample_user')->withTimestamps();
    }

    public function semphonyAuthorizedSamples(): BelongsToMany
    {
        return $this->belongsToMany(Sample::class, 'sample_semphony_user')->withTimestamps();
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user is a regular user.
     */
    public function isRegularUser(): bool
    {
        return $this->role === 'user';
    }
}
