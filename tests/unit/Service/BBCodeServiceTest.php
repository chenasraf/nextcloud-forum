<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\BBCode;
use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Service\BBCodeService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BBCodeServiceTest extends TestCase {
	private BBCodeService $service;
	private BBCodeMapper $bbCodeMapper;
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->bbCodeMapper = $this->createMock(BBCodeMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new BBCodeService(
			$this->bbCodeMapper,
			$this->logger
		);
	}

	public function testParseSimpleBBCodeWithoutParameters(): void {
		$bbCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$content = 'This is [b]bold text[/b].';
		$expected = 'This is <strong>bold text</strong>.';

		$result = $this->service->parse($content, [$bbCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseMultipleBBCodes(): void {
		$boldCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$italicCode = $this->createBBCode('i', '<em>{content}</em>', true, true);
		$content = 'This is [b]bold[/b] and [i]italic[/i] text.';
		$expected = 'This is <strong>bold</strong> and <em>italic</em> text.';

		$result = $this->service->parse($content, [$boldCode, $italicCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseBBCodeWithParameters(): void {
		$urlCode = $this->createBBCode('url', '<a href="{url}">{content}</a>', true, true);
		$content = 'Click [url url="https://example.com"]here[/url].';
		$expected = 'Click <a href="https://example.com">here</a>.';

		$result = $this->service->parse($content, [$urlCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseEscapesHTMLToPreventXSS(): void {
		$bbCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$content = 'This is [b]<script>alert("XSS")</script>[/b].';
		$expected = 'This is <strong>&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;</strong>.';

		$result = $this->service->parse($content, [$bbCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseConvertsNewlinesToBr(): void {
		$bbCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$content = "Line 1\nLine 2\n[b]Bold line[/b]";

		$result = $this->service->parse($content, [$bbCode]);

		$this->assertStringContainsString('Line 1<br />', $result);
		$this->assertStringContainsString('Line 2<br />', $result);
		$this->assertStringContainsString('<strong>Bold line</strong>', $result);
	}

	public function testParseNestedBBCodesWithParseInner(): void {
		$boldCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$italicCode = $this->createBBCode('i', '<em>{content}</em>', true, true);
		$content = '[b]Bold [i]and italic[/i][/b]';

		$result = $this->service->parse($content, [$boldCode, $italicCode]);

		$this->assertStringContainsString('<strong>Bold <em>and italic</em></strong>', $result);
	}

	public function testParseIgnoresDisabledBBCodes(): void {
		$enabledCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$disabledCode = $this->createBBCode('i', '<em>{content}</em>', false, true);
		$content = 'This is [b]bold[/b] and [i]not italic[/i].';
		$expected = 'This is <strong>bold</strong> and [i]not italic[/i].';

		$result = $this->service->parse($content, [$enabledCode, $disabledCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseBBCodeWithNoParseInner(): void {
		$codeBlock = $this->createBBCode('code', '<pre><code>{content}</code></pre>', true, false);
		$content = '[code]function test() { return true; }[/code]';

		$result = $this->service->parse($content, [$codeBlock]);

		$this->assertStringContainsString('<pre><code>function test() { return true; }</code></pre>', $result);
	}

	public function testParseNoParseInnerProtectsFromFurtherProcessing(): void {
		$codeBlock = $this->createBBCode('code', '<pre><code>{content}</code></pre>', true, false);
		$boldCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$content = '[code][b]This should not be bold[/b][/code]';

		$result = $this->service->parse($content, [$codeBlock, $boldCode]);

		// The [b] tag inside [code] should not be processed
		$this->assertStringContainsString('[b]This should not be bold[/b]', $result);
		$this->assertStringNotContainsString('<strong>', $result);
	}

	public function testParseWithEmptyContent(): void {
		$bbCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$content = '';
		$expected = '';

		$result = $this->service->parse($content, [$bbCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseWithNoMatchingBBCodes(): void {
		$bbCode = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$content = 'Plain text without any BBCode tags.';
		$expected = 'Plain text without any BBCode tags.';

		$result = $this->service->parse($content, [$bbCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseWithEmbeddedBBCodeWithParameters(): void {
		$colorCode = $this->createBBCode('color', '<span style="color: {color}">{content}</span>', true, true);
		$content = 'This is [color color="red"]red text[/color].';
		$expected = 'This is <span style="color: red">red text</span>.';

		$result = $this->service->parse($content, [$colorCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseWithMultipleParametersInSameTag(): void {
		$spanCode = $this->createBBCode('span', '<span style="color: {color}; font-size: {size}px">{content}</span>', true, true);
		$content = '[span color="blue" size="20"]Blue text[/span]';
		$expected = '<span style="color: blue; font-size: 20px">Blue text</span>';

		$result = $this->service->parse($content, [$spanCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseWithEnabledLoadsAllEnabledBBCodes(): void {
		$bbCode1 = $this->createBBCode('b', '<strong>{content}</strong>', true, true);
		$bbCode2 = $this->createBBCode('i', '<em>{content}</em>', true, true);

		$this->bbCodeMapper->expects($this->once())
			->method('findAllEnabled')
			->willReturn([$bbCode1, $bbCode2]);

		$content = '[b]Bold[/b] and [i]Italic[/i]';
		$result = $this->service->parseWithEnabled($content);

		$this->assertStringContainsString('<strong>Bold</strong>', $result);
		$this->assertStringContainsString('<em>Italic</em>', $result);
	}

	public function testParseHandlesSpecialCharactersInParameters(): void {
		$urlCode = $this->createBBCode('url', '<a href="{url}">{content}</a>', true, true);
		$content = '[url url="https://example.com/page?param=value&other=test"]Link[/url]';

		$result = $this->service->parse($content, [$urlCode]);

		$this->assertStringContainsString('https://example.com/page?param=value&amp;other=test', $result);
	}

	public function testParseWithQuotesInParameters(): void {
		$urlCode = $this->createBBCode('url', '<a href="{url}">{content}</a>', true, true);

		// Test with double quotes
		$content1 = '[url url="https://example.com"]Link[/url]';
		$result1 = $this->service->parse($content1, [$urlCode]);
		$this->assertStringContainsString('<a href="https://example.com">Link</a>', $result1);

		// Test with single quotes
		$content2 = "[url url='https://example.com']Link[/url]";
		$result2 = $this->service->parse($content2, [$urlCode]);
		$this->assertStringContainsString('<a href="https://example.com">Link</a>', $result2);
	}

	public function testParseWithShorthandSyntaxWhenParameterMatchesTag(): void {
		// When parameter name matches tag name, support shorthand [color="red"]
		$colorCode = $this->createBBCode('color', '<span style="color:{color}">{content}</span>', true, true);
		$content = '[color="red"]Red text[/color]';
		$expected = '<span style="color:red">Red text</span>';

		$result = $this->service->parse($content, [$colorCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseWithExplicitSyntaxStillWorksWhenParameterMatchesTag(): void {
		// Explicit syntax should still work: [color color="red"]
		$colorCode = $this->createBBCode('color', '<span style="color:{color}">{content}</span>', true, true);
		$content = '[color color="blue"]Blue text[/color]';
		$expected = '<span style="color:blue">Blue text</span>';

		$result = $this->service->parse($content, [$colorCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseShorthandDoesNotApplyWhenParameterDoesNotMatchTag(): void {
		// If parameter doesn't match tag name, shorthand should not work
		$urlCode = $this->createBBCode('link', '<a href="{url}">{content}</a>', true, true);
		// This should NOT match because parameter is "url" but tag is "link"
		$content = '[link="https://example.com"]Link[/link]';

		$result = $this->service->parse($content, [$urlCode]);

		// Should not be parsed because shorthand doesn't apply
		$this->assertStringContainsString('[link=', $result);
	}

	public function testParseShorthandWithMultipleColors(): void {
		$colorCode = $this->createBBCode('color', '<span style="color:{color}">{content}</span>', true, true);
		$content = 'This is [color="red"]red[/color] and [color="green"]green[/color] text.';
		$expected = 'This is <span style="color:red">red</span> and <span style="color:green">green</span> text.';

		$result = $this->service->parse($content, [$colorCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParsePreventsCSS_InjectionInColorParameter(): void {
		// Attempt to inject additional CSS through color parameter
		$colorCode = $this->createBBCode('color', '<span style="color:{color}">{content}</span>', true, true);
		$content = '[color="red;font-weight:bold"]text[/color]';

		$result = $this->service->parse($content, [$colorCode]);

		// Should not contain the injected font-weight style
		$this->assertStringNotContainsString('font-weight:bold', $result);
		// Should strip the injection and only use valid color or remove the tag entirely
		$this->assertStringNotContainsString('style="color:red;font-weight:bold"', $result);
	}

	public function testParsePreventsJavaScriptInjectionInURLParameter(): void {
		// Attempt to inject JavaScript through URL parameter
		$urlCode = $this->createBBCode('url', '<a href="{url}">{content}</a>', true, true);
		$content = '[url url="javascript:alert(\'XSS\')"]Click me[/url]';

		$result = $this->service->parse($content, [$urlCode]);

		// Should not contain javascript: protocol
		$this->assertStringNotContainsString('javascript:', $result);
	}

	public function testParseAllowsValidColorValues(): void {
		$colorCode = $this->createBBCode('color', '<span style="color:{color}">{content}</span>', true, true);

		// Test various valid color formats
		$tests = [
			'[color="red"]text[/color]' => 'red',
			'[color="#ff0000"]text[/color]' => '#ff0000',
			'[color="#f00"]text[/color]' => '#f00',
			'[color="rgb(255, 0, 0)"]text[/color]' => 'rgb(255, 0, 0)',
			'[color="rgba(255, 0, 0, 0.5)"]text[/color]' => 'rgba(255, 0, 0, 0.5)',
			'[color="hsl(0, 100%, 50%)"]text[/color]' => 'hsl(0, 100%, 50%)',
		];

		foreach ($tests as $input => $expectedColor) {
			$result = $this->service->parse($input, [$colorCode]);
			$this->assertStringContainsString("color:$expectedColor", $result, "Failed for: $input");
		}
	}

	private function createBBCode(string $tag, string $replacement, bool $enabled, bool $parseInner): BBCode {
		$bbCode = new BBCode();
		$bbCode->setTag($tag);
		$bbCode->setReplacement($replacement);
		$bbCode->setEnabled($enabled);
		$bbCode->setParseInner($parseInner);
		return $bbCode;
	}
}
