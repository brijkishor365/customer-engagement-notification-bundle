<?php

namespace CustomerEngagementNotificationBundle\Notification\Provider\Email;

use CustomerEngagementNotificationBundle\Notification\Contract\EmailProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * SMTP email provider using Symfony Mailer.
 *
 * Sends plain HTML emails through the configured mailer transport.
 * Supports scalar placeholder substitution in the subject and body using
 * the provided $params array.
 *
 * Note: documentPath is ignored for SMTP mode; use PimcoreEmailProvider for
 * Pimcore Email Document templates.
 */
class SmtpEmailProvider implements EmailProviderInterface
{
    /**
     * SmtpEmailProvider constructor.
     *
     * @param MailerInterface $mailer Symfony Mailer transport
     * @param string $fromEmail Sender email address
     * @param string $fromName Sender display name
     * @param LoggerInterface $logger PSR-3 logger for delivery events
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string          $fromEmail,
        private readonly string          $fromName,
        private readonly LoggerInterface $logger
    ) {}

    public function sendEmail(
        string $to,
        string $subject,
        string $body,
        array $params = [],
        ?string $documentPath = null
    ): bool {
        if ($documentPath !== null) {
            $this->logger->warning('[smtp_email] documentPath is ignored in SMTP mode: {path}', [
                'path' => $documentPath,
            ]);
        }

        if (!$this->isValidEmail($to)) {
            $this->logger->error('[smtp_email] Invalid recipient email: {to}', ['to' => $to]);
            return false;
        }

        if (!$this->isValidEmail($this->fromEmail)) {
            $this->logger->error('[smtp_email] Invalid from email: {from}', ['from' => $this->fromEmail]);
            return false;
        }

        $resolvedSubject = $this->resolvePlaceholders($subject, $params);
        $resolvedBody = $this->resolvePlaceholders($body, $params);

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($to)
            ->subject($resolvedSubject)
            ->html($resolvedBody)
            ->text($this->buildTextFallback($resolvedBody));

        try {
            $this->mailer->send($email);

            $this->logger->info('[smtp_email] Email sent to {to}', ['to' => $to]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('[smtp_email] SMTP send failed to {to}: {error}', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'smtp_email';
    }

    private function resolvePlaceholders(string $value, array $params): string
    {
        foreach ($params as $key => $paramValue) {
            if (!is_scalar($paramValue) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $key)) {
                continue;
            }

            $value = str_replace('{' . $key . '}', (string) $paramValue, $value);
        }

        return $value;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function buildTextFallback(string $html): string
    {
        return trim(strip_tags($html));
    }
}
