<?php

namespace JMS\RstBundle\Generator;

interface PathAware
{
    public function setCurrentPathname($pathname);
}