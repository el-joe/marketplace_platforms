<?php

namespace App\Services;

use App\Services\Ai\MockAiProvider;

class AiProviderFactory
{
    /**
     * Return the configured AI provider.
     *
     * To plug in a real provider:
     *   1. Set AI_PROVIDER=openai (or replicate / falai / etc.) in .env
     *   2. Add the matching case below, e.g.:
     *        'openai'    => new \App\Services\Ai\OpenAiProvider(),
     *        'replicate' => new \App\Services\Ai\ReplicateProvider(),
     *        'falai'     => new \App\Services\Ai\FalAiProvider(),
     */
    public static function make(): AiProviderInterface
    {
        return match (config('services.ai_provider', 'mock')) {
            // 'openai'    => new \App\Services\Ai\OpenAiProvider(),
            // 'replicate' => new \App\Services\Ai\ReplicateProvider(),
            // 'falai'     => new \App\Services\Ai\FalAiProvider(),
            default => new MockAiProvider(),
        };
    }
}
