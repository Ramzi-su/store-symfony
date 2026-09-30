# Tailwind Redesign, Bug Fixes and Blog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move every MiniStore page to a Tailwind + Twig Components design system (the "Ink & indigo" palette), fix the broken or insecure flows found during analysis, and build a working blog with a back-office editor.

**Architecture:**
- **Styling:** Tailwind v4 is compiled by `symfonycasts/tailwind-bundle` (standalone binary, no Node) and served through AssetMapper, which is already installed.
- **Reusable markup:** anonymous Twig Components (`templates/components/*.html.twig`) for UI pieces, and one global form theme for every Symfony form.
- **Behaviour:** small Stimulus controllers replace jQuery, Bootstrap JS and inline scripts. Turbo stays disabled.
- **Order of work:** bug fixes and dead-code removal come first, then the foundation, then page groups, then the blog, then clean-up.

**Tech Stack:** PHP 8.4+, Symfony 7.4, Twig 3, Doctrine ORM, PHPUnit 11, Tailwind CSS v4, symfony/ux-twig-component, Stimulus 3 (stimulus-bundle), KnpPaginator, SymfonyCasts ResetPasswordBundle.

**Spec:** `docs/superpowers/specs/2026-09-30-tailwind-redesign-design.md`. Read §7 first: it overrides §1–§6 where they differ.

## Global Constraints

- **Code:** code, identifiers and comments in English. Every visible UI text in French (`<html lang="fr">`).
- **PHP:** PSR-12, strict types where the file already uses them, and constructor injection (no `$container->get()` in `src/`).
- **Composer:** `composer.json` allows PHP `>=8.4`. Do not use PHP 8.5-only APIs such as the `SortDirection` enum.
- **Design tokens:** use only the Tailwind colour tokens from Task 5 (`ink`, `muted`, `line`, `canvas`, `surface`, `subtle`, `accent`, `accent-hover`, `accent-soft`, `accent-ink`, `signal`, `signal-soft`, `signal-ink`, `success`, `success-soft`, `success-ink`, `warning-soft`, `warning-ink`). No raw hex values in templates.
- **Twig safety:** never use `|raw` in templates. User-supplied text is only ever output through auto-escaping (`{{ }}`), plus `|nl2br` for line breaks.
- **No inline JavaScript:** no `onclick`, `onchange` or `onsubmit` attributes and no `<script>` blocks in templates, except `{{ importmap('app') }}` in `base.html.twig`. Behaviour lives in `assets/controllers/*_controller.js`.
- **Test marker classes:** these classes stay in the markup with no CSS attached, because tests select them:
  - listings: `ms-grid`, `ms-tile__name`, `ms-category`, `ms-hero__feature`, `ms-pagination`, `related-products`
  - product page: `product-title` (on the `h1`), `product-price`, `product-actions`, `add-to-cart-form`
  - cart: `cart-summary`, `quantity-input`
  - alerts: `alert`, `alert-success`, `alert-danger`, `alert-warning`, `alert-info`
  - unchanged: every form `name` and `action` attribute, `#checkout-form`, `header form[role="search"]`
- **Minimum widths:** every page works at 360px with no horizontal page scroll, and at 1280px.
- **Contrast:** text contrast is at least 4.5:1 (all token pairs above were measured and pass).
- **Commits:** Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`, `docs:`). The user is the sole author: no `Co-Authored-By`, no "Generated with" line.
- **Test runs:** run the full suite with `php bin/phpunit` before every commit. It must be green.
- **Behaviour:** routes, security rules and business logic stay unchanged, except where a task explicitly fixes a bug listed in spec §7.1.

## Review Focus

The inputs and conditions most likely to break for a real user, where no task's normal tests would catch them. Each one is pinned by a test or check in the task named.

1. **HTML or script in user-controlled text** (a post body, a search query, a product name, a flash message) must be displayed as text, never executed. Pinned in Task 6 (`Alert`), Task 10 (search heading) and Task 17 (post content).
2. **Upload disguised as an image** (a PHP or text file renamed `photo.jpg`, or a file over 2 MB) must be rejected with a form error, and nothing may be written to disk. Pinned in Task 16.
3. **Empty data** (no products, no posts, no orders, empty cart) must render a proper empty state, not a crash or a blank page. Pinned in Task 10, Task 13 and Task 17, and swept by `PageSmokeTest` in Task 19.
4. **Pages without JavaScript** (a script failed to load, or JS is disabled): the cart quantity, the shop sort and filters and the delete confirmations must still submit. The existing functional tests submit these forms without JS (`tests/Cart`, `ShopFiltersTest`, `AdminPostTest`). Tasks 10 and 11 check that the `<noscript>` fallback buttons exist, and Task 19 checks everything in a browser with JavaScript disabled.
5. **Long, unbreakable text at 360px** (a long French word, a pasted URL in a post, a very long product name) must wrap without horizontal scroll. Pinned by the browser check in Task 19, which asserts `scrollWidth <= innerWidth` on every page.

---

## File Structure

**Created**

| Path | Responsibility |
|---|---|
| `config/packages/symfonycasts_tailwind.yaml` | Tailwind binary version |
| `assets/styles/app.css` | Tailwind entry: `@theme` tokens, base layer, `page-wrap` utility (replaces the Symfony stub) |
| `assets/controllers/{menu,dropdown,dismiss,autosubmit,confirm,disclosure}_controller.js` | One behaviour each |
| `templates/components/{Button,Badge,Alert,Card,ProductCard,PriceTag,EmptyState,PageHeader,Pagination}.html.twig` | Anonymous Twig Components |
| `templates/form/theme.html.twig` | Global form theme |
| `templates/partials/flashes.html.twig` | Renders every flash through `Alert` |
| `templates/admin/layout.html.twig` | Back-office shell (sidebar and top bar) |
| `templates/account/orders.html.twig`, `templates/account/order_show.html.twig` | Customer order pages (fix F4) |
| `templates/reset_password/check_email.html.twig` | Missing page (fix F1) |
| `src/Repository/ResetPasswordRequestRepository.php` | Real token storage (fix F1) |
| `src/Form/ResetPasswordRequestFormType.php`, `src/Form/ResetPasswordFormType.php` | CSRF and password rules (fix F2) |
| `src/Enum/PostCategory.php` | Blog categories |
| `src/Blog/PostSlugger.php` | Unique slug from a title |
| `src/Blog/PostImageUploader.php` | Stores and deletes post images |
| `src/Form/PostType.php` | Back-office post form |
| `src/Controller/AdminPostController.php` | `/admin/posts` CRUD |
| `templates/blog/index.html.twig`, `templates/blog/show.html.twig`, `templates/blog/_sidebar.html.twig`, `templates/blog/_card.html.twig` | Public blog |
| `templates/admin/posts/{index,new,edit,_form}.html.twig` | Back-office blog |
| `migrations/VersionYYYYMMDDHHMMSS.php` | Post columns (generated) |
| `tests/Twig/Components/*Test.php`, `tests/Controller/{ResetPasswordTest,BlogPageTest,AdminPostTest,ContactFormTest,PageSmokeTest,FormThemeTest,CustomerOrdersTest}.php`, `tests/Blog/PostSluggerTest.php` | Tests |

**Deleted**
- `src/Controller/UserController.php`, `src/Form/UserType.php`, `templates/user/*`
- `templates/api_register/index.html.twig`, `templates/security/verify_phone.html.twig`
- `src/Controller/SinglePostController.php`, `templates/single-post/`, `templates/blog/blog.html.twig`
- `templates/partials/{blog_sidebar,breadcrumb,pagination,product_card}.html.twig`
- `assets/controllers/hello_controller.js`
- `public/style.css`, `public/css/{bootstrap.min,vendor,theme}.css`, `public/css/ajax-loader.gif` (if unreferenced)
- `public/js/{jquery-1.11.0.min,plugins,script,modernizr,bootstrap.bundle.min}.js`

**Modified**
- **Config and entry points:** `templates/base.html.twig`, `assets/app.js`, `assets/controllers.json`, `config/packages/twig.yaml`, `config/packages/security.yaml`, `config/packages/reset_password.yaml`, `config/services.yaml`, `.gitignore`, `README.md`.
- **Entities:** `src/Entity/ResetPasswordRequest.php`, `src/Entity/Post.php`.
- **Repository:** `src/Repository/PostRepository.php`.
- **Controllers:** `ResetPasswordController`, `OrderController`, `AdminController`, `BlogController`, `ContactController`.
- **Form types:** every form type under `src/Form/` (strip Bootstrap classes, translate labels to French).
- **Templates:** every remaining template.
- **Tests:** `tests/Controller/AccessControlTest.php`, `tests/Controller/HomePageTest.php`, `tests/Controller/RegistrationFormTest.php`.

---

## Part A — Fixes before the redesign

### Task 1: Remove the broken duplicate `/user` admin and dead templates (F3, F5)

**Why:** `/user/*` crashes because of wrong route names, duplicates `/admin/users`, and `user/show.html.twig` prints the password hash and the SMS verification code. Deleting it removes the leak and the duplicate. The two dead templates are also deleted; one of them exposes local file paths.

**Files:**
- Delete: `src/Controller/UserController.php`, `src/Form/UserType.php`, `templates/user/` (the whole directory), `templates/api_register/index.html.twig`, `templates/security/verify_phone.html.twig`
- Modify: `config/packages/security.yaml:36` (remove the `^/user` rule)
- Modify: `tests/Controller/AccessControlTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: none. `/admin/users` (`AdminController`, routes `admin_users`, `admin_user_edit` and `admin_user_delete`) is the only user management from now on.

- [ ] **Step 1: Write the failing test.** Replace the `user management` case in `tests/Controller/AccessControlTest.php` and add a test proving the route is gone:

```php
    public static function adminOnlyUrls(): iterable
    {
        yield 'orders list' => ['/orders'];
        yield 'order creation' => ['/orders/new'];
        yield 'order edition' => ['/orders/1/edit'];
        yield 'admin dashboard' => ['/admin'];
        yield 'user management' => ['/admin/users'];
    }

    public function testTheOldDuplicateUserAdminIsGone(): void
    {
        // /user/* duplicated /admin/users and displayed password hashes.
        $client = static::createClient();
        $client->request('GET', '/user/');

        $this->assertResponseStatusCodeSame(404);
    }
```

- [ ] **Step 2: Run the test to verify it fails.**

Run: `php bin/phpunit tests/Controller/AccessControlTest.php`
Expected: FAIL in `testTheOldDuplicateUserAdminIsGone`: the status is 302 (redirect to `/login`), not 404.

- [ ] **Step 3: Delete the files and the access rule.**

```bash
git rm -q src/Controller/UserController.php src/Form/UserType.php -r templates/user \
  templates/api_register/index.html.twig templates/security/verify_phone.html.twig
```

In `config/packages/security.yaml`, delete exactly this line:

```yaml
        - { path: ^/user, roles: ROLE_ADMIN }
```

- [ ] **Step 4: Check that nothing else references the removed code.**

Run: `grep -rnE "UserType|user_(index|new|show|edit|delete)|app_user_|verify_phone|api_register/index" src templates config tests`
Expected: no output. `src/Controller/ApiRegisterController.php` stays: it serves JSON routes and does not use the deleted template.

- [ ] **Step 5: Run the full suite.**

Run: `php bin/console cache:clear && php bin/phpunit`
Expected: PASS (all tests).

- [ ] **Step 6: Commit.**

```bash
git add -A src/Controller src/Form templates config/packages/security.yaml tests/Controller/AccessControlTest.php
git commit -m "refactor: remove the duplicate /user admin that exposed password hashes"
```

---

### Task 2: Make password reset work and protect it (F1, F2)

**Why:** reset tokens are never stored, because the config points at the bundle's fake repository and `App\Repository\ResetPasswordRequestRepository` does not exist. The check-email page has no template (500). A successful reset redirects to a route that does not exist (500). The forms have no CSRF protection, and any new password is accepted, even one character long. Asking for a reset twice within the throttle window throws an uncaught exception (500).

**Files:**
- Create: `src/Repository/ResetPasswordRequestRepository.php`, `src/Form/ResetPasswordRequestFormType.php`, `src/Form/ResetPasswordFormType.php`, `templates/reset_password/check_email.html.twig`, `tests/Controller/ResetPasswordTest.php`
- Modify: `src/Entity/ResetPasswordRequest.php`, `config/packages/reset_password.yaml`, `src/Controller/ResetPasswordController.php`, `templates/reset_password/request.html.twig`, `templates/reset_password/reset.html.twig`

**Interfaces:**
- Consumes: `App\Entity\User`, `SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface`.
- Produces:
  - Routes `app_forgot_password_request` (`/reset-password`), `app_check_email` (`/reset-password/check-email`) and `app_reset_password` (`/reset-password/reset/{token?}`).
  - Form types `ResetPasswordRequestFormType` (field `email`) and `ResetPasswordFormType` (field `plainPassword`, repeated: `first`/`second`). Task 12 restyles these templates.

- [ ] **Step 1: Write the failing tests** in `tests/Controller/ResetPasswordTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Group('database')]
class ResetPasswordTest extends DatabaseWebTestCase
{
    // Stateless CSRF (Symfony 7.2+) accepts same-origin requests.
    private const SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    public function testRequestingAResetSendsOneEmailAndShowsTheCheckEmailPage(): void
    {
        $this->createUser('ada@example.com');

        $this->requestReset('ada@example.com');

        $this->assertEmailCount(1);
        $this->assertResponseRedirects('/reset-password/check-email');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Consultez votre boîte mail');
    }

    public function testAnUnknownEmailGetsTheSameAnswerWithoutAnEmail(): void
    {
        // Same response for known and unknown addresses: the form must not reveal who has an account.
        $this->requestReset('nobody@example.com');

        $this->assertEmailCount(0);
        $this->assertResponseRedirects('/reset-password/check-email');
    }

    public function testASecondRequestWithinTheThrottleWindowDoesNotCrash(): void
    {
        $this->createUser('ada@example.com');

        $this->requestReset('ada@example.com');
        $this->requestReset('ada@example.com');

        $this->assertResponseRedirects('/reset-password/check-email');
    }

    public function testAShortPasswordIsRejected(): void
    {
        $this->openResetPage($this->createUser('ada@example.com'));

        $this->submitNewPassword('short', 'short');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('form[name="reset_password_form"]', 'au moins 8 caractères');
    }

    public function testAValidResetChangesThePasswordAndRedirectsToLogin(): void
    {
        $user = $this->createUser('ada@example.com');
        $this->openResetPage($user);

        $this->submitNewPassword('a-long-new-password', 'a-long-new-password');

        $this->assertResponseRedirects('/login');
        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($user->getId());
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($reloaded, 'a-long-new-password'));
    }

    public function testAnInvalidTokenSendsTheVisitorBackToTheRequestForm(): void
    {
        $this->client->request('GET', '/reset-password/reset/not-a-real-token');
        $this->assertResponseRedirects('/reset-password/reset');

        $this->client->followRedirect();
        $this->assertResponseRedirects('/reset-password');
    }

    private function requestReset(string $email): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $form = $crawler->filter('form[name="reset_password_request_form"]')->form([
            'reset_password_request_form[email]' => $email,
        ]);
        $this->client->submit($form, [], self::SAME_ORIGIN);
    }

    private function openResetPage(User $user): void
    {
        $token = static::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($user);

        // The token is moved from the URL into the session, then the page reloads without it.
        $this->client->request('GET', '/reset-password/reset/' . $token->getToken());
        $this->assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    private function submitNewPassword(string $first, string $second): void
    {
        $form = $this->client->getCrawler()->filter('form[name="reset_password_form"]')->form([
            'reset_password_form[plainPassword][first]' => $first,
            'reset_password_form[plainPassword][second]' => $second,
        ]);
        $this->client->submit($form, [], self::SAME_ORIGIN);
    }
}
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `php bin/phpunit tests/Controller/ResetPasswordTest.php`
Expected: FAIL. There is no form named `reset_password_request_form`, and the check-email page returns 500.

- [ ] **Step 3: Give the entity a constructor.** The bundle creates requests through it. In `src/Entity/ResetPasswordRequest.php`, add this after the `$user` property:

```php
    public function __construct(User $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }
```

- [ ] **Step 4: Create the repository** `src/Repository/ResetPasswordRequestRepository.php`:

```php
<?php

namespace App\Repository;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\Repository\ResetPasswordRequestRepositoryTrait;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;

/**
 * Stores reset tokens (only a hash of each token is saved, never the token itself).
 *
 * @extends ServiceEntityRepository<ResetPasswordRequest>
 */
class ResetPasswordRequestRepository extends ServiceEntityRepository implements ResetPasswordRequestRepositoryInterface
{
    use ResetPasswordRequestRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordRequest::class);
    }

    /**
     * @param User $user
     */
    public function createResetPasswordRequest(object $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken): ResetPasswordRequestInterface
    {
        return new ResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
    }
}
```

- [ ] **Step 5: Point the bundle at it.** Replace `config/packages/reset_password.yaml` with:

```yaml
symfonycasts_reset_password:
    request_password_repository: App\Repository\ResetPasswordRequestRepository
```

- [ ] **Step 6: Create the two form types.**

`src/Form/ResetPasswordRequestFormType.php`:

```php
<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Adresse e-mail',
            'attr' => ['autocomplete' => 'email', 'autofocus' => true],
            'constraints' => [
                new NotBlank(message: 'Saisissez votre adresse e-mail.'),
                new Email(message: 'Saisissez une adresse e-mail valide.'),
            ],
        ]);
    }
}
```

`src/Form/ResetPasswordFormType.php` uses the same rules as registration (`RegistrationFormType`: `NotBlank`, `Length` 8–4096):

```php
<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options' => ['label' => 'Nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'invalid_message' => 'Les deux mots de passe doivent être identiques.',
            'constraints' => [
                new NotBlank(message: 'Saisissez un mot de passe.'),
                // Same rule as registration. The max stops very long inputs from slowing the hasher down.
                new Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
            ],
        ]);
    }
}
```

- [ ] **Step 7: Rewrite the controller** `src/Controller/ResetPasswordController.php`:

```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/reset-password', name: 'app_forgot_password_request')]
    public function request(Request $request, UserRepository $userRepository, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $userRepository->findOneBy(['email' => $form->get('email')->getData()]);

            if ($user instanceof User) {
                try {
                    $resetToken = $this->resetPasswordHelper->generateResetToken($user);

                    $mailer->send((new TemplatedEmail())
                        ->from(new Address('noreply@monsite.com', 'Support Ministore'))
                        ->to((string) $user->getEmail())
                        ->subject('Réinitialisation de votre mot de passe')
                        ->htmlTemplate('reset_password/email.html.twig')
                        ->context(['resetToken' => $resetToken]));
                } catch (ResetPasswordExceptionInterface) {
                    // Throttled (a request is already pending): answer exactly as for any other address,
                    // so the page never reveals whether an account exists.
                }
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', ['requestForm' => $form]);
    }

    #[Route('/reset-password/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig');
    }

    #[Route('/reset-password/reset/{token}', name: 'app_reset_password', defaults: ['token' => null])]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, ?string $token = null): Response
    {
        if ($token !== null) {
            // Keep the token out of the URL (browser history, Referer headers): store it and reload.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if ($token === null) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide. Faites une nouvelle demande.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->cleanSessionAfterReset();
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $this->entityManager->flush();
            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', ['resetForm' => $form]);
    }
}
```

`testAnInvalidTokenSendsTheVisitorBackToTheRequestForm` expects `/reset-password/reset/not-a-real-token` to redirect to `/reset-password/reset`, and that page to redirect to `/reset-password`. That's what the code above does: the stored token fails validation.

- [ ] **Step 8: Write the templates.** They use the old base layout for now; Task 12 restyles them.

`templates/reset_password/request.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Mot de passe oublié - MiniStore{% endblock %}

{% block body %}
  <main>
    <h1>Mot de passe oublié</h1>
    <p>Saisissez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
    {{ form_start(requestForm) }}
      {{ form_row(requestForm.email) }}
      <button type="submit">Envoyer le lien</button>
    {{ form_end(requestForm) }}
  </main>
{% endblock %}
```

`templates/reset_password/reset.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Nouveau mot de passe - MiniStore{% endblock %}

{% block body %}
  <main>
    <h1>Choisissez un nouveau mot de passe</h1>
    {{ form_start(resetForm) }}
      {{ form_row(resetForm.plainPassword.first) }}
      {{ form_row(resetForm.plainPassword.second) }}
      <button type="submit">Enregistrer</button>
    {{ form_end(resetForm) }}
  </main>
{% endblock %}
```

`templates/reset_password/check_email.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Consultez votre boîte mail - MiniStore{% endblock %}

{% block body %}
  <main>
    <h1>Consultez votre boîte mail</h1>
    <p>Si un compte existe pour cette adresse, un lien de réinitialisation vient d’être envoyé. Il reste valable une heure.</p>
    <p>Rien reçu ? Vérifiez vos courriers indésirables ou <a href="{{ path('app_forgot_password_request') }}">refaites une demande</a>.</p>
  </main>
{% endblock %}
```

- [ ] **Step 9: Run the tests.**

Run: `php bin/phpunit tests/Controller/ResetPasswordTest.php`
Expected: PASS (6 tests).

- [ ] **Step 10: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add src/Entity/ResetPasswordRequest.php src/Repository/ResetPasswordRequestRepository.php src/Form/ResetPasswordRequestFormType.php src/Form/ResetPasswordFormType.php src/Controller/ResetPasswordController.php config/packages/reset_password.yaml templates/reset_password tests/Controller/ResetPasswordTest.php
git commit -m "fix: make password reset work end to end, with CSRF and password rules"
```

---

### Task 3: Give customers their own order templates (F4)

