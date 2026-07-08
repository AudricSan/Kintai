<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\Mail\NativeTransport;
use kintai\Core\Mail\SmtpTransport;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

final class MailTestController
{
    public function __construct(
        private readonly ViewRenderer $view,
    ) {}

    public function show(Request $request): Response
    {
        return Response::html($this->view->render('system.mail-test', [
            'title'      => 'Test d\'envoi de mail',
            'mailConfig' => $this->loadConfig(),
            'phpIni'     => $this->phpIniInfo(),
            'result'     => null,
            'last_to'    => '',
        ], 'layout.app'));
    }

    public function send(Request $request): Response
    {
        $to = trim((string) $request->post('to', ''));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $result = ['success' => false, 'error' => 'Adresse e-mail invalide.'];
        } else {
            $result = $this->attemptSend($to);
        }

        return Response::html($this->view->render('system.mail-test', [
            'title'      => 'Test d\'envoi de mail',
            'mailConfig' => $this->loadConfig(),
            'phpIni'     => $this->phpIniInfo(),
            'result'     => $result,
            'last_to'    => $to,
        ], 'layout.app'));
    }

    private function attemptSend(string $to): array
    {
        $config      = $this->loadConfig();
        $fromAddress = $config['from']['address'] ?? 'noreply@example.com';
        $fromName    = $config['from']['name']    ?? 'Kintai';
        $subject     = '[Kintai] Mail de test — ' . date('Y-m-d H:i:s');
        $body        = '<p>Ceci est un mail de test envoyé depuis Kintai.<br>'
                     . 'Si vous recevez ce message, la configuration mail est correcte.</p>'
                     . '<p><small>Envoyé le ' . date('d/m/Y à H:i:s') . ' via le driver <strong>'
                     . htmlspecialchars($config['driver'] ?? 'native') . '</strong>.</small></p>';

        if (($config['driver'] ?? 'native') === 'smtp') {
            $smtp      = $config['smtp'] ?? [];
            $transport = new SmtpTransport(
                host:       (string) ($smtp['host']       ?? 'localhost'),
                port:       (int)    ($smtp['port']       ?? 587),
                username:   (string) ($smtp['username']   ?? ''),
                password:   (string) ($smtp['password']   ?? ''),
                encryption: (string) ($smtp['encryption'] ?? 'tls'),
            );

            $ok = $transport->send($fromAddress, $fromName, [$to], $subject, $body);

            if ($ok) {
                return ['success' => true];
            }

            $err = $transport->getLastError() ?? 'Échec SMTP — consultez le journal PHP pour les détails.';
            return ['success' => false, 'error' => $err];
        }

        // Driver natif (mail())
        error_clear_last();
        $transport = new NativeTransport();
        $ok        = $transport->send($fromAddress, $fromName, [$to], $subject, $body);

        if ($ok) {
            return ['success' => true];
        }

        $err = error_get_last()['message'] ?? 'Échec mail() — consultez le journal PHP pour les détails.';
        return ['success' => false, 'error' => $err];
    }

    private function loadConfig(): array
    {
        $file = defined('BASE_PATH') ? BASE_PATH . '/config/mail.php' : '';
        return ($file !== '' && file_exists($file)) ? (require $file) : [];
    }

    private function phpIniInfo(): array
    {
        return [
            'SMTP'          => ini_get('SMTP')          ?: '(non défini)',
            'smtp_port'     => ini_get('smtp_port')     ?: '(non défini)',
            'sendmail_path' => ini_get('sendmail_path') ?: '(non défini)',
        ];
    }
}
