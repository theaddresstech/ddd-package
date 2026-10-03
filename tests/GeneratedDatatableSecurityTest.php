<?php

namespace Tests;

use Tests\Support\GeneratedApplication;

class GeneratedDatatableSecurityTest extends GeneratedApplication
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!trait_exists(\Src\Infrastructure\Traits\BuilderParameters::class, false)) {
            $this->loadStub('Infrastructure/Traits/BuilderParameters.stub');
        }
    }

    public function test_database_option_values_stay_text_in_both_html_and_javascript(): void
    {
        $builder = new class { use \Src\Infrastructure\Traits\BuilderParameters; };
        $payload = "</script><img src=x onerror=alert(1)>');alert(2);//";
        $script = $builder->getJsStr([], [['index_num' => 0, 'selectValues' => [$payload => $payload]]], false);
        $this->assertStringNotContainsString('</script>', $script);
        $this->assertStringNotContainsString('<img', $script);
        $this->assertSame(1, preg_match('/\$\(select\)\.html\((.*)\);/', $script, $matches));
        $html = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $document = new \DOMDocument();
        $document->loadHTML('<select>'.$html.'</select>');
        $option = $document->getElementsByTagName('option')->item(1);
        $this->assertSame($payload, $option->textContent);
        $this->assertSame($payload, $option->getAttribute('value'));
        $this->assertSame(0, $document->getElementsByTagName('img')->length);
    }

    public function test_column_indices_cannot_be_javascript_expressions(): void
    {
        $builder = new class { use \Src\Infrastructure\Traits\BuilderParameters; };
        $this->expectException(\InvalidArgumentException::class);
        $builder->getJsStr(['0]);alert(1);//'], [], false);
    }
}
