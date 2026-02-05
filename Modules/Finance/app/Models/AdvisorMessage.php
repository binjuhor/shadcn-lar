<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\{Model, Relations\BelongsTo};

class AdvisorMessage extends Model
{
    protected $table = 'finance_advisor_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'ai_provider',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AdvisorConversation::class, 'conversation_id');
    }
}
