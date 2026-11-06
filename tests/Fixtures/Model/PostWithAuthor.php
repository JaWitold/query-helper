<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests\Fixtures\Model;

final class PostWithAuthor
{
    public function __construct(
        private readonly PostSummary $post,
        private readonly UserSummary $author,
    ) {
    }

    public function getPost(): PostSummary
    {
        return $this->post;
    }

    public function getAuthor(): UserSummary
    {
        return $this->author;
    }
}
