<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Restores the locale stored in the session on every request.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly string $defaultLocale = 'fr') {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->getSession()->get('_locale', $this->defaultLocale);
        $request->setLocale($locale);
    }

    public static function getSubscribedEvents(): array
    {
        // Must run AFTER the session is started (priority 15)
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 15]],
        ];
    }
}
