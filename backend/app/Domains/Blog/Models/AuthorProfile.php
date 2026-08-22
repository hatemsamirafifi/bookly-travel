<?php

namespace App\Domains\Blog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorProfile extends Model
{
    protected $table = 'author_profiles';

    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'avatar_url',
    ];

    protected $casts = [
        'display_name' => 'array',
        'bio' => 'array',
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contentFor(string $field, string $locale): ?string
    {
        $values = $this->{$field} ?? [];
        if (! is_array($values)) {
            return null;
        }

        if (isset($values[$locale]) && filled($values[$locale])) {
            return $values[$locale];
        }

        if (isset($values['en']) && filled($values['en'])) {
            return $values['en'];
        }

        return collect($values)->first(fn ($v) => filled($v));
    }
}
