/* Tambah setting provider type dan web search toggle */
INSERT INTO settings (setting_key, setting_value, setting_type, category, description) VALUES
('ai_provider_type', 'openai_compatible', 'string', 'ai_provider', 'Provider type: openai_compatible or kimi'),
('ai_web_search', '0', 'boolean', 'ai_provider', 'Enable web search (Kimi $web_search only)');
