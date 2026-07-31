<?php

declare(strict_types=1);

namespace App\ComplexityReport\Exception;

/**
 * Rejects a submitted repository with a message that is safe to show to the submitter.
 */
final class SubmissionFailed extends \RuntimeException
{
    /**
     * How much of a rejected input is quoted back. Enough to recognise what was pasted - the message ends
     * up in a flash, and whatever was sent is not worth carrying through a session for.
     */
    private const int QUOTED_INPUT_LENGTH = 120;

    public static function invalidInput(string $input): self
    {
        return new self(sprintf(
            'Cannot read a GitHub repository from "%s" - please use the format owner/repository.',
            self::quote($input)
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

    public static function oversizedRepository(string $identifier, int $sizeInKilobytes, int $limitInKilobytes): self
    {
        return new self(sprintf(
            'Repository %s is %d MB and too large to analyse - the limit is %d MB.',
            $identifier,
            intdiv($sizeInKilobytes, 1024),
            intdiv($limitInKilobytes, 1024)
        ));
    }

    public static function gitHubUnavailable(string $identifier, \Throwable $previous): self
    {
        return new self(
            sprintf('Cannot reach GitHub to verify %s right now - please try again later.', $identifier),
            0,
            $previous
        );
    }

    public static function tooManySubmissions(): self
    {
        return new self('The report is taking in more repositories than it can measure right now - please try again later.');
    }

    private static function quote(string $input): string
    {
        if (mb_strlen($input) <= self::QUOTED_INPUT_LENGTH) {
            return $input;
        }

        return mb_substr($input, 0, self::QUOTED_INPUT_LENGTH).'...';
    }
}