**Why:** `orders/index` and `orders/show` serve both `/account/orders` (a customer's own orders) and `/orders` (all orders, admin only), and switch on `is_granted('ROLE_ADMIN')`. An admin who opens their own `/account/orders` sees "Toutes les commandes" and admin edit and delete buttons. Separate templates remove that branching.

**Files:**
- Create: `templates/account/orders.html.twig`, `templates/account/order_show.html.twig`, `tests/Controller/CustomerOrdersTest.php`
- Modify: `src/Controller/OrderController.php:23,33`, `templates/orders/index.html.twig`, `templates/orders/show.html.twig`, `tests/DatabaseWebTestCase.php`

**Interfaces:**
- Consumes: `orders/_status_badge.html.twig` (expects `status`: `OrderStatus|null`).
- Produces:
  - Templates `account/orders.html.twig` (variable `orders`: `Orders[]`) and `account/order_show.html.twig` (variable `order`: `Orders`). Task 13 restyles them.
  - The test helper `DatabaseWebTestCase::createOrder(?User $user, OrderStatus $status = OrderStatus::Paid): Orders` (total 12345, subtotal 10000, tax 1500, shipping 845). Tasks 13, 14 and 19 use it.

- [ ] **Step 0: Add the shared order helper** to `tests/DatabaseWebTestCase.php`. Add the `use` lines for `App\Entity\Orders` and `App\Enum\OrderStatus`, then add after `createUser()`:

```php
    // Amounts in cents: 100,00 $ + 8,45 $ shipping + 15,00 $ tax = 123,45 $.
    protected function createOrder(?User $user, OrderStatus $status = OrderStatus::Paid): Orders
    {
        $order = (new Orders())
            ->setUser($user)
            ->setStatus($status)
            ->setTotal(12345)
            ->setSubtotal(10000)
            ->setTax(1500)
            ->setShippingCost(845)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
```

- [ ] **Step 1: Write the failing test** `tests/Controller/CustomerOrdersTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class CustomerOrdersTest extends DatabaseWebTestCase
{
    public function testAnAdminSeesOnlyCustomerViewsOfTheirOwnOrders(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $order = $this->createOrder($admin);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/account/orders');
        $this->assertSelectorTextContains('h1', 'Mes commandes');
        $this->assertSelectorNotExists(sprintf('a[href="/orders/%d/edit"]', $order->getId()));

        $this->client->request('GET', sprintf('/account/orders/%d', $order->getId()));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists(sprintf('form[action="/orders/%d"]', $order->getId()));
        $this->assertSelectorExists('a[href="/account/orders"]');
    }

    public function testACustomerWithoutOrdersSeesAnEmptyState(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/account/orders');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Vous n’avez pas encore passé de commande');
    }
}
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `php bin/phpunit tests/Controller/CustomerOrdersTest.php`
Expected: FAIL. The page shows "Toutes les commandes" (there is no `h1` "Mes commandes") and the admin edit link.

- [ ] **Step 3: Create the customer templates** (placeholder markup: Task 13 restyles them).

`templates/account/orders.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Mes commandes - MiniStore{% endblock %}

{% block body %}
  {% include 'partials/svg_icons.html.twig' %}
  {% include 'partials/header.html.twig' %}
  <main class="container">
    <h1>Mes commandes</h1>
    {% if orders is empty %}
      <p>Vous n’avez pas encore passé de commande.</p>
      <a href="{{ path('app_shop') }}">Voir la boutique</a>
    {% else %}
      <table>
        <thead><tr><th>Commande</th><th>Date</th><th>Statut</th><th>Total</th><th></th></tr></thead>
        <tbody>
          {% for order in orders %}
            <tr>
              <td>#{{ order.id }}</td>
              <td>{{ order.createdAt ? order.createdAt|date('d/m/Y') : '' }}</td>
              <td>{{ include('orders/_status_badge.html.twig', {status: order.status}) }}</td>
              <td>{{ order.total|money }}</td>
              <td><a href="{{ path('app_account_order_show', {id: order.id}) }}">Détails</a></td>
            </tr>
          {% endfor %}
        </tbody>
      </table>
    {% endif %}
  </main>
  {% include 'partials/footer.html.twig' %}
{% endblock %}
```

`templates/account/order_show.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Commande #{{ order.id }} - MiniStore{% endblock %}

{% block body %}
  {% include 'partials/svg_icons.html.twig' %}
  {% include 'partials/header.html.twig' %}
  <main class="container">
    <h1>Commande #{{ order.id }}</h1>
    <p>Passée le {{ order.createdAt ? order.createdAt|date('d/m/Y à H:i') : '' }}</p>
    <p>Statut : {{ include('orders/_status_badge.html.twig', {status: order.status}) }}</p>
    <p>Total : {{ order.total|money }}</p>
    <a href="{{ path('app_orders') }}">Retour à mes commandes</a>
  </main>
  {% include 'partials/footer.html.twig' %}
{% endblock %}
```

- [ ] **Step 4: Point the customer controller at them.** In `src/Controller/OrderController.php`, change `'orders/index.html.twig'` to `'account/orders.html.twig'` and `'orders/show.html.twig'` to `'account/order_show.html.twig'`.

- [ ] **Step 5: Make `orders/*` admin-only.**
  - In `templates/orders/index.html.twig`:
    - Replace `{{ is_granted('ROLE_ADMIN') ? 'Toutes les commandes' : 'Mes commandes' }}` with `Toutes les commandes`.
    - Remove the `{% if is_granted('ROLE_ADMIN') %}` / `{% endif %}` wrapper around the "Nouvelle commande" link.
    - Replace the whole actions `<td>` content with the admin links only (the `{% if is_granted %}` branch content, without its `{% else %}` part).
  - In `templates/orders/show.html.twig`:
    - The back link becomes `path('app_orders_index')`.
    - Remove the `{% if is_granted('ROLE_ADMIN') %}` / `{% endif %}` wrapper around the edit and delete actions.
    - Remove the two comments that say the template is shared.

- [ ] **Step 6: Run the tests.**

Run: `php bin/phpunit tests/Controller/CustomerOrdersTest.php tests/Security/OrderAccessTest.php`
Expected: PASS.

- [ ] **Step 7: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add tests/DatabaseWebTestCase.php src/Controller/OrderController.php templates/account/orders.html.twig templates/account/order_show.html.twig templates/orders/index.html.twig templates/orders/show.html.twig tests/Controller/CustomerOrdersTest.php
git commit -m "fix: separate customer order pages from the admin order templates"
```

---

### Task 4: Stop using the PHP 8.5-only `SortDirection` enum (F6)

**Why:** `composer.json` declares `"php": ">=8.4"`, but `SortDirection` only exists from PHP 8.5. On 8.4, `/admin/users` and `/blog` would crash with "Class SortDirection not found".

**Files:**
- Modify: `src/Controller/AdminController.php:10,38`, `src/Controller/BlogController.php:7,20`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing new.

- [ ] **Step 1: Replace the enum with the plain DQL direction.** In both files, delete the line `use SortDirection;`. Replace `SortDirection::Descending` with `'DESC'`:

```php
            ->orderBy('u.createdAt', 'DESC');
```

```php
            ->orderBy('p.createdAt', 'DESC')
```

- [ ] **Step 2: Verify that nothing else uses it.**

Run: `grep -rn "SortDirection" src tests`
Expected: no output.

- [ ] **Step 3: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add src/Controller/AdminController.php src/Controller/BlogController.php
git commit -m "fix: keep PHP 8.4 compatibility by not using the SortDirection enum"
```

---

## Part B — Foundation

### Task 5: Install Tailwind and Twig Components, and switch the layout to AssetMapper

**Why:** this sets up the toolchain and design tokens. From this commit on, the old stylesheets and scripts are no longer loaded. Pages that haven't been migrated yet look unstyled until their task, but they keep working (spec §4, "Intermediate state").

**Files:**
- Create: `config/packages/symfonycasts_tailwind.yaml`
- Modify (replace): `assets/styles/app.css`, `assets/app.js`
- Modify: `assets/controllers.json`, `templates/base.html.twig`, `tests/Controller/HomePageTest.php`
- Delete: `assets/controllers/hello_controller.js`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - Tailwind utilities for every token listed in Global Constraints, plus `rounded-control` (10px), `rounded-card` (14px), `shadow-card`, `shadow-card-hover` and the `page-wrap` utility (centred, max-width 80rem, 1rem gutter, 1.5rem from `sm`).
  - `base.html.twig` loads `{{ importmap('app') }}`, which brings in `styles/app.css` and starts Stimulus.

- [ ] **Step 1: Write the failing regression test.** Append to `tests/Controller/HomePageTest.php`:

```php
    public function testPagesLoadTheAssetMapperBundleAndNoLegacyAssets(): void
    {
        $this->client->request('GET', '/');
        $html = (string) $this->client->getResponse()->getContent();

        $this->assertSelectorExists('script[type="importmap"]');
        // jQuery 1.11 has known XSS vulnerabilities; CDN scripts are a supply-chain risk.
        foreach (['jquery', 'bootstrap.bundle', 'bootstrap.min.css', 'style.css', 'theme.css', 'cdn.jsdelivr.net'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $html, "Legacy asset still referenced: $legacy");
        }
    }
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `php bin/phpunit --filter testPagesLoadTheAssetMapperBundleAndNoLegacyAssets`
Expected: FAIL: `Failed asserting that the page contains "script[type="importmap"]"`.

- [ ] **Step 3: Install the two packages.**

Run: `composer require symfonycasts/tailwind-bundle symfony/ux-twig-component`
Expected: both installed. The Flex recipes register the bundles and create `config/packages/twig_component.yaml`, with anonymous components in `templates/components/`.

- [ ] **Step 4: Pin the Tailwind binary** in `config/packages/symfonycasts_tailwind.yaml`. `tailwind:init` only runs interactively, so write the file by hand:

```yaml
symfonycasts_tailwind:
    # Standalone Tailwind binary (no Node.js). Update with the latest v4 tag from
    # https://github.com/tailwindlabs/tailwindcss/releases
    binary_version: 'v4.3.3'
```

- [ ] **Step 5: Replace `assets/styles/app.css`** (the design tokens):

```css
@import "tailwindcss";

/* Folders Tailwind scans for class names (relative to this file). */
@source "../../templates";
@source "../controllers";

/*
 * Design tokens, "Ink & indigo" palette. Every token becomes utilities:
 * --color-accent => bg-accent, text-accent, ring-accent, border-accent...
 * Contrast measured against WCAG AA (4.5:1), see the spec §3.2 and §7.2.
 */
@theme {
  --font-sans: "Manrope", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;

  --color-ink: #0f172a;          /* main text */
  --color-muted: #5b6577;        /* secondary text: 5.88:1 on white, 5.37:1 on subtle */
  --color-line: #e2e8f0;         /* borders */
  --color-canvas: #f8fafc;       /* page background */
  --color-surface: #ffffff;      /* cards, inputs */
  --color-subtle: #f1f5f9;       /* photo tiles, summaries */

  --color-accent: #4f46e5;       /* actions only: 6.29:1 with white */
  --color-accent-hover: #4338ca;
  --color-accent-soft: #e0e7ff;
  --color-accent-ink: #3730a3;   /* on accent-soft: 8.06:1 */

  --color-signal: #dc2626;       /* sale, out of stock, errors: 4.83:1 with white */
  --color-signal-soft: #fee2e2;
  --color-signal-ink: #991b1b;   /* on signal-soft: 6.80:1 */
  --color-success: #15803d;
  --color-success-soft: #dcfce7;
  --color-success-ink: #166534;  /* on success-soft: 6.49:1 */
  --color-warning-soft: #fef3c7;
  --color-warning-ink: #92400e;  /* on warning-soft: 6.37:1 */

  --radius-control: 10px;
  --radius-card: 14px;

  /* Two layers: a thin sharp edge plus a wide soft shadow. */
  --shadow-card: 0 1px 2px rgb(16 24 40 / 0.04), 0 8px 24px -12px rgb(16 24 40 / 0.18);
  --shadow-card-hover: 0 2px 4px rgb(16 24 40 / 0.06), 0 18px 36px -14px rgb(16 24 40 / 0.28);
}

/* Centred page column with the standard side gutter. */
@utility page-wrap {
  width: 100%;
  max-width: 80rem;
  margin-inline: auto;
  padding-inline: 1rem;

  @media (width >= 40rem) {
    padding-inline: 1.5rem;
  }
}

@layer base {
  body {
    @apply bg-canvas font-sans text-ink antialiased;
  }

  h1, h2, h3 {
    text-wrap: balance;
  }

  /* A visible focus indicator on everything reachable by keyboard. */
  :where(a, button, summary, [tabindex]):focus-visible {
    outline: 2px solid var(--color-accent);
    outline-offset: 2px;
    border-radius: 4px;
  }

  /* Symfony adds class="required" to the labels of required fields. */
  label.required::after {
    content: " *";
    color: var(--color-signal);
  }
}
```

- [ ] **Step 6: Replace `assets/app.js`:**

```js
// Entry point loaded by {{ importmap('app') }}: starts Stimulus and pulls in the Tailwind CSS.
import './bootstrap.js';
import './styles/app.css';
```

- [ ] **Step 7: Disable Turbo Drive.** Loading the importmap would otherwise turn it on and change how forms submit (spec §3.5). In `assets/controllers.json`, set `"enabled": false` under `@symfony/ux-turbo` → `turbo-core`. Then delete the scaffold controller:

```bash
git rm -q assets/controllers/hello_controller.js
```

- [ ] **Step 8: Replace the `<head>` of `templates/base.html.twig`.** The `body` block stays as it is for now; Task 8 builds it.

```twig
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}MiniStore{% endblock %}</title>
    <meta name="description" content="{% block meta_description %}MiniStore : smartphones, montres connectées et audio, avec paiement sécurisé.{% endblock %}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">

    {% block stylesheets %}{% endblock %}
    {% block javascripts %}
      {# Import map + app.js: Stimulus controllers and the Tailwind CSS (imported by app.js). #}
      {{ importmap('app') }}
    {% endblock %}
  </head>

  <body>
    {% block body %}{% endblock %}
  </body>
</html>
```

- [ ] **Step 9: Build the CSS and check the tokens are compiled.**

Run: `php bin/console tailwind:build && grep -c -- "--color-accent" var/tailwind/app.built.css`
Expected: the first run downloads the v4.3.3 binary; the count is ≥ 1.

- [ ] **Step 10: Run the full suite.** Tests don't need the build: the bundle's strict mode is off in `test`.

Run: `php bin/phpunit`
Expected: PASS, including the new test. The cart template's `{% block javascripts %}{{ parent() }}…` now extends the new block, which is fine.

- [ ] **Step 11: Commit.**

```bash
git add composer.json composer.lock symfony.lock config/bundles.php config/packages/symfonycasts_tailwind.yaml config/packages/twig_component.yaml assets templates/base.html.twig tests/Controller/HomePageTest.php
git commit -m "feat(design): Tailwind v4 and Twig Components on AssetMapper, Turbo disabled"
```

---

### Task 6: Base UI components (Button, Badge, Alert, Card)

**Files:**
- Create: `templates/components/Button.html.twig`, `templates/components/Badge.html.twig`, `templates/components/Alert.html.twig`, `templates/components/Card.html.twig`, `tests/Twig/Components/UiComponentsTest.php`

**Interfaces:**
- Consumes: the tokens from Task 5 and the `#close` icon in `partials/svg_icons.html.twig`.
- Produces (anonymous components, called as `<twig:Name …>content</twig:Name>`):
  - `Button`: props `variant` (`primary`, `secondary`, `ghost`, `danger`; default `primary`), `size` (`sm`, `md`, `lg`; default `md`), `href` (string or null), `type` (default `button`). Renders `<a href>` when `href` is set, `<button type>` otherwise. Extra attributes, including `class`, are merged through `attributes`.
  - `Badge`: prop `tone` (`neutral`, `accent`, `signal`, `success`, `warning`; default `neutral`).
  - `Alert`: props `tone` (`info`, `success`, `warning`, `danger`; default `info`), `message` (string or null: escaped text used instead of the content block), `dismissible` (bool). The root carries the marker classes `alert alert-{tone}`. `role="alert"` for `danger`, `role="status"` otherwise.
  - `Card`: no props. Surface, border, 14px radius and shadow; the caller sets the padding.

- [ ] **Step 1: Write the failing tests** in `tests/Twig/Components/UiComponentsTest.php`:

```php
<?php

namespace App\Tests\Twig\Components;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

class UiComponentsTest extends KernelTestCase
{
    use InteractsWithTwigComponents;

    public function testButtonWithHrefIsALink(): void
    {
        $crawler = $this->renderTwigComponent('Button', ['href' => '/shop'], blocks: ['content' => 'Voir'])->crawler();

        $link = $crawler->filter('a[href="/shop"]');
        $this->assertCount(1, $link);
        $this->assertSame('Voir', trim($link->text()));
        $this->assertCount(0, $crawler->filter('button'));
    }

    public function testButtonWithoutHrefIsAButtonOfTheGivenType(): void
    {
        $crawler = $this->renderTwigComponent('Button', ['type' => 'submit'], blocks: ['content' => 'Payer'])->crawler();

        $this->assertSame('submit', $crawler->filter('button')->attr('type'));
    }

    public function testButtonVariantsAndUnknownVariantFallback(): void
    {
        $danger = $this->renderTwigComponent('Button', ['variant' => 'danger'])->crawler()->filter('button');
        $this->assertStringContainsString('bg-signal', $danger->attr('class'));

        $unknown = $this->renderTwigComponent('Button', ['variant' => 'nope'])->crawler()->filter('button');
        $this->assertStringContainsString('bg-accent', $unknown->attr('class'));
    }

    public function testButtonMergesExtraClassesAndAttributes(): void
    {
        $button = $this->renderTwigComponent('Button', ['class' => 'w-full', 'data-test' => 'go', 'disabled' => true])
            ->crawler()->filter('button');

        $this->assertStringContainsString('w-full', $button->attr('class'));
        $this->assertStringContainsString('rounded-control', $button->attr('class'));
        $this->assertSame('go', $button->attr('data-test'));
        $this->assertNotNull($button->attr('disabled'));
    }

    public function testBadgeTone(): void
    {
        $badge = $this->renderTwigComponent('Badge', ['tone' => 'success'], blocks: ['content' => 'Payée'])
            ->crawler()->filter('span');

        $this->assertStringContainsString('bg-success-soft', $badge->attr('class'));
        $this->assertSame('Payée', trim($badge->text()));
    }

    public function testAlertKeepsTheMarkerClassesAndRole(): void
    {
        $alert = $this->renderTwigComponent('Alert', ['tone' => 'danger', 'message' => 'Stock insuffisant'])
            ->crawler()->filter('div.alert');

        $this->assertStringContainsString('alert-danger', $alert->attr('class'));
        $this->assertSame('alert', $alert->attr('role'));
        $this->assertStringContainsString('Stock insuffisant', $alert->text());
    }

    public function testAlertEscapesItsMessage(): void
    {
        // Flash messages can contain user input (a product name, an email): they must never become HTML.
        $html = (string) $this->renderTwigComponent('Alert', ['message' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testDismissibleAlertHasACloseButtonWiredToStimulus(): void
    {
        $crawler = $this->renderTwigComponent('Alert', ['message' => 'Ok', 'dismissible' => true])->crawler();

        $this->assertSame('dismiss', $crawler->filter('div.alert')->attr('data-controller'));
        $this->assertSame('dismiss#close', $crawler->filter('button')->attr('data-action'));
    }

    public function testCardWrapsItsContent(): void
    {
        $card = $this->renderTwigComponent('Card', ['class' => 'p-6'], blocks: ['content' => '<p>Bonjour</p>'])
            ->crawler()->filter('div');

        $this->assertStringContainsString('rounded-card', $card->attr('class'));
        $this->assertStringContainsString('p-6', $card->attr('class'));
        $this->assertSame('Bonjour', $card->filter('p')->text());
    }
}
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `php bin/phpunit tests/Twig/Components/UiComponentsTest.php`
Expected: FAIL: `Unknown component "Button"`.

- [ ] **Step 3: Create `templates/components/Button.html.twig`.** It uses a single tag variable, so the `content` block is declared only once (Twig forbids declaring the same block twice).

```twig
{# Button or link styled as a button. <twig:Button href="/shop">…</twig:Button> renders an <a>. #}
{% props variant = 'primary', size = 'md', href = null, type = 'button' %}

{% set variants = {
  primary: 'bg-accent text-white shadow-xs hover:bg-accent-hover',
  secondary: 'border border-line bg-surface text-ink shadow-xs hover:bg-subtle',
  ghost: 'text-ink hover:bg-subtle',
  danger: 'bg-signal text-white shadow-xs hover:bg-red-700',
} %}
{% set sizes = {
  sm: 'h-9 px-3 text-sm',
  md: 'h-11 px-5 text-sm',
  lg: 'h-12 px-6 text-base',
} %}
{% set classes = [
  'inline-flex items-center justify-center gap-2 rounded-control font-semibold whitespace-nowrap transition duration-150',
  'focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-accent/25',
  'disabled:cursor-not-allowed disabled:opacity-50 motion-safe:active:scale-[.98]',
  variants[variant] ?? variants.primary,
  sizes[size] ?? sizes.md,
]|join(' ') %}
{% set tag = href ? 'a' : 'button' %}

<{{ tag }} {% if href %}href="{{ href }}"{% else %}type="{{ type }}"{% endif %} {{ attributes.defaults({class: classes}) }}>
  {%- block content %}{% endblock -%}
</{{ tag }}>
```

- [ ] **Step 4: Create `templates/components/Badge.html.twig`:**

```twig
{% props tone = 'neutral' %}

{% set tones = {
  neutral: 'bg-subtle text-ink',
  accent: 'bg-accent-soft text-accent-ink',
  signal: 'bg-signal-soft text-signal-ink',
  success: 'bg-success-soft text-success-ink',
  warning: 'bg-warning-soft text-warning-ink',
} %}

<span {{ attributes.defaults({class: 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ' ~ (tones[tone] ?? tones.neutral)}) }}>
  {%- block content %}{% endblock -%}
</span>
```

- [ ] **Step 5: Create `templates/components/Alert.html.twig`:**

```twig
{# Message box. "alert alert-{tone}" are marker classes used by functional tests (no CSS). #}
{% props tone = 'info', message = null, dismissible = false %}

{% set tones = {
  info: 'border-accent/20 bg-accent-soft text-accent-ink',
  success: 'border-success/20 bg-success-soft text-success-ink',
  warning: 'border-warning-ink/20 bg-warning-soft text-warning-ink',
  danger: 'border-signal/25 bg-signal-soft text-signal-ink',
} %}
{% set tone = tones[tone] is defined ? tone : 'info' %}

<div role="{{ tone == 'danger' ? 'alert' : 'status' }}"
     {{ attributes.defaults({
       class: 'alert alert-' ~ tone ~ ' flex items-start gap-3 rounded-control border px-4 py-3 text-sm font-medium ' ~ tones[tone],
     }|merge(dismissible ? {'data-controller': 'dismiss'} : {})) }}>
  <div class="min-w-0 flex-1 break-words">
    {%- if message is not null -%}{{ message }}{%- else -%}{% block content %}{% endblock %}{%- endif -%}
  </div>
  {% if dismissible %}
    <button type="button" data-action="dismiss#close"
            class="-m-1 rounded-md p-1 opacity-70 transition hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current">
      <svg class="size-4" aria-hidden="true"><use href="#close"></use></svg>
      <span class="sr-only">Fermer</span>
    </button>
  {% endif %}
</div>
```

- [ ] **Step 6: Create `templates/components/Card.html.twig`:**

```twig
<div {{ attributes.defaults({class: 'rounded-card border border-line bg-surface shadow-card'}) }}>
  {%- block content %}{% endblock -%}
</div>
```

- [ ] **Step 7: Run the tests.**

Run: `php bin/phpunit tests/Twig/Components/UiComponentsTest.php`
Expected: PASS (9 tests). If `testButtonMergesExtraClassesAndAttributes` shows that `class` was replaced instead of merged, the installed version's `attributes.defaults()` doesn't merge classes. In that case, change the four components to `class="{{ classes }} {{ attributes.render('class') }}" {{ attributes }}` and re-run.

- [ ] **Step 8: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/components tests/Twig/Components/UiComponentsTest.php
git commit -m "feat(design): Button, Badge, Alert and Card components"
```

---

### Task 7: Global form theme, and form types without Bootstrap classes

**Files:**
- Create: `templates/form/theme.html.twig`, `tests/Controller/FormThemeTest.php`
- Modify: `config/packages/twig.yaml`
- Modify: `src/Form/VerifyCodeType.php`, `src/Form/ChangePasswordFormType.php`, `src/Form/LoginFormType.php`, `src/Form/RegistrationFormType.php`, `src/Form/OrdersType.php`, `src/Form/EditProfileFormType.php`, `src/Controller/ContactController.php`

**Interfaces:**
- Consumes: the tokens from Task 5.
- Produces: every `form_row()` renders `<div class="field …">` (the marker class `field`). An invalid widget gets `aria-invalid="true"` and `aria-describedby="{id}_errors"`. Field errors are `<ul class="field-error" id="{id}_errors">`. Root-level errors render as a `div.alert.alert-danger`. Submit buttons use the primary button style.

- [ ] **Step 1: Write the failing test** `tests/Controller/FormThemeTest.php`. It uses the password-reset request page from Task 2 because that page renders with `form_row()`.

```php
<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class FormThemeTest extends DatabaseWebTestCase
{
    public function testFieldsAreDrawnByTheGlobalTheme(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');

        $this->assertCount(1, $crawler->filter('form[name="reset_password_request_form"] .field'));
        $class = $crawler->filter('#reset_password_request_form_email')->attr('class');
        $this->assertStringContainsString('rounded-control', $class);
        $this->assertStringNotContainsString('form-control', $class);
    }

    public function testAnInvalidFieldIsMarkedAndLinkedToItsError(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $form = $crawler->filter('form[name="reset_password_request_form"]')->form([
            'reset_password_request_form[email]' => 'not-an-email',
        ]);
        $crawler = $this->client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);

        $this->assertResponseStatusCodeSame(422);
        $input = $crawler->filter('#reset_password_request_form_email');
        $this->assertSame('true', $input->attr('aria-invalid'));
        $this->assertSame('reset_password_request_form_email_errors', $input->attr('aria-describedby'));
        $this->assertSelectorTextContains('#reset_password_request_form_email_errors', 'adresse e-mail valide');
    }

    public function testNoFormTypeCarriesBootstrapClassesAnyMore(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $this->assertCount(0, $crawler->filter('.form-control, .form-check-input, .form-label'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `php bin/phpunit tests/Controller/FormThemeTest.php`
Expected: FAIL: there is no `.field` element, and `/login` still contains `.form-control`.

- [ ] **Step 3: Create `templates/form/theme.html.twig`:**

```twig
{# Global form theme (config/packages/twig.yaml): every form_row()/form_widget() in the app is drawn here.
   It builds on Symfony's default div layout and only adds classes and accessibility attributes. #}
{% use 'form_div_layout.html.twig' %}

{%- block control_class -%}
block w-full rounded-control border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-xs transition placeholder:text-muted focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/20 disabled:bg-subtle disabled:text-muted aria-[invalid=true]:border-signal aria-[invalid=true]:focus:ring-signal/20
{%- endblock control_class -%}

{# Row: label, widget, help, errors. The widget points at its help and error ids so screen readers announce them. #}
{%- block form_row -%}
    {%- set described_by = [] -%}
    {%- if help is not empty -%}{%- set described_by = described_by|merge([id ~ '_help']) -%}{%- endif -%}
    {%- if errors|length > 0 -%}{%- set described_by = described_by|merge([id ~ '_errors']) -%}{%- endif -%}
    {%- set widget_attr = described_by is empty ? {} : {attr: {'aria-describedby': described_by|join(' ')}} -%}
    {%- set is_check = 'checkbox' in block_prefixes or 'radio' in block_prefixes -%}
    {%- set row_class = is_check ? 'field flex flex-wrap items-start gap-x-3 gap-y-1' : 'field space-y-1.5' -%}
    <div{% with {attr: row_attr|merge({class: (row_class ~ ' ' ~ (row_attr.class ?? ''))|trim})} %}{{ block('attributes') }}{% endwith %}>
        {%- if is_check -%}
            {{- form_widget(form, widget_attr) -}}
            {{- form_label(form) -}}
        {%- else -%}
            {{- form_label(form) -}}
            {{- form_widget(form, widget_attr) -}}
        {%- endif -%}
        {{- form_help(form) -}}
        {{- form_errors(form) -}}
    </div>
{%- endblock form_row -%}

{%- block form_widget_simple -%}
    {%- set type = type|default('text') -%}
    {%- if type == 'file' -%}
        {%- set attr = attr|merge({class: ('block w-full text-sm text-muted file:mr-4 file:rounded-control file:border-0 file:bg-accent-soft file:px-4 file:py-2 file:text-sm file:font-semibold file:text-accent-ink hover:file:bg-accent-soft/70 ' ~ (attr.class ?? ''))|trim}) -%}
    {%- elseif type != 'hidden' -%}
        {%- set attr = attr|merge({class: (block('control_class') ~ ' ' ~ (attr.class ?? ''))|trim}) -%}
    {%- endif -%}
    {%- if not valid -%}{%- set attr = attr|merge({'aria-invalid': 'true'}) -%}{%- endif -%}
    {{- parent() -}}
{%- endblock form_widget_simple -%}

{%- block textarea_widget -%}
    {%- set attr = attr|merge({class: (block('control_class') ~ ' min-h-28 ' ~ (attr.class ?? ''))|trim}) -%}
    {%- if not valid -%}{%- set attr = attr|merge({'aria-invalid': 'true'}) -%}{%- endif -%}
    {{- parent() -}}
{%- endblock textarea_widget -%}

{%- block choice_widget_collapsed -%}
    {%- set attr = attr|merge({class: (block('control_class') ~ ' pr-9 ' ~ (attr.class ?? ''))|trim}) -%}
    {%- if not valid -%}{%- set attr = attr|merge({'aria-invalid': 'true'}) -%}{%- endif -%}
    {{- parent() -}}
{%- endblock choice_widget_collapsed -%}

{%- block choice_widget_expanded -%}
    {%- set attr = attr|merge({class: ('space-y-2 ' ~ (attr.class ?? ''))|trim}) -%}
    <div {{ block('widget_container_attributes') }}>
        {%- for child in form -%}
            <div class="flex items-center gap-3">
                {{- form_widget(child) -}}
                {{- form_label(child, null, {translation_domain: choice_translation_domain}) -}}
            </div>
        {%- endfor -%}
    </div>
{%- endblock choice_widget_expanded -%}

{%- block checkbox_widget -%}
    {%- set attr = attr|merge({class: ('mt-0.5 size-4 shrink-0 rounded border-line accent-accent focus:outline-none focus:ring-4 focus:ring-accent/20 ' ~ (attr.class ?? ''))|trim}) -%}
    {{- parent() -}}
{%- endblock checkbox_widget -%}

{%- block radio_widget -%}
    {%- set attr = attr|merge({class: ('mt-0.5 size-4 shrink-0 rounded-full border-line accent-accent focus:outline-none focus:ring-4 focus:ring-accent/20 ' ~ (attr.class ?? ''))|trim}) -%}
    {{- parent() -}}
{%- endblock radio_widget -%}

{%- block form_label -%}
    {%- set is_check = 'checkbox' in block_prefixes or 'radio' in block_prefixes -%}
    {%- set label_attr = label_attr|merge({class: ((is_check ? 'text-sm text-ink' : 'block text-sm font-semibold text-ink') ~ ' ' ~ (label_attr.class ?? ''))|trim}) -%}
    {{- parent() -}}
{%- endblock form_label -%}

{%- block form_help -%}
    {%- set help_attr = help_attr|merge({class: ('w-full text-sm text-muted ' ~ (help_attr.class ?? ''))|trim}) -%}
    {{- parent() -}}
{%- endblock form_help -%}

{%- block form_errors -%}
    {%- if errors|length > 0 -%}
        {%- if form is rootform -%}
            {# Errors not tied to one field (an expired CSRF token, for example). #}
            <div class="alert alert-danger rounded-control border border-signal/25 bg-signal-soft px-4 py-3 text-sm font-medium text-signal-ink" role="alert">
                <ul class="space-y-1">{%- for error in errors -%}<li>{{ error.message }}</li>{%- endfor -%}</ul>
            </div>
        {%- else -%}
            <ul class="field-error w-full space-y-1 text-sm font-medium text-signal" id="{{ id }}_errors">
                {%- for error in errors -%}<li>{{ error.message }}</li>{%- endfor -%}
            </ul>
        {%- endif -%}
    {%- endif -%}
{%- endblock form_errors -%}

{%- block button_widget -%}
    {%- set attr = attr|merge({class: ('inline-flex h-11 items-center justify-center gap-2 rounded-control bg-accent px-5 text-sm font-semibold text-white shadow-xs transition hover:bg-accent-hover focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-accent/25 ' ~ (attr.class ?? ''))|trim}) -%}
    {{- parent() -}}
{%- endblock button_widget -%}
```

- [ ] **Step 4: Register the theme.** In `config/packages/twig.yaml`, under `twig:`:

```yaml
twig:
    file_name_pattern: '*.twig'
    form_themes: ['form/theme.html.twig']
```

- [ ] **Step 5: Strip the Bootstrap classes from the form types.**
  - In each of `VerifyCodeType`, `ChangePasswordFormType`, `LoginFormType`, `RegistrationFormType`, `OrdersType` and `EditProfileFormType`:
    - Delete every `'class' => '…'` entry inside an `attr`, `label_attr` or `row_attr` array.
    - Delete any array that becomes empty, including its key (for example `'label_attr' => []`).
  - In `LoginFormType`, also delete the `'id' => 'inputEmail'`, `'id' => 'inputPassword'` and `'id' => 'remember_me'` entries. They override the generated ids and break the labels' `for` attributes. Keep the `autocomplete` entries.
  - In `src/Controller/ContactController.php`, delete `'attr' => ['class' => 'btn btn-primary mt-3']` from the `submit` field.

Run: `grep -rnE "'class' =>|'id' => 'input" src/Form src/Controller/ContactController.php`
Expected: no output.

- [ ] **Step 6: Run the new test and the form-heavy suites.**

Run: `php bin/console cache:clear && php bin/phpunit tests/Controller/FormThemeTest.php tests/Controller/RegistrationFormTest.php tests/Order`
Expected: PASS. `RegistrationFormTest` still passes because Task 12 has not changed `.card-body` yet.

- [ ] **Step 7: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/form config/packages/twig.yaml src/Form src/Controller/ContactController.php tests/Controller/FormThemeTest.php
git commit -m "feat(design): global Tailwind form theme with accessible error states"
```

---

### Task 8: Layout, header, footer, flash messages and Stimulus controllers

**Files:**
- Create: `assets/controllers/menu_controller.js`, `assets/controllers/dropdown_controller.js`, `assets/controllers/dismiss_controller.js`, `assets/controllers/autosubmit_controller.js`, `assets/controllers/confirm_controller.js`, `assets/controllers/disclosure_controller.js`, `templates/partials/flashes.html.twig`, `tests/Controller/HeaderTest.php`
- Modify (replace): `templates/partials/header.html.twig`, `templates/partials/footer.html.twig`
- Modify: `templates/base.html.twig` (the `body` element)

**Interfaces:**
- Consumes: `Alert` (Task 6), the Twig global `cart_count` (set by `App\EventSubscriber\CartSubscriber`), and the icons in `partials/svg_icons.html.twig`.
- Produces:
  - `base.html.twig` includes the SVG sprite and a skip link, then a default `{% block body %}` containing the header, `<main id="main">` with the flash messages and `{% block content %}`, and the footer. **Migrated pages override `content` only.** Pages not yet migrated keep overriding `body`.
  - Stimulus identifiers: `menu` (targets: `dialog`; actions `open`, `close`, `backdropClose`), `dropdown` (targets: `button`, `menu`; actions `toggle`, `closeOnOutsideClick`, `closeOnEscape`), `dismiss` (action `close`), `autosubmit` (action `submit`), `confirm` (value `message`; action `ask`), `disclosure` (values `keepOpen`: Boolean, `breakpoint`: String).
  - Header markers: `header form[role="search"]`, `#account-menu`, `header dialog`.

- [ ] **Step 1: Write the failing test** `tests/Controller/HeaderTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class HeaderTest extends DatabaseWebTestCase
{
    public function testCartLinkShowsTheItemCount(): void
    {
        $this->addToCartFromShop($this->createProduct());

        $this->client->request('GET', '/');

        $this->assertSelectorTextContains('header a[href="/cart"]', '1');
    }

    public function testMobileMenuIsANativeDialogHoldingTheNavigation(): void
    {
        $crawler = $this->client->request('GET', '/');

        $this->assertCount(1, $crawler->filter('header dialog[data-menu-target="dialog"] a[href="/shop"]'));
        $this->assertSame('menu#open', $crawler->filter('header button[data-action="menu#open"]')->attr('data-action'));
    }

    public function testGuestAccountMenuOffersLoginAndRegister(): void
    {
        $crawler = $this->client->request('GET', '/');

        $button = $crawler->filter('header [data-dropdown-target="button"]');
        $this->assertSame('account-menu', $button->attr('aria-controls'));
        $this->assertSame('false', $button->attr('aria-expanded'));
        $this->assertCount(1, $crawler->filter('#account-menu a[href="/login"]'));
        $this->assertCount(1, $crawler->filter('#account-menu a[href="/register"]'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `php bin/phpunit tests/Controller/HeaderTest.php`
Expected: FAIL. The old header has no `dialog` and no `data-dropdown-target`.

- [ ] **Step 3: Create the six Stimulus controllers.**

`assets/controllers/menu_controller.js`:

```js
import { Controller } from '@hotwired/stimulus';

// Mobile navigation drawer built on the native <dialog> element:
// showModal() traps focus, closes on Escape and makes the page behind it inert.
export default class extends Controller {
    static targets = ['dialog'];

    open() {
        this.dialogTarget.showModal();
    }

    close() {
        this.dialogTarget.close();
    }

    // A click on the dimmed backdrop lands on the <dialog> element itself.
    backdropClose(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
```

`assets/controllers/dropdown_controller.js`:

```js
import { Controller } from '@hotwired/stimulus';

// Account menu: the button toggles the panel; a click outside or Escape closes it.
// The document-level listeners are declared in the HTML (click@document, keydown.esc@document).
export default class extends Controller {
    static targets = ['button', 'menu'];

    toggle() {
        this.menuTarget.hidden ? this.open() : this.close();
    }

    open() {
        this.menuTarget.hidden = false;
        this.buttonTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.menuTarget.hidden = true;
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }

    closeOnOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    closeOnEscape() {
        if (!this.menuTarget.hidden) {
            this.close();
            this.buttonTarget.focus();
        }
    }
}
```

`assets/controllers/dismiss_controller.js`:

```js
import { Controller } from '@hotwired/stimulus';

// Removes a dismissible alert from the page.
export default class extends Controller {
    close() {
        this.element.remove();
    }
}
```

`assets/controllers/autosubmit_controller.js`:

```js
import { Controller } from '@hotwired/stimulus';

// Submits the field's form as soon as its value changes (shop sort, cart quantity).
// event.target.form also works for a <select form="…"> placed outside its form.
// Each form keeps a <noscript> submit button, so it still works without JavaScript.
export default class extends Controller {
    submit(event) {
        const form = event.target.form ?? this.element.closest('form');
        form?.requestSubmit();
    }
}
```

`assets/controllers/confirm_controller.js`:

```js
import { Controller } from '@hotwired/stimulus';

// Asks for confirmation before a destructive form is submitted.
// Usage: <form data-controller="confirm" data-confirm-message-value="…" data-action="confirm#ask">
export default class extends Controller {
    static values = { message: String };

    ask(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
```

`assets/controllers/disclosure_controller.js`:

```js
import { Controller } from '@hotwired/stimulus';

// Folds a <details> on small screens so the content below is visible first,
// unless keepOpen is set (for example when filters are active).
export default class extends Controller {
    static values = {
        keepOpen: Boolean,
        breakpoint: { type: String, default: '(max-width: 1023.98px)' },
    };

    connect() {
        if (!this.keepOpenValue && window.matchMedia(this.breakpointValue).matches) {
            this.element.open = false;
        }
    }
}
```

`confirm#ask` needs no event prefix: Stimulus's default event for a `<form>` is `submit`.

- [ ] **Step 4: Create `templates/partials/flashes.html.twig`:**

```twig
{# Every flash message, whatever the page. Types are mapped to Alert tones; "danger" keeps the
   .alert-danger marker the tests rely on. #}
{% set tones = {success: 'success', warning: 'warning', error: 'danger', danger: 'danger', info: 'info'} %}
{% set flashes = app.flashes %}
{% if flashes is not empty %}
  <div class="page-wrap mt-6 space-y-3">
    {% for type, messages in flashes %}
      {% for message in messages %}
        <twig:Alert :tone="tones[type] ?? 'info'" :message="message" dismissible />
      {% endfor %}
    {% endfor %}
  </div>
{% endif %}
```

- [ ] **Step 5: Replace the `<body>` of `templates/base.html.twig`:**

```twig
  <body class="flex min-h-dvh flex-col">
    {% include 'partials/svg_icons.html.twig' %}
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-surface focus:px-4 focus:py-2 focus:font-semibold focus:shadow-card">Aller au contenu</a>

    {# Migrated pages override "content". Pages not yet migrated override the whole "body". #}
    {% block body %}
      {% include 'partials/header.html.twig' %}
      <main id="main" class="flex-1">
        {% include 'partials/flashes.html.twig' %}
        {% block content %}{% endblock %}
      </main>
      {% include 'partials/footer.html.twig' %}
    {% endblock %}
  </body>
```

- [ ] **Step 6: Replace `templates/partials/header.html.twig`:**

```twig
{% set route = app.request.attributes.get('_route') %}
{% set nav = [
  {route: 'app_shop', label: 'Boutique'},
  {route: 'app_about', label: 'À propos'},
  {route: 'app_contact', label: 'Contact'},
] %}
{% set count = cart_count is defined ? cart_count : 0 %}
{% set icon_button = 'relative inline-flex size-10 items-center justify-center rounded-full text-ink transition hover:bg-subtle' %}
{% set menu_item = 'block px-4 py-2 text-sm font-medium text-ink hover:bg-subtle' %}
{% set search_input = 'h-10 w-full rounded-full border border-line bg-subtle pl-9 pr-4 text-sm text-ink placeholder:text-muted focus:border-accent focus:bg-surface focus:outline-none focus:ring-4 focus:ring-accent/20' %}

<header class="sticky top-0 z-40 border-b border-line bg-surface/90 backdrop-blur" data-controller="menu">
  <div class="page-wrap flex h-16 items-center gap-4 lg:gap-8">
    <a href="{{ path('app_home') }}" class="text-lg font-extrabold tracking-tight text-ink">Mini<span class="text-accent">Store</span></a>

    <nav aria-label="Navigation principale" class="hidden lg:block">
      <ul class="flex items-center gap-1">
        {% for item in nav %}
          <li>
            <a href="{{ path(item.route) }}" {% if route == item.route %}aria-current="page"{% endif %}
               class="rounded-control px-3 py-2 text-sm font-semibold text-muted transition hover:bg-subtle hover:text-ink aria-[current=page]:text-ink">{{ item.label }}</a>
          </li>
        {% endfor %}
      </ul>
    </nav>

    <form role="search" method="get" action="{{ path('app_shop') }}" class="relative ml-auto hidden w-full max-w-xs md:block">
      <label for="header-search" class="sr-only">Rechercher un produit</label>
      <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden="true"><use href="#search"></use></svg>
      <input type="search" id="header-search" name="q" value="{{ app.request.query.get('q') }}" maxlength="100" placeholder="Rechercher un produit" class="{{ search_input }}">
    </form>

    <div class="ml-auto flex items-center gap-1 md:ml-0">
      <div class="relative" data-controller="dropdown"
           data-action="click@document->dropdown#closeOnOutsideClick keydown.esc@document->dropdown#closeOnEscape">
        <button type="button" class="{{ icon_button }}" data-dropdown-target="button" data-action="dropdown#toggle"
                aria-expanded="false" aria-haspopup="true" aria-controls="account-menu">
          <svg class="size-5" aria-hidden="true"><use href="#user"></use></svg>
          <span class="sr-only">Compte</span>
        </button>
        <div id="account-menu" data-dropdown-target="menu" hidden
             class="absolute right-0 mt-2 w-60 overflow-hidden rounded-card border border-line bg-surface py-2 shadow-card-hover">
          {% if app.user %}
            <p class="truncate px-4 pb-2 text-xs font-semibold text-muted">{{ app.user.userIdentifier }}</p>
            <a href="{{ path('app_account') }}" class="{{ menu_item }}">Mon compte</a>
            <a href="{{ path('app_orders') }}" class="{{ menu_item }}">Mes commandes</a>
            {% if is_granted('ROLE_ADMIN') %}
              <hr class="my-2 border-line">
              <a href="{{ path('admin_dashboard') }}" class="{{ menu_item }}">Administration</a>
            {% endif %}
            <hr class="my-2 border-line">
            <a href="{{ path('app_logout') }}" class="{{ menu_item }}">Se déconnecter</a>
          {% else %}
            <a href="{{ path('app_login') }}" class="{{ menu_item }}">Se connecter</a>
            <a href="{{ path('app_register') }}" class="{{ menu_item }}">Créer un compte</a>
          {% endif %}
        </div>
      </div>

      <a href="{{ path('app_cart') }}" class="{{ icon_button }}">
        <svg class="size-5" aria-hidden="true"><use href="#cart"></use></svg>
        <span class="sr-only">Panier{% if count > 0 %}, {{ count }} article{{ count > 1 ? 's' }}{% endif %}</span>
        {% if count > 0 %}
          <span aria-hidden="true" class="absolute -right-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-bold leading-5 text-white">{{ count }}</span>
        {% endif %}
      </a>

      <button type="button" class="{{ icon_button }} lg:hidden" data-action="menu#open">
        <svg class="size-5" aria-hidden="true"><use href="#navbar-icon"></use></svg>
        <span class="sr-only">Menu</span>
      </button>
    </div>
  </div>

  <dialog data-menu-target="dialog" data-action="click->menu#backdropClose" aria-labelledby="mobile-menu-title"
          class="m-0 ml-auto h-dvh max-h-none w-80 max-w-[85vw] bg-surface p-0 text-ink shadow-card-hover backdrop:bg-ink/40 open:flex open:flex-col">
    <div class="flex h-16 items-center justify-between border-b border-line px-4">
      <span id="mobile-menu-title" class="text-lg font-extrabold tracking-tight">Mini<span class="text-accent">Store</span></span>
      <button type="button" class="{{ icon_button }}" data-action="menu#close">
        <svg class="size-5" aria-hidden="true"><use href="#close"></use></svg>
        <span class="sr-only">Fermer le menu</span>
      </button>
    </div>
    <div class="flex-1 space-y-6 overflow-y-auto p-4">
      <form role="search" method="get" action="{{ path('app_shop') }}" class="relative">
        <label for="mobile-search" class="sr-only">Rechercher un produit</label>
        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden="true"><use href="#search"></use></svg>
        <input type="search" id="mobile-search" name="q" value="{{ app.request.query.get('q') }}" maxlength="100" placeholder="Rechercher un produit" class="{{ search_input }}">
      </form>
      <nav aria-label="Navigation mobile">
        <ul class="space-y-1">
          {% for item in nav %}
            <li><a href="{{ path(item.route) }}" {% if route == item.route %}aria-current="page"{% endif %}
                   class="block rounded-control px-3 py-2.5 font-semibold text-ink hover:bg-subtle aria-[current=page]:bg-accent-soft aria-[current=page]:text-accent-ink">{{ item.label }}</a></li>
          {% endfor %}
        </ul>
      </nav>
    </div>
  </dialog>
</header>
```

- [ ] **Step 7: Replace `templates/partials/footer.html.twig`:**

```twig
{# Only facts the shop actually applies: shipping comes from the same constant as the cart. #}
{% set footer_link = 'text-sm text-muted transition hover:text-ink' %}
<footer class="mt-16 border-t border-line bg-surface">
  <div class="page-wrap grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
    <div class="space-y-4">
      <a href="{{ path('app_home') }}" class="text-lg font-extrabold tracking-tight text-ink">Mini<span class="text-accent">Store</span></a>
      <p class="text-sm text-muted">Smartphones, montres connectées et audio.</p>
      <ul class="space-y-2 text-sm text-muted">
        <li class="flex items-center gap-2">
          <svg class="size-4 text-accent" aria-hidden="true"><use href="#truck"></use></svg>
          Livraison {{ constant('App\\Cart\\CartTotals::SHIPPING_COST')|money }} par commande
        </li>
        <li class="flex items-center gap-2">
          <svg class="size-4 text-accent" aria-hidden="true"><use href="#lock"></use></svg>
          Paiement sécurisé par Stripe
        </li>
      </ul>
    </div>

    <nav aria-labelledby="footer-shop">
      <h2 id="footer-shop" class="text-sm font-bold text-ink">Boutique</h2>
      <ul class="mt-3 space-y-2">
        <li><a href="{{ path('app_shop') }}" class="{{ footer_link }}">Tous les produits</a></li>
        <li><a href="{{ path('app_cart') }}" class="{{ footer_link }}">Panier</a></li>
      </ul>
    </nav>

    <nav aria-labelledby="footer-account">
      <h2 id="footer-account" class="text-sm font-bold text-ink">Compte</h2>
      <ul class="mt-3 space-y-2">
        {% if app.user %}
          <li><a href="{{ path('app_account') }}" class="{{ footer_link }}">Mon compte</a></li>
          <li><a href="{{ path('app_orders') }}" class="{{ footer_link }}">Mes commandes</a></li>
        {% else %}
          <li><a href="{{ path('app_login') }}" class="{{ footer_link }}">Se connecter</a></li>
          <li><a href="{{ path('app_register') }}" class="{{ footer_link }}">Créer un compte</a></li>
        {% endif %}
      </ul>
    </nav>

    <nav aria-labelledby="footer-help">
      <h2 id="footer-help" class="text-sm font-bold text-ink">Aide</h2>
      <ul class="mt-3 space-y-2">
        <li><a href="{{ path('app_contact') }}" class="{{ footer_link }}">Contact</a></li>
        <li><a href="{{ path('app_about') }}" class="{{ footer_link }}">À propos</a></li>
      </ul>
    </nav>
  </div>
  <div class="border-t border-line">
    <p class="page-wrap py-6 text-xs text-muted">© {{ 'now'|date('Y') }} MiniStore</p>
  </div>
</footer>
```

- [ ] **Step 8: Run the tests.**

Run: `php bin/phpunit tests/Controller/HeaderTest.php tests/Controller/HomePageTest.php tests/Controller/ShopSearchTest.php`
Expected: PASS. `testAdminCanSeeTheStorefront` finds `header a[href="/admin"]` inside `#account-menu`.

- [ ] **Step 9: Check the page and the CSRF behaviour in a browser.** This is the step where stateless CSRF JavaScript starts running (spec §5.3).

Run: `php bin/console tailwind:build && php -S 127.0.0.1:8000 -t public` (in the background).

With Playwright at 375px and 1280px on `http://127.0.0.1:8000/`:
- The header renders with the indigo accent.
- The account button opens the menu; a click outside and Escape both close it.
- At 375px the menu button opens the dialog; Escape and a click on the backdrop close it.

Then, as a real user:
- log in and log out;
- register a new account (the SMS code step may need Twilio: stop at the verification page);
- add a product to the cart from `/shop` (the page is still unstyled; that's expected).

Expected: each submission succeeds (no "invalid CSRF token" message).

- [ ] **Step 10: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add assets/controllers templates/base.html.twig templates/partials/header.html.twig templates/partials/footer.html.twig templates/partials/flashes.html.twig tests/Controller/HeaderTest.php
git commit -m "feat(design): Tailwind layout, header, footer, flash messages and Stimulus controllers"
```

---

## Part C — Storefront pages

### Task 9: Catalogue components (PriceTag, PageHeader, Pagination, EmptyState, ProductCard)

**Files:**
- Create: `templates/components/PriceTag.html.twig`, `templates/components/PageHeader.html.twig`, `templates/components/Pagination.html.twig`, `templates/components/EmptyState.html.twig`, `templates/components/ProductCard.html.twig`, `tests/Twig/Components/CatalogComponentsTest.php`
- Modify: `assets/styles/app.css` (add the `product-grid` utility)

**Interfaces:**
- Consumes: `Badge` and `Button` (Task 6), the `money` and `category_label` filters, and the icons `#chevron-left`, `#chevron-right` and `#box`.
- Produces:
  - `PriceTag`: props `price` (int, in cents), `size` (`sm`, `md`, `lg`).
  - `PageHeader`: props `title` (string), `parent` (string or null), `parentUrl` (string or null), `showTitle` (bool, default true), `lead` (string or null). It renders a breadcrumb, then an `h1` when `showTitle` is true.
  - `Pagination`: props `page` (int), `pages` (int), `route` (string), `params` (array). It renders nothing when `pages <= 1`. Its root has the marker `ms-pagination`, and it links `rel="prev"`/`rel="next"`. It shows the first, last and current ±1 pages, with an ellipsis for gaps.
  - `EmptyState`: props `title` (string), `icon` (sprite id, default `box`). Blocks: `content` (the text) and `actions`.
  - `ProductCard`: prop `product` (`App\Entity\Product`). The title carries the marker `ms-tile__name`. When stock > 0 it contains the add-to-cart form (`form[action="/cart/add/{id}"]` with the `cart` CSRF token).
  - The `product-grid` utility: a responsive grid, one column at 360px, auto-filling columns of at least 15rem.

- [ ] **Step 1: Write the failing tests** in `tests/Twig/Components/CatalogComponentsTest.php`. `ProductCard` needs a request and a session (for `csrf_token`), so it is tested through real pages in Task 10.

```php
<?php

namespace App\Tests\Twig\Components;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

class CatalogComponentsTest extends KernelTestCase
{
    use InteractsWithTwigComponents;

    public function testPriceTagUsesFrenchFormatting(): void
    {
        $html = (string) $this->renderTwigComponent('PriceTag', ['price' => 123456]);

        $this->assertStringContainsString("1\u{202F}234,56\u{00A0}$", $html);
        $this->assertStringContainsString('tabular-nums', $html);
    }

    public function testPageHeaderRendersBreadcrumbAndEscapedTitle(): void
    {
        $crawler = $this->renderTwigComponent('PageHeader', [
            'title' => 'Résultats pour « <b>x</b> »',
            'parent' => 'Boutique',
            'parentUrl' => '/shop',
        ])->crawler();

        $this->assertSame('Résultats pour « <b>x</b> »', $crawler->filter('h1')->text());
        $this->assertCount(0, $crawler->filter('h1 b'));
        $this->assertCount(1, $crawler->filter('nav a[href="/shop"]'));
        $this->assertSame('page', $crawler->filter('[aria-current]')->attr('aria-current'));
    }

    public function testPageHeaderCanLeaveTheTitleToThePage(): void
    {
        $crawler = $this->renderTwigComponent('PageHeader', ['title' => 'iPhone', 'showTitle' => false])->crawler();

        $this->assertCount(0, $crawler->filter('h1'));
    }

    public function testPaginationKeepsTheQueryAndLinksNext(): void
    {
        $crawler = $this->renderTwigComponent('Pagination', [
            'page' => 1, 'pages' => 3, 'route' => 'app_shop', 'params' => ['category' => 'phones'],
        ])->crawler();

        $this->assertCount(0, $crawler->filter('a[rel="prev"]'));
        $this->assertSame('/shop?category=phones&page=2', $crawler->filter('.ms-pagination a[rel="next"]')->attr('href'));
        $this->assertSame('1', $crawler->filter('[aria-current="page"]')->text());
    }

    public function testPaginationCollapsesLongRanges(): void
    {
        $crawler = $this->renderTwigComponent('Pagination', [
            'page' => 5, 'pages' => 10, 'route' => 'app_shop',
        ])->crawler();

        $labels = $crawler->filter('.ms-pagination > a:not([rel]), .ms-pagination > span')->each(fn ($n) => trim($n->text()));
        $this->assertSame(['1', '…', '4', '5', '6', '…', '10'], $labels);
    }

    public function testPaginationIsHiddenForASinglePage(): void
    {
        $html = trim((string) $this->renderTwigComponent('Pagination', ['page' => 1, 'pages' => 1, 'route' => 'app_shop']));

        $this->assertSame('', $html);
    }

    public function testEmptyStateRendersTitleTextAndActions(): void
    {
        $crawler = $this->renderTwigComponent('EmptyState', ['title' => 'Votre panier est vide'], blocks: [
            'content' => 'Parcourez la boutique.',
            'actions' => '<a href="/shop">Voir la boutique</a>',
        ])->crawler();

        $this->assertSame('Votre panier est vide', $crawler->filter('h2')->text());
        $this->assertStringContainsString('Parcourez la boutique.', $crawler->text());
        $this->assertCount(1, $crawler->filter('a[href="/shop"]'));
    }
}
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `php bin/phpunit tests/Twig/Components/CatalogComponentsTest.php`
Expected: FAIL: `Unknown component "PriceTag"`.

- [ ] **Step 3: Create `templates/components/PriceTag.html.twig`:**

```twig
{% props price, size = 'md' %}
{% set sizes = {sm: 'text-sm', md: 'text-base', lg: 'text-2xl sm:text-3xl'} %}
<span {{ attributes.defaults({class: 'font-bold tabular-nums tracking-tight text-ink ' ~ (sizes[size] ?? sizes.md)}) }}>{{ price|money }}</span>
```

- [ ] **Step 4: Create `templates/components/PageHeader.html.twig`:**

```twig
{# Page title with its breadcrumb. showTitle: false when the page renders its own <h1>. #}
{% props title, parent = null, parentUrl = null, showTitle = true, lead = null %}
<div {{ attributes.defaults({class: 'page-wrap pb-6 pt-8 sm:pt-10'}) }}>
  <nav aria-label="Fil d’Ariane">
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-muted">
      <li><a href="{{ path('app_home') }}" class="hover:text-ink">Accueil</a></li>
      {% if parent and parentUrl %}
        <li aria-hidden="true">/</li>
        <li><a href="{{ parentUrl }}" class="hover:text-ink">{{ parent }}</a></li>
      {% endif %}
      <li aria-hidden="true">/</li>
      <li aria-current="page" class="max-w-full truncate font-medium text-ink">{{ title }}</li>
    </ol>
  </nav>
  {% if showTitle %}
    <h1 class="mt-3 break-words text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ title }}</h1>
    {% if lead %}<p class="mt-2 max-w-2xl text-muted">{{ lead }}</p>{% endif %}
  {% endif %}
</div>
```

- [ ] **Step 5: Create `templates/components/Pagination.html.twig`:**

```twig
{# Numbered pagination: first, last, current ±1, "…" for gaps. ".ms-pagination" is a test marker.
   params: the query string to keep (filters, sort); "page" is replaced. #}
{% props page, pages, route, params = {} %}
{% set item = 'inline-flex h-10 min-w-10 items-center justify-center gap-1 rounded-control px-3 text-sm font-semibold' %}
{% set window = 1 %}
{% if pages > 1 %}
  <nav aria-label="Pagination" {{ attributes.defaults({class: 'ms-pagination mt-10 flex flex-wrap items-center justify-center gap-1.5'}) }}>
    {% if page > 1 %}
      <a href="{{ path(route, params|merge({page: page - 1})) }}" rel="prev" class="{{ item }} text-ink hover:bg-subtle">
        <svg class="size-4" aria-hidden="true"><use href="#chevron-left"></use></svg><span class="sr-only sm:not-sr-only">Précédent</span>
      </a>
    {% endif %}
    {% for n in 1..pages %}
      {% if n == 1 or n == pages or (n >= page - window and n <= page + window) %}
        {% if n == page %}
          <span aria-current="page" class="{{ item }} bg-accent text-white">{{ n }}</span>
        {% else %}
          <a href="{{ path(route, params|merge({page: n})) }}" class="{{ item }} text-ink hover:bg-subtle">{{ n }}</a>
        {% endif %}
      {% elseif n == page - window - 1 or n == page + window + 1 %}
        <span class="{{ item }} text-muted" aria-hidden="true">…</span>
      {% endif %}
    {% endfor %}
    {% if page < pages %}
      <a href="{{ path(route, params|merge({page: page + 1})) }}" rel="next" class="{{ item }} text-ink hover:bg-subtle">
        <span class="sr-only sm:not-sr-only">Suivant</span><svg class="size-4" aria-hidden="true"><use href="#chevron-right"></use></svg>
      </a>
    {% endif %}
  </nav>
{% endif %}
```

- [ ] **Step 6: Create `templates/components/EmptyState.html.twig`:**

```twig
{% props title, icon = 'box' %}
<div {{ attributes.defaults({class: 'flex flex-col items-center rounded-card border border-dashed border-line bg-surface px-6 py-14 text-center'}) }}>
  <span class="inline-flex size-12 items-center justify-center rounded-full bg-accent-soft text-accent">
    <svg class="size-6" aria-hidden="true"><use href="#{{ icon }}"></use></svg>
  </span>
  <h2 class="mt-4 text-lg font-bold text-ink">{{ title }}</h2>
  <div class="mt-2 max-w-md text-sm text-muted">{% block content %}{% endblock %}</div>
  <div class="mt-6 flex flex-wrap justify-center gap-3">{% block actions %}{% endblock %}</div>
</div>
```

- [ ] **Step 7: Create `templates/components/ProductCard.html.twig`.** It uses a "stretched link": the title link's `::after` covers the whole card, so the card is clickable while there is still only one link per product. The form sits above it (`relative z-10`).

```twig
{# Product tile. Stock status is the real value, not decoration. ".ms-tile__name" is a test marker. #}
{% props product %}
{% set url = path('app_single_product', {slug: product.slug}) %}
{% set low_stock_threshold = 3 %}

<article {{ attributes.defaults({class: 'group relative flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-card transition duration-200 hover:shadow-card-hover motion-safe:hover:-translate-y-0.5'}) }}>
  <div class="relative aspect-square overflow-hidden bg-subtle">
    {% if product.image %}
      <img src="{{ asset(product.image) }}" alt="" loading="lazy" width="400" height="400"
           class="size-full object-cover transition duration-300 motion-safe:group-hover:scale-[1.03]">
    {% endif %}
    {% if product.isIsSale %}
      <twig:Badge tone="signal" class="absolute left-3 top-3">Promo</twig:Badge>
    {% endif %}
  </div>

  <div class="flex flex-1 flex-col gap-3 p-4">
    <div class="min-w-0">
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ product.category|category_label }}</p>
      <h3 class="ms-tile__name mt-1 break-words font-bold leading-snug text-ink">
        <a href="{{ url }}" class="after:absolute after:inset-0">{{ product.name }}</a>
      </h3>
    </div>

    <div class="mt-auto flex flex-wrap items-center justify-between gap-2">
      <twig:PriceTag :price="product.price" />
      {% if product.stock <= 0 %}
        <twig:Badge tone="signal">Épuisé</twig:Badge>
      {% elseif product.stock <= low_stock_threshold %}
        <twig:Badge tone="warning">Plus que {{ product.stock }}</twig:Badge>
      {% else %}
        <twig:Badge tone="success">En stock</twig:Badge>
      {% endif %}
    </div>

    {% if product.stock > 0 %}
      <form action="{{ path('app_cart_add', {id: product.id}) }}" method="post" class="relative z-10">
        <input type="hidden" name="_token" value="{{ csrf_token('cart') }}">
        <twig:Button type="submit" class="w-full">
          Ajouter au panier<span class="sr-only"> : {{ product.name }}</span>
        </twig:Button>
      </form>
    {% else %}
      <twig:Button variant="secondary" class="relative z-10 w-full" disabled>Indisponible</twig:Button>
    {% endif %}
  </div>
</article>
```

- [ ] **Step 8: Add the grid utility** to `assets/styles/app.css`, after `page-wrap`:

```css
/* Product and post grids: one column at 360px, then as many >= 15rem columns as fit. */
@utility product-grid {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
}
```

- [ ] **Step 9: Run the tests.**

Run: `php bin/phpunit tests/Twig/Components/CatalogComponentsTest.php`
Expected: PASS (7 tests). If `testPaginationKeepsTheQueryAndLinksNext` fails only because of parameter order (`page=2&category=phones`), compare the query as parsed arrays instead of strings. Don't change the component.

- [ ] **Step 10: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/components assets/styles/app.css tests/Twig/Components/CatalogComponentsTest.php
git commit -m "feat(design): catalogue components (price, page header, pagination, empty state, product card)"
```

---

### Task 10: Home, shop and product pages

**Files:**
- Modify (replace): `templates/index/index.html.twig`, `templates/shop/shop.html.twig`, `templates/single-product/single-product.html.twig`
- Create: `tests/Controller/CataloguePagesTest.php`

**Interfaces:**
- Consumes:
  - components `ProductCard`, `PriceTag`, `PageHeader`, `Pagination`, `EmptyState`, `Button` and `Badge`;
  - the Stimulus controllers `autosubmit` and `disclosure`;
  - controller variables, unchanged:
    - home: `featured` (`Product` or null), `categories` (map of category ⇒ count), `new_arrivals` (`Product[]`);
    - shop: `filters` (`App\Catalog\ProductFilters`: `query`, `category`, `minPriceCents`, `maxPriceCents`, `inStockOnly`, `sort`, `isFiltered`), `categories`, `sorts` (map of value ⇒ label), `page` (`items`, `totalCount`, `pageCount`, `pageNumber`);
    - product: `product`, `related_products`.
- Produces: pages built on `{% block content %}`, keeping every marker listed in Global Constraints.

- [ ] **Step 1: Write the failing tests** `tests/Controller/CataloguePagesTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class CataloguePagesTest extends DatabaseWebTestCase
{
    public function testCatalogueLayoutsRenderInsideTheNewLayout(): void
    {
        $this->createProduct('Test Phone');

        foreach (['/', '/shop', '/product/test-phone'] as $url) {
            $crawler = $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
            $this->assertCount(1, $crawler->filter('main#main'), "$url must use the base layout's <main>.");
            $this->assertCount(0, $crawler->filter('.container, .row, [class*="col-md-"]'), "$url still has Bootstrap markup.");
        }
    }

    public function testProductCardShowsSaleAndStockBadges(): void
    {
        $this->createProduct('Sale Phone')->setIsSale(true);
        $this->createProduct('Gone Phone')->setStock(0);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/shop');

        $sale = $crawler->filter('article')->reduce(fn ($a) => str_contains($a->text(), 'Sale Phone'));
        $this->assertStringContainsString('Promo', $sale->text());
        $this->assertStringContainsString('En stock', $sale->text());
        $gone = $crawler->filter('article')->reduce(fn ($a) => str_contains($a->text(), 'Gone Phone'));
        $this->assertStringContainsString('Épuisé', $gone->text());
        $this->assertCount(0, $gone->filter('form'));
    }

    public function testSearchTermIsShownAsTextNotHtml(): void
    {
        $this->client->request('GET', '/shop?q=' . rawurlencode('<script>alert(1)</script>'));

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $this->client->getResponse()->getContent());
        $this->assertSelectorTextContains('h1', '<script>alert(1)</script>');
    }

    public function testEmptyShopShowsAnEmptyState(): void
    {
        $this->client->request('GET', '/shop?q=nothing-matches');

        $this->assertSelectorTextContains('main h2', 'Aucun produit ne correspond');
        $this->assertSelectorExists('main a[href="/shop"]');
    }

    public function testSortWorksWithoutJavascript(): void
    {
        $crawler = $this->client->request('GET', '/shop');

        $this->assertSame('shop-filters', $crawler->filter('select[name="tri"]')->attr('form'));
        $this->assertSame('change->autosubmit#submit', $crawler->filter('select[name="tri"]')->attr('data-action'));
        $this->assertStringContainsString('form="shop-filters"', $crawler->filter('noscript')->html());
    }
}
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `php bin/phpunit tests/Controller/CataloguePagesTest.php`
Expected: FAIL. There is no `main#main` (the old templates override `body`), and the old sort `select` has an inline `onchange`.

- [ ] **Step 3: Replace `templates/index/index.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}MiniStore : smartphones, montres et audio{% endblock %}

{% block content %}
  <section class="border-b border-line bg-surface">
    <div class="page-wrap grid items-center gap-10 py-12 sm:py-16 lg:grid-cols-2 lg:py-20">
      <div>
        <h1 class="text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">Smartphones, montres et audio.</h1>
        <p class="mt-4 max-w-xl text-lg text-muted">Le stock affiché est le stock réel, et votre commande est réservée pendant le paiement.</p>
        <div class="mt-8">
          <twig:Button :href="path('app_shop')" size="lg">Voir la boutique</twig:Button>
        </div>
      </div>

      {% if featured %}
        <a href="{{ path('app_single_product', {slug: featured.slug}) }}"
           class="ms-hero__feature group relative block overflow-hidden rounded-card bg-subtle shadow-card">
          {% if featured.image %}
            <img src="{{ asset(featured.image) }}" alt="{{ featured.name }}" width="800" height="640" fetchpriority="high"
                 class="aspect-[5/4] w-full object-cover transition duration-500 motion-safe:group-hover:scale-[1.02]">
          {% endif %}
          <span class="absolute inset-x-4 bottom-4 flex items-center justify-between gap-4 rounded-control bg-surface/95 px-4 py-3 shadow-card backdrop-blur">
            <strong class="min-w-0 truncate font-bold text-ink">{{ featured.name }}</strong>
            <twig:PriceTag :price="featured.price" />
          </span>
        </a>
      {% endif %}
    </div>
  </section>

  {% if categories is not empty %}
    <section class="page-wrap py-12" aria-labelledby="home-categories">
      <h2 id="home-categories" class="text-2xl font-extrabold tracking-tight">Catégories</h2>
      <div class="mt-6 grid gap-4 sm:grid-cols-3">
        {% for category, total in categories %}
          <a href="{{ path('app_shop', {category: category}) }}"
             class="ms-category group flex items-center justify-between rounded-card border border-line bg-surface p-5 shadow-card transition hover:border-accent/40 hover:shadow-card-hover">
            <span class="font-bold text-ink">{{ category|category_label }}</span>
            <span class="text-sm text-muted">{{ total }} produit{{ total > 1 ? 's' }}</span>
          </a>
        {% endfor %}
      </div>
    </section>
  {% endif %}

  <section class="page-wrap py-12" aria-labelledby="home-new">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <h2 id="home-new" class="text-2xl font-extrabold tracking-tight">Nouveautés</h2>
      <a href="{{ path('app_shop') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">Voir tous les produits →</a>
    </div>
    {% if new_arrivals is not empty %}
      <div class="ms-grid product-grid mt-6">
        {% for product in new_arrivals %}
          <twig:ProductCard :product="product" />
        {% endfor %}
      </div>
    {% else %}
      <twig:EmptyState title="Aucun produit pour le moment" class="mt-6">Revenez bientôt : le catalogue arrive.</twig:EmptyState>
    {% endif %}
  </section>

  <section class="page-wrap pb-4" aria-label="Nos engagements">
    <ul class="grid gap-4 md:grid-cols-3">
      {% for fact in [
        {icon: 'truck', title: 'Livraison ' ~ constant('App\\Cart\\CartTotals::SHIPPING_COST')|money, text: 'Un seul tarif par commande.'},
        {icon: 'lock', title: 'Paiement sécurisé', text: 'Carte bancaire via Stripe, nous ne voyons jamais votre numéro.'},
        {icon: 'box', title: 'Stock réservé', text: 'Vos articles sont mis de côté pendant 30 minutes de paiement.'},
      ] %}
        <li class="flex gap-4 rounded-card border border-line bg-surface p-5">
          <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent">
            <svg class="size-5" aria-hidden="true"><use href="#{{ fact.icon }}"></use></svg>
          </span>
          <div>
            <p class="font-bold text-ink">{{ fact.title }}</p>
            <p class="mt-1 text-sm text-muted">{{ fact.text }}</p>
          </div>
        </li>
      {% endfor %}
    </ul>
  </section>
{% endblock %}
```

- [ ] **Step 4: Replace `templates/shop/shop.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% set heading = filters.query != '' ? 'Résultats pour « ' ~ filters.query ~ ' »'
  : (filters.category ? filters.category|category_label : 'Boutique') %}

{% block title %}{{ heading }} - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader :title="heading" :parent="heading != 'Boutique' ? 'Boutique' : null" :parentUrl="path('app_shop')" />

  <div class="page-wrap grid gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
    {# One GET form for filters and sort: every combination is a shareable URL. It wraps the filters only:
       product cards contain their own add-to-cart forms, and forms cannot be nested.
       The sort select joins it through form="shop-filters". #}
    <form id="shop-filters" method="get" action="{{ path('app_shop') }}" class="lg:sticky lg:top-24 lg:self-start">
      {% if filters.query != '' %}<input type="hidden" name="q" value="{{ filters.query }}">{% endif %}

      <details open data-controller="disclosure" data-disclosure-keep-open-value="{{ filters.isFiltered ? 'true' : 'false' }}"
               class="group rounded-card border border-line bg-surface shadow-card">
        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 font-bold text-ink">
          Filtres
          <svg class="size-4 text-muted transition group-open:rotate-90" aria-hidden="true"><use href="#chevron-right"></use></svg>
        </summary>

        <div class="space-y-6 border-t border-line px-5 py-5">
          <fieldset class="space-y-2">
            <legend class="mb-2 text-sm font-semibold text-ink">Catégorie</legend>
            <label class="flex items-center gap-3 text-sm text-ink">
              <input type="radio" name="category" value="" class="size-4 accent-accent" {% if filters.category is null %}checked{% endif %}>
              Toutes
            </label>
            {% for category, total in categories %}
              <label class="flex items-center gap-3 text-sm text-ink">
                <input type="radio" name="category" value="{{ category }}" class="size-4 accent-accent" {% if filters.category == category %}checked{% endif %}>
                <span class="flex-1">{{ category|category_label }}</span>
                <span class="text-xs text-muted">{{ total }}</span>
              </label>
            {% endfor %}
          </fieldset>

          <fieldset>
            <legend class="mb-2 text-sm font-semibold text-ink">Prix ($)</legend>
            <div class="flex items-center gap-2">
              {% set price_input = 'w-full rounded-control border border-line bg-surface px-3 py-2 text-sm tabular-nums focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/20' %}
              <label class="flex-1">
                <span class="sr-only">Prix minimum</span>
                <input type="number" name="min" min="0" step="1" inputmode="numeric" placeholder="Min" class="{{ price_input }}"
                       value="{{ filters.minPriceCents is not null ? filters.minPriceCents / 100 : '' }}">
              </label>
              <span class="text-sm text-muted" aria-hidden="true">à</span>
              <label class="flex-1">
                <span class="sr-only">Prix maximum</span>
                <input type="number" name="max" min="0" step="1" inputmode="numeric" placeholder="Max" class="{{ price_input }}"
                       value="{{ filters.maxPriceCents is not null ? filters.maxPriceCents / 100 : '' }}">
              </label>
            </div>
          </fieldset>

          <fieldset>
            <legend class="mb-2 text-sm font-semibold text-ink">Disponibilité</legend>
            <label class="flex items-center gap-3 text-sm text-ink">
              <input type="checkbox" name="stock" value="1" class="size-4 rounded accent-accent" {% if filters.inStockOnly %}checked{% endif %}>
              En stock uniquement
            </label>
          </fieldset>

          <div class="flex flex-wrap items-center gap-3">
            <twig:Button type="submit">Appliquer</twig:Button>
            {% if filters.isFiltered %}
              <a href="{{ path('app_shop') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">Effacer les filtres</a>
            {% endif %}
          </div>
        </div>
      </details>
    </form>

    <section aria-labelledby="results-count" class="min-w-0">
      <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p id="results-count" class="text-sm font-semibold text-muted">{{ page.totalCount }} produit{{ page.totalCount > 1 ? 's' }}</p>
        <label class="flex items-center gap-2 text-sm font-semibold text-ink">
          Trier par
          <select name="tri" form="shop-filters" data-controller="autosubmit" data-action="change->autosubmit#submit"
                  class="rounded-control border border-line bg-surface py-2 pl-3 pr-8 text-sm focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/20">
            {% for value, label in sorts %}
              <option value="{{ value }}" {% if filters.sort == value %}selected{% endif %}>{{ label }}</option>
            {% endfor %}
          </select>
          <noscript><twig:Button type="submit" form="shop-filters" variant="secondary" size="sm">Trier</twig:Button></noscript>
        </label>
      </div>

      {% if page.items is not empty %}
        <div class="ms-grid product-grid">
          {% for product in page.items %}
            <twig:ProductCard :product="product" />
          {% endfor %}
        </div>
      {% else %}
        <twig:EmptyState title="Aucun produit ne correspond à ces filtres" icon="search">
          Essayez une autre recherche ou retirez un filtre.
          <twig:block name="actions">
            <twig:Button :href="path('app_shop')" variant="secondary">Voir tous les produits</twig:Button>
          </twig:block>
        </twig:EmptyState>
      {% endif %}

      <twig:Pagination :page="page.pageNumber" :pages="page.pageCount" route="app_shop" :params="app.request.query.all" />
    </section>
  </div>
{% endblock %}
```

- [ ] **Step 5: Replace `templates/single-product/single-product.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ product.name }} - MiniStore{% endblock %}
{% block meta_description %}{{ product.name }} : {{ product.description|length > 150 ? product.description|slice(0, 150) ~ '…' : product.description }}{% endblock %}

{% block content %}
  {% set low_stock_threshold = 3 %}

  <twig:PageHeader :title="product.name" parent="Boutique" :parentUrl="path('app_shop')" :showTitle="false" />

  <div class="page-wrap grid gap-8 lg:grid-cols-2 lg:gap-14">
    <div class="relative overflow-hidden rounded-card bg-subtle shadow-card">
      {% if product.image %}
        <img src="{{ asset(product.image) }}" alt="{{ product.name }}" width="800" height="800" fetchpriority="high" class="aspect-square w-full object-cover">
      {% endif %}
      {% if product.isIsSale %}<twig:Badge tone="signal" class="absolute left-4 top-4">Promo</twig:Badge>{% endif %}
    </div>

    <div class="min-w-0">
      <a href="{{ path('app_shop', {category: product.category}) }}" class="text-sm font-semibold uppercase tracking-wide text-accent hover:text-accent-hover">{{ product.category|category_label }}</a>
      <h1 class="product-title mt-2 break-words text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ product.name }}</h1>

      <div class="product-price mt-4"><twig:PriceTag :price="product.price" size="lg" /></div>

      <div class="product-actions mt-6 space-y-4">
        {% if product.stock > 0 %}
          {% if product.stock <= low_stock_threshold %}
            <twig:Badge tone="warning">Plus que {{ product.stock }} en stock</twig:Badge>
          {% else %}
            <twig:Badge tone="success">{{ product.stock }} en stock</twig:Badge>
          {% endif %}
          <form action="{{ path('app_cart_add', {id: product.id}) }}" method="post" class="add-to-cart-form flex flex-wrap items-end gap-3">
            <input type="hidden" name="_token" value="{{ csrf_token('cart') }}">
            <label class="text-sm font-semibold text-ink">
              Quantité
              <input type="number" id="quantity" name="quantity" value="1" min="1" max="{{ min(product.stock, 99) }}"
                     class="mt-1.5 block h-12 w-24 rounded-control border border-line bg-surface text-center font-bold tabular-nums focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/20">
            </label>
            <twig:Button type="submit" size="lg" class="flex-1 sm:flex-none">Ajouter au panier</twig:Button>
          </form>
        {% else %}
          <twig:Badge tone="signal">Épuisé</twig:Badge>
          <div><twig:Button size="lg" variant="secondary" disabled>Indisponible</twig:Button></div>
        {% endif %}
      </div>

      <div class="mt-8 space-y-3 border-t border-line pt-8 text-ink/90">
        {# Escaped by Twig first, then line breaks become <br>: no HTML from the database is trusted. #}
        <p class="break-words leading-relaxed">{{ product.description|nl2br }}</p>
      </div>

      <ul class="mt-8 grid gap-3 sm:grid-cols-2">
        <li class="flex items-center gap-3 rounded-control bg-subtle px-4 py-3 text-sm text-ink">
          <svg class="size-5 text-accent" aria-hidden="true"><use href="#truck"></use></svg>
          Livraison {{ constant('App\\Cart\\CartTotals::SHIPPING_COST')|money }} par commande
        </li>
        <li class="flex items-center gap-3 rounded-control bg-subtle px-4 py-3 text-sm text-ink">
          <svg class="size-5 text-accent" aria-hidden="true"><use href="#lock"></use></svg>
          Paiement sécurisé par Stripe
        </li>
      </ul>
    </div>
  </div>

  {% if related_products is not empty %}
    <section class="related-products page-wrap mt-16" aria-labelledby="related-title">
      <h2 id="related-title" class="text-2xl font-extrabold tracking-tight">Dans la même catégorie</h2>
      <div class="ms-grid product-grid mt-6">
        {% for related in related_products %}
          <twig:ProductCard :product="related" />
        {% endfor %}
      </div>
    </section>
  {% endif %}
{% endblock %}
```

- [ ] **Step 6: Add the `#search` icon to `EmptyState` callers.** It already exists in the sprite, so there's nothing to add. Check that it's there:

Run: `grep -c 'id="search"' templates/partials/svg_icons.html.twig`
Expected: `1`.

- [ ] **Step 7: Run the catalogue suites.**

Run: `php bin/phpunit tests/Controller/CataloguePagesTest.php tests/Controller/HomePageTest.php tests/Controller/ShopFiltersTest.php tests/Controller/ShopSearchTest.php tests/Controller/ProductPageTest.php`
Expected: PASS.

- [ ] **Step 8: Check the pages in the browser.**

Run: `php bin/console tailwind:build`

With the server running, take Playwright screenshots of `/`, `/shop`, `/shop?category=phones` and one product page, at 375px and 1280px. At 375px, the shop filters start folded; opening them works; changing the sort reloads the list with the new order. Share the screenshots with the user.

- [ ] **Step 9: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/index/index.html.twig templates/shop/shop.html.twig templates/single-product/single-product.html.twig tests/Controller/CataloguePagesTest.php
git commit -m "feat(design): home, shop and product pages on Tailwind"
```

---

### Task 11: Cart, checkout and order confirmation (with fix F8)

**Files:**
- Modify (replace): `templates/cart/index.html.twig`, `templates/checkout/checkout.html.twig`, `templates/checkout/success.html.twig`, `templates/orders/_status_badge.html.twig`
- Create: `templates/orders/_summary.html.twig`, `tests/Controller/PurchasePagesTest.php`

**Interfaces:**
- Consumes:
  - cart variables `cart_items` (items with `product`, `color`, `storage`, `quantity`, `subtotal`), `cart_subtotal`, `shipping_cost`, `tax`, `cart_total`, plus `App\Cart\CartTotals::TAX_RATE_PERCENT`;
  - checkout: the same variables, and the route `app_checkout_create_session`;
  - success: `order` (`Orders` with `items`, `firstName`, `lastName`, `address`, `city`, `state`, `postcode`, `country`, `createdAt`, `status`, `subtotal`, `shippingCost`, `tax`, `total`, `user`).
- Produces:
  - `orders/_status_badge.html.twig` (expects `status`: `OrderStatus` or null) renders a `Badge`.
  - `orders/_summary.html.twig` (expects `order`) renders the item list plus the totals. Tasks 13 and 14 reuse it.

- [ ] **Step 1: Write the failing F8 test** `tests/Controller/PurchasePagesTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class PurchasePagesTest extends DatabaseWebTestCase
{
    public function testCheckoutTermsCheckboxBelongsToTheCheckoutForm(): void
    {
        // It sat outside #checkout-form, so its "required" was never enforced (F8).
        $this->addToCartFromShop($this->createProduct());

        $crawler = $this->client->request('GET', '/checkout');

        $this->assertSame('checkout-form', $crawler->filter('#termsConditions')->attr('form'));
        $this->assertNotNull($crawler->filter('#termsConditions')->attr('required'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `php bin/phpunit tests/Controller/PurchasePagesTest.php`
Expected: FAIL: `#termsConditions` has no `form` attribute.

- [ ] **Step 3: Fix F8 in the current template and commit it on its own.** In `templates/checkout/checkout.html.twig`, change `<input class="form-check-input" type="checkbox" id="termsConditions" required>` to:

```twig
<input class="form-check-input" type="checkbox" id="termsConditions" form="checkout-form" required>
```

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/checkout/checkout.html.twig tests/Controller/PurchasePagesTest.php
git commit -m "fix: enforce the checkout terms checkbox by attaching it to the checkout form"
```

- [ ] **Step 3b: Add the redesign tests.** Append these two methods to `PurchasePagesTest`, before the closing brace:

```php
    public function testCartUsesStimulusInsteadOfInlineScripts(): void
    {
        $this->addToCartFromShop($this->createProduct());

        $crawler = $this->client->request('GET', '/cart');

        $this->assertCount(1, $crawler->filter('main#main'));
        $this->assertCount(0, $crawler->filter('main script, [onsubmit], [onchange]'));
        $this->assertSame('change->autosubmit#submit', $crawler->filter('.quantity-input')->attr('data-action'));
        $this->assertSame('confirm', $crawler->filter('form[action="/cart/clear"]')->attr('data-controller'));
    }

    public function testCheckoutIsInFrench(): void
    {
        $this->addToCartFromShop($this->createProduct());

        $this->client->request('GET', '/checkout');

        $this->assertSelectorTextContains('h1', 'Finaliser la commande');
        $this->assertSelectorTextContains('#checkout-form', 'Prénom');
    }
```

Run: `php bin/phpunit tests/Controller/PurchasePagesTest.php`
Expected: the 2 new tests FAIL (the inline script is still on the cart page, and the checkout is still in English). The F8 test PASSES.

- [ ] **Step 4: Replace `templates/orders/_status_badge.html.twig`:**

```twig
{# Expects "status": an App\Enum\OrderStatus case (or null for legacy rows) #}
{% set tone = {pending: 'warning', paid: 'success', shipped: 'accent', cancelled: 'signal'}[status.value ?? ''] ?? null %}
{% if tone %}
  <twig:Badge :tone="tone">{{ status.label }}</twig:Badge>
{% else %}
  <twig:Badge>Inconnu</twig:Badge>
{% endif %}
```

- [ ] **Step 5: Create `templates/orders/_summary.html.twig`:**

```twig
{# Items and totals of an order. Expects "order". Shared by the confirmation, customer and admin pages. #}
<ul class="divide-y divide-line">
  {% for item in order.items %}
    <li class="flex items-center gap-4 py-4">
      <div class="size-14 shrink-0 overflow-hidden rounded-control bg-subtle">
        {% if item.product and item.product.image %}
          <img src="{{ asset(item.product.image) }}" alt="" width="56" height="56" class="size-full object-cover">
        {% endif %}
      </div>
      <div class="min-w-0 flex-1">
        <p class="break-words font-semibold text-ink">{{ item.product ? item.product.name : 'Produit retiré' }}</p>
        <p class="text-sm text-muted">
          {{ item.quantity }} × {{ item.price|money }}
          {% if item.color or item.storage %} · {{ [item.color, item.storage]|filter(v => v)|join(', ') }}{% endif %}
        </p>
      </div>
      <p class="font-bold tabular-nums text-ink">{{ (item.price * item.quantity)|money }}</p>
    </li>
  {% endfor %}
</ul>
<dl class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
  <div class="flex justify-between"><dt class="text-muted">Sous-total</dt><dd class="font-semibold tabular-nums">{{ order.subtotal|money }}</dd></div>
  <div class="flex justify-between"><dt class="text-muted">Livraison</dt><dd class="font-semibold tabular-nums">{{ order.shippingCost|money }}</dd></div>
  <div class="flex justify-between"><dt class="text-muted">Taxes</dt><dd class="font-semibold tabular-nums">{{ order.tax|money }}</dd></div>
  <div class="flex justify-between border-t border-line pt-3 text-base"><dt class="font-extrabold">Total</dt><dd class="font-extrabold tabular-nums">{{ order.total|money }}</dd></div>
</dl>
```

- [ ] **Step 6: Replace `templates/cart/index.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}Panier - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader title="Panier" />

  <div class="page-wrap">
    {% if cart_items is empty %}
      <twig:EmptyState title="Votre panier est vide" icon="cart">
        Parcourez la boutique pour ajouter des produits.
        <twig:block name="actions"><twig:Button :href="path('app_shop')">Voir la boutique</twig:Button></twig:block>
      </twig:EmptyState>
    {% else %}
      <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="cart-lines-title">
          <h2 id="cart-lines-title" class="sr-only">Articles</h2>
          <ul class="divide-y divide-line rounded-card border border-line bg-surface px-4 shadow-card sm:px-6">
            {% for item in cart_items %}
              {% set product_url = path('app_single_product', {slug: item.product.slug}) %}
              <li class="grid grid-cols-[4.5rem_minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 py-5 sm:grid-cols-[6rem_minmax(0,1fr)_auto_auto_auto]">
                <a href="{{ product_url }}" tabindex="-1" aria-hidden="true" class="row-span-2 block aspect-square overflow-hidden rounded-control bg-subtle sm:row-span-1">
                  {% if item.product.image %}<img src="{{ asset(item.product.image) }}" alt="" width="96" height="96" class="size-full object-cover">{% endif %}
                </a>

                <div class="min-w-0">
                  <a href="{{ product_url }}" class="break-words font-bold text-ink hover:text-accent">{{ item.product.name }}</a>
                  {% if item.color or item.storage %}
                    <p class="text-sm text-muted">{{ [item.color, item.storage]|filter(v => v)|join(', ') }}</p>
                  {% endif %}
                  <p class="text-sm tabular-nums text-muted">{{ item.product.price|money }} l’unité</p>
                </div>

                <form action="{{ path('app_cart_remove', {id: item.product.id}) }}" method="post" class="justify-self-end sm:order-last">
                  <input type="hidden" name="_token" value="{{ csrf_token('cart') }}">
                  <input type="hidden" name="color" value="{{ item.color }}">
                  <input type="hidden" name="storage" value="{{ item.storage }}">
                  <button type="submit" class="inline-flex size-10 items-center justify-center rounded-full text-muted transition hover:bg-signal-soft hover:text-signal">
                    <svg class="size-5" aria-hidden="true"><use href="#trash"></use></svg>
                    <span class="sr-only">Retirer {{ item.product.name }} du panier</span>
                  </button>
                </form>

                <form action="{{ path('app_cart_update', {id: item.product.id}) }}" method="post" class="quantity-form flex items-center gap-2">
                  <input type="hidden" name="_token" value="{{ csrf_token('cart') }}">
                  <input type="hidden" name="color" value="{{ item.color }}">
                  <input type="hidden" name="storage" value="{{ item.storage }}">
                  <label>
                    <span class="sr-only">Quantité de {{ item.product.name }}</span>
                    <input type="number" name="quantity" value="{{ item.quantity }}" min="1" max="{{ min(item.product.stock, 99) }}"
                           data-controller="autosubmit" data-action="change->autosubmit#submit"
                           class="quantity-input h-10 w-20 rounded-control border border-line bg-surface text-center font-bold tabular-nums focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/20">
                  </label>
                  <noscript><twig:Button type="submit" variant="secondary" size="sm">Mettre à jour</twig:Button></noscript>
                </form>

                <p class="justify-self-end font-extrabold tabular-nums text-ink">{{ item.subtotal|money }}</p>
              </li>
            {% endfor %}
          </ul>

          <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ path('app_shop') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">← Continuer mes achats</a>
            <form action="{{ path('app_cart_clear') }}" method="post"
                  data-controller="confirm" data-confirm-message-value="Vider le panier ?" data-action="confirm#ask">
              <input type="hidden" name="_token" value="{{ csrf_token('cart') }}">
              <button type="submit" class="text-sm font-semibold text-muted hover:text-signal">Vider le panier</button>
            </form>
          </div>
        </section>

        <aside class="cart-summary rounded-card bg-subtle p-6 lg:sticky lg:top-24" aria-labelledby="summary-title">
          <h2 id="summary-title" class="text-lg font-extrabold">Récapitulatif</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-muted">Sous-total</dt><dd class="font-semibold tabular-nums">{{ cart_subtotal|money }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Livraison</dt><dd class="font-semibold tabular-nums">{{ shipping_cost|money }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Taxes ({{ constant('App\\Cart\\CartTotals::TAX_RATE_PERCENT') }} %)</dt><dd class="font-semibold tabular-nums">{{ tax|money }}</dd></div>
            <div class="flex justify-between border-t border-line pt-3 text-lg"><dt class="font-extrabold">Total</dt><dd class="font-extrabold tabular-nums">{{ cart_total|money }}</dd></div>
          </dl>
          <twig:Button :href="path('app_checkout')" size="lg" class="mt-6 w-full">Passer la commande</twig:Button>
          <p class="mt-4 flex items-center justify-center gap-2 text-sm text-muted">
            <svg class="size-4" aria-hidden="true"><use href="#lock"></use></svg>
            Paiement sécurisé par Stripe
          </p>
        </aside>
      </div>
    {% endif %}
  </div>
{% endblock %}
```

- [ ] **Step 7: Replace `templates/checkout/checkout.html.twig`.** The field names, `#checkout-form`, the CSRF token id `checkout` and the option `value`s don't change (the controller reads them). Only labels are translated. The terms checkbox keeps `form="checkout-form"` and `required`.

```twig
{% extends 'base.html.twig' %}

{% block title %}Finaliser la commande - MiniStore{% endblock %}

{% block content %}
  {% set control = 'block w-full rounded-control border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-xs placeholder:text-muted focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/20' %}
  {% set label = 'block text-sm font-semibold text-ink' %}

  <twig:PageHeader title="Finaliser la commande" parent="Panier" :parentUrl="path('app_cart')" />

  <div class="page-wrap grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_24rem]">
    <twig:Card class="p-6 sm:p-8">
      <h2 class="text-lg font-extrabold">Adresse de livraison</h2>
      <form action="{{ path('app_checkout_create_session') }}" method="post" id="checkout-form" class="mt-6 grid gap-5 sm:grid-cols-2">
        <input type="hidden" name="_token" value="{{ csrf_token('checkout') }}">

        <div class="space-y-1.5">
          <label for="firstName" class="{{ label }} required">Prénom</label>
          <input type="text" id="firstName" name="firstName" required autocomplete="given-name" class="{{ control }}" {% if app.user %}value="{{ app.user.firstName }}"{% endif %}>
        </div>
        <div class="space-y-1.5">
          <label for="lastName" class="{{ label }} required">Nom</label>
          <input type="text" id="lastName" name="lastName" required autocomplete="family-name" class="{{ control }}" {% if app.user %}value="{{ app.user.lastName }}"{% endif %}>
        </div>
        <div class="space-y-1.5 sm:col-span-2">
          <label for="companyName" class="{{ label }}">Société (facultatif)</label>
          <input type="text" id="companyName" name="companyName" autocomplete="organization" class="{{ control }}">
        </div>
        <div class="space-y-1.5 sm:col-span-2">
          <label for="country" class="{{ label }} required">Pays</label>
          <select id="country" name="country" required autocomplete="country" class="{{ control }}">
            <option value="">Choisissez un pays</option>
            <option value="US">États-Unis</option>
            <option value="CA">Canada</option>
            <option value="UK">Royaume-Uni</option>
            <option value="AU">Australie</option>
          </select>
        </div>
        <div class="space-y-1.5 sm:col-span-2">
          <label for="streetAddress" class="{{ label }} required">Adresse</label>
          <input type="text" id="streetAddress" name="streetAddress" required autocomplete="address-line1" placeholder="Numéro et nom de rue" class="{{ control }}">
          <label for="streetAddress2" class="sr-only">Complément d’adresse</label>
          <input type="text" id="streetAddress2" name="streetAddress2" autocomplete="address-line2" placeholder="Appartement, étage… (facultatif)" class="{{ control }} mt-2">
        </div>
        <div class="space-y-1.5">
          <label for="city" class="{{ label }} required">Ville</label>
          <input type="text" id="city" name="city" required autocomplete="address-level2" class="{{ control }}">
        </div>
        <div class="space-y-1.5">
          <label for="postcode" class="{{ label }} required">Code postal</label>
          <input type="text" id="postcode" name="postcode" required autocomplete="postal-code" class="{{ control }}">
        </div>
        <div class="space-y-1.5 sm:col-span-2">
          <label for="state" class="{{ label }} required">État / région</label>
          <select id="state" name="state" required autocomplete="address-level1" class="{{ control }}">
            <option value="">Choisissez un état</option>
            <option value="AL">Alabama</option>
            <option value="AK">Alaska</option>
            <option value="AZ">Arizona</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label for="phone" class="{{ label }} required">Téléphone</label>
          <input type="tel" id="phone" name="phone" required autocomplete="tel" class="{{ control }}" {% if app.user %}value="{{ app.user.phoneNumber }}"{% endif %}>
        </div>
        <div class="space-y-1.5">
          <label for="email" class="{{ label }} required">Adresse e-mail</label>
          <input type="email" id="email" name="email" required autocomplete="email" class="{{ control }}" {% if app.user %}value="{{ app.user.email }}"{% endif %}>
        </div>
        <label class="flex items-start gap-3 text-sm text-ink sm:col-span-2">
          <input type="checkbox" id="createAccount" name="createAccount" class="mt-0.5 size-4 rounded accent-accent" {% if not app.user %}checked{% else %}disabled{% endif %}>
          {{ app.user ? 'Vous êtes déjà connecté' : 'Créer un compte' }}
        </label>
        <div class="space-y-1.5 sm:col-span-2">
          <label for="orderNotes" class="{{ label }}">Instructions de livraison (facultatif)</label>
          <textarea id="orderNotes" name="orderNotes" rows="3" placeholder="Code d’accès, horaires…" class="{{ control }}"></textarea>
        </div>
      </form>
    </twig:Card>

    <aside class="rounded-card bg-subtle p-6 lg:sticky lg:top-24" aria-labelledby="order-title">
      <h2 id="order-title" class="text-lg font-extrabold">Votre commande</h2>
      <ul class="mt-4 divide-y divide-line text-sm">
        {% for item in cart_items %}
          <li class="flex justify-between gap-4 py-2">
            <span class="min-w-0 break-words">{{ item.product.name }} × {{ item.quantity }}</span>
            <span class="font-semibold tabular-nums">{{ item.subtotal|money }}</span>
          </li>
        {% endfor %}
      </ul>
      <dl class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
        <div class="flex justify-between"><dt class="text-muted">Sous-total</dt><dd class="font-semibold tabular-nums">{{ cart_subtotal|money }}</dd></div>
        <div class="flex justify-between"><dt class="text-muted">Livraison</dt><dd class="font-semibold tabular-nums">{{ shipping_cost|money }}</dd></div>
        <div class="flex justify-between"><dt class="text-muted">Taxes</dt><dd class="font-semibold tabular-nums">{{ tax|money }}</dd></div>
        <div class="flex justify-between border-t border-line pt-3 text-lg"><dt class="font-extrabold">Total</dt><dd class="font-extrabold tabular-nums">{{ cart_total|money }}</dd></div>
      </dl>
      <p class="mt-6 flex items-center gap-2 rounded-control bg-surface px-4 py-3 text-sm text-ink">
        <svg class="size-4 text-accent" aria-hidden="true"><use href="#lock"></use></svg>
        Paiement par carte bancaire, sécurisé par Stripe.
      </p>
      <label class="mt-4 flex items-start gap-3 text-sm text-ink">
        <input type="checkbox" id="termsConditions" form="checkout-form" required class="mt-0.5 size-4 rounded accent-accent">
        <span>J’ai lu et j’accepte les conditions générales de vente <span class="text-signal" aria-hidden="true">*</span></span>
      </label>
      <twig:Button type="submit" form="checkout-form" size="lg" class="mt-6 w-full">Payer la commande</twig:Button>
    </aside>
  </div>
{% endblock %}
```

The old template linked "terms and conditions" to `#`, a dead link. No terms page exists, so the new text has no link. Mention this to the user as a follow-up.

- [ ] **Step 8: Replace `templates/checkout/success.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}Commande confirmée - MiniStore{% endblock %}

{% block content %}
  <div class="page-wrap max-w-3xl py-12">
    <div class="text-center">
      <span class="inline-flex size-14 items-center justify-center rounded-full bg-success-soft text-success">
        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
      </span>
      <h1 class="mt-4 text-3xl font-extrabold tracking-tight">Merci pour votre commande !</h1>
      <p class="mt-2 text-muted">Commande n° {{ order.id }} · {{ order.createdAt|date('d/m/Y') }} · {{ include('orders/_status_badge.html.twig', {status: order.status}) }}</p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <twig:Button :href="path('app_shop')" variant="secondary">Continuer mes achats</twig:Button>
        {% if app.user and order.user and order.user.id == app.user.id %}
          <twig:Button :href="path('app_account_order_show', {id: order.id})">Voir la commande</twig:Button>
        {% endif %}
      </div>
    </div>

    <div class="mt-10 grid gap-6 sm:grid-cols-[minmax(0,1fr)_14rem]">
      <twig:Card class="p-6">
        <h2 class="font-extrabold">Articles</h2>
        {{ include('orders/_summary.html.twig', {order: order}) }}
      </twig:Card>
      <twig:Card class="self-start p-6">
        <h2 class="font-extrabold">Livraison</h2>
        <address class="mt-3 break-words text-sm not-italic leading-relaxed text-ink">
          {{ order.firstName }} {{ order.lastName }}<br>
          {{ order.address }}<br>
          {{ order.postcode }} {{ order.city }}{{ order.state ? ', ' ~ order.state }}<br>
          {{ order.country }}
        </address>
      </twig:Card>
    </div>
  </div>
{% endblock %}
```

- [ ] **Step 9: Run the purchase suites.**

Run: `php bin/phpunit tests/Controller/PurchasePagesTest.php tests/Cart tests/Controller/CheckoutFlowTest.php tests/Controller/CheckoutPaymentTest.php tests/Order`
Expected: PASS.

- [ ] **Step 10: Check the flow in the browser.** At 375px and 1280px:
  - Add two products, change a quantity: the page reloads with the new total. Clearing the cart asks for confirmation.
  - Open the checkout. Submitting without ticking the terms is blocked by the browser.
  - Screenshot the cart and checkout pages for the user.

- [ ] **Step 11: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/cart templates/checkout templates/orders/_status_badge.html.twig templates/orders/_summary.html.twig tests/Controller/PurchasePagesTest.php
git commit -m "feat(design): cart, checkout and order confirmation on Tailwind"
```

---

### Task 12: Authentication pages, with French form labels

**Files:**
- Modify (replace): `templates/security/login.html.twig`, `templates/security/register.html.twig`, `templates/verify/code.html.twig`, `templates/reset_password/request.html.twig`, `templates/reset_password/reset.html.twig`, `templates/reset_password/check_email.html.twig`, `src/Form/RegistrationFormType.php`
- Modify: `src/Form/LoginFormType.php` (labels only), `tests/Controller/RegistrationFormTest.php`

**Interfaces:**
- Consumes:
  - `Card`, `Alert`, `Button` and the form theme;
  - login variables `loginForm`, `error`, `verified`;
  - register `registrationForm`; verify `form` (`email`, `code`, `submit`); reset `requestForm` and `resetForm` (Task 2).
- Produces: the same forms (names unchanged: `login_form`, `registration_form`, `reset_password_request_form`, `reset_password_form`) inside a centred card.

- [ ] **Step 1: Update the registration test first.** It fails until the messages are translated. In `tests/Controller/RegistrationFormTest.php`, replace the assertion and the provider:

```php
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('form[name="registration_form"]', $expectedError);
    }

    public static function invalidPasswords(): iterable
    {
        yield 'too short' => ['short', 'short', 'au moins 8 caractères'];
        yield 'mismatch' => ['a-long-password-1', 'a-long-password-2', 'Les deux mots de passe doivent être identiques.'];
    }
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `php bin/phpunit tests/Controller/RegistrationFormTest.php`
Expected: FAIL: the messages are still in English.

- [ ] **Step 3: Replace `src/Form/RegistrationFormType.php`.** The constraints are unchanged; labels, placeholders and messages are now French.

```php
<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['autocomplete' => 'given-name'],
                'constraints' => [new NotBlank(message: 'Saisissez votre prénom.')],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'family-name'],
                'constraints' => [new NotBlank(message: 'Saisissez votre nom.')],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['autocomplete' => 'email', 'placeholder' => 'vous@exemple.fr'],
                'constraints' => [
                    new NotBlank(message: 'Saisissez votre adresse e-mail.'),
                    new Email(message: 'Saisissez une adresse e-mail valide.'),
                ],
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Téléphone',
                'help' => 'Nous vous enverrons un code de vérification par SMS.',
                'attr' => ['autocomplete' => 'tel', 'placeholder' => '+33612345678'],
                'constraints' => [
                    new NotBlank(message: 'Saisissez votre numéro de téléphone.'),
                    new Regex(
                        pattern: '/^\+[1-9]\d{1,14}$/',
                        message: 'Saisissez un numéro au format international, par exemple +33612345678.',
                    ),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'constraints' => [
                    new NotBlank(message: 'Saisissez un mot de passe.'),
                    new Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J’accepte les conditions générales de vente',
                'constraints' => [new IsTrue(message: 'Vous devez accepter les conditions générales.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
```

In `src/Form/LoginFormType.php`, change the three labels: `'Email'` → `'Adresse e-mail'`, `'Password'` → `'Mot de passe'`, `'Remember me'` → `'Rester connecté'`.

- [ ] **Step 4: Replace `templates/security/login.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}Connexion - MiniStore{% endblock %}

{% block content %}
  <div class="page-wrap flex justify-center py-12 sm:py-16">
    <twig:Card class="w-full max-w-md p-6 sm:p-8">
      <h1 class="text-2xl font-extrabold tracking-tight">Connexion</h1>
      <p class="mt-1 text-sm text-muted">Heureux de vous revoir.</p>

      <div class="mt-6 space-y-3">
        {% if verified == 'success' %}
          <twig:Alert tone="success" message="Votre compte est vérifié : vous pouvez vous connecter." />
        {% endif %}
        {% if error %}
          <twig:Alert tone="danger" :message="error.messageKey|trans(error.messageData, 'security')" />
        {% endif %}
      </div>

      {{ form_start(loginForm, {action: path('app_login'), method: 'POST', attr: {class: 'mt-6 space-y-5'}}) }}
        {{ form_row(loginForm.email) }}
        {{ form_row(loginForm.password) }}
        <div class="flex flex-wrap items-center justify-between gap-3">
          {{ form_row(loginForm.remember_me) }}
          <a href="{{ path('app_forgot_password_request') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">Mot de passe oublié ?</a>
        </div>
        <input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">
        <twig:Button type="submit" size="lg" class="w-full">Se connecter</twig:Button>
      {{ form_end(loginForm) }}

      <p class="mt-6 text-center text-sm text-muted">
        Pas encore de compte ? <a href="{{ path('app_register') }}" class="font-semibold text-accent hover:text-accent-hover">Créer un compte</a>
      </p>
    </twig:Card>
  </div>
{% endblock %}
```

- [ ] **Step 5: Replace `templates/security/register.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}Créer un compte - MiniStore{% endblock %}

{% block content %}
  <div class="page-wrap flex justify-center py-12 sm:py-16">
    <twig:Card class="w-full max-w-lg p-6 sm:p-8">
      <h1 class="text-2xl font-extrabold tracking-tight">Créer un compte</h1>
      <p class="mt-1 text-sm text-muted">Suivez vos commandes et passez commande plus vite.</p>

      {{ form_start(registrationForm, {attr: {class: 'mt-6 space-y-5'}}) }}
        {# Form-level errors (an invalid CSRF token, for example) are not attached to any field. #}
        {{ form_errors(registrationForm) }}
        <div class="grid gap-5 sm:grid-cols-2">
          {{ form_row(registrationForm.firstName) }}
          {{ form_row(registrationForm.lastName) }}
        </div>
        {{ form_row(registrationForm.email) }}
        {{ form_row(registrationForm.phoneNumber) }}
        {{ form_row(registrationForm.plainPassword.first) }}
        {{ form_row(registrationForm.plainPassword.second) }}
        {{ form_row(registrationForm.agreeTerms) }}
        <twig:Button type="submit" size="lg" class="w-full">Créer mon compte</twig:Button>
      {{ form_end(registrationForm) }}

      <p class="mt-6 text-center text-sm text-muted">
        Déjà inscrit ? <a href="{{ path('app_login') }}" class="font-semibold text-accent hover:text-accent-hover">Se connecter</a>
      </p>
    </twig:Card>
  </div>
{% endblock %}
```

- [ ] **Step 6: Replace `templates/verify/code.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}Vérification du compte - MiniStore{% endblock %}

{% block content %}
  <div class="page-wrap flex justify-center py-12 sm:py-16">
    <twig:Card class="w-full max-w-md p-6 sm:p-8">
      <h1 class="text-2xl font-extrabold tracking-tight">Vérifiez votre compte</h1>
      <p class="mt-1 text-sm text-muted">Saisissez le code à 6 chiffres reçu par SMS.</p>

      {{ form_start(form, {attr: {class: 'mt-6 space-y-5'}}) }}
        {{ form_row(form.email) }}
        {{ form_row(form.code, {attr: {inputmode: 'numeric', autocomplete: 'one-time-code', class: 'text-center text-lg tracking-[0.4em]'}}) }}
        {{ form_row(form.submit, {attr: {class: 'w-full'}}) }}
      {{ form_end(form) }}

      <form method="post" action="{{ path('app_resend_code') }}" class="mt-4 text-center">
        <input type="hidden" name="email" value="{{ form.email.vars.value }}">
        <input type="hidden" name="_token" value="{{ csrf_token('resend-code') }}">
        <button type="submit" class="text-sm font-semibold text-accent hover:text-accent-hover">Renvoyer le code</button>
      </form>
    </twig:Card>
  </div>
{% endblock %}
```

- [ ] **Step 7: Replace the three password-reset templates.** The variables come from Task 2.

`templates/reset_password/request.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Mot de passe oublié - MiniStore{% endblock %}

{% block content %}
  <div class="page-wrap flex justify-center py-12 sm:py-16">
    <twig:Card class="w-full max-w-md p-6 sm:p-8">
      <h1 class="text-2xl font-extrabold tracking-tight">Mot de passe oublié</h1>
      <p class="mt-1 text-sm text-muted">Saisissez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
      {{ form_start(requestForm, {attr: {class: 'mt-6 space-y-5'}}) }}
        {{ form_row(requestForm.email) }}
        <twig:Button type="submit" size="lg" class="w-full">Envoyer le lien</twig:Button>
      {{ form_end(requestForm) }}
      <p class="mt-6 text-center text-sm"><a href="{{ path('app_login') }}" class="font-semibold text-accent hover:text-accent-hover">← Retour à la connexion</a></p>
    </twig:Card>
  </div>
{% endblock %}
```

`templates/reset_password/reset.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Nouveau mot de passe - MiniStore{% endblock %}

{% block content %}
  <div class="page-wrap flex justify-center py-12 sm:py-16">
    <twig:Card class="w-full max-w-md p-6 sm:p-8">
      <h1 class="text-2xl font-extrabold tracking-tight">Choisissez un nouveau mot de passe</h1>
      <p class="mt-1 text-sm text-muted">8 caractères minimum.</p>
      {{ form_start(resetForm, {attr: {class: 'mt-6 space-y-5'}}) }}
        {{ form_row(resetForm.plainPassword.first) }}
        {{ form_row(resetForm.plainPassword.second) }}
        <twig:Button type="submit" size="lg" class="w-full">Enregistrer</twig:Button>
      {{ form_end(resetForm) }}
    </twig:Card>
  </div>
{% endblock %}
```

`templates/reset_password/check_email.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Consultez votre boîte mail - MiniStore{% endblock %}

{% block content %}
  <div class="page-wrap flex justify-center py-12 sm:py-16">
    <twig:Card class="w-full max-w-md p-6 text-center sm:p-8">
      <span class="inline-flex size-12 items-center justify-center rounded-full bg-accent-soft text-accent">
        <svg class="size-6" aria-hidden="true"><use href="#lock"></use></svg>
      </span>
      <h1 class="mt-4 text-2xl font-extrabold tracking-tight">Consultez votre boîte mail</h1>
      <p class="mt-2 text-sm text-muted">Si un compte existe pour cette adresse, un lien de réinitialisation vient d’être envoyé. Il reste valable une heure.</p>
      <p class="mt-4 text-sm text-muted">Rien reçu ? Vérifiez vos courriers indésirables ou <a href="{{ path('app_forgot_password_request') }}" class="font-semibold text-accent hover:text-accent-hover">refaites une demande</a>.</p>
    </twig:Card>
  </div>
{% endblock %}
```

- [ ] **Step 8: Run the auth suites.**

Run: `php bin/phpunit tests/Controller/RegistrationFormTest.php tests/Controller/ResetPasswordTest.php tests/Controller/FormThemeTest.php tests/Controller/CsrfProtectionTest.php tests/Security`
Expected: PASS.

- [ ] **Step 9: Check in the browser.** At 375px and 1280px:
  - Log in with a real account.
  - Submit the register form with mismatching passwords: the error appears under the field and the field has a red border.
  - Screenshot login and register for the user.

- [ ] **Step 10: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/security templates/verify templates/reset_password src/Form/RegistrationFormType.php src/Form/LoginFormType.php tests/Controller/RegistrationFormTest.php
git commit -m "feat(design): authentication pages on Tailwind, forms translated to French"
```

---

### Task 13: Customer account pages

**Files:**
- Modify (replace): `templates/account/index.html.twig`, `templates/account/edit.html.twig`, `templates/account/change_password.html.twig`, `templates/account/orders.html.twig`, `templates/account/order_show.html.twig`
- Modify: `tests/Controller/CustomerOrdersTest.php` (add one test)

**Interfaces:**
- Consumes:
  - `PageHeader`, `Card`, `Button`, `EmptyState`, `orders/_status_badge.html.twig` and `orders/_summary.html.twig` (Task 11);
  - `app.user` (`firstName`, `lastName`, `email`, `phoneNumber`, `createdAt`, `isVerified`);
  - `form` (edit: `firstName`, `lastName`, `email`, `phoneNumber`; password: `currentPassword`, `newPassword.first`, `newPassword.second`);
  - `orders` and `order`.
- Produces: `templates/account/_nav.html.twig`, the sidebar shared by the account pages (variable `active`: `profile`, `orders` or `password`).

- [ ] **Step 1: Add the failing test** to `tests/Controller/CustomerOrdersTest.php`:

```php
    public function testOrderDetailListsItsItemsAndTotals(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder($user);
        $this->client->loginUser($user);

        $this->client->request('GET', sprintf('/account/orders/%d', $order->getId()));

        $this->assertSelectorTextContains('main', 'Sous-total');
        $this->assertSelectorTextContains('main', "123,45\u{00A0}$");
        $this->assertSelectorTextContains('main', 'Payée');
        $this->assertSelectorExists('nav[aria-label="Mon compte"] a[aria-current="page"][href="/account/orders"]');
    }
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `php bin/phpunit --filter testOrderDetailListsItsItemsAndTotals`
Expected: FAIL: there is no account navigation and no totals.

- [ ] **Step 3: Create `templates/account/_nav.html.twig`:**

```twig
{# Account sidebar. Expects "active": profile | orders | password. #}
{% set links = [
  {key: 'profile', label: 'Mon profil', href: path('app_account')},
  {key: 'orders', label: 'Mes commandes', href: path('app_orders')},
  {key: 'password', label: 'Mot de passe', href: path('app_account_password')},
] %}
<nav aria-label="Mon compte" class="flex gap-1 overflow-x-auto lg:flex-col">
  {% for link in links %}
    <a href="{{ link.href }}" {% if active == link.key %}aria-current="page"{% endif %}
       class="whitespace-nowrap rounded-control px-3 py-2 text-sm font-semibold text-muted transition hover:bg-subtle hover:text-ink aria-[current=page]:bg-accent-soft aria-[current=page]:text-accent-ink">{{ link.label }}</a>
  {% endfor %}
  <a href="{{ path('app_logout') }}" class="whitespace-nowrap rounded-control px-3 py-2 text-sm font-semibold text-muted transition hover:bg-signal-soft hover:text-signal-ink">Se déconnecter</a>
</nav>
```

- [ ] **Step 4: Replace the five account templates.** They share one layout: a `PageHeader`, then a two-column grid with `_nav` on the left.

`templates/account/index.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Mon compte - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader title="Mon compte" :lead="'Bonjour ' ~ app.user.firstName ~ ' !'" />
  <div class="page-wrap grid gap-8 lg:grid-cols-[14rem_minmax(0,1fr)]">
    {{ include('account/_nav.html.twig', {active: 'profile'}) }}
    <twig:Card class="p-6 sm:p-8">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h2 class="text-lg font-extrabold">Informations personnelles</h2>
        <twig:Button :href="path('app_account_edit')" variant="secondary" size="sm">Modifier</twig:Button>
      </div>
      <dl class="mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2">
        <div><dt class="text-sm text-muted">Nom</dt><dd class="mt-1 break-words font-semibold">{{ app.user.firstName }} {{ app.user.lastName }}</dd></div>
        <div><dt class="text-sm text-muted">Adresse e-mail</dt><dd class="mt-1 break-words font-semibold">{{ app.user.email }}</dd></div>
        <div><dt class="text-sm text-muted">Téléphone</dt><dd class="mt-1 font-semibold">{{ app.user.phoneNumber }}</dd></div>
        <div><dt class="text-sm text-muted">Membre depuis</dt><dd class="mt-1 font-semibold">{{ app.user.createdAt ? app.user.createdAt|date('d/m/Y') : 'Non disponible' }}</dd></div>
        <div>
          <dt class="text-sm text-muted">Compte</dt>
          <dd class="mt-1">
            {% if app.user.isVerified %}<twig:Badge tone="success">Vérifié</twig:Badge>{% else %}<twig:Badge tone="warning">Non vérifié</twig:Badge>{% endif %}
          </dd>
        </div>
      </dl>
    </twig:Card>
  </div>
{% endblock %}
```

`templates/account/edit.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Modifier mon profil - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader title="Modifier mon profil" parent="Mon compte" :parentUrl="path('app_account')" />
  <div class="page-wrap grid gap-8 lg:grid-cols-[14rem_minmax(0,1fr)]">
    {{ include('account/_nav.html.twig', {active: 'profile'}) }}
    <twig:Card class="max-w-2xl p-6 sm:p-8">
      {{ form_start(form, {attr: {class: 'space-y-5'}}) }}
        <div class="grid gap-5 sm:grid-cols-2">
          {{ form_row(form.firstName) }}
          {{ form_row(form.lastName) }}
        </div>
        {{ form_row(form.email) }}
        {{ form_row(form.phoneNumber) }}
        <div class="flex flex-wrap gap-3">
          <twig:Button type="submit">Enregistrer</twig:Button>
          <twig:Button :href="path('app_account')" variant="ghost">Annuler</twig:Button>
        </div>
      {{ form_end(form) }}
    </twig:Card>
  </div>
{% endblock %}
```

`templates/account/change_password.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Changer mon mot de passe - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader title="Changer mon mot de passe" parent="Mon compte" :parentUrl="path('app_account')" />
  <div class="page-wrap grid gap-8 lg:grid-cols-[14rem_minmax(0,1fr)]">
    {{ include('account/_nav.html.twig', {active: 'password'}) }}
    <twig:Card class="max-w-xl p-6 sm:p-8">
      {{ form_start(form, {attr: {class: 'space-y-5'}}) }}
        {{ form_row(form.currentPassword) }}
        {{ form_row(form.newPassword.first) }}
        {{ form_row(form.newPassword.second) }}
        <twig:Button type="submit">Mettre à jour</twig:Button>
      {{ form_end(form) }}
    </twig:Card>
  </div>
{% endblock %}
```

`templates/account/orders.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Mes commandes - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader title="Mes commandes" parent="Mon compte" :parentUrl="path('app_account')" />
  <div class="page-wrap grid gap-8 lg:grid-cols-[14rem_minmax(0,1fr)]">
    {{ include('account/_nav.html.twig', {active: 'orders'}) }}
    {% if orders is empty %}
      <twig:EmptyState title="Vous n’avez pas encore passé de commande" icon="cart">
        Vos commandes apparaîtront ici.
        <twig:block name="actions"><twig:Button :href="path('app_shop')">Voir la boutique</twig:Button></twig:block>
      </twig:EmptyState>
    {% else %}
      <ul class="space-y-3">
        {% for order in orders %}
          <li>
            <a href="{{ path('app_account_order_show', {id: order.id}) }}"
               class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-card border border-line bg-surface p-5 shadow-card transition hover:shadow-card-hover">
              <span class="font-extrabold text-ink">#{{ order.id }}</span>
              <span class="text-sm text-muted">{{ order.createdAt ? order.createdAt|date('d/m/Y') : '' }}</span>
              {{ include('orders/_status_badge.html.twig', {status: order.status}) }}
              <span class="ml-auto font-bold tabular-nums text-ink">{{ order.total|money }}</span>
              <svg class="size-4 text-muted" aria-hidden="true"><use href="#chevron-right"></use></svg>
            </a>
          </li>
        {% endfor %}
      </ul>
    {% endif %}
  </div>
{% endblock %}
```

`templates/account/order_show.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Commande #{{ order.id }} - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader :title="'Commande #' ~ order.id" parent="Mes commandes" :parentUrl="path('app_orders')" />
  <div class="page-wrap grid gap-8 lg:grid-cols-[14rem_minmax(0,1fr)]">
    {{ include('account/_nav.html.twig', {active: 'orders'}) }}
    <twig:Card class="p-6 sm:p-8">
      <div class="flex flex-wrap items-center gap-3">
        <p class="text-sm text-muted">Passée le {{ order.createdAt ? order.createdAt|date('d/m/Y à H:i') : '' }}</p>
        {{ include('orders/_status_badge.html.twig', {status: order.status}) }}
      </div>
      <div class="mt-4">{{ include('orders/_summary.html.twig', {order: order}) }}</div>
      <div class="mt-6"><twig:Button :href="path('app_orders')" variant="secondary">← Retour à mes commandes</twig:Button></div>
    </twig:Card>
  </div>
{% endblock %}
```

- [ ] **Step 5: Run the account suites.**

Run: `php bin/phpunit tests/Controller/CustomerOrdersTest.php tests/Security/OrderAccessTest.php`
Expected: PASS (the empty-state text from Task 3 is kept).

- [ ] **Step 6: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/account tests/Controller/CustomerOrdersTest.php
git commit -m "feat(design): customer account pages on Tailwind"
```

---

## Part D — Back office

### Task 14: Back-office layout, dashboard, users and orders

**Files:**
- Create: `templates/admin/layout.html.twig`, `tests/Controller/AdminPagesTest.php`
- Modify (replace): `templates/admin/index.html.twig`, `templates/admin/users.html.twig`, `templates/admin/edit_user.html.twig`, `templates/orders/index.html.twig`, `templates/orders/new.html.twig`, `templates/orders/edit.html.twig`, `templates/orders/show.html.twig`, `templates/orders/_form.html.twig`, `templates/orders/_delete_form.html.twig`

**Interfaces:**
- Consumes:
  - `Card`, `Button`, `Badge`, `EmptyState`, `Pagination`, the `_status_badge` and `_summary` partials, the form theme and the `confirm` controller;
  - variables: `pagination` (Knp `SlidingPagination` of `User`) and `search` (string) for the users list; `form` and `user` for user editing; `orders` for the order list; `form` and `order` for the order pages.
- Produces:
  - `admin/layout.html.twig`: every back-office page extends it and fills `{% block content %}`.
  - Its navigation lives in the `admin_sections` variable at the top of the layout; Task 16 appends "Articles" to it.
  - The nav is `nav[aria-label="Administration"]`, and the active link has `aria-current="page"`.

- [ ] **Step 1: Write the failing tests** `tests/Controller/AdminPagesTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class AdminPagesTest extends DatabaseWebTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $this->client->loginUser($this->admin);
    }

    public function testBackOfficeHasItsOwnLayout(): void
    {
        $this->client->request('GET', '/admin');

        $this->assertSelectorExists('nav[aria-label="Administration"] a[href="/admin/users"]');
        $this->assertSelectorExists('nav[aria-label="Administration"] a[aria-current="page"][href="/admin"]');
        $this->assertSelectorNotExists('header form[role="search"]');
    }

    public function testUserDeletionAsksForConfirmationAndCarriesACsrfToken(): void
    {
        $customer = $this->createUser('ada@example.com');

        $crawler = $this->client->request('GET', '/admin/users');

        $form = $crawler->filter(sprintf('form[action="/admin/users/%d/delete"]', $customer->getId()));
        $this->assertSame('confirm', $form->attr('data-controller'));
        $this->assertCount(1, $form->filter('input[name="_token"]'));
        $this->assertCount(0, $crawler->filter('[onsubmit], [onclick]'));
    }

    public function testAnEmptyUserSearchShowsAnEmptyState(): void
    {
        $this->client->request('GET', '/admin/users?q=zzz-nobody');

        $this->assertSelectorTextContains('main', 'Aucun utilisateur trouvé');
    }

    public function testOrderListShowsStatusesAndAdminActions(): void
    {
        $order = $this->createOrder(null);

        $crawler = $this->client->request('GET', '/orders');

        $this->assertSelectorTextContains('main', 'Payée');
        $this->assertCount(1, $crawler->filter(sprintf('a[href="/orders/%d/edit"]', $order->getId())));
        $this->assertSelectorExists('nav[aria-label="Administration"] a[aria-current="page"][href="/orders"]');
    }
}
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `php bin/phpunit tests/Controller/AdminPagesTest.php`
Expected: FAIL: there is no `nav[aria-label="Administration"]`.

- [ ] **Step 3: Create `templates/admin/layout.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{# Back-office shell: dark sidebar (a scrollable bar on phones), top bar, content.
   "match" is a route-name prefix that marks the section as active. #}
{% set admin_sections = [
  {route: 'admin_dashboard', match: 'admin_dashboard', label: 'Tableau de bord', icon: 'box'},
  {route: 'app_orders_index', match: 'app_orders_', label: 'Commandes', icon: 'cart'},
  {route: 'admin_users', match: 'admin_user', label: 'Utilisateurs', icon: 'user'},
] %}

{% block body %}
  {% set route = app.request.attributes.get('_route') %}
  <div class="flex min-h-dvh flex-col lg:flex-row">
    <aside class="bg-ink text-white lg:sticky lg:top-0 lg:h-dvh lg:w-64 lg:shrink-0">
      <div class="flex h-16 items-center px-5">
        <a href="{{ path('admin_dashboard') }}" class="text-lg font-extrabold tracking-tight">Mini<span class="text-accent-soft">Store</span> <span class="text-sm font-semibold text-white/60">admin</span></a>
      </div>
      <nav aria-label="Administration" class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:pb-0">
        {% for section in admin_sections %}
          <a href="{{ path(section.route) }}" {% if route starts with section.match %}aria-current="page"{% endif %}
             class="flex shrink-0 items-center gap-3 rounded-control px-3 py-2 text-sm font-semibold text-white/70 transition hover:bg-white/10 hover:text-white aria-[current=page]:bg-white/15 aria-[current=page]:text-white">
            <svg class="size-4" aria-hidden="true"><use href="#{{ section.icon }}"></use></svg>
            {{ section.label }}
          </a>
        {% endfor %}
      </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <div class="flex h-16 items-center justify-end gap-4 border-b border-line bg-surface px-4 sm:px-6">
        <a href="{{ path('app_home') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">Voir la boutique</a>
        <span class="hidden truncate text-sm text-muted sm:inline">{{ app.user.userIdentifier }}</span>
        <a href="{{ path('app_logout') }}" class="text-sm font-semibold text-muted hover:text-ink">Se déconnecter</a>
      </div>
      <main id="main" class="flex-1 px-4 py-8 sm:px-6">
        <div class="mx-auto max-w-6xl">
          {% include 'partials/flashes.html.twig' %}
          {% block content %}{% endblock %}
        </div>
      </main>
    </div>
  </div>
{% endblock %}
```

`partials/flashes.html.twig` wraps its alerts in `page-wrap mt-6`. Inside the admin's `max-w-6xl` column that only adds a gutter, which is acceptable; keep the partial shared.

- [ ] **Step 4: Replace `templates/admin/index.html.twig`:**

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Tableau de bord - MiniStore admin{% endblock %}

{% block content %}
  <h1 class="text-2xl font-extrabold tracking-tight">Bonjour {{ app.user.firstName }} 👋</h1>
  <p class="mt-1 text-muted">Que voulez-vous gérer aujourd’hui ?</p>

  <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    {% for card in [
      {href: path('app_orders_index'), title: 'Commandes', text: 'Suivre, modifier ou annuler les commandes.', icon: 'cart'},
      {href: path('admin_users'), title: 'Utilisateurs', text: 'Modifier les comptes et leurs rôles.', icon: 'user'},
    ] %}
      <a href="{{ card.href }}" class="group rounded-card border border-line bg-surface p-6 shadow-card transition hover:border-accent/40 hover:shadow-card-hover">
        <span class="inline-flex size-10 items-center justify-center rounded-full bg-accent-soft text-accent">
          <svg class="size-5" aria-hidden="true"><use href="#{{ card.icon }}"></use></svg>
        </span>
        <p class="mt-4 font-bold text-ink group-hover:text-accent">{{ card.title }}</p>
        <p class="mt-1 text-sm text-muted">{{ card.text }}</p>
      </a>
    {% endfor %}
  </div>
{% endblock %}
```

- [ ] **Step 5: Replace `templates/admin/users.html.twig`:**

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Utilisateurs - MiniStore admin{% endblock %}

{% block content %}
  <div class="flex flex-wrap items-end justify-between gap-4">
    <h1 class="text-2xl font-extrabold tracking-tight">Utilisateurs</h1>
    <form method="get" role="search" class="flex w-full gap-2 sm:w-auto">
      <label for="user-search" class="sr-only">Rechercher un utilisateur</label>
      <input id="user-search" type="search" name="q" value="{{ search }}" placeholder="Nom, prénom ou e-mail"
             class="h-10 w-full rounded-control border border-line bg-surface px-3 text-sm focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/20 sm:w-72">
      <twig:Button type="submit" variant="secondary">Rechercher</twig:Button>
    </form>
  </div>

  {% if pagination.totalItemCount == 0 %}
    <twig:EmptyState title="Aucun utilisateur trouvé" icon="user" class="mt-6">Essayez une autre recherche.</twig:EmptyState>
  {% else %}
    <div class="mt-6 overflow-x-auto rounded-card border border-line bg-surface shadow-card">
      <table class="w-full min-w-[48rem] text-left text-sm">
        <thead class="bg-subtle text-xs font-semibold uppercase tracking-wide text-muted">
          <tr>
            <th scope="col" class="px-4 py-3">Utilisateur</th>
            <th scope="col" class="px-4 py-3">Téléphone</th>
            <th scope="col" class="px-4 py-3">Rôles</th>
            <th scope="col" class="px-4 py-3">Vérifié</th>
            <th scope="col" class="px-4 py-3">Inscrit le</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line">
          {% for user in pagination %}
            <tr>
              <td class="px-4 py-3">
                <p class="font-semibold text-ink">{{ user.firstName }} {{ user.lastName }}</p>
                <p class="text-muted">{{ user.email }}</p>
              </td>
              <td class="px-4 py-3 tabular-nums">{{ user.phoneNumber }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  {% for role in user.roles %}
                    <twig:Badge :tone="role == 'ROLE_ADMIN' ? 'accent' : 'neutral'">{{ role == 'ROLE_ADMIN' ? 'Admin' : 'Client' }}</twig:Badge>
                  {% endfor %}
                </div>
              </td>
              <td class="px-4 py-3">
                {% if user.isVerified %}<twig:Badge tone="success">Oui</twig:Badge>{% else %}<twig:Badge tone="warning">Non</twig:Badge>{% endif %}
              </td>
              <td class="px-4 py-3 text-muted">{{ user.createdAt ? user.createdAt|date('d/m/Y') : '' }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-2">
                  <twig:Button :href="path('admin_user_edit', {id: user.id})" variant="secondary" size="sm">Modifier</twig:Button>
                  {% if app.user.id != user.id %}
                    <form method="post" action="{{ path('admin_user_delete', {id: user.id}) }}"
                          data-controller="confirm" data-confirm-message-value="Supprimer le compte de {{ user.email }} ?" data-action="confirm#ask">
                      <input type="hidden" name="_token" value="{{ csrf_token('delete-user-' ~ user.id) }}">
                      <twig:Button type="submit" variant="danger" size="sm">Supprimer</twig:Button>
                    </form>
                  {% else %}
                    <span class="self-center text-xs text-muted">(vous)</span>
                  {% endif %}
                </div>
              </td>
            </tr>
          {% endfor %}
        </tbody>
      </table>
    </div>
    <twig:Pagination :page="pagination.currentPageNumber" :pages="pagination.pageCount" route="admin_users" :params="app.request.query.all" />
  {% endif %}
{% endblock %}
```

- [ ] **Step 6: Replace `templates/admin/edit_user.html.twig`:**

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Modifier l’utilisateur #{{ user.id }} - MiniStore admin{% endblock %}

{% block content %}
  <a href="{{ path('admin_users') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">← Utilisateurs</a>
  <h1 class="mt-2 text-2xl font-extrabold tracking-tight">Modifier {{ user.firstName }} {{ user.lastName }}</h1>

  <twig:Card class="mt-6 max-w-3xl p-6 sm:p-8">
    {{ form_start(form, {attr: {class: 'space-y-5'}}) }}
      <div class="grid gap-5 sm:grid-cols-2">
        {{ form_row(form.firstName) }}
        {{ form_row(form.lastName) }}
        {{ form_row(form.email) }}
        {{ form_row(form.phoneNumber) }}
      </div>
      {{ form_row(form.roles) }}
      <div class="flex flex-wrap gap-3">
        <twig:Button type="submit">Enregistrer</twig:Button>
        <twig:Button :href="path('admin_users')" variant="ghost">Annuler</twig:Button>
      </div>
    {{ form_end(form) }}
  </twig:Card>
{% endblock %}
```

- [ ] **Step 7: Replace the admin order templates.**

`templates/orders/index.html.twig`:

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Commandes - MiniStore admin{% endblock %}

{% block content %}
  <div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-2xl font-extrabold tracking-tight">Toutes les commandes</h1>
    <twig:Button :href="path('app_orders_new')">Nouvelle commande</twig:Button>
  </div>

  {% if orders is empty %}
    <twig:EmptyState title="Aucune commande pour le moment" icon="cart" class="mt-6" />
  {% else %}
    <div class="mt-6 overflow-x-auto rounded-card border border-line bg-surface shadow-card">
      <table class="w-full min-w-[40rem] text-left text-sm">
        <thead class="bg-subtle text-xs font-semibold uppercase tracking-wide text-muted">
          <tr>
            <th scope="col" class="px-4 py-3">Commande</th>
            <th scope="col" class="px-4 py-3">Date</th>
            <th scope="col" class="px-4 py-3">Statut</th>
            <th scope="col" class="px-4 py-3 text-right">Total</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line">
          {% for order in orders %}
            <tr>
              <td class="px-4 py-3 font-semibold">#{{ order.id }}</td>
              <td class="px-4 py-3 text-muted">{{ order.createdAt ? order.createdAt|date('d/m/Y H:i') : '' }}</td>
              <td class="px-4 py-3">{{ include('orders/_status_badge.html.twig', {status: order.status}) }}</td>
              <td class="px-4 py-3 text-right font-bold tabular-nums">{{ order.total|money }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-2">
                  <twig:Button :href="path('app_orders_show', {id: order.id})" variant="ghost" size="sm">Détails</twig:Button>
                  <twig:Button :href="path('app_orders_edit', {id: order.id})" variant="secondary" size="sm">Modifier</twig:Button>
                </div>
              </td>
            </tr>
          {% endfor %}
        </tbody>
      </table>
    </div>
  {% endif %}
{% endblock %}
```

`templates/orders/show.html.twig`:

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Commande #{{ order.id }} - MiniStore admin{% endblock %}

{% block content %}
  <a href="{{ path('app_orders_index') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">← Commandes</a>
  <div class="mt-2 flex flex-wrap items-center gap-3">
    <h1 class="text-2xl font-extrabold tracking-tight">Commande #{{ order.id }}</h1>
    {{ include('orders/_status_badge.html.twig', {status: order.status}) }}
  </div>
  <p class="mt-1 text-sm text-muted">Passée le {{ order.createdAt ? order.createdAt|date('d/m/Y à H:i') : '' }}</p>

  <twig:Card class="mt-6 max-w-3xl p-6 sm:p-8">
    {{ include('orders/_summary.html.twig', {order: order}) }}
    <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-6">
      <twig:Button :href="path('app_orders_edit', {id: order.id})" variant="secondary">Modifier</twig:Button>
      {{ include('orders/_delete_form.html.twig') }}
    </div>
  </twig:Card>
{% endblock %}
```

`templates/orders/new.html.twig`:

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Nouvelle commande - MiniStore admin{% endblock %}

{% block content %}
  <a href="{{ path('app_orders_index') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">← Commandes</a>
  <h1 class="mt-2 text-2xl font-extrabold tracking-tight">Nouvelle commande</h1>
  <twig:Card class="mt-6 max-w-3xl p-6 sm:p-8">
    {{ include('orders/_form.html.twig') }}
  </twig:Card>
{% endblock %}
```

`templates/orders/edit.html.twig`:

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Modifier la commande #{{ order.id }} - MiniStore admin{% endblock %}

{% block content %}
  <a href="{{ path('app_orders_index') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">← Commandes</a>
  <h1 class="mt-2 text-2xl font-extrabold tracking-tight">Modifier la commande #{{ order.id }}</h1>
  <twig:Card class="mt-6 max-w-3xl p-6 sm:p-8">
    {{ include('orders/_form.html.twig', {button_label: 'Mettre à jour'}) }}
    <div class="mt-6 border-t border-line pt-6">
      {{ include('orders/_delete_form.html.twig') }}
    </div>
  </twig:Card>
{% endblock %}
```

`templates/orders/_form.html.twig` (the fields are `total`, `status`, `created_at` and `user`, all drawn by the theme):

```twig
{{ form_start(form, {attr: {class: 'space-y-5'}}) }}
  <div class="grid gap-5 sm:grid-cols-2">
    {{ form_row(form.total, {label: 'Total'}) }}
    {{ form_row(form.status, {label: 'Statut'}) }}
    {{ form_row(form.created_at, {label: 'Date'}) }}
    {{ form_row(form.user, {label: 'Client (id)'}) }}
  </div>
  <twig:Button type="submit">{{ button_label|default('Enregistrer') }}</twig:Button>
{{ form_end(form) }}
```

`templates/orders/_delete_form.html.twig`:

```twig
<form method="post" action="{{ path('app_orders_delete', {id: order.id}) }}"
      data-controller="confirm" data-confirm-message-value="Supprimer définitivement la commande #{{ order.id }} ?" data-action="confirm#ask">
  <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ order.id) }}">
  <twig:Button type="submit" variant="danger">Supprimer la commande</twig:Button>
</form>
```

- [ ] **Step 8: Run the back-office suites.**

Run: `php bin/phpunit tests/Controller/AdminPagesTest.php tests/Order tests/Security tests/Controller/AccessControlTest.php tests/Entity`
Expected: PASS. `AdminOrderCancelTest` still finds `form[name="orders"]`, `select[name="orders[status]"]` and `.alert` messages (rendered by the layout's flashes).

- [ ] **Step 9: Check in the browser.** As an admin, at 375px and 1280px:
  - The dashboard, the users list (the table scrolls horizontally inside its card at 375px, not the page) and the order edit page all render.
  - Deleting an order asks for confirmation.
  - Screenshot the dashboard and the users list for the user.

- [ ] **Step 10: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/admin templates/orders tests/Controller/AdminPagesTest.php
git commit -m "feat(design): back-office layout, dashboard, users and orders on Tailwind"
```

---

## Part E — Blog

### Task 15: Blog domain: post fields, categories, slugs

**Files:**
- Create: `src/Enum/PostCategory.php`, `src/Blog/PostSlugger.php`, `migrations/Version<timestamp>.php` (generated), `tests/Blog/PostSluggerTest.php`, `tests/Blog/PostRepositoryTest.php`
- Modify (replace): `src/Entity/Post.php`, `src/Repository/PostRepository.php`
- Modify: `tests/DatabaseWebTestCase.php` (add `createPost()`)

**Interfaces:**
- Consumes: `App\Entity\User`, and `Symfony\Component\String\Slugger\SluggerInterface` (autowired).
- Produces:
  - `enum PostCategory: string` with `News = 'news'`, `Guides = 'guides'`, `Reviews = 'reviews'`; `label(): string` returns "Actualités", "Guides" or "Tests produits".
  - `Post` getters and setters:
    - `getSlug()`/`setSlug(string)`, `getExcerpt()`/`setExcerpt(?string)`, `getImage()`/`setImage(?string)`;
    - `getCategory(): ?PostCategory`/`setCategory(?PostCategory)`, `getAuthor(): ?User`/`setAuthor(?User)`;
    - `setTitle(?string)` and `setContent(?string)` now accept `null`, because forms submit null for empty fields.
  - `PostRepository`:
    - `createListQuery(?PostCategory $category): Doctrine\ORM\Query` (newest first);
    - `countByCategory(): array<string, int>`, keyed by `PostCategory` value;
    - `findRecent(int $limit, ?Post $exclude = null): Post[]`;
    - `slugExists(string $slug): bool`.
  - `PostSlugger::uniqueSlug(string $title): string`: lower-case ASCII, at most 180 characters, `article` when the title gives no letters, and `-2`, `-3`… appended while the slug is taken.
  - `DatabaseWebTestCase::createPost(string $title = 'Premier article', PostCategory $category = PostCategory::News, ?User $author = null, string $content = 'Contenu de test.', ?\DateTimeImmutable $createdAt = null): Post`.

- [ ] **Step 1: Write the failing tests.**

`tests/Blog/PostSluggerTest.php`:

```php
<?php

namespace App\Tests\Blog;

use App\Blog\PostSlugger;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class PostSluggerTest extends DatabaseWebTestCase
{
    private PostSlugger $slugger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slugger = static::getContainer()->get(PostSlugger::class);
    }

    public function testAccentsAndPunctuationBecomeAPlainSlug(): void
    {
        $this->assertSame('ete-a-paris', $this->slugger->uniqueSlug('Été à Paris !'));
    }

    public function testATakenSlugGetsANumberSuffix(): void
    {
        $this->createPost('Hello world');
        $this->assertSame('hello-world-2', $this->slugger->uniqueSlug('Hello, World'));

        $this->createPost('Hello world 2');
        $this->assertSame('hello-world-3', $this->slugger->uniqueSlug('Hello world'));
    }

    public function testATitleWithoutLettersFallsBackToArticle(): void
    {
        $this->assertSame('article', $this->slugger->uniqueSlug('!!! ???'));
    }

    public function testVeryLongTitlesAreCut(): void
    {
        $this->assertLessThanOrEqual(180, strlen($this->slugger->uniqueSlug(str_repeat('mot ', 100))));
    }
}
```

`tests/Blog/PostRepositoryTest.php`:

```php
<?php

namespace App\Tests\Blog;

use App\Enum\PostCategory;
use App\Repository\PostRepository;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class PostRepositoryTest extends DatabaseWebTestCase
{
    public function testListIsNewestFirstAndCanBeFilteredByCategory(): void
    {
        $this->createPost('Ancien', PostCategory::News, createdAt: new \DateTimeImmutable('-2 days'));
        $this->createPost('Récent', PostCategory::Guides, createdAt: new \DateTimeImmutable('-1 day'));
        $repository = static::getContainer()->get(PostRepository::class);

        $all = $repository->createListQuery(null)->getResult();
        $this->assertSame(['Récent', 'Ancien'], array_map(fn ($p) => $p->getTitle(), $all));

        $guides = $repository->createListQuery(PostCategory::Guides)->getResult();
        $this->assertSame(['Récent'], array_map(fn ($p) => $p->getTitle(), $guides));
    }

    public function testCountsByCategoryAndRecentPostsExcludingOne(): void
    {
        $first = $this->createPost('Un', PostCategory::News);
        $this->createPost('Deux', PostCategory::News);
        $this->createPost('Trois', PostCategory::Reviews);
        $repository = static::getContainer()->get(PostRepository::class);

        $this->assertSame(['news' => 2, 'reviews' => 1], $repository->countByCategory());
        $this->assertNotContains($first, $repository->findRecent(5, $first));
        $this->assertTrue($repository->slugExists('un'));
        $this->assertFalse($repository->slugExists('quatre'));
    }
}
```

- [ ] **Step 2: Add the test helper** to `tests/DatabaseWebTestCase.php`. Add `use App\Entity\Post;` and `use App\Enum\PostCategory;`, then add:

```php
    protected function createPost(
        string $title = 'Premier article',
        PostCategory $category = PostCategory::News,
        ?User $author = null,
        string $content = 'Contenu de test.',
        ?\DateTimeImmutable $createdAt = null,
    ): Post {
        $post = (new Post())
            ->setTitle($title)
            ->setSlug(strtolower(str_replace(' ', '-', $title)))
            ->setExcerpt('Résumé de ' . $title)
            ->setContent($content)
            ->setCategory($category)
            ->setAuthor($author)
            ->setCreatedAt($createdAt ?? new \DateTimeImmutable());
        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }
```

- [ ] **Step 3: Run the tests to verify they fail.**

Run: `php bin/phpunit tests/Blog`
Expected: FAIL: `Class "App\Enum\PostCategory" not found`.

- [ ] **Step 4: Create `src/Enum/PostCategory.php`:**

```php
<?php

namespace App\Enum;

/**
 * Blog categories. The string value is stored in post.category and used in URLs (?categorie=guides).
 */
enum PostCategory: string
{
    case News = 'news';
    case Guides = 'guides';
    case Reviews = 'reviews';

    public function label(): string
    {
        return match ($this) {
            self::News => 'Actualités',
            self::Guides => 'Guides',
            self::Reviews => 'Tests produits',
        };
    }
}
```

- [ ] **Step 5: Replace `src/Entity/Post.php`:**

```php
<?php

namespace App\Entity;

use App\Enum\PostCategory;
use App\Repository\PostRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'post')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Saisissez un titre.')]
    #[Assert\Length(max: 160, maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $title = null;

    // Set once from the title when the post is created, then never changed: published URLs stay valid.
    #[ORM\Column(length: 180, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 300)]
    #[Assert\NotBlank(message: 'Saisissez un résumé.')]
    #[Assert\Length(max: 300, maxMessage: 'Le résumé ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $excerpt = null;

    // Plain text, rendered escaped: HTML typed here is shown as text, never executed.
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Saisissez le contenu de l’article.')]
    private ?string $content = null;

    // File name inside %app.post_upload_dir% (random, chosen by the server).
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 20, enumType: PostCategory::class)]
    #[Assert\NotNull(message: 'Choisissez une catégorie.')]
    private ?PostCategory $category = null;

    // Deleting the author's account keeps the post (author becomes null).
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getExcerpt(): ?string { return $this->excerpt; }
    public function setExcerpt(?string $excerpt): static { $this->excerpt = $excerpt; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): static { $this->content = $content; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    public function getCategory(): ?PostCategory { return $this->category; }
    public function setCategory(?PostCategory $category): static { $this->category = $category; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
```

Check that nothing called the removed `setId()`: `grep -rn "setId(" src tests` shows no `Post` usage.

- [ ] **Step 6: Replace `src/Repository/PostRepository.php`:**

```php
<?php

namespace App\Repository;

use App\Entity\Post;
use App\Enum\PostCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /** Newest first; paginated by the caller (KnpPaginator accepts a Query). */
    public function createListQuery(?PostCategory $category): Query
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC');

        if ($category !== null) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category);
        }

        return $qb->getQuery();
    }

    /** @return array<string, int> category value => number of posts (categories without posts are absent) */
    public function countByCategory(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.category AS category, COUNT(p.id) AS total')
            ->groupBy('p.category')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $key = $row['category'] instanceof PostCategory ? $row['category']->value : (string) $row['category'];
            $counts[$key] = (int) $row['total'];
        }
        ksort($counts);

        return $counts;
    }

    /** @return Post[] */
    public function findRecent(int $limit, ?Post $exclude = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit);

        if ($exclude !== null) {
            $qb->andWhere('p != :exclude')->setParameter('exclude', $exclude);
        }

        return $qb->getQuery()->getResult();
    }

    public function slugExists(string $slug): bool
    {
        return $this->count(['slug' => $slug]) > 0;
    }
}
```

- [ ] **Step 7: Create `src/Blog/PostSlugger.php`:**

```php
<?php

namespace App\Blog;

use App\Repository\PostRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Turns a post title into a unique URL slug: "Été à Paris !" => "ete-a-paris", then "ete-a-paris-2"...
 */
final class PostSlugger
{
    private const MAX_LENGTH = 170; // leaves room for a "-NNN" suffix within the 180-character column

    public function __construct(
        private readonly PostRepository $posts,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function uniqueSlug(string $title): string
    {
        $base = $this->slugger->slug($title)->lower()->truncate(self::MAX_LENGTH)->trim('-')->toString();
        if ($base === '') {
            $base = 'article';
        }

        $slug = $base;
        for ($suffix = 2; $this->posts->slugExists($slug); ++$suffix) {
            $slug = $base . '-' . $suffix;
        }

        return $slug;
    }
}
```

- [ ] **Step 8: Generate and review the migration.**

Run: `php bin/console make:migration`

Open the generated file. Its `up()` must contain only these changes to `post` (the table is empty today, so the new `NOT NULL` columns are safe; `SELECT COUNT(*) FROM post` returned 0 during analysis):
- `ADD` columns `slug VARCHAR(180) NOT NULL`, `excerpt VARCHAR(300) NOT NULL`, `image VARCHAR(255) DEFAULT NULL`, `category VARCHAR(20) NOT NULL` and `author_id INT DEFAULT NULL`;
- a unique index on `slug` and an index on `author_id`;
- the foreign key to `users (id)` `ON DELETE SET NULL`.

Delete any unrelated statement the diff may include. If the table is not empty in some environment, stop and ask the user before running it.

Run: `php bin/console doctrine:migrations:migrate --no-interaction`
Expected: the migration is applied.

- [ ] **Step 9: Run the tests.**

Run: `php bin/phpunit tests/Blog`
Expected: PASS (6 tests).

- [ ] **Step 10: Run the full suite, then commit.** `/blog` still uses the old template until Task 17; `BlogPageTest` doesn't exist yet.

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add src/Enum/PostCategory.php src/Entity/Post.php src/Repository/PostRepository.php src/Blog/PostSlugger.php migrations tests/Blog tests/DatabaseWebTestCase.php
git commit -m "feat(blog): post slug, excerpt, image, category and author"
```

---

### Task 16: Back-office post editor with image upload

**Files:**
- Create: `src/Blog/PostImageUploader.php`, `src/Form/PostType.php`, `src/Controller/AdminPostController.php`, `templates/admin/posts/index.html.twig`, `templates/admin/posts/new.html.twig`, `templates/admin/posts/edit.html.twig`, `templates/admin/posts/_form.html.twig`, `tests/Controller/AdminPostTest.php`
- Modify: `config/services.yaml`, `templates/admin/layout.html.twig` (the nav entry), `templates/admin/index.html.twig` (the dashboard card)

**Interfaces:**
- Consumes: `Post`, `PostCategory`, `PostRepository::createListQuery()` and `PostSlugger::uniqueSlug()` (Task 15); the admin layout (Task 14).
- Produces:
  - Routes `admin_posts` (`GET /admin/posts`), `admin_post_new` (`GET|POST /admin/posts/new`), `admin_post_edit` (`GET|POST /admin/posts/{id}/edit`) and `admin_post_delete` (`POST /admin/posts/{id}/delete`, CSRF id `delete-post-{id}`).
  - The parameter `app.post_upload_dir`.
  - `PostImageUploader::upload(UploadedFile): string` (returns the stored file name) and `remove(?string): void`.
  - The form `post` with fields `title`, `excerpt`, `content`, `category` and `imageFile` (unmapped).

- [ ] **Step 1: Write the failing tests** `tests/Controller/AdminPostTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;

#[Group('database')]
class AdminPostTest extends DatabaseWebTestCase
{
    // Stateless CSRF (Symfony 7.2+) accepts same-origin requests.
    private const SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    private string $uploadDir;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadDir = static::getContainer()->getParameter('app.post_upload_dir');
        (new Filesystem())->remove($this->uploadDir);
        $this->admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
    }

    public function testCustomersCannotOpenThePostAdmin(): void
    {
        $this->client->loginUser($this->createUser('ada@example.com'));

        $this->client->request('GET', '/admin/posts');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnAdminPublishesAPostWithAnImage(): void
    {
        $this->client->loginUser($this->admin);

        $this->submitNewPost('Été à Paris', $this->makePng());

        $this->assertResponseRedirects('/admin/posts', 303);
        $post = $this->em->getRepository(Post::class)->findOneBy(['slug' => 'ete-a-paris']);
        $this->assertNotNull($post);
        $this->assertSame($this->admin->getId(), $post->getAuthor()?->getId());
        // Random name chosen by the server, extension from the detected type: never the client's file name.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', (string) $post->getImage());
        $this->assertFileExists($this->uploadDir . '/' . $post->getImage());
    }

    public function testAFileThatIsNotAnImageIsRejectedEvenWithAnImageName(): void
    {
        $this->client->loginUser($this->admin);
        $fake = sys_get_temp_dir() . '/photo-' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($fake, '<?php echo "pwned";');

        $this->submitNewPost('Article piégé', $fake);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('form[name="post"]', 'JPEG, PNG ou WebP');
        $this->assertSame(0, $this->em->getRepository(Post::class)->count([]));
        $this->assertSame([], glob($this->uploadDir . '/*') ?: []);
    }

    public function testTheSlugStaysTheSameWhenTheTitleChanges(): void
    {
        $post = $this->createPost('Titre initial');
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', sprintf('/admin/posts/%d/edit', $post->getId()));
        $form = $crawler->filter('form[name="post"]')->form(['post[title]' => 'Nouveau titre']);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        $this->assertResponseRedirects('/admin/posts', 303);
        $this->em->clear();
        $reloaded = $this->em->getRepository(Post::class)->find($post->getId());
        $this->assertSame('Nouveau titre', $reloaded->getTitle());
        $this->assertSame('titre-initial', $reloaded->getSlug());
    }

    public function testDeletingAPostRemovesItsImage(): void
    {
        $this->client->loginUser($this->admin);
        $this->submitNewPost('À supprimer', $this->makePng());
        $post = $this->em->getRepository(Post::class)->findOneBy(['slug' => 'a-supprimer']);
        $imagePath = $this->uploadDir . '/' . $post->getImage();

        $crawler = $this->client->request('GET', '/admin/posts');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/posts/%d/delete"]', $post->getId()))->form());

        $this->assertResponseRedirects('/admin/posts', 303);
        $this->assertFileDoesNotExist($imagePath);
        $this->em->clear();
        $this->assertNull($this->em->getRepository(Post::class)->find($post->getId()));
    }

    public function testDeleteWithoutAValidTokenDoesNothing(): void
    {
        $post = $this->createPost();
        $this->client->loginUser($this->admin);

        $this->client->request('POST', sprintf('/admin/posts/%d/delete', $post->getId()), ['_token' => 'forged']);

        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(Post::class)->find($post->getId()));
    }

    private function submitNewPost(string $title, string $imagePath): void
    {
        $crawler = $this->client->request('GET', '/admin/posts/new');
        $form = $crawler->filter('form[name="post"]')->form([
            'post[title]' => $title,
            'post[excerpt]' => 'Un résumé.',
            'post[content]' => "Premier paragraphe.\n\nSecond paragraphe.",
            'post[category]' => 'guides',
        ]);
        $form['post[imageFile]']->upload($imagePath);
        $this->client->submit($form, [], self::SAME_ORIGIN);
    }

    private function makePng(): string
    {
        $path = sys_get_temp_dir() . '/upload-' . bin2hex(random_bytes(4)) . '.png';
        $image = imagecreatetruecolor(4, 4);
        imagepng($image, $path);

        return $path;
    }
}
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `php bin/phpunit tests/Controller/AdminPostTest.php`
Expected: FAIL: the parameter `app.post_upload_dir` is not defined.

- [ ] **Step 3: Declare the upload directory** in `config/services.yaml`. Under `parameters:`:

```yaml
    # Blog images: public so they can be served, random file names (PostImageUploader).
    app.post_upload_dir: '%kernel.project_dir%/public/uploads/posts'
```

At the end of the file:

```yaml
when@test:
    parameters:
        # Tests never write into public/.
        app.post_upload_dir: '%kernel.project_dir%/var/test-uploads/posts'
```

- [ ] **Step 4: Create `src/Blog/PostImageUploader.php`:**

```php
<?php

namespace App\Blog;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores blog images. The form has already checked the real type (JPEG/PNG/WebP, read from the
 * file content) and the size; this class only decides where the file goes and under which name.
 */
final class PostImageUploader
{
    public function __construct(
        #[Autowire(param: 'app.post_upload_dir')]
        private readonly string $targetDirectory,
    ) {
    }

    /** @return string the stored file name, to save in Post::$image */
    public function upload(UploadedFile $file): string
    {
        // Name and extension come from the server, never from the client: no "../", no ".php".
        $filename = bin2hex(random_bytes(16)) . '.' . ($file->guessExtension() ?? 'bin');
        $file->move($this->targetDirectory, $filename);

        return $filename;
    }

    public function remove(?string $filename): void
    {
        // basename() check: a tampered value such as "../../.env" is never deleted.
        if ($filename === null || $filename === '' || basename($filename) !== $filename) {
            return;
        }

        $path = $this->targetDirectory . '/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
```

- [ ] **Step 5: Create `src/Form/PostType.php`:**

```php
<?php

namespace App\Form;

use App\Entity\Post;
use App\Enum\PostCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre', 'attr' => ['maxlength' => 160]])
            ->add('category', EnumType::class, [
                'label' => 'Catégorie',
                'class' => PostCategory::class,
                'choice_label' => fn (PostCategory $category) => $category->label(),
                'placeholder' => 'Choisissez une catégorie',
            ])
            ->add('excerpt', TextareaType::class, [
                'label' => 'Résumé',
                'help' => 'Affiché dans la liste des articles (300 caractères maximum).',
                'attr' => ['rows' => 3, 'maxlength' => 300],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'help' => 'Texte brut : laissez une ligne vide entre deux paragraphes.',
                'attr' => ['rows' => 14],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'required' => false,
                'help' => 'JPEG, PNG ou WebP, 2 Mo maximum.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    // The type is detected from the file content, so a renamed .php file is rejected.
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        maxSizeMessage: 'L’image ne doit pas dépasser 2 Mo.',
                        mimeTypesMessage: 'Choisissez une image JPEG, PNG ou WebP.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Post::class]);
    }
}
```

- [ ] **Step 6: Create `src/Controller/AdminPostController.php`:**

```php
<?php

namespace App\Controller;

use App\Blog\PostImageUploader;
use App\Blog\PostSlugger;
use App\Entity\Post;
use App\Entity\User;
use App\Form\PostType;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/posts')]
#[IsGranted('ROLE_ADMIN')]
final class AdminPostController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostImageUploader $uploader,
    ) {
    }

    #[Route('', name: 'admin_posts', methods: ['GET'])]
    public function index(Request $request, PostRepository $posts, PaginatorInterface $paginator): Response
    {
        $pagination = $paginator->paginate($posts->createListQuery(null), max(1, $request->query->getInt('page', 1)), 15);

        return $this->render('admin/posts/index.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/new', name: 'admin_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PostSlugger $slugger): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $author = $this->getUser();
            $post->setSlug($slugger->uniqueSlug((string) $post->getTitle()))
                ->setAuthor($author instanceof User ? $author : null)
                ->setCreatedAt(new \DateTimeImmutable());
            $this->storeImage($form, $post);
            $this->em->persist($post);
            $this->em->flush();

            $this->addFlash('success', 'Article publié.');

            return $this->redirectToRoute('admin_posts', [], Response::HTTP_SEE_OTHER);
        }

        // Passing the form (not createView()) makes render() answer 422 when it is invalid.
        return $this->render('admin/posts/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_post_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Post $post, Request $request): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeImage($form, $post);
            $this->em->flush();

            $this->addFlash('success', 'Article mis à jour.');

            return $this->redirectToRoute('admin_posts', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/posts/edit.html.twig', ['form' => $form, 'post' => $post]);
    }

    #[Route('/{id}/delete', name: 'admin_post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Post $post, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-post-' . $post->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide : l’article n’a pas été supprimé.');

            return $this->redirectToRoute('admin_posts', [], Response::HTTP_SEE_OTHER);
        }

        $image = $post->getImage();
        $this->em->remove($post);
        $this->em->flush();
        $this->uploader->remove($image);

        $this->addFlash('success', 'Article supprimé.');

        return $this->redirectToRoute('admin_posts', [], Response::HTTP_SEE_OTHER);
    }

    private function storeImage(FormInterface $form, Post $post): void
    {
        $file = $form->get('imageFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $previous = $post->getImage();
        $post->setImage($this->uploader->upload($file));
        $this->uploader->remove($previous);
    }
}
```

- [ ] **Step 7: Create the back-office templates.**

`templates/admin/posts/index.html.twig`:

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Articles - MiniStore admin{% endblock %}

{% block content %}
  <div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-2xl font-extrabold tracking-tight">Articles</h1>
    <twig:Button :href="path('admin_post_new')">Nouvel article</twig:Button>
  </div>

  {% if pagination.totalItemCount == 0 %}
    <twig:EmptyState title="Aucun article pour le moment" class="mt-6">
      Publiez votre premier article : il apparaîtra sur le blog.
      <twig:block name="actions"><twig:Button :href="path('admin_post_new')">Écrire un article</twig:Button></twig:block>
    </twig:EmptyState>
  {% else %}
    <div class="mt-6 overflow-x-auto rounded-card border border-line bg-surface shadow-card">
      <table class="w-full min-w-[40rem] text-left text-sm">
        <thead class="bg-subtle text-xs font-semibold uppercase tracking-wide text-muted">
          <tr>
            <th scope="col" class="px-4 py-3">Article</th>
            <th scope="col" class="px-4 py-3">Catégorie</th>
            <th scope="col" class="px-4 py-3">Publié le</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line">
          {% for post in pagination %}
            <tr>
              <td class="px-4 py-3">
                <a href="{{ path('app_single_post', {slug: post.slug}) }}" class="font-semibold text-ink hover:text-accent">{{ post.title }}</a>
                <p class="text-muted">{{ post.author ? post.author.firstName ~ ' ' ~ post.author.lastName : 'Auteur supprimé' }}</p>
              </td>
              <td class="px-4 py-3"><twig:Badge tone="accent">{{ post.category.label }}</twig:Badge></td>
              <td class="px-4 py-3 text-muted">{{ post.createdAt|date('d/m/Y') }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-2">
                  <twig:Button :href="path('admin_post_edit', {id: post.id})" variant="secondary" size="sm">Modifier</twig:Button>
                  <form method="post" action="{{ path('admin_post_delete', {id: post.id}) }}"
                        data-controller="confirm" data-confirm-message-value="Supprimer l’article « {{ post.title }} » ?" data-action="confirm#ask">
                    <input type="hidden" name="_token" value="{{ csrf_token('delete-post-' ~ post.id) }}">
                    <twig:Button type="submit" variant="danger" size="sm">Supprimer</twig:Button>
                  </form>
                </div>
              </td>
            </tr>
          {% endfor %}
        </tbody>
      </table>
    </div>
    <twig:Pagination :page="pagination.currentPageNumber" :pages="pagination.pageCount" route="admin_posts" :params="app.request.query.all" />
  {% endif %}
{% endblock %}
```

`templates/admin/posts/_form.html.twig`:

```twig
{# Expects "form"; "post" is set on the edit page (to preview the current image). #}
{{ form_start(form, {attr: {class: 'space-y-5'}}) }}
  {{ form_errors(form) }}
  {{ form_row(form.title) }}
  {{ form_row(form.category) }}
  {{ form_row(form.excerpt) }}
  {{ form_row(form.content) }}
  {% if post is defined and post.image %}
    <div class="flex items-center gap-4">
      <img src="{{ asset('uploads/posts/' ~ post.image) }}" alt="" width="96" height="64" class="h-16 w-24 rounded-control object-cover">
      <p class="text-sm text-muted">Image actuelle : choisissez un fichier ci-dessous pour la remplacer.</p>
    </div>
  {% endif %}
  {{ form_row(form.imageFile) }}
  <twig:Button type="submit">{{ button_label|default('Publier') }}</twig:Button>
{{ form_end(form) }}
```

`templates/admin/posts/new.html.twig`:

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Nouvel article - MiniStore admin{% endblock %}

{% block content %}
  <a href="{{ path('admin_posts') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">← Articles</a>
  <h1 class="mt-2 text-2xl font-extrabold tracking-tight">Nouvel article</h1>
  <twig:Card class="mt-6 max-w-3xl p-6 sm:p-8">
    {{ include('admin/posts/_form.html.twig') }}
  </twig:Card>
{% endblock %}
```

`templates/admin/posts/edit.html.twig`:

```twig
{% extends 'admin/layout.html.twig' %}

{% block title %}Modifier l’article - MiniStore admin{% endblock %}

{% block content %}
  <a href="{{ path('admin_posts') }}" class="text-sm font-semibold text-accent hover:text-accent-hover">← Articles</a>
  <h1 class="mt-2 break-words text-2xl font-extrabold tracking-tight">{{ post.title }}</h1>
  <p class="mt-1 text-sm text-muted">Adresse : /blog/{{ post.slug }} (elle ne change pas si vous modifiez le titre)</p>
  <twig:Card class="mt-6 max-w-3xl p-6 sm:p-8">
    {{ include('admin/posts/_form.html.twig', {button_label: 'Enregistrer'}) }}
  </twig:Card>
{% endblock %}
```

- [ ] **Step 8: Add "Articles" to the back office.**
  - In `templates/admin/layout.html.twig`, append to `admin_sections`:

    ```twig
      {route: 'admin_posts', match: 'admin_post', label: 'Articles', icon: 'box'},
    ```

  - In `templates/admin/index.html.twig`, append to the cards list:

    ```twig
      {href: path('admin_posts'), title: 'Articles', text: 'Écrire et publier des articles de blog.', icon: 'box'},
    ```

- [ ] **Step 9: Run the tests.**

Run: `php bin/phpunit tests/Controller/AdminPostTest.php tests/Controller/AdminPagesTest.php`
Expected: PASS (6 + 4 tests).

- [ ] **Step 10: Check in the browser.** As an admin:
  - Create a post with a real photo.
  - Try a `.txt` file renamed `.jpg`: the error appears under the image field.
  - Edit the title and check that the slug shown is unchanged.
  - Delete the post (a confirmation appears).

- [ ] **Step 11: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add config/services.yaml src/Blog/PostImageUploader.php src/Form/PostType.php src/Controller/AdminPostController.php templates/admin tests/Controller/AdminPostTest.php
git commit -m "feat(blog): back-office post editor with validated image upload"
```

---

### Task 17: Public blog pages

**Files:**
- Modify (replace): `src/Controller/BlogController.php`
- Create: `templates/blog/index.html.twig`, `templates/blog/show.html.twig`, `templates/blog/_card.html.twig`, `templates/blog/_sidebar.html.twig`, `tests/Controller/BlogPageTest.php`
- Delete: `src/Controller/SinglePostController.php`, `templates/single-post/`, `templates/blog/blog.html.twig`, `templates/partials/blog_sidebar.html.twig`, `templates/partials/pagination.html.twig`
- Modify: `templates/partials/header.html.twig` and `templates/partials/footer.html.twig` (Blog links)

**Interfaces:**
- Consumes:
  - `PostRepository::createListQuery()`, `countByCategory()` and `findRecent()`, plus `PostCategory` (Task 15);
  - `PageHeader`, `Pagination`, `EmptyState`, `Badge` and `Button`;
  - the Twig function `enum_cases()`, available since Twig 3.12; the project requires `^3.30`.
- Produces:
  - `app_blog` (`GET /blog`, query `categorie` and `page`) and `app_single_post` (`GET /blog/{slug}`, 404 when unknown). Both route names are unchanged.
  - The post body is rendered inside `.post-content`.

- [ ] **Step 1: Write the failing tests** `tests/Controller/BlogPageTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Enum\PostCategory;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class BlogPageTest extends DatabaseWebTestCase
{
    public function testListShowsPostsNewestFirst(): void
    {
        $this->createPost('Ancien article', createdAt: new \DateTimeImmutable('-2 days'));
        $this->createPost('Nouvel article', createdAt: new \DateTimeImmutable('-1 day'));

        $crawler = $this->client->request('GET', '/blog');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['Nouvel article', 'Ancien article'], $crawler->filter('main article h2')->each(fn ($h) => trim($h->text())));
    }

    public function testCategoryFilterAndUnknownCategory(): void
    {
        $this->createPost('Un guide', PostCategory::Guides);
        $this->createPost('Une actu', PostCategory::News);

        $crawler = $this->client->request('GET', '/blog?categorie=guides');
        $this->assertSelectorTextContains('h1', 'Guides');
        $this->assertSame(['Un guide'], $crawler->filter('main article h2')->each(fn ($h) => trim($h->text())));

        $crawler = $this->client->request('GET', '/blog?categorie=nope');
        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('main article'));
    }

    public function testPostContentIsEscapedAndSplitIntoParagraphs(): void
    {
        $post = $this->createPost('Securite', content: "<script>alert(1)</script>\n\nDeuxième paragraphe\navec un saut de ligne.");

        $crawler = $this->client->request('GET', '/blog/' . $post->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $this->client->getResponse()->getContent());
        $this->assertCount(2, $crawler->filter('.post-content p'));
        $this->assertCount(1, $crawler->filter('.post-content p br'));
    }

    public function testUnknownSlugIsANotFound(): void
    {
        $this->client->request('GET', '/blog/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPaginationAfterSixPosts(): void
    {
        for ($i = 1; $i <= 7; ++$i) {
            $this->createPost('Article ' . $i);
        }

        $crawler = $this->client->request('GET', '/blog');

        $this->assertCount(6, $crawler->filter('main article'));
        $this->assertSame('/blog?page=2', $crawler->filter('.ms-pagination a[rel="next"]')->attr('href'));
    }

    public function testEmptyBlogAndNavigationLink(): void
    {
        $this->client->request('GET', '/blog');
        $this->assertSelectorTextContains('main h2', 'Aucun article');

        $this->client->request('GET', '/');
        $this->assertSelectorExists('header a[href="/blog"]');
    }
}
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `php bin/phpunit tests/Controller/BlogPageTest.php`
Expected: FAIL. `/blog/{slug}` returns 500 (missing template), and the list template reads fields that aren't used this way.

- [ ] **Step 3: Replace `src/Controller/BlogController.php`, and delete the old controller and templates.**

```php
<?php

namespace App\Controller;

use App\Entity\Post;
use App\Enum\PostCategory;
use App\Repository\PostRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    private const PER_PAGE = 6;

    #[Route('/blog', name: 'app_blog', methods: ['GET'])]
    public function index(Request $request, PostRepository $posts, PaginatorInterface $paginator): Response
    {
        // An unknown ?categorie= value is ignored (all posts are listed) rather than raising an error.
        $category = PostCategory::tryFrom((string) $request->query->get('categorie', ''));

        $pagination = $paginator->paginate(
            $posts->createListQuery($category),
            max(1, $request->query->getInt('page', 1)),
            self::PER_PAGE,
        );

        return $this->render('blog/index.html.twig', [
            'posts' => $pagination,
            'category' => $category,
            'category_counts' => $posts->countByCategory(),
            'recent_posts' => $posts->findRecent(5),
        ]);
    }

    // MapEntity looks the post up by slug; an unknown slug becomes a 404 automatically.
    #[Route('/blog/{slug}', name: 'app_single_post', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Post $post, PostRepository $posts): Response
    {
        return $this->render('blog/show.html.twig', [
            'post' => $post,
            'category_counts' => $posts->countByCategory(),
            'recent_posts' => $posts->findRecent(5, $post),
        ]);
    }
}
```

```bash
git rm -q src/Controller/SinglePostController.php -r templates/single-post templates/blog/blog.html.twig templates/partials/blog_sidebar.html.twig templates/partials/pagination.html.twig
```

Run: `grep -rn "partials/pagination\|blog_sidebar\|single-post\|single_post/" templates src`
Expected: no output.

- [ ] **Step 4: Create `templates/blog/_card.html.twig`:**

```twig
{# Post tile. Expects "post". #}
<article class="group relative flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-card transition hover:shadow-card-hover motion-safe:hover:-translate-y-0.5">
  <div class="aspect-[16/9] overflow-hidden bg-subtle">
    {% if post.image %}
      <img src="{{ asset('uploads/posts/' ~ post.image) }}" alt="" loading="lazy" width="640" height="360" class="size-full object-cover">
    {% else %}
      <div class="size-full bg-gradient-to-br from-accent-soft to-subtle" aria-hidden="true"></div>
    {% endif %}
  </div>
  <div class="flex flex-1 flex-col gap-3 p-5">
    <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
      <twig:Badge tone="accent">{{ post.category.label }}</twig:Badge>
      <time datetime="{{ post.createdAt|date('Y-m-d') }}">{{ post.createdAt|date('d/m/Y') }}</time>
    </div>
    <h2 class="break-words text-lg font-bold leading-snug text-ink">
      <a href="{{ path('app_single_post', {slug: post.slug}) }}" class="after:absolute after:inset-0">{{ post.title }}</a>
    </h2>
    <p class="break-words text-sm text-muted">{{ post.excerpt }}</p>
  </div>
</article>
```

- [ ] **Step 5: Create `templates/blog/_sidebar.html.twig`:**

```twig
{# Expects "category_counts" (value => count), "recent_posts" and optionally "category" (active PostCategory). #}
<aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
  <nav aria-labelledby="blog-categories" class="rounded-card border border-line bg-surface p-5">
    <h2 id="blog-categories" class="text-sm font-bold text-ink">Catégories</h2>
    <ul class="mt-3 space-y-1">
      <li>
        <a href="{{ path('app_blog') }}" {% if category is not defined or category is null %}aria-current="page"{% endif %}
           class="flex justify-between rounded-control px-2 py-1.5 text-sm text-muted hover:bg-subtle hover:text-ink aria-[current=page]:font-semibold aria-[current=page]:text-accent-ink">
          Tous les articles
        </a>
      </li>
      {% for case in enum_cases('App\\Enum\\PostCategory') %}
        {% set total = category_counts[case.value] ?? 0 %}
        {% if total > 0 %}
          <li>
            <a href="{{ path('app_blog', {categorie: case.value}) }}" {% if category is defined and category == case %}aria-current="page"{% endif %}
               class="flex justify-between rounded-control px-2 py-1.5 text-sm text-muted hover:bg-subtle hover:text-ink aria-[current=page]:font-semibold aria-[current=page]:text-accent-ink">
              {{ case.label }} <span class="tabular-nums">{{ total }}</span>
            </a>
          </li>
        {% endif %}
      {% endfor %}
    </ul>
  </nav>

  {% if recent_posts is not empty %}
    <section aria-labelledby="blog-recent" class="rounded-card border border-line bg-surface p-5">
      <h2 id="blog-recent" class="text-sm font-bold text-ink">Articles récents</h2>
      <ul class="mt-3 space-y-3">
        {% for recent in recent_posts %}
          <li>
            <a href="{{ path('app_single_post', {slug: recent.slug}) }}" class="block break-words text-sm font-semibold text-ink hover:text-accent">{{ recent.title }}</a>
            <p class="text-xs text-muted">{{ recent.createdAt|date('d/m/Y') }}</p>
          </li>
        {% endfor %}
      </ul>
    </section>
  {% endif %}
</aside>
```

- [ ] **Step 6: Create `templates/blog/index.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% set heading = category ? category.label : 'Blog' %}

{% block title %}{{ heading }} - Blog MiniStore{% endblock %}
{% block meta_description %}Guides, actualités et tests produits de l’équipe MiniStore.{% endblock %}

{% block content %}
  <twig:PageHeader :title="heading" :parent="category ? 'Blog' : null" :parentUrl="path('app_blog')"
                   lead="Guides, actualités et tests produits de l’équipe MiniStore." />

  <div class="page-wrap grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="min-w-0">
      {% if posts.totalItemCount > 0 %}
        <div class="product-grid">
          {% for post in posts %}
            {{ include('blog/_card.html.twig', {post: post}) }}
          {% endfor %}
        </div>
        <twig:Pagination :page="posts.currentPageNumber" :pages="posts.pageCount" route="app_blog" :params="app.request.query.all" />
      {% else %}
        <twig:EmptyState title="Aucun article pour le moment" icon="box">Les premiers articles arrivent bientôt.</twig:EmptyState>
      {% endif %}
    </div>
    {{ include('blog/_sidebar.html.twig') }}
  </div>
{% endblock %}
```

- [ ] **Step 7: Create `templates/blog/show.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ post.title }} - Blog MiniStore{% endblock %}
{% block meta_description %}{{ post.excerpt }}{% endblock %}

{% block content %}
  <twig:PageHeader :title="post.title" parent="Blog" :parentUrl="path('app_blog')" :showTitle="false" />

  <div class="page-wrap grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
    <article class="min-w-0">
      <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
        <a href="{{ path('app_blog', {categorie: post.category.value}) }}"><twig:Badge tone="accent">{{ post.category.label }}</twig:Badge></a>
        <time datetime="{{ post.createdAt|date('Y-m-d') }}">{{ post.createdAt|date('d/m/Y') }}</time>
        {% if post.author %}<span>· par {{ post.author.firstName }}</span>{% endif %}
      </div>
      <h1 class="mt-3 break-words text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ post.title }}</h1>
      <p class="mt-3 text-lg text-muted">{{ post.excerpt }}</p>

      {% if post.image %}
        <img src="{{ asset('uploads/posts/' ~ post.image) }}" alt="" width="1200" height="675" class="mt-8 aspect-[16/9] w-full rounded-card object-cover shadow-card">
      {% endif %}

      {# Plain text: each block separated by an empty line is a paragraph; single line breaks are kept.
         nl2br escapes the text first, so HTML typed in the editor is displayed, never executed. #}
      <div class="post-content mt-8 max-w-prose space-y-5 break-words text-lg leading-relaxed text-ink/90">
        {% for paragraph in post.content|replace({"\r\n": "\n"})|split("\n\n") %}
          {% if paragraph|trim is not empty %}
            <p>{{ paragraph|trim|nl2br }}</p>
          {% endif %}
        {% endfor %}
      </div>

      <div class="mt-10 border-t border-line pt-6">
        <twig:Button :href="path('app_blog')" variant="secondary">← Tous les articles</twig:Button>
      </div>
    </article>
    {{ include('blog/_sidebar.html.twig', {category: post.category}) }}
  </div>
{% endblock %}
```

- [ ] **Step 8: Add the Blog links.**
  - In `templates/partials/header.html.twig`, add `{route: 'app_blog', label: 'Blog'},` to `nav` after the `Boutique` entry.
  - In `templates/partials/footer.html.twig`, add under the "Aide" list:

    ```twig
            <li><a href="{{ path('app_blog') }}" class="{{ footer_link }}">Blog</a></li>
    ```

- [ ] **Step 9: Run the tests.**

Run: `php bin/phpunit tests/Controller/BlogPageTest.php tests/Blog tests/Controller/HeaderTest.php`
Expected: PASS.

- [ ] **Step 10: Check in the browser.**
  - Publish two posts from `/admin/posts/new` (one with a long pasted URL in the content).
  - Check `/blog` and a post at 375px: the URL wraps and the page has no horizontal scroll.
  - Screenshot both pages for the user.

- [ ] **Step 11: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add -A src/Controller templates/blog templates/single-post templates/partials tests/Controller/BlogPageTest.php
git commit -m "feat(blog): public blog list and post pages with category filter"
```

---

## Part F — Content pages and clean-up

### Task 18: Contact form fix (F7), then the About and Contact pages

**Files:**
- Create: `tests/Controller/ContactFormTest.php`
- Modify: `src/Controller/ContactController.php`
- Modify (replace): `templates/contact/index.html.twig`, `templates/contact/confirmation.html.twig`, `templates/about/index.html.twig`

**Interfaces:**
- Consumes: `PageHeader`, `Card`, `Button`, the form theme and `CartTotals::SHIPPING_COST`.
- Produces: the contact form (name `form`, fields `name`, `email`, `message`, `submit`) validated server-side. Mail is sent from the shop address, with `Reply-To` set to the visitor.

- [ ] **Step 1: Write the failing tests** `tests/Controller/ContactFormTest.php`:

```php
<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContactFormTest extends WebTestCase
{
    // Stateless CSRF (Symfony 7.2+) accepts same-origin requests.
    private const SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    public function testTheMessageIsSentFromTheShopWithTheVisitorAsReplyTo(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        $client->submit($crawler->filter('form[name="form"]')->form([
            'form[name]' => 'Ada Lovelace',
            'form[email]' => 'ada@example.com',
            'form[message]' => 'Bonjour, ma commande est-elle partie ?',
        ]), [], self::SAME_ORIGIN);

        $this->assertResponseRedirects('/contact/confirmation');
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        // Sending "from" the visitor's address fails SPF/DMARC and lets anyone spoof any sender.
        $this->assertEmailAddressContains($email, 'From', 'noreply@monsite.com');
        $this->assertEmailAddressContains($email, 'Reply-To', 'ada@example.com');
    }

    #[DataProvider('invalidMessages')]
    public function testInvalidInputIsRejectedWithoutSendingAnything(array $fields, string $expectedError): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        $client->submit($crawler->filter('form[name="form"]')->form($fields), [], self::SAME_ORIGIN);

        $this->assertResponseStatusCodeSame(422);
        $this->assertEmailCount(0);
        $this->assertSelectorTextContains('form[name="form"]', $expectedError);
    }

    public static function invalidMessages(): iterable
    {
        $valid = ['form[name]' => 'Ada', 'form[email]' => 'ada@example.com', 'form[message]' => 'Un message assez long.'];

        yield 'empty name' => [array_merge($valid, ['form[name]' => '']), 'Saisissez votre nom.'];
        yield 'bad email' => [array_merge($valid, ['form[email]' => 'nope']), 'adresse e-mail valide'];
        yield 'short message' => [array_merge($valid, ['form[message]' => 'Salut']), 'au moins 10 caractères'];
    }
}
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `php bin/phpunit tests/Controller/ContactFormTest.php`
Expected: FAIL. The `From` is the visitor's address, and invalid input is accepted (a redirect instead of 422).

- [ ] **Step 3: Fix the controller.** In `src/Controller/ContactController.php`:
  - Add the imports `use Symfony\Component\Mime\Address;` and `use Symfony\Component\Validator\Constraints as Assert;`.
  - Replace the form builder and the email:

```php
        $form = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Votre nom', 'autocomplete' => 'name', 'maxlength' => 100],
                'constraints' => [
                    new Assert\NotBlank(message: 'Saisissez votre nom.'),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['placeholder' => 'vous@exemple.fr', 'autocomplete' => 'email'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Saisissez votre adresse e-mail.'),
                    new Assert\Email(message: 'Saisissez une adresse e-mail valide.'),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['placeholder' => 'Votre message', 'rows' => 6, 'maxlength' => 5000],
                'constraints' => [
                    new Assert\NotBlank(message: 'Saisissez votre message.'),
                    new Assert\Length(min: 10, max: 5000, minMessage: 'Votre message doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Envoyer'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Sent by the shop; answering the email replies to the visitor.
            $email = (new Email())
                ->from(new Address('noreply@monsite.com', 'Site MiniStore'))
                ->to('support@monsite.com')
                ->replyTo(new Address($data['email'], $data['name']))
                ->subject('Message de contact de ' . $data['name'])
                ->text($data['message']);

            $mailer->send($email);

            return $this->redirectToRoute('app_contact_confirmation');
        }

        // Passing the form (not createView()) makes render() answer 422 when it is invalid.
        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
```

- [ ] **Step 4: Run the tests, then commit the fix on its own.**

Run: `php bin/phpunit tests/Controller/ContactFormTest.php`
Expected: PASS (4 tests).

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add src/Controller/ContactController.php tests/Controller/ContactFormTest.php
git commit -m "fix: send contact mail from the shop with Reply-To, and validate the fields"
```

- [ ] **Step 5: Replace `templates/contact/index.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}Contact - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader title="Contactez-nous" lead="Une question sur un produit ou une commande ? Nous répondons sous 48 heures ouvrées." />
  <div class="page-wrap grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <twig:Card class="p-6 sm:p-8">
      {{ form_start(form, {attr: {class: 'space-y-5'}}) }}
        <div class="grid gap-5 sm:grid-cols-2">
          {{ form_row(form.name) }}
          {{ form_row(form.email) }}
        </div>
        {{ form_row(form.message) }}
        {{ form_row(form.submit) }}
      {{ form_end(form) }}
    </twig:Card>
    <aside class="space-y-4">
      <div class="rounded-card bg-subtle p-5">
        <p class="font-bold text-ink">Une commande en cours ?</p>
        <p class="mt-1 text-sm text-muted">Indiquez son numéro : vous le trouverez dans « Mes commandes ».</p>
      </div>
      <div class="rounded-card bg-subtle p-5">
        <p class="font-bold text-ink">Paiement</p>
        <p class="mt-1 text-sm text-muted">Les paiements sont traités par Stripe : nous ne voyons jamais votre numéro de carte.</p>
      </div>
    </aside>
  </div>
{% endblock %}
```

The response delay ("48 heures ouvrées") is a service promise. Ask the user to confirm it, or remove the sentence, before the final commit of this task.

- [ ] **Step 6: Replace `templates/contact/confirmation.html.twig`:**

```twig
{% extends 'base.html.twig' %}

{% block title %}Message envoyé - MiniStore{% endblock %}

{% block content %}
  <div class="page-wrap flex justify-center py-16">
    <twig:Card class="w-full max-w-md p-8 text-center">
      <span class="inline-flex size-12 items-center justify-center rounded-full bg-success-soft text-success">
        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
      </span>
      <h1 class="mt-4 text-2xl font-extrabold tracking-tight">Merci pour votre message !</h1>
      <p class="mt-2 text-muted">Nous vous répondrons par e-mail dans les plus brefs délais.</p>
      <twig:Button :href="path('app_home')" variant="secondary" class="mt-6">Retour à l’accueil</twig:Button>
    </twig:Card>
  </div>
{% endblock %}
```

- [ ] **Step 7: Replace `templates/about/index.html.twig`.** It contains only true statements, taken from features that exist in the code (spec §7.1, "Content"). The fictional team, the founding year and the missing images are gone.

```twig
{% extends 'base.html.twig' %}

{% block title %}À propos - MiniStore{% endblock %}

{% block content %}
  <twig:PageHeader title="À propos de MiniStore" lead="Une boutique en ligne de smartphones, montres connectées et audio, pensée pour être simple et honnête." />

  <div class="page-wrap space-y-12">
    <section class="grid gap-4 md:grid-cols-3" aria-label="Nos engagements">
      {% for item in [
        {icon: 'box', title: 'Un stock réel', text: 'Le stock affiché est celui de l’entrepôt. Pendant le paiement, vos articles sont réservés pour que personne ne vous les prenne.'},
        {icon: 'lock', title: 'Un paiement sûr', text: 'Le paiement par carte passe par Stripe. Votre numéro de carte ne transite jamais par nos serveurs.'},
        {icon: 'truck', title: 'Un prix de livraison clair', text: 'Un seul tarif par commande : ' ~ constant('App\\Cart\\CartTotals::SHIPPING_COST')|money ~ ', quel que soit le nombre d’articles.'},
      ] %}
        <twig:Card class="p-6">
          <span class="inline-flex size-10 items-center justify-center rounded-full bg-accent-soft text-accent">
            <svg class="size-5" aria-hidden="true"><use href="#{{ item.icon }}"></use></svg>
          </span>
          <h2 class="mt-4 font-bold text-ink">{{ item.title }}</h2>
          <p class="mt-2 text-sm leading-relaxed text-muted">{{ item.text }}</p>
        </twig:Card>
      {% endfor %}
    </section>

    <section class="rounded-card bg-ink px-6 py-10 text-center text-white sm:px-12">
      <h2 class="text-2xl font-extrabold tracking-tight">Prêt à découvrir le catalogue ?</h2>
      <p class="mx-auto mt-2 max-w-xl text-white/75">Smartphones, montres et audio, avec le stock en temps réel.</p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <twig:Button :href="path('app_shop')" size="lg">Voir la boutique</twig:Button>
        <twig:Button :href="path('app_contact')" size="lg" variant="ghost" class="text-white hover:bg-white/10">Nous contacter</twig:Button>
      </div>
    </section>
  </div>
{% endblock %}
```

`white/75` on `ink` measures about 11:1, and `ghost` with `text-white` stays readable on `bg-ink`.

- [ ] **Step 8: Run the tests.**

Run: `php bin/phpunit tests/Controller/ContactFormTest.php`
Expected: PASS.

- [ ] **Step 9: Run the full suite, then commit.**

Run: `php bin/phpunit`
Expected: PASS.

```bash
git add templates/contact templates/about
git commit -m "feat(design): about and contact pages on Tailwind"
```

---

### Task 19: Remove legacy assets, add a smoke test, final checks

**Files:**
- Delete:
  - `public/style.css`, `public/css/bootstrap.min.css`, `public/css/vendor.css`, `public/css/theme.css`, `public/css/ajax-loader.gif`
  - `public/js/jquery-1.11.0.min.js`, `public/js/plugins.js`, `public/js/script.js`, `public/js/modernizr.js`, `public/js/bootstrap.bundle.min.js`
  - `templates/partials/breadcrumb.html.twig`, `templates/partials/product_card.html.twig`
- Create: `tests/Controller/PageSmokeTest.php`
- Modify: `.gitignore`, `README.md`

**Interfaces:**
- Consumes: every page and every helper (`createProduct`, `createUser`, `createOrder`, `createPost`).
- Produces: a branch that is ready for review (spec §4: merge only after this task).

- [ ] **Step 1: Write the smoke test** `tests/Controller/PageSmokeTest.php`:

```php
<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Requests every GET page with realistic data, as a guest, a customer and an admin:
 * no page may crash, and none may still carry Bootstrap/template markup.
 */
#[Group('database')]
class PageSmokeTest extends DatabaseWebTestCase
{
    private const LEGACY_MARKUP = '.container, .row, .btn, .card-body, .form-control, .padding-large, [class*="col-md-"]';

    public function testEveryPageRendersWithTheNewDesign(): void
    {
        $product = $this->createProduct('Test Phone');
        $customer = $this->createUser('ada@example.com');
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $order = $this->createOrder($customer);
        $post = $this->createPost('Premier article', author: $admin);

        $this->assertPagesRender([
            '/', '/shop', '/shop?q=phone', '/product/' . $product->getSlug(), '/cart', '/about', '/contact',
            '/contact/confirmation', '/blog', '/blog/' . $post->getSlug(), '/login', '/register', '/verify',
            '/reset-password', '/reset-password/check-email',
        ]);

        $this->client->loginUser($customer);
        $this->addToCartFromShop($product);
        $this->assertPagesRender([
            '/cart', '/checkout', '/account', '/account/edit', '/account/password', '/account/orders',
            '/account/orders/' . $order->getId(),
        ]);

        $this->client->loginUser($admin);
        $this->assertPagesRender([
            '/admin', '/admin/users', '/admin/users/' . $customer->getId() . '/edit', '/admin/posts',
            '/admin/posts/new', '/admin/posts/' . $post->getId() . '/edit', '/orders', '/orders/new',
            '/orders/' . $order->getId(), '/orders/' . $order->getId() . '/edit',
        ]);
    }

    /** @param string[] $urls */
    private function assertPagesRender(array $urls): void
    {
        foreach ($urls as $url) {
            $crawler = $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();

            $this->assertSame(200, $status, "$url answered $status.");
            $this->assertCount(0, $crawler->filter(self::LEGACY_MARKUP), "$url still has Bootstrap/template markup.");
            $this->assertCount(1, $crawler->filter('main#main'), "$url has no <main id=\"main\">.");
        }
    }
}
```

- [ ] **Step 2: Run it.**

Run: `php bin/phpunit tests/Controller/PageSmokeTest.php`
Expected: PASS. If a page fails, the message names the URL. Fix that template (it is still using legacy classes), then re-run. `/verify` renders the verification form for any visitor, so it answers 200.

- [ ] **Step 3: Check that the legacy files are no longer referenced, then delete them.**

Run: `grep -rnE "style\.css|css/(bootstrap|vendor|theme)|js/(jquery|plugins|script|modernizr|bootstrap)|ajax-loader|partials/(breadcrumb|product_card)" templates src config assets`
Expected: no output.

```bash
git rm -q public/style.css public/css/bootstrap.min.css public/css/vendor.css public/css/theme.css public/css/ajax-loader.gif \
  public/js/jquery-1.11.0.min.js public/js/plugins.js public/js/script.js public/js/modernizr.js public/js/bootstrap.bundle.min.js \
  templates/partials/breadcrumb.html.twig templates/partials/product_card.html.twig
```

Run: `ls public/css public/js 2>/dev/null`
Expected: both directories are gone or empty. Keep `public/images` (the product images).

- [ ] **Step 4: Update `.gitignore`.** Add at the end:

```gitignore
# Brainstorming mockups written by the visual companion
/.superpowers/
```

`var/` (which includes `var/tailwind` and `var/test-uploads`) and `/public/uploads/` are already ignored.

- [ ] **Step 5: Document the front-end workflow.** In `README.md`, under "## Lancer le site", add:

````markdown
Le CSS est généré par Tailwind (binaire autonome, sans Node.js). Pendant le développement,
laissez tourner la compilation à côté du serveur :

```bash
php bin/console tailwind:build --watch
```

Déploiement : compilez le CSS **avant** les assets.

```bash
php bin/console tailwind:build --minify
php bin/console asset-map:compile
```

Les images des articles de blog sont envoyées dans `public/uploads/posts/` (non versionné) :
ce dossier doit être persistant et accessible en écriture sur le serveur.
````

- [ ] **Step 6: Run the whole suite and a production build.**

Run: `php bin/phpunit`
Expected: PASS.

Run: `php bin/console tailwind:build --minify && wc -c var/tailwind/app.built.css`
Expected: builds without warnings. The size is reported (typically a few dozen KB, far below the ~160 KB of the removed Bootstrap and template CSS).

- [ ] **Step 7: Final browser pass.** With `php -S 127.0.0.1:8000 -t public` and the minified build, check every URL listed in `PageSmokeTest`, as the right user, at **360px** and **1280px**. On each page, run with Playwright:

```js
() => document.documentElement.scrollWidth <= window.innerWidth
```

Expected: `true` everywhere (no horizontal scroll).

Then check, at 360px:
- **Keyboard only:** Tab through the header, account menu and mobile menu; the focus ring is visible and Escape closes the overlays.
- **JavaScript disabled:** the cart quantity (with the "Mettre à jour" button), the shop sort (the "Trier" button) and the deletions (no confirmation, but they still submit) all work.

Share a final set of screenshots with the user.

- [ ] **Step 8: Commit.**

```bash
git add -A public .gitignore README.md templates/partials tests/Controller/PageSmokeTest.php
git commit -m "chore(design): remove legacy CSS/JS and add a page smoke test"
```

- [ ] **Step 9: Hand over.** Tell the user that the branch is ready for review and merge (spec §4). List the follow-ups found during the work, which are outside this plan:
  - no terms-and-conditions page exists;
  - the checkout country and state lists are placeholders;
  - the password-reset email still needs inline styling;
  - the admin user search does not escape `%` and `_`;
  - the contact response delay needs the user's confirmation.
