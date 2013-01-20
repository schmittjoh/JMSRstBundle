<?php

namespace JMS\RstBundle\Model;

class File
{
    private $pathname;
    private $title;
    private $body;
    private $toc;
    private $displayToc;
    private $parents;
    private $prev;
    private $next;

    public function __construct($pathname, $title, $body, $toc, $displayToc, array $parents = null, array $prev = null, array $next = null)
    {
        $this->pathname = $pathname;
        $this->title = $title;
        $this->body = $body;
        $this->toc = $toc;
        $this->displayToc = (Boolean) $displayToc;
        $this->parents = $parents;
        $this->prev = $prev;
        $this->next = $next;
    }

    public function getPathname()
    {
        return $this->pathname;
    }

    public function getVisiblePathname()
    {
        if ('index' === $this->pathname) {
            return '/';
        }

        if ('index' === substr($this->pathname, -5)) {
            return substr($this->pathname, 0, -5);
        }

        return $this->pathname;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getToc()
    {
        return $this->toc;
    }

    public function isDisplayToc()
    {
        return $this->displayToc;
    }

    public function getParents()
    {
        return $this->parents;
    }

    public function getPrev()
    {
        return $this->prev;
    }

    public function getNext()
    {
        return $this->next;
    }
}