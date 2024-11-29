<?php

declare(strict_types=1);

namespace Workspace\Infrastructure\Listeners;

use Easy\Container\Attributes\Inject;
use Laminas\Diactoros\ServerRequestFactory;
use Option\Infrastructure\OptionResolver;
use Presentation\Resources\Api\WorkspaceResource;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;
use Twig\Environment;
use Workspace\Domain\Events\InvitationCreatedEvent;

class SendInvitationEmail
{
    public function __construct(
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private Environment $twig,
        private OptionResolver $optionResolver,

        #[Inject('option.mail.from.address')]
        private ?string $fromAddress = null,

        #[Inject('option.mail.from.name')]
        private ?string $fromName = null,

        #[Inject('option.site.name')]
        private ?string $siteName = null,
    ) {
    }

    public function __invoke(InvitationCreatedEvent $event)
    {
        $inv = $event->invitation;
        $ws = $inv->getWorkspace();

        try {
            $email = new Email();

            if ($this->fromAddress) {
                $email->from(new Address(
                    $this->fromAddress,
                    $this->fromName ?: ''
                ));
            }

            // Create accept invitation url 
            $path = '/app/workspace'
                . '/' . (string) $ws->getId()->getValue()
                . '/invitations'
                . '/' . $inv->getId()->getValue();

            $req = ServerRequestFactory::fromGlobals();
            $uri = $req->getUri()
                ->withPath($path)
                ->withQuery('')
                ->withFragment('');

            $data = [
                'email' => $inv->getEmail()->value,
                'workspace' => new WorkspaceResource($ws),
                'accept_invitation_url' => (string) $uri,
            ];

            $data = array_merge($data, $this->optionResolver->getOptionMap());

            $email
                ->to($inv->getEmail()->value)
                ->subject(
                    $this->siteName ?
                        sprintf('You were invited to the workspace %s on %s', $ws->getName()->value, $this->siteName)
                        : sprintf('You were invited to the workspace %s', $ws->getName()->value)
                )
                ->html($this->twig->render('@emails/workspace-invitation.twig', $data));

            $this->mailer->send($email);
        } catch (Throwable $th) {
            // Log error
            $this->logger->error(
                $th->getMessage(),
                [
                    'exception' => $th,
                    'invitation' => $inv->getId()->getValue(),
                    'workspace' => $ws->getId()->getValue(),
                ]
            );
        }
    }
}
