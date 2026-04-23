<?php

namespace BotWA;

/**
 * Factory that returns the correct AI provider based on settings.
 * 
 * Usage:
 *   $ai = AIFactory::create();
 *   $result = $ai->chat($messages);
 *   $result = $ai->testConnection();
 * 
 * Both AIProvider and KimiProvider have the same interface:
 *   - chat(array $messages): ?array  → returns ['content', 'tokens_used', 'response_time_ms', 'model']
 *   - testConnection(): array        → returns ['success', 'message', ...]
 */
class AIFactory
{
    /**
     * Create the appropriate AI provider based on ai_provider_type setting
     */
    public static function create(): AIProvider|KimiProvider
    {
        $providerType = Config::get('ai_provider_type', 'openai_compatible');

        Logger::debug("AIFactory creating provider", ['type' => $providerType]);

        return match ($providerType) {
            'kimi' => new KimiProvider(),
            default => new AIProvider(),
        };
    }

    /**
     * Get current provider type name
     */
    public static function getProviderType(): string
    {
        return Config::get('ai_provider_type', 'openai_compatible');
    }
}
