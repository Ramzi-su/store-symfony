# store-symfony

Site e-commerce (boutique de produits high-tech) réalisé avec Symfony 7.4 LTS :
catalogue, panier invité ou connecté, paiement Stripe, comptes vérifiés par SMS (Twilio)
et espace d'administration.

## Prérequis

- PHP ≥ 8.4 avec les extensions `ctype`, `iconv`, `intl`, `pdo_mysql`
- [Composer](https://getcomposer.org/) 2
- MySQL 5.7 ou plus récent
- Optionnel : [Symfony CLI](https://symfony.com/download) (serveur local) et
  [Stripe CLI](https://stripe.com/docs/stripe-cli) (webhooks en local)

## Installation

```bash
git clone https://github.com/Ramzi-su/store-symfony.git
cd store-symfony
composer install
```

Créez un fichier `.env.local` (jamais commité) avec **vos** valeurs :

| Variable | Rôle |
|---|---|
| `APP_SECRET` | Secret de l'application (chaîne aléatoire) |
| `DATABASE_URL` | Ex. `mysql://user:pass@127.0.0.1:3306/store?serverVersion=5.7&charset=utf8mb4` |
| `STRIPE_SECRET_KEY` | Clé secrète Stripe (`sk_test_…` en développement). Sans elle, le paiement est refusé. |
| `STRIPE_WEBHOOK_SECRET` | Secret de signature du webhook Stripe (`whsec_…`). Sans lui, le webhook répond 503. |
| `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM`, `TWILIO_DSN` | Envoi des codes de vérification par SMS |
| `MAILER_DSN` | Envoi des e-mails (formulaire de contact, mot de passe oublié) |
| `CORS_ALLOW_ORIGIN` | Origines autorisées pour l'API |
| `SYMFONY_TRUSTED_PROXIES` | IP du reverse proxy en production (sinon la limite d'inscriptions par IP est partagée par tous les visiteurs) |

> Les secrets ne doivent jamais être écrits dans `.env` (commité) ni dans le code.
> En production, préférez `php bin/console secrets:set NOM`.

Puis créez la base :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## Lancer le site

```bash
symfony serve          # ou : php -S 127.0.0.1:8000 -t public
```

Webhook Stripe en local (le secret `whsec_…` affiché va dans `STRIPE_WEBHOOK_SECRET`) :

```bash
stripe listen --forward-to 127.0.0.1:8000/checkout/webhook
```

## Tests

Les tests fonctionnels utilisent une base dédiée : Doctrine ajoute le suffixe `_test`
au nom de la base (`config/packages/doctrine.yaml`), et son schéma est **recréé à chaque test**.
Ne pointez jamais `DATABASE_URL` de l'environnement de test vers une base contenant des données.

```bash
php bin/console doctrine:database:create --env=test
php bin/phpunit                          # toute la suite
php bin/phpunit --exclude-group database # uniquement les tests sans base de données
```

La suite échoue si notre code (`src/`) déclenche une dépréciation, pour préparer Symfony 8.

## Architecture

| Dossier | Contenu |
|---|---|
| `src/Cart/` | `CartService` : panier en session (invités) ou en base (utilisateurs connectés), totaux |
| `src/Controller/` | Contrôleurs HTTP (boutique, panier, paiement, compte, administration, API) |
| `src/Entity/`, `migrations/` | Modèle Doctrine et migrations |
| `src/Enum/OrderStatus.php` | Statuts de commande : `pending`, `paid`, `shipped`, `cancelled` |
| `src/Security/` | `UserChecker` (compte vérifié obligatoire) et `OrderVoter` (accès à une commande) |
| `src/Twig/MoneyExtension.php` | Filtre `money` pour l'affichage des montants |

Conventions :

- **Montants en centimes** (`int`) partout : `1999` = 19,99 $. Ils ne sont convertis qu'à
  l'affichage, avec `{{ montant|money }}`.
- **Paiement** : une commande n'est marquée `paid` qu'après vérification auprès de Stripe
  (page de succès) ou via le webhook signé — jamais sur la seule redirection du navigateur.
- **Stock** : réservé à la création de la commande (avant Stripe) par une mise à jour SQL atomique,
  puis rendu si le paiement est annulé ou si la session Stripe expire (30 min, webhook
  `checkout.session.expired`). Le webhook Stripe doit donc écouter `checkout.session.completed`
  **et** `checkout.session.expired`.
- **Autorisations** : `access_control` protège les zones (`/admin`, `/orders`, `/user` : admin ;
  `/account` : connecté), `OrderVoter` protège chaque commande (propriétaire ou admin).
- Les formulaires qui modifient des données (panier, paiement, renvoi de code) sont en POST
  avec jeton CSRF, et les codes SMS sont limités (`config/packages/rate_limiter.yaml`).

## Commits

Messages au format [Conventional Commits](https://www.conventionalcommits.org/fr/) :
`feat:`, `fix:`, `refactor:`, `test:`, `build(deps):`…
