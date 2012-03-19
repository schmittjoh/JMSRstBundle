<?php

namespace JMS\RstBundle\Model;

class Project
{
    private $files = array();

    public function addFile(File $file)
    {
        $this->files[$file->getPath()] = $file;
    }

    public function getFiles()
    {
        return $this->files;
    }
}