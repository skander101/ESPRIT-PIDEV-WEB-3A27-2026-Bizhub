<?php

namespace App\Community\DTO;

class PostStatsDTO
{
    public int $postId;
    public int $commentCount;

    public function __construct(int $postId, int $commentCount)
    {
        $this->postId = $postId;
        $this->commentCount = $commentCount;
    }
}
