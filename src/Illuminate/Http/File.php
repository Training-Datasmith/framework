<?php

declare (strict_types=1);
namespace Illuminate\Http;

use Symfony\Component\Http_Foundation\File\File as SymfonyFile;
class File extends Symfony_File
{
    use File_Helpers;
}