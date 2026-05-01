<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\ModerationActionLog;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Enum\ModerationActionType;
use App\Domain\ValueObject\Target;
use App\Domain\Contract\PostRepositoryInterface;
use App\Domain\Contract\CommentRepositoryInterface;

final class ModerationActionLogFactory
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private CommentRepositoryInterface $commentRepository,
    ) {}

    public function create(
        Target $target,
        ModerationActionType $actionType,
        ContentStatus $previous,
        ContentStatus $new,
        ?User $moderator,
        ?string $reason = null,
        ?array $context = null
    ): ModerationActionLog {
        $log = (new ModerationActionLog())
            ->setActionType($actionType)
            ->setModerator($moderator)
            ->setReason($reason)
            ->setContext($context)
            ->setPreviousStatus($previous)
            ->setNewStatus($new);

        if ($target->isPost()) {
            $post = $this->postRepository->find($target->getId());

            if (!$post) {
                throw new \RuntimeException('Post not found for Target: '.$target);
            }

            $log->assignPost($post);
        } elseif ($target->isComment()) {
            $comment = $this->commentRepository->find($target->getId());

            if (!$comment) {
                throw new \RuntimeException('Comment not found for Target: '.$target);
            }

            $log->assignComment($comment);
        }

        return $log;
    }
}