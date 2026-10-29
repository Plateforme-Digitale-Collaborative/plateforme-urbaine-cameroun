<?php

namespace App\Event\EventSubscriber\Actor;

use App\Services\Actor\ActorChangeWorkflow;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Quand la modification d'un administrateur de page est mise « en attente de validation » au lieu d'être
 * appliquée, la réponse (200/201) ressemble à un succès classique : on ajoute l'en-tête
 *   X-Change-Pending: <id de la demande>
 * pour que le front puisse afficher « Modification soumise au point focal ».
 *
 * Si le front est appelé depuis une autre origine, exposer l'en-tête côté CORS
 * (expose_headers: ['X-Change-Pending'] dans nelmio_cors).
 */
class PendingValidationHeaderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $requestId = $event->getRequest()->attributes->get(ActorChangeWorkflow::REQUEST_ATTRIBUTE_PENDING);
        if (null === $requestId) {
            return;
        }

        $event->getResponse()->headers->set('X-Change-Pending', (string) $requestId);
    }
}