<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Force le HTTPS en production et pose les en-têtes de sécurité sur chaque réponse.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public const HEALTH_PATH = '/sante';

    public function __construct(private readonly string $environment)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 512],
            KernelEvents::RESPONSE => ['onResponse', -128],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || 'prod' !== $this->environment) {
            return;
        }

        $request = $event->getRequest();

        // Le contrôle de santé de Railway interroge le conteneur directement, en HTTP.
        if ($request->isSecure() || self::HEALTH_PATH === $request->getPathInfo()) {
            return;
        }

        if ($request->isMethodSafe()) {
            $event->setResponse(new RedirectResponse('https://' . $request->getHttpHost() . $request->getRequestUri(), 301));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;

        // La barre de débogage de Symfony (environnement de développement) gère sa propre politique.
        if (str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "script-src 'self'",
            "style-src 'self'",
            "font-src 'self'",
            // Les visuels du catalogue peuvent être hébergés ailleurs, mais toujours en HTTPS.
            "img-src 'self' data: https:",
            "connect-src 'self'",
            // Seule destination de formulaire hors du site : la page de paiement Stripe.
            "form-action 'self' https://checkout.stripe.com",
        ];

        if ('prod' === $this->environment) {
            $csp[] = 'upgrade-insecure-requests';
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', implode('; ', $csp));
        }

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.stripe.com"), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        // Les pages d'un visiteur connecté ou porteuses d'un formulaire ne doivent pas rester en cache.
        if ($request->hasPreviousSession() && !$headers->hasCacheControlDirective('public')) {
            $headers->set('Cache-Control', 'private, no-store');
        }
    }
}
