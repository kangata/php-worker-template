<?php

namespace App;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as Validation;

class Validator
{
    private $factory;

    public function __construct()
    {
        $this->factory = new Validation(
            $this->loadTranslator()
        );
    }

    private function loadTranslator()
    {
        $filesystem = new Filesystem();

        $loader = new FileLoader(
            $filesystem, __DIR__.'/lang'
        );

        $loader->addNamespace(
            'lang', __DIR__.'/lang'
        );

        $loader->load('en', 'validation', 'lang');

        return new Translator($loader, 'en');
    }

    public function __call($method, $args)
    {
        return call_user_func_array(
            [$this->factory, $method],
            $args
        );
    }
}
