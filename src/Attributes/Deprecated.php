<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Attributes;

use Attribute;

/**
 * Attribute to mark a handler as deprecated.
 *
 * @example
 * ```php
 * #[ExtensionHandler(UserCreated::class)]
 * #[Deprecated(
 *     reason: 'Use NewWelcomeEmailHandler instead',
 *     since: '2.0.0',
 *     removeIn: '3.0.0'
 * )]
 * class OldWelcomeEmailHandler { }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Deprecated
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $since = null,
        public readonly ?string $removeIn = null,
        public readonly ?string $replacement = null,
    ) {}

    /**
     * Get a formatted deprecation message.
     */
    public function getMessage(string $handlerClass): string
    {
        $message = "Handler [{$handlerClass}] is deprecated";

        if ($this->since !== null) {
            $message .= " since {$this->since}";
        }

        if ($this->removeIn !== null) {
            $message .= " and will be removed in {$this->removeIn}";
        }

        $message .= ". {$this->reason}";

        if ($this->replacement !== null) {
            $message .= " Use [{$this->replacement}] instead.";
        }

        return $message;
    }
}
