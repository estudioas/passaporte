INSERT INTO settings (setting_key, setting_value) VALUES
('public_ranking_enabled', '0'),
('voting_manual_closed', '0'),
('registration_manual_closed', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Conteúdo demonstrativo: substitua as URLs pelo painel antes da abertura da votação.
INSERT INTO finalists (slug, participant_name, project_title, instagram_url, active, sort_order) VALUES
('finalista-aurora', 'Profissional finalista 01', 'Ambiente Aurora', 'https://www.instagram.com/ruffinoacabamentos/', 1, 1),
('finalista-trama', 'Profissional finalista 02', 'Casa Trama', 'https://www.instagram.com/ruffinoacabamentos/', 1, 2),
('finalista-horizonte', 'Profissional finalista 03', 'Refúgio Horizonte', 'https://www.instagram.com/ruffinoacabamentos/', 1, 3)
ON DUPLICATE KEY UPDATE slug = VALUES(slug);
