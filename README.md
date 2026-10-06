# Binux Shop

Boutique en ligne de démonstration signée [BinuxLabs](https://binuxlabs.com) : catalogue, recherche, panier, commande, paiement Stripe, suivi et administration.

En ligne : https://binux-shop.binuxlabs.com

## Ce que fait l'application

Côté client
- Accueil en vitrine, catalogue filtrable (rayon, stock, promotions, tri), recherche avec suggestions.
- Panier, commande, paiement par carte sur la page de Stripe, suivi et annulation des commandes non payées.
- Compte : profil, adresses, mot de passe.

Côté administration (`/admin`)
- Tableau de bord : encaissé, panier moyen, commandes à préparer, stock à surveiller.
- Produits et catégories, avec image envoyée ou adresse `https`.
- Commandes : une commande ne se prépare qu'une fois payée ; l'annuler remet ses articles en stock.

## Pile technique

Symfony 6.4 (PHP 8.3), Doctrine et PostgreSQL, Twig, Stripe Checkout. Interface sans dépendance externe : une feuille de style, un script, polices et icônes servies par le site. En production, FrankenPHP (voir `Dockerfile` et `Caddyfile`).

## Installation locale

```bash
composer install
docker compose up -d database          # PostgreSQL local
php bin/console doctrine:migrations:migrate
php -S 127.0.0.1:8000 -t public
```

Les migrations créent le schéma **et** le catalogue de départ (6 catégories, 16 produits). Les réglages propres à votre poste vont dans `.env.local`, qui n'est jamais versionné.

Créer le compte administrateur : définir `SETUP_TOKEN` (24 caractères au moins) dans `.env.local`, ouvrir `/installation`, puis retirer la variable.

## Variables d'environnement

| Variable | Rôle |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `prod` et `0` en production |
| `APP_SECRET` | Secret de l'application (jetons de formulaires, sessions) |
| `DATABASE_URL` | Connexion PostgreSQL |
| `STRIPE_SECRET_KEY` | Clé secrète Stripe. Une clé de test affiche le bandeau « boutique de démonstration » |
| `STRIPE_WEBHOOK_SECRET` | Secret de signature du point `/stripe/webhook` (facultatif, recommandé) |
| `SETUP_TOKEN` | Code d'installation du compte administrateur. À supprimer une fois le compte créé |
| `TRUSTED_PROXIES` | Proxys de confiance (`127.0.0.1,REMOTE_ADDR` sur Railway, déjà réglé dans l'image) |

Aucun secret ne figure dans le dépôt. Il n'existe aucun compte par défaut.

## Paiement

- La boutique ne voit jamais la carte : le client la saisit chez Stripe.
- Les montants sont calculés en centimes entiers (`App\Util\Money`), jamais en nombres à virgule flottante.
- Une commande n'est confirmée que si Stripe déclare la session payée **et** que le montant encaissé est exactement celui de la commande.
- Pour confirmer un paiement même si le client ferme son navigateur, déclarer dans Stripe un point de notification vers `https://<domaine>/stripe/webhook` (événement `checkout.session.completed`) et renseigner `STRIPE_WEBHOOK_SECRET`.
- Une commande restée sans paiement deux heures est annulée et son stock libéré (`php bin/console app:orders:expire`, lancé aussi à chaque commande et à l'ouverture du tableau de bord).

Carte de test Stripe : `4242 4242 4242 4242`, date future, cryptogramme quelconque.

## Sécurité

- Mots de passe hachés, 10 caractères au minimum, mots de passe compromis refusés.
- Connexion limitée à 5 essais par minute ; création de compte et page d'installation limitées elles aussi.
- Jeton anti-CSRF sur chaque formulaire et chaque action, déconnexion comprise.
- En-têtes de sécurité sur toutes les réponses, dont une politique de contenu stricte : ni script ni style en ligne, aucune ressource tierce hormis les visuels du catalogue en `https`.
- HTTPS imposé, cookie de session `Secure`, `HttpOnly`, `SameSite=Lax`.
- Images envoyées : extension et contenu vérifiés, nom tiré au hasard ; le serveur n'exécute aucun fichier PHP en dehors du contrôleur frontal.
- Stock réservé par une mise à jour atomique : pas de survente, pas de stock négatif.

## Tests

```bash
php bin/phpunit
```

La base de test est reconstruite par les migrations à chaque lancement. Stripe y est remplacé par une passerelle simulée (`tests/Support/FakeGateway.php`).

## Déploiement (Railway)

Le dépôt contient tout le nécessaire : `Dockerfile`, `Caddyfile`, `railway.json`.
À chaque déploiement, Railway construit l'image, applique les migrations, démarre le serveur et ne bascule le trafic que si `/sante` répond (base joignable, migrations à jour).

Les images envoyées depuis l'administration sont stockées dans le conteneur : elles disparaissent au déploiement suivant tant qu'aucun volume n'est monté sur `/app/public/images`.

---

Signé [BinuxLabs](https://binuxlabs.com)
