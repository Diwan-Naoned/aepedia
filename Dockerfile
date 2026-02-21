FROM mediawiki:1.45

# Install Lockdown - update URL here to upgrade
RUN curl -fSL https://extdist.wmflabs.org/dist/extensions/Lockdown-REL1_45-d761dbb.tar.gz \
    | tar -xz -C /var/www/html/extensions/

# Copy your custom extension
COPY extensions/AEPedia /var/www/html/extensions/AEPedia
