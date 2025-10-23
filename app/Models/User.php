<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Mod;
use App\Models\ModComment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'profile_title',
        'bio',
        'avatar',
        'avatar_type',
        'avatar_preset_id',
        'banner',
        'profile_views',
        'last_activity_at',
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
            'is_admin' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class);
    }

    public function modComments(): HasMany
    {
        return $this->hasMany(ModComment::class);
    }

    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    // Profile relationships
    public function socialLinks(): HasMany
    {
        return $this->hasMany(UserSocialLink::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class)->orderBy('created_at', 'desc');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class)->orderBy('created_at', 'desc');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function bookmarkedMods(): BelongsToMany
    {
        return $this->belongsToMany(Mod::class, 'bookmarks')->withTimestamps();
    }

    // Following relationships
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    // Helper methods
    public function isFollowing(User $user): bool
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function isFollowedBy(User $user): bool
    {
        return $this->followers()->where('follower_id', $user->id)->exists();
    }

    public function hasBookmarked(Mod $mod): bool
    {
        return $this->bookmarkedMods()->where('mod_id', $mod->id)->exists();
    }

    public function isOnline(): bool
    {
        if (!$this->last_activity_at) {
            return false;
        }

        return $this->last_activity_at->diffInMinutes(now()) <= 20;
    }

    public function getLastActiveText(): string
    {
        if (!$this->last_activity_at) {
            return 'Never active';
        }

        return $this->last_activity_at->diffForHumans();
    }

    public function getAvatarUrl(int $size = 256): string
    {
        if ($this->avatar_type === 'custom' && $this->avatar) {
            return asset('storage/' . $this->avatar);
        }

        if ($this->avatar_type === 'preset' && $this->avatar_preset_id) {
            return asset('images/avatars/preset-' . $this->avatar_preset_id . '.png');
        }

        // Default gravatar or placeholder
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&size=' . $size . '&background=ec4899&color=fff';
    }

    public function getBannerUrl(): ?string
    {
        if ($this->banner) {
            return asset('storage/' . $this->banner);
        }

        return null;
    }

    public function incrementProfileViews(): void
    {
        $this->increment('profile_views');
    }

    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function getStatistics(): array
    {
        return [
            'uploads' => $this->mods()->count(),
            'downloads' => $this->mods()->sum('downloads'),
            'likes' => 0, // TODO: Implement likes system
            'comments' => $this->modComments()->count(),
            'followers' => $this->followers()->count(),
            'following' => $this->following()->count(),
        ];
    }
}
