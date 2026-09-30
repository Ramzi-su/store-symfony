<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    private const SUPPORTED = ['fr', 'en'];

    #[Route('/locale/{locale}', name: 'app_set_locale', requirements: ['locale' => 'fr|en'])]
    public function setLocale(string $locale, Request $request): Response
    {
        if (in_array($locale, self::SUPPORTED, true)) {
            $request->getSession()->set('_locale', $locale);
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_home'));
    }
}
