<?php

namespace App\Entities\Articles;

/**
 * 文章
 */
class Article
{   
    /**
    * @var int
    */
    public $articleId = -1;

    /**
    * @var int
    */
    public $authorId = -1;

    /**
    * @var string
    */
    public $avatar = '';

    /**
    * @var string
    */
    public $title = '';

    /**
    * @var int
    */
    public $views = 0;

    /**
    * @var string
    */
    public $postTime = '';

    /**
    * @var string
    */
    public $postAddr = '';

    /**
    * @var string
    */
    public $type = '';

    /**
    * @var string
    */
    public $content = '';

    /**
    * @var string[]
    */
    public $images = [];

    /**
    * @var string
    */
    public $video = '';
}