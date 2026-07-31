<?php

declare(strict_types=1);

namespace App\ComplexityReport\Exception;

/**
 * Rejects a submitted repository with a message that is safe to show to the submitter.
 */
final class SubmissionFailed extends \RuntimeException
{
    public static function invalidInput(string $input): self
    {
        return new self(sprintf(
            'Cannot read a GitHub repository from "%s" - please use the format vendor/repository.',
            $input
        ));
    }

    public static function unknownRepository(string $identifier): self
    {
        return new self(sprintf('There is no public repository github.com/%s.', $identifier));
    }

    public static function notSubmitted(string $identifier): self
    {
        return new self(sprintf('Repository %s is not part of the report.', $identifier));
    }

    public static function noPhpRepository(string $identifier): self
    {
        return new self(sprintf('Repository %s does not contain enough PHP code to be analysed.', $identifier));
    }

    public static function forkedRepository(string $identifier): self
    {
        return new self(sprintf('Repository %s is a fork - please submit the original repository.', $identifier));
    }

    public static function emptyRepository(string $identifier): self
    {
        return new self(sprintf('Repository %s is empty.', $identifier));
    }

    public static function gitHubUnavailable(string $identifier, \Throwable $previous): self
    {
        return new self(
            sprintf('Cannot reach GitHub to verify %s right now - please try again later.', $identifier),
            0,
            $previous
        );
    }
}
