#!/bin/bash

echo "🚀 Déploiement de Binux Shop..."

# Installer les dépendances
echo "📦 Installation des dépendances..."
composer install --no-dev --optimize-autoloader --no-interaction

# Vider le cache
echo "🧹 Nettoyage du cache..."
php bin/console cache:clear --env=prod --no-debug

# Créer la base de données si elle n'existe pas
echo "🗄️ Configuration de la base de données..."
php bin/console doctrine:database:create --if-not-exists --env=prod --no-interaction || true

# Exécuter les migrations
echo "⬆️ Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Charger les fixtures en mode dev/test uniquement
if [ "$APP_ENV" != "prod" ]; then
    echo "📊 Chargement des données de test..."
    php bin/console doctrine:fixtures:load --no-interaction --env=dev || true
fi

echo "✅ Déploiement terminé !"

