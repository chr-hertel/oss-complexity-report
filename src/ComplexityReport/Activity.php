<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use App\Entity\Tag;

/**
 * One entry of the strip closing the start page: a repository and the last thing that happened to it.
 *
 * The page used to close on two lists - the repositories somebody submitted, and the releases a worker
 * measured. They are the two ends of the same work, and read next to each other they are one line: what
 * came in. So they are the same pill here, and what differs is only what its dark half says - a state
 * while a repository is waiting for a worker, the version once one measured something.
 *
 * Waiting repositories come first and are never crowded out. A queued repository is the one thing on
 * this strip somebody is still owed an answer about; a release that arrived is done.
 */
final readonly class Activity
{
    private function __construct(
        /** The repository, `vendor/repository` - which is also what the pill links to its chart by. */
        public string $repository,
        /** What is said about it: a state while it waits, the version of the release otherwise. */
        public string $value,
        /** Whether that value is a state rather than a version - a state is set like every other label. */
        public bool $state,
        public string $title,
    ) {
    }

    /**
     * The strip: everything still waiting for a worker, then the newest releases filling what is left.
     *
     * A repository is named once. A queued repository whose first releases just arrived would otherwise
     * stand on the strip twice, saying two different things about itself.
     *
     * @param list<Repository> $submitted most recently submitted first
     * @param list<Tag>        $released  most recently measured first
     *
     * @return list<self>
     */
    public static function feed(array $submitted, array $released, int $limit): array
    {
        $feed = [];
        $named = [];

        foreach ($submitted as $repository) {
            // a repository that is measured has nothing outstanding - its releases speak for it below
            if ($repository->isAnalysed()) {
                continue;
            }

            $feed[] = self::waiting($repository);
            $named[$repository->getName()] = true;
        }

        foreach ($released as $tag) {
            $name = $tag->getRepository()->getName();

            if (\count($feed) >= $limit) {
                break;
            }

            if (isset($named[$name])) {
                continue;
            }

            $feed[] = self::released($tag);
            $named[$name] = true;
        }

        return \array_slice($feed, 0, $limit);
    }

    /**
     * A repository between being submitted and being in the report: queued while nothing was measured
     * yet, being analysed once the first releases came in.
     */
    private static function waiting(Repository $repository): self
    {
        return $repository->hasData()
            ? new self(
                $repository->getName(),
                'analysing',
                true,
                sprintf('%s is being measured right now - %d releases so far', $repository->getName(), $repository->getReleaseCount()),
            )
            : new self(
                $repository->getName(),
                'queued',
                true,
                sprintf('%s is waiting for a worker', $repository->getName()),
            );
    }

    private static function released(Tag $tag): self
    {
        return new self(
            $tag->getRepository()->getName(),
            $tag->getName(),
            false,
            sprintf(
                '%s %s, tagged %s',
                $tag->getRepository()->getName(),
                $tag->getName(),
                $tag->getCreated()->format('F j, Y'),
            ),
        );
    }
}
