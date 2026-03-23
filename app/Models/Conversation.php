<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_key',
        'channel',
        'provider_used',
        'metadata',
    ];

    /**
     * @return array{metadata: string}
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function appendMessage(string $role, string $content, ?array $metadata = null): Message
    {
        return $this->messages()->create([
            'role' => $role,
            'channel' => $this->channel,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }
}
