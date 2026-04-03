<?php

namespace App\Services;

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ImapConnection;
use App\Exceptions\Imap\AuthenticationException;
use App\Exceptions\Imap\ImapConnectionException;
use App\Models\ImapSetting;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class WebklexImapConnectionService implements ImapConnectionService
{
    public function __construct(
        protected ClientManager $clientManager,
        protected int $timeout = 10,
    ) {}

    public function testConnection(
        string $hostname,
        int $port,
        string $username,
        string $password,
        ?string $encryption,
    ): bool {
        $client = $this->makeClient($hostname, $port, $username, $password, $encryption);

        try {
            $client->connect();

            return true;
        } catch (Throwable $exception) {
            throw $this->mapException($exception);
        } finally {
            $this->safeDisconnect($client);
        }
    }

    public function connect(ImapSetting $settings): ImapConnection
    {
        $client = $this->makeClient(
            $settings->hostname,
            $settings->port,
            $settings->username,
            $settings->decrypted_password,
            $settings->encryption,
        );

        try {
            $client->connect();

            return new ImapConnection($client, $settings);
        } catch (Throwable $exception) {
            $this->safeDisconnect($client);

            throw $this->mapException($exception);
        }
    }

    public function getFolders(ImapConnection $connection): array
    {
        try {
            return collect($this->client($connection)->getFolders(false, null, true))
                ->map(fn ($folder) => $folder->path ?? $folder->name ?? null)
                ->filter(fn (?string $folder): bool => filled($folder))
                ->values()
                ->all();
        } catch (Throwable $exception) {
            throw $this->mapException($exception);
        }
    }

    public function getMessageIds(ImapConnection $connection, string $folder): array
    {
        try {
            $messages = $this->queryFolder($connection, $folder)
                ->all()
                ->setFetchBody(false)
                ->setFetchFlags(false)
                ->get();

            return collect($messages)
                ->map(fn ($message) => $this->stringValue($message->getMessageId()))
                ->filter(fn (?string $messageId): bool => filled($messageId))
                ->values()
                ->all();
        } catch (Throwable $exception) {
            throw $this->mapException($exception);
        }
    }

    public function getEmail(ImapConnection $connection, string $messageId): EmailData
    {
        try {
            foreach ($this->getFolders($connection) as $folder) {
                $message = $this->queryFolder($connection, $folder)
                    ->messageId($messageId)
                    ->setFetchBody(true)
                    ->setFetchFlags(false)
                    ->get()
                    ->first();

                if ($message !== null) {
                    return new EmailData(
                        messageId: $this->stringValue($message->getMessageId()) ?? $messageId,
                        fromAddress: $this->firstAddress($message->getFrom())['address'] ?? '',
                        fromName: $this->firstAddress($message->getFrom())['name'] ?? null,
                        toAddresses: $this->normalizeAddresses($message->getTo()),
                        ccAddresses: $this->normalizeAddresses($message->getCc()),
                        subject: $this->stringValue($message->getSubject()) ?? '',
                        date: $this->normalizeDate($message->getDate()),
                        bodyText: $this->nullableString($message->getTextBody()),
                        bodyHtml: $this->nullableString($message->getHTMLBody()),
                        attachments: $this->normalizeAttachments($message->getAttachments()),
                    );
                }
            }
        } catch (ImapConnectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->mapException($exception);
        }

        throw new ImapConnectionException("Email with message ID [{$messageId}] was not found.");
    }

    protected function makeClient(
        string $hostname,
        int $port,
        string $username,
        string $password,
        ?string $encryption,
    ): object {
        return $this->clientManager->make([
            'host' => $hostname,
            'port' => $port,
            'protocol' => 'imap',
            'encryption' => $this->normalizeEncryption($encryption),
            'validate_cert' => true,
            'username' => $username,
            'password' => $password,
            'timeout' => $this->timeout,
        ]);
    }

    protected function client(ImapConnection $connection): object
    {
        if (! is_object($connection->resource)) {
            throw new ImapConnectionException('The IMAP connection resource is invalid.');
        }

        return $connection->resource;
    }

    protected function queryFolder(ImapConnection $connection, string $folder): object
    {
        $folderInstance = $this->client($connection)->getFolder($folder);

        if ($folderInstance === null) {
            throw new ImapConnectionException("Folder [{$folder}] was not found.");
        }

        return $folderInstance->messages();
    }

    /**
     * @return array<int, array{address:string, name:?string}>
     */
    protected function normalizeAddresses(mixed $addresses): array
    {
        if ($addresses === null) {
            return [];
        }

        if (is_object($addresses) && method_exists($addresses, 'toArray')) {
            $addresses = $addresses->toArray();
        }

        if ($addresses instanceof \Traversable) {
            $addresses = iterator_to_array($addresses);
        }

        if (! is_array($addresses)) {
            $addresses = [$addresses];
        }

        return collect($addresses)
            ->map(function (mixed $address): ?array {
                if (is_object($address) && method_exists($address, 'toArray')) {
                    $address = $address->toArray();
                }

                if (is_string($address)) {
                    return ['address' => $address, 'name' => null];
                }

                if (is_object($address)) {
                    $address = get_object_vars($address);
                }

                if (! is_array($address)) {
                    return null;
                }

                $email = $address['address'] ?? $address['mail'] ?? $address['email'] ?? null;
                $name = $address['name'] ?? $address['personal'] ?? null;

                if (! filled($email)) {
                    return null;
                }

                return [
                    'address' => (string) $email,
                    'name' => filled($name) ? (string) $name : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{address:string, name:?string}|null
     */
    protected function firstAddress(mixed $addresses): ?array
    {
        return $this->normalizeAddresses($addresses)[0] ?? null;
    }

    /**
     * @return array<int, array{filename:?string, filetype:?string}>
     */
    protected function normalizeAttachments(mixed $attachments): array
    {
        if ($attachments === null) {
            return [];
        }

        if ($attachments instanceof \Traversable) {
            $attachments = iterator_to_array($attachments);
        }

        if (is_object($attachments) && method_exists($attachments, 'toArray')) {
            $attachments = $attachments->toArray();
        }

        if (! is_array($attachments)) {
            $attachments = [$attachments];
        }

        return collect($attachments)
            ->map(function (mixed $attachment): ?array {
                if (is_object($attachment)) {
                    $filename = $this->nullableString(
                        $attachment->getFilename()
                            ?? $attachment->getName()
                            ?? $attachment->filename
                            ?? $attachment->name
                            ?? null,
                    );

                    $filetype = $this->nullableString(
                        $attachment->getContentType()
                            ?? $attachment->content_type
                            ?? $attachment->type
                            ?? null,
                    );
                } elseif (is_array($attachment)) {
                    $filename = $this->nullableString($attachment['filename'] ?? $attachment['name'] ?? null);
                    $filetype = $this->nullableString($attachment['filetype'] ?? $attachment['content_type'] ?? $attachment['type'] ?? null);
                } else {
                    return null;
                }

                if ($filename === null && $filetype === null) {
                    return null;
                }

                return [
                    'filename' => $filename,
                    'filetype' => $filetype,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeDate(mixed $date): DateTimeInterface
    {
        if ($date instanceof DateTimeInterface) {
            return $date;
        }

        if (is_object($date) && method_exists($date, 'toDate')) {
            $date = $date->toDate();

            if ($date instanceof DateTimeInterface) {
                return $date;
            }
        }

        return CarbonImmutable::parse((string) $date);
    }

    protected function normalizeEncryption(?string $encryption): string
    {
        return $encryption ?? 'none';
    }

    protected function nullableString(mixed $value): ?string
    {
        $string = $this->stringValue($value);

        return filled($string) ? $string : null;
    }

    protected function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'toString')) {
            $value = $value->toString();
        } elseif (is_object($value) && method_exists($value, 'first')) {
            $value = $value->first();
        }

        return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))
            ? trim((string) $value)
            : null;
    }

    protected function safeDisconnect(object $client): void
    {
        if (method_exists($client, 'disconnect')) {
            $client->disconnect();
        }
    }

    protected function mapException(Throwable $exception): ImapConnectionException
    {
        if ($exception instanceof ImapConnectionException) {
            return $exception;
        }

        if ($exception instanceof AuthFailedException) {
            return new AuthenticationException(
                'Invalid username or password. Please check your credentials.',
                previous: $exception,
            );
        }

        if ($exception instanceof ConnectionFailedException || str_contains(strtolower($exception->getMessage()), 'ssl') || str_contains(strtolower($exception->getMessage()), 'tls') || str_contains(strtolower($exception->getMessage()), 'certificate')) {
            $message = str_contains(strtolower($exception->getMessage()), 'ssl')
                || str_contains(strtolower($exception->getMessage()), 'tls')
                || str_contains(strtolower($exception->getMessage()), 'certificate')
                ? 'Secure connection failed. Please verify SSL/TLS settings.'
                : 'Unable to connect to mail server. Please check hostname and port.';

            return new ImapConnectionException($message, previous: $exception);
        }

        return new ImapConnectionException($exception->getMessage(), previous: $exception);
    }
}
