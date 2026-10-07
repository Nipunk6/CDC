<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\PasswordResetLinkMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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
        'is_super_admin',
        'is_active',
        'company_id',
        'first_name',
        'middle_name',
        'last_name',
        'designation',
        'mobile',
        'alias',
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
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'invited_at' => 'datetime',
            'last_invited_at' => 'datetime',
            'invite_count' => 'integer',
            'activated_at' => 'datetime',
            'invite_revoked_at' => 'datetime',
        ];
    }

    /**
     * Invitation status of a student account (S5): `accepted` once a password was set, `revoked` when the CDC
     * cancelled the link before that, otherwise `sent`.
     */
    public function invitationStatus(): string
    {
        if ($this->activated_at !== null) {
            return 'accepted';
        }

        return $this->invite_revoked_at !== null ? 'revoked' : 'sent';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PortalNotification::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(FormStatusHistory::class, 'changed_by');
    }

    public function sendPasswordResetNotification($token): void
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $query = http_build_query([
            'token' => (string) $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);

        // Students get the roll-number flavour of the page so they land on the student login afterwards (D53).
        $path = $this->role === 'student' ? '/auth/student/set-password' : '/auth/reset-password';

        Mail::to($this->email)->send(new PasswordResetLinkMail(
            name: (string) $this->name,
            resetUrl: "{$frontendUrl}{$path}?{$query}",
        ));
    }
}
