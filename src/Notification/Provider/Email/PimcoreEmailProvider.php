<?php

namespace CustomerEngagementNotificationBundle\Notification\Provider\Email;

use CustomerEngagementNotificationBundle\Notification\Contract\EmailProviderInterface;
use Pimcore\Mail;
use Pimcore\Model\Document\Email as EmailDocument;
use Psr\Log\LoggerInterface;

/**
 * Pimcore email provider — supports two email delivery modes:
 *
 * MODE A — Pimcore Email Document (recommended for branded templates)
 *   Pass a $documentPath like '/emails/order-shipped'.
 *   Pimcore loads the email document, injects $params as Twig variables, and
 *   renders the final HTML/text body from the document template.
 *
 * MODE B — Plain HTML fallback for simple or system-generated emails.
 *   No $documentPath → sends raw $body HTML directly with optional
 *   {placeholder} substitution from $params.
 *
 * This provider is configured with a sender email and name via DI.
 */
class PimcoreEmailProvider implements EmailProviderInterface
{
    /**
     * PimcoreEmailProvider constructor.
     *
     * @param string $fromEmail Sender address used for outgoing emails
     * @param string $fromName Sender display name used for outgoing emails
     * @param LoggerInterface $logger PSR-3 logger for recording delivery events
     */
    public function __construct(
        private readonly string          $fromEmail,
        private readonly string          $fromName,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Send an email using either a Pimcore Email Document or plain HTML mode.
     *
     * @param string $to Recipient email address
     * @param string $subject Subject line or fallback subject for document mode
     * @param string $body HTML body used for plain mode only
     * @param array $params Context vars for document template or placeholder substitution
     * @param string|null $documentPath Optional Pimcore email document path for document mode
     * @return bool True on successful send, false on failure
     */
    public function sendEmail(
        string  $to,
        string  $subject,
        string  $body,
        array   $params       = [],
        ?string $documentPath = null
    ): bool {
        try {
            $mail = $documentPath !== null
                ? $this->buildDocumentMail($to, $subject, $params, $documentPath)
                : $this->buildPlainMail($to, $subject, $body, $params);

            $mail->send();

            $this->logger->info('[pimcore_email] Sent to {to} via {mode}', [
                'to'   => $to,
                'mode' => $documentPath !== null ? 'document:' . $documentPath : 'plain',
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->logger->error('[pimcore_email] Failed to send to {to}: {error}', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Returns the provider identifier.
     *
     * @return string Provider name used for logging and metrics
     */
    public function getProviderName(): string
    {
        return 'pimcore_email';
    }

    /**
     * Load a Pimcore Email Document and inject Twig variables safely.
     *
     * @param string $to Recipient email address
     * @param string $fallbackSubject Subject fallback if the document has no subject
     * @param array $params Template variables for the document
     * @param string $documentPath Path to the Pimcore email document
     * @return Mail Configured Pimcore mail instance
     * @throws \InvalidArgumentException When the document path is invalid
     * @throws \RuntimeException When the email document cannot be loaded or is invalid
     */
    private function buildDocumentMail(
        string $to,
        string $fallbackSubject,
        array  $params,
        string $documentPath
    ): Mail {
        // Validate path - prevent directory traversal
        if (str_contains($documentPath, '..') || !str_starts_with($documentPath, '/')) {
            throw new \InvalidArgumentException(sprintf(
                'PimcoreEmailProvider: invalid document path "%s".',
                $documentPath
            ));
        }

        $document = EmailDocument::getByPath($documentPath);

        if ($document === null) {
            throw new \RuntimeException(sprintf(
                'PimcoreEmailProvider: email document not found at "%s".',
                $documentPath
            ));
        }

        if (!$document instanceof EmailDocument) {
            throw new \RuntimeException(sprintf(
                'PimcoreEmailProvider: "%s" is not an email document.',
                $documentPath
            ));
        }

        if (!$document->isPublished()) {
            throw new \RuntimeException(sprintf(
                'PimcoreEmailProvider: email document "%s" is not published.',
                $documentPath
            ));
        }

        $mail = new Mail();
        $mail->setDocument($document);

        // Validate and inject params as Twig variables (SECURITY: only allow scalar values)
        $this->injectSafeParams($mail, $params);

        $mail->to($to);
        $mail->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail));

        // Use fallback subject only if the Pimcore document has no subject defined
        if ($fallbackSubject !== '' && trim((string) $document->getSubject()) === '') {
            $mail->subject($fallbackSubject);
        }

        return $mail;
    }

    /**
     * Inject parameters safely into a Pimcore Mail document.
     *
     * Only scalar parameter values are allowed. Non-scalar values are skipped
     * and logged to avoid dangerous template injection paths.
     *
     * @param Mail $mail Mail document instance to populate
     * @param array $params Template params to inject
     */
    private function injectSafeParams(Mail $mail, array $params): void
    {
        foreach ($params as $key => $value) {
            // Validate key (alphanumeric + underscore only)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $key)) {
                $this->logger->warning('[pimcore_email] Skipping invalid param key: {key}', ['key' => $key]);
                continue;
            }

            // Only allow scalar values (string, int, float, bool) — no objects/arrays
            if (!is_scalar($value)) {
                $this->logger->warning('[pimcore_email] Skipping non-scalar param: {key}', ['key' => $key]);
                continue;
            }

            // For string values, escape HTML entities to prevent injection
            if (is_string($value)) {
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
            }

            $mail->setParam($key, $value);
        }
    }

    /**
     * Build a plain HTML Mail fallback when no document path is provided.
     *
     * @param string $to Recipient email address
     * @param string $subject Subject line with placeholder substitution
     * @param string $body HTML body with placeholder substitution
     * @param array $params Replacement values for placeholders
     * @return Mail Configured Pimcore mail instance
     */
    private function buildPlainMail(
        string $to,
        string $subject,
        string $body,
        array  $params
    ): Mail {
        // Validate and escape params before substitution (SECURITY)
        $safeParams = $this->validateAndEscapeParams($params);

        foreach ($safeParams as $key => $value) {
            $body    = str_replace('{' . $key . '}', $value, $body);
            $subject = str_replace('{' . $key . '}', $value, $subject);
        }

        $mail = new Mail();
        $mail->to($to);
        $mail->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail));
        $mail->subject($subject);
        $mail->html($body);

        return $mail;
    }

    /**
     * Validate and escape placeholder parameters used in plain HTML mode.
     *
     * @param array $params Input values to validate and escape
     * @return array<string,string> Escaped scalar values keyed by placeholder name
     */
    private function validateAndEscapeParams(array $params): array
    {
        $safe = [];

        foreach ($params as $key => $value) {
            // Validate key
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $key)) {
                continue;
            }

            // Only allow scalar values
            if (!is_scalar($value)) {
                $this->logger->warning('[pimcore_email] Skipping non-scalar param: {key}', ['key' => $key]);
                continue;
            }

            // Convert to string and escape HTML
            $safe[$key] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }

        return $safe;
    }
}
