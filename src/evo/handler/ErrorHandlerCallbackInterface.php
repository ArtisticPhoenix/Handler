<?php
namespace evo\handler;

interface ErrorHandlerCallbackInterface
{
    /**
     * @param int $severity
     * @param string $message
     * @param string|null $source
     * @param string|null $file
     * @param int|null $line
     * @param string|null $trace
     * @return bool
     */
    public static function errorHandlerCallback(
        int $severity,
        #[\SensitiveParameter] string $message,
        #[\SensitiveParameter] ?string $source=null,
        #[\SensitiveParameter] ?string $file=null,
        #[\SensitiveParameter] ?int $line=null,
        #[\SensitiveParameter] ?string $trace=null
    ): bool;
}