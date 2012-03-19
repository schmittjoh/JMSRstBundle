<?php

namespace JMS\RstBundle\Model;

class File
{
    private $path;
    private $title;
    private $body;
    private $toc;
    private $displayToc;
    private $name;
    private $parents;
    private $prev;
    private $next;

    public function __construct($path, $title, $body, $toc, $displayToc, $name, array $parents = null, array $prev = null, array $next = null)
    {
        $this->path = $path;
        $this->title = $title;
        $this->body = $body;
        $this->toc = $toc;
        $this->displayToc = (Boolean) $displayToc;
        $this->name = $name;
        $this->parents = $parents;
        $this->prev = $prev;
        $this->next = $next;
    }

    public function getPath()
    {
        return $this->path;
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

    public function getName()
    {
        return $this->name;
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