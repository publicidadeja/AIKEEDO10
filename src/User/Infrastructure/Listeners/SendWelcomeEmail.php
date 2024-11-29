<?php

declare(strict_types=1);

namespace User\Infrastructure\Listeners;

use Easy\Container\Attributes\Inject;
use Option\Infrastructure\OptionResolver;
use Presentation\Resources\Api\UserResource;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;
use Twig\Environment;
use User\Domain\Events\EmailVerifiedEvent;
use User\Domain\Events\UserCreatedEvent;

class SendWelcomeEmail
{
    /**
     * @param MailerInterface $mailer 
     * @param Environment $twig 
     * @param OptionResolver $optionResolver 
     * @param null|string $fromAddress 
     * @param null|string $fromName 
     * @param null|string $siteName 
     * @return void 
     */
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private OptionResolver $optionResolver,

        #[Inject('option.mail.from.address')]
        private ?string $fromAddress = null,

        #[Inject('option.mail.from.name')]
        private ?string $fromName = null,

        #[Inject('option.site.name')]
        private ?string $siteName = null,

        #[Inject('option.site.email_verification_policy')]
        private ?string $policy = null,
    ) {
    }

    /**
     * @param UserCreatedEvent $event
     * @return void
     */
    public function __invoke(UserCreatedEvent|EmailVerifiedEvent $event)
    {
        $user = $event->user;

        if (
            $this->policy == 'strict'
            && !$user->isEmailVerified()->value
        ) {
            // Skip sending welcome email if email verification is required
            // Welcome email will be sent after email verification
            return;
        }

        if (
            $this->policy == 'relaxed'
            && $user->isEmailVerified()->value
        ) {
            // Welcome email has already been sent
            return;
        }

        try {
            $email = new Email;

            if ($this->fromAddress) {
                $email->from(new Address(
                    $this->fromAddress,
                    $this->fromName ?: ''
                ));
            }

            $data = ['user' => new UserResource($user)];
            $data = array_merge($data, $this->optionResolver->getOptionMap());

            $email
                ->to($user->getEmail()->value)
                ->subject($this->siteName ? sprintf('Welcome to our %s!', $this->siteName) : 'Thank you for signing up!')
                ->html($this->twig->render('@emails/welcome.twig', $data));

            $this->mailer->send($email);
        } catch (Throwable $th) {
            // Catch exception here to avoid breaking the application
        }
    }
}
