<?php

namespace App\Controller;

use App\EventSubscriber\SecurityHeadersSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    /**
     * Contrôle de santé interrogé par Railway avant de basculer le trafic sur un nouveau déploiement :
     * la base doit répondre et toutes les migrations doivent être appliquées.
     */
    #[Route(SecurityHeadersSubscriber::HEALTH_PATH, name: 'app_health', methods: ['GET'])]
    public function __invoke(Connection $connection, DependencyFactory $migrations): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
            $pending = \count($migrations->getMigrationStatusCalculator()->getNewMigrations());
        } catch (\Throwable) {
            return $this->json(['status' => 'erreur', 'base' => 'injoignable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($pending > 0) {
            return $this->json(['status' => 'erreur', 'migrations_en_attente' => $pending], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json(['status' => 'ok']);
    }
}
