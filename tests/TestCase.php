<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O Symfony envia "Accept-Language: en-us" por padrão; os testes simulam o visitante brasileiro.
        // Os testes de idioma (LocalizationTest) sobrescrevem esse cabeçalho.
        $this->withHeader('Accept-Language', 'pt-BR');
    }
}
