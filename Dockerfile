FROM mediawiki:1.45

# Install composer 
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');"

# Install Lockdown - update URL here to upgrade
RUN curl -fSL https://extdist.wmflabs.org/dist/extensions/Lockdown-REL1_45-b29af0e.tar.gz \
    | tar -xz -C /var/www/html/extensions/

# Install UniversalLanguageSelector
RUN curl -fSL https://extdist.wmflabs.org/dist/extensions/UniversalLanguageSelector-REL1_45-64163f1.tar.gz \
    | tar -xz -C /var/www/html/extensions/

# Install Translate
RUN curl -fSL https://extdist.wmflabs.org/dist/extensions/Translate-REL1_45-5d90b09.tar.gz \
    | tar -xz -C /var/www/html/extensions/

# Install TranslationNotifications and its dependencies
RUN curl -fSL https://extdist.wmflabs.org/dist/extensions/MassMessage-REL1_45-c581968.tar.gz \
    | tar -xz -C /var/www/html/extensions/
RUN curl -fSL https://extdist.wmflabs.org/dist/extensions/MassMessageEmail-REL1_45-9df7517.tar.gz \
    | tar -xz -C /var/www/html/extensions/
RUN curl -fSL https://extdist.wmflabs.org/dist/extensions/TranslationNotifications-REL1_45-17889f7.tar.gz \
    | tar -xz -C /var/www/html/extensions/

RUN cat <<'EOF' > ./composer.local.json
{
        "extra": {
                "merge-plugin": {
                        "include": [
                                "extensions/*/composer.json",
                                "skins/*/composer.json"
                        ]
                }
        }
}
EOF

# Install dependencies. Security blocking have to be disabled because of some required deps...
RUN ./composer.phar install --no-dev --no-security-blocking

# Copy your custom extension
COPY extensions/AEPedia /var/www/html/extensions/AEPedia

# Copy production settings
COPY ./config/LocalSettings.prod.php /var/www/html/LocalSettings.php
