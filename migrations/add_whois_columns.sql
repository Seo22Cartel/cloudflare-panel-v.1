-- Migration: Add WHOIS columns for domain registration tracking
-- Created: 2024-11-30
-- Description: Adds WHOIS data columns to cloudflare_accounts table

-- Add WHOIS columns to cloudflare_accounts
ALTER TABLE cloudflare_accounts ADD COLUMN whois_registrar TEXT DEFAULT NULL;
ALTER TABLE cloudflare_accounts ADD COLUMN whois_created_date TEXT DEFAULT NULL;
ALTER TABLE cloudflare_accounts ADD COLUMN whois_expiry_date TEXT DEFAULT NULL;
ALTER TABLE cloudflare_accounts ADD COLUMN whois_updated_date TEXT DEFAULT NULL;
ALTER TABLE cloudflare_accounts ADD COLUMN whois_registrant TEXT DEFAULT NULL;
ALTER TABLE cloudflare_accounts ADD COLUMN whois_name_servers TEXT DEFAULT NULL;
ALTER TABLE cloudflare_accounts ADD COLUMN whois_status TEXT DEFAULT NULL;
ALTER TABLE cloudflare_accounts ADD COLUMN whois_last_check TEXT DEFAULT NULL;
ALTER TABLE cloudflare_accounts ADD COLUMN whois_days_until_expiry INTEGER DEFAULT NULL;

-- Create index for expiry date filtering
CREATE INDEX IF NOT EXISTS idx_whois_expiry ON cloudflare_accounts(whois_expiry_date);
CREATE INDEX IF NOT EXISTS idx_whois_days_until_expiry ON cloudflare_accounts(whois_days_until_expiry);