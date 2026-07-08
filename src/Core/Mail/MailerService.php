<?php

declare(strict_types=1);

namespace kintai\Core\Mail;

/**
 * Facade mail : choisit le transport (native ou SMTP) selon config/mail.php.
 * Injectée dans tous les services qui envoient des mails.
 */
final class MailerService
{
    private readonly string $fromAddress;
    private readonly string $fromName;
    private readonly SmtpTransport|NativeTransport $transport;

    public function __construct(array $config)
    {
        $this->fromAddress = $config['from']['address'] ?? 'noreply@example.com';
        $this->fromName    = $config['from']['name']    ?? 'Kintai';

        if (($config['driver'] ?? 'native') === 'smtp') {
            $smtp = $config['smtp'] ?? [];
            $this->transport = new SmtpTransport(
                host:       (string) ($smtp['host']       ?? 'localhost'),
                port:       (int)    ($smtp['port']       ?? 587),
                username:   (string) ($smtp['username']   ?? ''),
                password:   (string) ($smtp['password']   ?? ''),
                encryption: (string) ($smtp['encryption'] ?? 'tls'),
            );
        } else {
            $this->transport = new NativeTransport();
        }
    }

    /**
     * @param string[] $to
     * @param string[] $attachmentPaths  Chemins absolus vers les fichiers à joindre
     */
    public function send(array $to, string $subject, string $htmlBody, array $attachmentPaths = []): bool
    {
        $validTo = array_values(array_filter($to, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false));
        if (empty($validTo)) {
            return false;
        }

        return $this->transport->send(
            fromAddress:     $this->fromAddress,
            fromName:        $this->fromName,
            to:              $validTo,
            subject:         $subject,
            htmlBody:        $htmlBody,
            attachmentPaths: $attachmentPaths,
        );
    }
}
