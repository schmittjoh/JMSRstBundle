<?php

namespace JMS\RstBundle\Model;

class Project
{
    /** @var File[] */
    private $files = array();

    public function addFile(File $file)
    {
        $this->files[$file->getPathname()] = $file;
    }

    public function getFiles()
    {
        return $this->files;
    }
}