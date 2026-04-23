/* Tambah Firecrawl settings */
INSERT INTO settings (setting_key, setting_value, setting_type, category, description) VALUES
('firecrawl_api_key', '', 'string', 'ai_provider', 'Firecrawl API key for web search'),
('firecrawl_enabled', '1', 'boolean', 'ai_provider', 'Enable web search via Firecrawl'),
('firecrawl_max_results', '5', 'number', 'ai_provider', 'Max search results to fetch (1-10)');
