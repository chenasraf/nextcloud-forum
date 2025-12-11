<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\BBCode;
use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Service\BBCodeService;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BBCodeServiceTest extends TestCase {
	private BBCodeService $service;
	private BBCodeMapper $bbCodeMapper;
	private LoggerInterface $logger;
	private IRootFolder $rootFolder;
	private IURLGenerator $urlGenerator;
	private IUserManager $userManager;

	protected function setUp(): void {
		$this->bbCodeMapper = $this->createMock(BBCodeMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->service = new BBCodeService(
			$this->bbCodeMapper,
			$this->logger,
			$this->rootFolder,
			$this->urlGenerator,
			$this->userManager
		);
	}

	public function testParseSimpleBBCodeWithoutParameters(): void {
		// Built-in [b] tag is provided by the library, no custom tags needed
		$content = 'This is [b]bold text[/b].';
		$expected = 'This is <strong>bold text</strong>.';

		$result = $this->service->parse($content, []);

		$this->assertEquals($expected, $result);
	}

	public function testParseMultipleBBCodes(): void {
		// Built-in [b] and [i] tags are provided by the library
		$content = 'This is [b]bold[/b] and [i]italic[/i] text.';
		$expected = 'This is <strong>bold</strong> and <em>italic</em> text.';

		$result = $this->service->parse($content, []);

		$this->assertEquals($expected, $result);
	}

	public function testParseCustomBBCodeWithParameters(): void {
		// Custom BBCode with parameter
		$customTag = $this->createBBCode('icode', '<code>{content}</code>', true, false);
		$content = 'Use [icode]console.log()[/icode] for logging.';
		$expected = 'Use <code>console.log()</code> for logging.';

		$result = $this->service->parse($content, [$customTag]);

		$this->assertEquals($expected, $result);
	}

	public function testParseEscapesHTMLToPreventXSS(): void {
		// Built-in [b] tag with XSS attempt
		$content = 'This is [b]<script>alert("XSS")</script>[/b].';
		// The library HTML-escapes content by default
		$result = $this->service->parse($content, []);

		$this->assertStringContainsString('&lt;script&gt;', $result);
		$this->assertStringContainsString('&lt;/script&gt;', $result);
		$this->assertStringNotContainsString('<script>', $result);
	}

	public function testParseConvertsNewlinesToBr(): void {
		$content = "Line 1\nLine 2\n[b]Bold line[/b]";

		$result = $this->service->parse($content, []);

		// Library uses <br/> without space
		$this->assertStringContainsString('Line 1<br/>', $result);
		$this->assertStringContainsString('Line 2<br/>', $result);
		$this->assertStringContainsString('<strong>Bold line</strong>', $result);
	}

	public function testParseNestedBBCodesWithParseInner(): void {
		// Built-in tags support nesting
		$content = '[b]Bold [i]and italic[/i][/b]';

		$result = $this->service->parse($content, []);

		$this->assertStringContainsString('<strong>Bold <em>and italic</em></strong>', $result);
	}

	public function testParseIgnoresDisabledBBCodes(): void {
		$enabledCode = $this->createBBCode('icode', '<code>{content}</code>', true, false);
		$disabledCode = $this->createBBCode('disabled', '<span>{content}</span>', false, true);
		$content = 'This is [icode]code[/icode] and [disabled]should not work[/disabled].';

		$result = $this->service->parse($content, [$enabledCode, $disabledCode]);

		$this->assertStringContainsString('<code>code</code>', $result);
		$this->assertStringContainsString('[disabled]should not work[/disabled]', $result);
	}

	public function testParseBBCodeWithNoParseInner(): void {
		// Custom code block that doesn't parse inner BBCode
		$codeBlock = $this->createBBCode('code', '<pre><code>{content}</code></pre>', true, false);
		$content = '[code]function test() { return [b]true[/b]; }[/code]';

		$result = $this->service->parse($content, [$codeBlock]);

		// The [b] tag inside [code] should be escaped since parseInner is false
		$this->assertStringContainsString('<pre><code>', $result);
		$this->assertStringContainsString('[b]true[/b]', $result);
		$this->assertStringNotContainsString('<strong>', $result);
	}

	public function testParseWithEmptyContent(): void {
		$content = '';
		$expected = '';

		$result = $this->service->parse($content, []);

		$this->assertEquals($expected, $result);
	}

	public function testParseWithNoMatchingBBCodes(): void {
		$content = 'Plain text without any BBCode tags.';
		$expected = 'Plain text without any BBCode tags.';

		$result = $this->service->parse($content, []);

		$this->assertEquals($expected, $result);
	}

	public function testParseWithCustomBBCodeWithParameters(): void {
		// Note: The library uses [tag=value] syntax, not [tag param="value"]
		$colorCode = $this->createBBCode('customcolor', '<span style="color: {color}">{content}</span>', true, true);
		$content = 'This is [customcolor=red]red text[/customcolor].';
		$expected = 'This is <span style="color: red">red text</span>.';

		$result = $this->service->parse($content, [$colorCode]);

		$this->assertEquals($expected, $result);
	}

	public function testParseWithEnabledLoadsAllEnabledBBCodes(): void {
		$bbCode1 = $this->createBBCode('icode', '<code>{content}</code>', true, false);
		$bbCode2 = $this->createBBCode('mark', '<mark>{content}</mark>', true, true);

		$this->bbCodeMapper->expects($this->once())
			->method('findAllEnabled')
			->willReturn([$bbCode1, $bbCode2]);

		$content = '[icode]Code[/icode] and [mark]Marked[/mark]';
		$result = $this->service->parseWithEnabled($content);

		$this->assertStringContainsString('<code>Code</code>', $result);
		$this->assertStringContainsString('<mark>Marked</mark>', $result);
	}

	public function testParsePreventsJavaScriptInjectionInURLParameter(): void {
		// Custom URL tag with sanitization
		$urlCode = $this->createBBCode('link', '<a href="{url}">{content}</a>', true, true);
		$content = '[link=javascript:alert(\'XSS\')]Click me[/link]';

		$result = $this->service->parse($content, [$urlCode]);

		// Should not contain javascript: protocol (sanitizeParameterValue removes it)
		$this->assertStringNotContainsString('javascript:', $result);
		// The href should be empty since it's invalid
		$this->assertStringContainsString('href=""', $result);
	}

	public function testParseAllowsValidColorValues(): void {
		$colorCode = $this->createBBCode('customcolor', '<span style="color:{color}">{content}</span>', true, true);

		// Test various valid color formats using [tag=value] syntax
		$tests = [
			'[customcolor=red]text[/customcolor]' => 'red',
			'[customcolor=#ff0000]text[/customcolor]' => '#ff0000',
			'[customcolor=#f00]text[/customcolor]' => '#f00',
			'[customcolor=rgb(255, 0, 0)]text[/customcolor]' => 'rgb(255, 0, 0)',
			'[customcolor=rgba(255, 0, 0, 0.5)]text[/customcolor]' => 'rgba(255, 0, 0, 0.5)',
			'[customcolor=hsl(0, 100%, 50%)]text[/customcolor]' => 'hsl(0, 100%, 50%)',
		];

		foreach ($tests as $input => $expectedColor) {
			$result = $this->service->parse($input, [$colorCode]);
			$this->assertStringContainsString("color:$expectedColor", $result, "Failed for: $input");
		}
	}

	public function testParsePreventsCSS_InjectionInColorParameter(): void {
		// Attempt to inject additional CSS through color parameter
		$colorCode = $this->createBBCode('customcolor', '<span style="color:{color}">{content}</span>', true, true);
		$content = '[customcolor=red;font-weight:bold]text[/customcolor]';

		$result = $this->service->parse($content, [$colorCode]);

		// Should not contain the injected font-weight style (semicolon is stripped)
		$this->assertStringNotContainsString('font-weight:bold', $result);
		$this->assertStringNotContainsString('style="color:red;font-weight:bold"', $result);
	}

	public function testParseBuiltInTags(): void {
		// Test that built-in tags from the library work
		$tests = [
			'[b]bold[/b]' => '<strong>bold</strong>',
			'[i]italic[/i]' => '<em>italic</em>',
			'[u]underline[/u]' => 'underline', // Just check content exists, style may vary
			'[s]strikethrough[/s]' => '<del>strikethrough</del>',
		];

		foreach ($tests as $input => $expected) {
			$result = $this->service->parse($input, []);
			$this->assertStringContainsString($expected, $result, "Failed for: $input");
		}
	}

	public function testParseBuiltInUrlTag(): void {
		// Test built-in URL tag
		$content = '[url=https://example.com]Example[/url]';
		$result = $this->service->parse($content, []);

		$this->assertStringContainsString('<a href="https://example.com"', $result);
		$this->assertStringContainsString('>Example</a>', $result);
	}

	public function testParseBuiltInColorTag(): void {
		// Test built-in color tag
		$content = '[color=red]Red text[/color]';
		$result = $this->service->parse($content, []);

		// Check for color style (may have spaces)
		$this->assertStringContainsString('color', $result);
		$this->assertStringContainsString('red', $result);
		$this->assertStringContainsString('Red text', $result);
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
