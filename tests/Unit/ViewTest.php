<?php

declare(strict_types=1);

namespace Tests\Unit;

use Framework\View\HtmlString;
use Framework\View\View;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->view = new View(dirname(__DIR__) . '/Fixtures/views');
    }

    public function testEscapesStringsByDefault(): void
    {
        $html = $this->view->render('greeting', ['name' => '<script>alert("x")</script>'], null);

        $this->assertSame("<p>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;</p>\n", $html);
    }

    public function testEscapesNestedArrays(): void
    {
        $html = $this->view->render('list', ['items' => [['label' => 'Tom & Jerry'], ['label' => "<i>it's</i>"]]], null);

        $this->assertSame('<li>Tom &amp; Jerry</li><li>&lt;i&gt;it&#039;s&lt;/i&gt;</li>', trim($html));
    }

    public function testHtmlStringIsLeftAlone(): void
    {
        $html = $this->view->render('raw', ['html' => new HtmlString('<em>ok</em>')], null);

        $this->assertSame('<em>ok</em>|&lt;b&gt;', trim($html));
    }

    public function testWrapsContentInTheLayout(): void
    {
        $this->view->share('title', 'A "quoted" title');

        $html = $this->view->render('greeting', ['name' => 'Jean']);

        $this->assertSame("<main title=\"A &quot;quoted&quot; title\"><p>Jean</p>\n</main>\n", $html);
    }

    public function testRejectsPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->view->render('../../composer');
    }

    public function testMissingTemplateThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $this->view->render('missing', [], null);
    }
}
