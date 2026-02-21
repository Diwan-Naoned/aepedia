-- Table for registration email allowlist
CREATE TABLE IF NOT EXISTS /*_*/aepedia_allowlist (
    al_id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    al_email   VARCHAR(255) NOT NULL,
    UNIQUE KEY al_email_unique (al_email)
) /*$wgDBTableOptions*/;

-- Table for user group assignments (email → group)
CREATE TABLE IF NOT EXISTS /*_*/aepedia_groups (
    ag_id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ag_email   VARCHAR(255) NOT NULL,
    ag_group   VARCHAR(255) NOT NULL,
    UNIQUE KEY ag_email_group_unique (ag_email, ag_group)
) /*$wgDBTableOptions*/;
