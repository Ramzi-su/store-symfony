<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $form = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => $translator->trans('contact.name.label'),
                'attr'  => ['placeholder' => $translator->trans('contact.name.placeholder')]
            ])
            ->add('email', EmailType::class, [
                'label' => $translator->trans('contact.email.label'),
                'attr'  => ['placeholder' => $translator->trans('contact.email.placeholder')]
            ])
            ->add('message', TextareaType::class, [
                'label' => $translator->trans('contact.message.label'),
                'attr'  => ['placeholder' => $translator->trans('contact.message.placeholder'), 'rows' => 6]
            ])
            ->add('submit', SubmitType::class, [
                'label' => $translator->trans('contact.submit'),
                'attr'  => ['class' => 'btn btn-primary mt-3']
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $email = (new Email())
                ->from($data['email'])
                ->to('support@monsite.com')
                ->subject('Message de contact de ' . $data['name'])
                ->text($data['message']);

            $mailer->send($email);

            $this->addFlash('success', $translator->trans('contact.success'));

            return $this->redirectToRoute('app_contact_confirmation');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/contact/confirmation', name: 'app_contact_confirmation')]
    public function confirmation(): Response
    {
        return $this->render('contact/confirmation.html.twig');
    }
}
