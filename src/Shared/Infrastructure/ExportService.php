<?php

declare(strict_types=1);

namespace Shared\Infrastructure;

use Easy\Container\Attributes\Inject;
use JsonSerializable;
use Shared\Domain\ValueObjects\Email as EmailValueObject;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\LogicException;
use Traversable;

class ExportService
{
    public function __construct(
        private MailerInterface $mailer,

        #[Inject('option.mail.from.address')]
        private ?string $fromAddress = null,

        #[Inject('option.mail.from.name')]
        private ?string $fromName = null,
    ) {
    }

    /**
     * @param EmailValueObject|string $to The email address to send the export to
     * @param Traversable<int,JsonSerializable> $data The data to export
     * @return void
     * @throws InvalidArgumentException If the email could not be sent
     * @throws LogicException If the email could not be sent
     */
    public function exportToEmail(
        EmailValueObject|string $to,
        Traversable $data
    ): void {
        try {
            $email = new Email();

            if ($this->fromAddress) {
                $email->from(new Address(
                    $this->fromAddress,
                    $this->fromName ?: ''
                ));
            }

            // Set up the email
            $email
                ->to($to instanceof EmailValueObject ? $to->value : $to)
                ->subject('Export of your data')
                ->attach(
                    $this->generateCsvContent($data),
                    'export.csv',
                    'text/csv'
                );

            // Send the email
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $th) {
            // Log the error
        }
    }

    /**
     * @param Traversable<int,JsonSerializable> $list
     * @return string
     */
    private function generateCsvContent(Traversable $list): string
    {
        $headers = [];
        $rows = [];

        foreach ($list as $item) {
            $item = $this->flatten($item);
            $rows[] = $item;
            $headers = array_merge($headers, array_keys($item));
        }

        $f = fopen('php://memory', 'w');
        fputcsv($f, array_unique($headers));

        foreach ($rows as $row) {
            $data = [];

            foreach (array_unique($headers) as $header) {
                $data[] = $row[$header] ?? '';
            }

            fputcsv($f, $data);
        }

        fseek($f, 0);
        return stream_get_contents($f);
    }

    private function flatten(JsonSerializable $item, $parentKey = ''): string|array
    {
        $result = [];
        $item = $item->jsonSerialize();

        if (!is_array($item)) {
            return (string) $item;
        }

        foreach ($item as $key => $value) {
            $currentKey = empty($parentKey) ? $key : $parentKey . '_' . $key;

            if ($value instanceof JsonSerializable) {
                $sub = $this->flatten($value, $currentKey);

                if (is_array($sub)) {
                    $result = array_merge($result, $sub);
                } else {
                    $result[$currentKey] = $sub;
                }
            } else {
                $result[$currentKey] = json_encode($value);
            }
        }

        return $result;
    }
}
