<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSocialLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get social link definitions with icons and base URLs
     */
    public static function getPlatformDefinitions(): array
    {
        return [
            'website' => [
                'icon' => 'fas fa-globe',
                'label' => 'Website',
                'prefix' => '',
            ],
            'facebook' => [
                'icon' => 'fab fa-facebook',
                'label' => 'Facebook',
                'prefix' => 'facebook.com/',
                'base_url' => 'https://www.facebook.com/',
            ],
            'x' => [
                'icon' => 'fab fa-x-twitter',
                'label' => 'X (Twitter)',
                'prefix' => 'x.com/',
                'base_url' => 'https://x.com/',
            ],
            'youtube' => [
                'icon' => 'fab fa-youtube',
                'label' => 'YouTube',
                'prefix' => 'youtube.com/',
                'base_url' => 'https://www.youtube.com/',
            ],
            'twitch' => [
                'icon' => 'fab fa-twitch',
                'label' => 'Twitch',
                'prefix' => 'twitch.tv/',
                'base_url' => 'https://www.twitch.tv/',
            ],
            'steam' => [
                'icon' => 'fab fa-steam',
                'label' => 'Steam',
                'prefix' => 'steamcommunity.com/id/',
                'base_url' => 'https://steamcommunity.com/id/',
            ],
            'socialclub' => [
                'icon' => 'fas fa-star',
                'label' => 'Rockstar Social Club',
                'prefix' => 'socialclub.rockstargames.com/member/',
                'base_url' => 'https://socialclub.rockstargames.com/member/',
            ],
            'instagram' => [
                'icon' => 'fab fa-instagram',
                'label' => 'Instagram',
                'prefix' => 'instagram.com/',
                'base_url' => 'https://www.instagram.com/',
            ],
            'github' => [
                'icon' => 'fab fa-github',
                'label' => 'GitHub',
                'prefix' => 'github.com/',
                'base_url' => 'https://github.com/',
            ],
            'discord' => [
                'icon' => 'fab fa-discord',
                'label' => 'Discord',
                'prefix' => 'discord.gg/',
                'base_url' => 'https://discord.gg/',
            ],
            'patreon' => [
                'icon' => 'fab fa-patreon',
                'label' => 'Patreon',
                'prefix' => 'patreon.com/',
                'base_url' => 'https://www.patreon.com/',
            ],
        ];
    }
}
