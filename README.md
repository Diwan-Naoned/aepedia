# Wiki AEPedia

Déploiement MediaWiki 1.45 pour les parents d'élèves, avec :
- **Inscription restreinte par e-mail** — seuls les e-mails autorisés peuvent créer un compte
- **Accès par espace de noms selon le groupe** — RH, Trésorerie, CA disposent chacun de pages privées
- **Interface d'administration dans le wiki** — gérez les e-mails et les groupes depuis `Special:AEPediaAdmin`

## Structure du dépôt

```
my-wiki/
├── Dockerfile                      # Construit l'image MediaWiki
├── config/
│   └── LocalSettings.php           # Configuration du wiki (espaces de noms, permissions, BDD)
└── extensions/
    └── AEPedia/                    # Extension personnalisée (liste d'autorisation + groupes + UI admin)
        ├── extension.json
        ├── i18n/
        ├── sql/tables.sql
        └── src/
            ├── RegistrationHooks.php
            ├── SchemaHooks.php
            └── SpecialAEPediaAdmin.php
```

## Dévelopement local

1. **À faire une seule fois** : `docker compose up install`
3. **À faire en case de modification du schéma de base de donées**: `docker compose up --build update`
2. `docker compose up -d

## Gestion des secrets

`config/LocalSettings.php` est **ignoré par git** (il contient les identifiants de base de données).
`config/LocalSettings.php.example` est versionné comme modèle.

Pour Dokploy : utilisez la fonctionnalité **File Mounts** pour injecter le vrai `LocalSettings.php`
dans le conteneur à l'emplacement `/var/www/html/LocalSettings.php`.

## Premier déploiement

1. Copiez `config/LocalSettings.php.example` → `config/LocalSettings.php` et renseignez :
   - `$wgDBserver`, `$wgDBname`, `$wgDBuser`, `$wgDBpassword`
   - `$wgServer` (votre URL publique)
   - `$wgSecretKey` et `$wgUpgradeKey` (à générer avec `openssl rand -hex 32`)

2. Construisez et déployez via Dokploy (mode Dockerfile).

3. Exécutez le script de mise à jour du schéma MediaWiki une seule fois après le premier déploiement,
   afin de créer les tables de la base de données pour AEPedia :
   ```
   docker exec -it <conteneur> php /var/www/html/maintenance/update.php
   ```

4. Rendez-vous sur `Special:AEPediaAdmin` (en tant que sysop) pour importer vos fichiers CSV.

## Ajouter un nouveau groupe

1. Ajoutez les constantes d'espace de noms et les règles Lockdown dans `LocalSettings.php`.
2. Ajoutez le nom du groupe dans `MANAGED_GROUPS` dans `src/GroupManager.php`.
3. Reconstruisez et redéployez.
4. Affectez des utilisateurs au nouveau groupe via `Special:AEPediaAdmin`.

## Mettre à jour Lockdown

Remplacez l'URL de l'archive dans le `Dockerfile` par une version plus récente disponible sur :
https://extdist.wmflabs.org/dist/extensions/

## Formats d'import CSV

### Liste d'autorisation (onglet « E-mails autorisés » de `Special:AEPediaAdmin`)
```
email
parent1@example.com
parent2@example.com
```

### Membres d'un groupe (onglet « Groupes » de `Special:AEPediaAdmin`)
```
email
alice@example.com
bob@example.com
```
Un e-mail par ligne. L'import remplace intégralement la liste des membres du groupe sélectionné.