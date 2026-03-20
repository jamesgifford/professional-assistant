<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_key',
        'channel',
        'messages',
        'provider_used',
        'metadata',
    ];

    /**
     * @return array{messages: string, metadata: string}
     */
    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'metadata' => 'array',
        ];
    }

    public function appendMessage(string $role, string $content): void
    {
        $messages = $this->messages ?? [];
        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->messages = $messages;
    }
}
