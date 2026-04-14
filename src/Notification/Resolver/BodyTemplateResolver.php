<?php

namespace CustomerEngagementNotificationBundle\Notification\Resolver;

/**
 * Resolves {placeholder} tokens inside body templates and header values.
 *
 * Built-in placeholders:
 *   {to}      — recipient phone number
 *   {message} — SMS body text
 *
 * Extended placeholders:
 *   Any key passed in $context is also available as {key}.
 *   e.g. context ['sender' => 'CEP'] makes {sender} available.
 *
 * Works recursively on nested arrays so complex body structures resolve correctly.
 */
class BodyTemplateResolver
{
    /**
     * Resolve placeholders in a flat or nested array of template values.
     *
     * @param  array  $template The body or headers template
     * @param  string $to       Recipient phone number
     * @param  string $message  SMS message body
     * @param  array  $context  Additional context key/value pairs
     * @return array            Resolved array ready to send
     */
    public function resolve(array $template, string $to, string $message, array $context = []): array
    {
        $placeholders = $this->buildPlaceholders($to, $message, $context);

        return $this->replaceInArray($template, $placeholders);
    }

    /**
     * Resolve placeholders in a raw string (used for 'raw' body format).
     */
    public function resolveString(string $template, string $to, string $message, array $context = []): string
    {
        $placeholders = $this->buildPlaceholders($to, $message, $context);

        return strtr($template, $placeholders);
    }

    // ── Private ────────────────────────────────────────────────────────────

    /** @return array<string, string> map of {token} => value */
    private function buildPlaceholders(string $to, string $message, array $context): array
    {
        $placeholders = [
            '{to}'      => $to,
            '{message}' => $message,
        ];

        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $placeholders['{' . $key . '}'] = (string) $value;
            }
        }

        return $placeholders;
    }

    private function replaceInArray(array $data, array $placeholders): array
    {
        $resolved = [];

        foreach ($data as $key => $value) {
            // Validate key - must be scalar type
            if (!is_scalar($key)) {
                // Skip non-scalar keys (should not happen in normal usage)
                continue;
            }

            // Resolve key if it's a string
            $resolvedKey = is_string($key) ? strtr($key, $placeholders) : $key;

            if (is_array($value)) {
                $resolved[$resolvedKey] = $this->replaceInArray($value, $placeholders);
            } elseif (is_string($value)) {
                $resolved[$resolvedKey] = strtr($value, $placeholders);
            } else {
                // int, bool, null, float — pass through unchanged
                $resolved[$resolvedKey] = $value;
            }
        }

        return $resolved;
    }
}
