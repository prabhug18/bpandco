<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExternalApiToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Generate a new Bearer Token and return plain text token string + model instance.
     */
    public static function createToken(string $name, ?string $storeCode = null, ?int $createdBy = null): array
    {
        $plainToken = Str::random(40);
        $hashedToken = hash('sha256', $plainToken);

        $tokenModel = self::create([
            'name'         => $name,
            'token'        => $hashedToken,
            'store_code'   => $storeCode,
            'is_active'    => true,
            'created_by'   => $createdBy,
        ]);

        return [
            'plain_text_token' => $plainToken,
            'token_model'      => $tokenModel,
        ];
    }

    /**
     * Find active token model by plain text Bearer token.
     */
    public static function findToken(?string $plainToken): ?self
    {
        if (empty($plainToken)) return null;

        $hashedToken = hash('sha256', $plainToken);

        return self::where('token', $hashedToken)
            ->where('is_active', true)
            ->first();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
