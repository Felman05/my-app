<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_TOURIST = 'tourist';

    public const ROLE_PROVIDER = 'provider';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_LGU = 'lgu';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'role',
        'role_id',
        'phone',
        'profile_photo',
        'travel_style',
        'is_active',
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
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function getNameAttribute(?string $value): string
    {
        if (! empty($value)) {
            return $value;
        }

        return trim(($this->attributes['first_name'] ?? '').' '.($this->attributes['last_name'] ?? ''));
    }

    public function getRoleAttribute(?string $value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        $roleName = $this->roleRelation?->name;

        if ($roleName === 'lgu_manager') {
            return self::ROLE_LGU;
        }

        return $roleName;
    }

    public static function roles(): array
    {
        return [
            self::ROLE_TOURIST,
            self::ROLE_PROVIDER,
            self::ROLE_ADMIN,
            self::ROLE_LGU,
        ];
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'provider_id');
    }

    public function itineraries()
    {
        return $this->hasMany(Itinerary::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function platformNotifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }
}
