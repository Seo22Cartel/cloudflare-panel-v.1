-- Migration: Add http_code column to cloudflare_accounts
-- Run this if you get "no such column: http_code" error

ALTER TABLE cloudflare_accounts ADD COLUMN http_code INTEGER DEFAULT 0;
ALTER TABLE cloudflare_accounts ADD COLUMN https_status INTEGER DEFAULT 0;

-- Update existing domains to have proper status based on domain_status
UPDATE cloudflare_accounts 
SET http_code = CASE 
    WHEN domain_status = 'online' THEN 200 
    WHEN domain_status LIKE 'online%' THEN 200
    ELSE 0 
END
WHERE http_code IS NULL OR http_code = 0;