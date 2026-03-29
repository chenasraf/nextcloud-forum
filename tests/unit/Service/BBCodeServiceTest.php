<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\BBCode;
use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Service\BBCodeService;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BBCodeServiceTest extends TestCase {
	private BBCodeService $service;
	/** @var BBCodeMapper&MockObject */
	private BBCodeMapper $bbCodeMapper;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRootFolder&MockObject */
	private IRootFolder $rootFolder;
	/** @var IURLGenerator&MockObject */
	private IURLGenerator $urlGenerator;
	/** @var IUserManager&MockObject */
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

	public function testParseYoutubeTagGeneratesCorrectIframe(): void {
		$content = '[youtube]dQw4w9WgXcQ[/youtube]';

		$result = $this->service->parse($content, []);

		$this->assertStringContainsString('<iframe', $result);
		$this->assertStringContainsString('src="https://www.youtube.com/embed/dQw4w9WgXcQ"', $result);
		$this->assertStringContainsString('allowfullscreen', $result);
		$this->assertStringContainsString('class="youtube-player"', $result);
		$this->assertStringContainsString('allow="accelerometer', $result);
	}

	public function testParseYoutubeTagEscapesVideoId(): void {
		$content = '[youtube]<script>alert("xss")</script>[/youtube]';

		$result = $this->service->parse($content, []);

		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringContainsString('&lt;script&gt;', $result);
	}

	public function testParseMultipleYoutubeTags(): void {
		$content = 'First: [youtube]abc123[/youtube] Second: [youtube]def456[/youtube]';

		$result = $this->service->parse($content, []);

		$this->assertStringContainsString('embed/abc123', $result);
		$this->assertStringContainsString('embed/def456', $result);
		$this->assertEquals(2, substr_count($result, '<iframe'));
	}

	public function testParseVideoAttachmentRendersVideoTag(): void {
		$bbCode = $this->createAttachmentBBCode();

		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getName')->willReturn('video.mp4');
		$file->method('getMimeType')->willReturn('video/mp4');
		$file->method('getSize')->willReturn(1024 * 1024);
		$file->method('getId')->willReturn(42);

		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->method('get')->willReturn($file);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/download');

		$content = '[attachment]Forum/video.mp4[/attachment]';
		$result = $this->service->parse($content, [$bbCode], 'alice', 1);

		$this->assertStringContainsString('<video', $result);
		$this->assertStringContainsString('controls', $result);
		$this->assertStringContainsString('playsinline', $result);
		$this->assertStringContainsString('<source', $result);
		$this->assertStringContainsString('type="video/mp4"', $result);
		$this->assertStringContainsString('class="attachment attachment-video"', $result);
	}

	public function testParseImageAttachmentRendersImgTag(): void {
		$bbCode = $this->createAttachmentBBCode();

		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getName')->willReturn('photo.jpg');
		$file->method('getMimeType')->willReturn('image/jpeg');
		$file->method('getSize')->willReturn(512 * 1024);
		$file->method('getId')->willReturn(43);

		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->method('get')->willReturn($file);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/preview');

		$content = '[attachment]Forum/photo.jpg[/attachment]';
		$result = $this->service->parse($content, [$bbCode], 'alice', 1);

		$this->assertStringContainsString('<img', $result);
		$this->assertStringContainsString('class="attachment attachment-image"', $result);
		$this->assertStringNotContainsString('<video', $result);
	}

	public function testParseAudioAttachmentRendersAudioTag(): void {
		$bbCode = $this->createAttachmentBBCode();

		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getName')->willReturn('podcast.mp3');
		$file->method('getMimeType')->willReturn('audio/mpeg');
		$file->method('getSize')->willReturn(5 * 1024 * 1024);
		$file->method('getId')->willReturn(44);

		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->method('get')->willReturn($file);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/download');

		$content = '[attachment]Forum/podcast.mp3[/attachment]';
		$result = $this->service->parse($content, [$bbCode], 'alice', 1);

		$this->assertStringContainsString('<audio', $result);
		$this->assertStringContainsString('controls', $result);
		$this->assertStringContainsString('<source', $result);
		$this->assertStringContainsString('type="audio/mpeg"', $result);
		$this->assertStringContainsString('class="attachment attachment-audio"', $result);
		$this->assertStringNotContainsString('<video', $result);
		$this->assertStringNotContainsString('<img', $result);
	}

	public function testParseGenericFileAttachmentRendersDownloadLink(): void {
		$bbCode = $this->createAttachmentBBCode();

		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getName')->willReturn('document.pdf');
		$file->method('getMimeType')->willReturn('application/pdf');
		$file->method('getSize')->willReturn(2 * 1024 * 1024);
		$file->method('getId')->willReturn(45);

		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->method('get')->willReturn($file);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/download');

		$content = '[attachment]Forum/document.pdf[/attachment]';
		$result = $this->service->parse($content, [$bbCode], 'alice', 1);

		$this->assertStringContainsString('class="attachment attachment-file"', $result);
		$this->assertStringContainsString('download="document.pdf"', $result);
		$this->assertStringContainsString('attachment-size', $result);
		$this->assertStringNotContainsString('<video', $result);
		$this->assertStringNotContainsString('<audio', $result);
		$this->assertStringNotContainsString('<img', $result);
	}

	public function testAttachmentTypeDispatchSelectsCorrectRenderer(): void {
		$bbCode = $this->createAttachmentBBCode();

		$types = [
			['image/png', 'attachment-image', '<img'],
			['video/webm', 'attachment-video', '<video'],
			['audio/ogg', 'attachment-audio', '<audio'],
			['application/zip', 'attachment-file', 'attachment-name'],
		];

		foreach ($types as [$mimeType, $expectedClass, $expectedElement]) {
			$file = $this->createMock(\OCP\Files\File::class);
			$file->method('getName')->willReturn('file.ext');
			$file->method('getMimeType')->willReturn($mimeType);
			$file->method('getSize')->willReturn(1024);
			$file->method('getId')->willReturn(1);

			$userFolder = $this->createMock(\OCP\Files\Folder::class);
			$userFolder->method('get')->willReturn($file);

			$rootFolder = $this->createMock(\OCP\Files\IRootFolder::class);
			$rootFolder->method('getUserFolder')->willReturn($userFolder);

			$urlGenerator = $this->createMock(\OCP\IURLGenerator::class);
			$urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/file');

			$service = new BBCodeService(
				$this->bbCodeMapper,
				$this->logger,
				$rootFolder,
				$urlGenerator,
				$this->userManager,
			);

			$result = $service->parse('[attachment]test[/attachment]', [$bbCode], 'user', 1);

			$this->assertStringContainsString($expectedClass, $result, "Failed for mime: $mimeType");
			$this->assertStringContainsString($expectedElement, $result, "Missing element for mime: $mimeType");
		}
	}

	public function testBuiltinOverrideYoutubeReplacesBrokenLibraryOutput(): void {
		// The library generates malformed HTML for youtube — our override should produce clean output
		$content = '[youtube]testVideoId[/youtube]';
		$result = $this->service->parse($content, []);

		// Should not contain the library's broken backslash in width attribute
		$this->assertStringNotContainsString('\\', $result);
		// Should contain our clean iframe
		$this->assertStringContainsString('www.youtube.com/embed/testVideoId', $result);
		$this->assertStringContainsString('allowfullscreen', $result);
	}

	public function testYoutubeEmbedWithSurroundingContent(): void {
		$content = 'Check this video: [youtube]abc123[/youtube] and this text after.';
		$result = $this->service->parse($content, []);

		$this->assertStringContainsString('Check this video:', $result);
		$this->assertStringContainsString('embed/abc123', $result);
		$this->assertStringContainsString('and this text after.', $result);
	}

	// ── XSS injection tests ─────────────────────────────────────

	public function testXssInBoldTagContentIsEscaped(): void {
		$content = '[b]<img src=x onerror=alert(1)>[/b]';
		$result = $this->service->parse($content, []);

		// Library HTML-escapes content — the tag is rendered as text, not executable HTML
		$this->assertStringContainsString('&lt;img', $result);
		$this->assertStringNotContainsString('<img src=x', $result);
	}

	public function testXssInItalicTagContentIsEscaped(): void {
		$content = '[i]<svg onload=alert(1)>[/i]';
		$result = $this->service->parse($content, []);

		$this->assertStringContainsString('&lt;svg', $result);
		$this->assertStringNotContainsString('<svg', $result);
	}

	public function testXssInUrlTagContentIsEscaped(): void {
		$content = '[url=https://example.com]<script>alert(1)</script>[/url]';
		$result = $this->service->parse($content, []);

		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringContainsString('&lt;script&gt;', $result);
	}

	public function testXssInCodeTagContentIsEscaped(): void {
		$content = '[code]<script>alert("xss")</script>[/code]';
		$result = $this->service->parse($content, []);

		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringContainsString('&lt;script&gt;', $result);
	}

	public function testXssInQuoteTagContentIsEscaped(): void {
		$content = '[quote]<iframe src="javascript:alert(1)"></iframe>[/quote]';
		$result = $this->service->parse($content, []);

		// Content is HTML-escaped inside blockquote
		$this->assertStringContainsString('&lt;iframe', $result);
		$this->assertStringNotContainsString('<iframe', $result);
	}

	public function testXssInSpoilerTagContentIsEscaped(): void {
		$content = '[spoiler]<script>document.cookie</script>[/spoiler]';
		$result = $this->service->parse($content, []);

		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringContainsString('&lt;script&gt;', $result);
	}

	public function testXssInYoutubeTagVideoIdIsEscaped(): void {
		$content = '[youtube]" onload="alert(1)" data-x="[/youtube]';
		$result = $this->service->parse($content, []);

		// Quotes are escaped via htmlspecialchars, preventing attribute breakout
		$this->assertStringContainsString('&quot;', $result);
		// The onload is inside the src attribute value (escaped), not a real attribute
		$this->assertStringNotContainsString('" onload="', $result);
	}

	public function testXssInYoutubeTagScriptIsEscaped(): void {
		$content = '[youtube]<script>alert(1)</script>[/youtube]';
		$result = $this->service->parse($content, []);

		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringContainsString('&lt;script&gt;', $result);
	}

	public function testXssInCustomTagContentIsEscaped(): void {
		$customTag = $this->createBBCode('highlight', '<mark>{content}</mark>', true, true);
		$content = '[highlight]<img src=x onerror=alert(1)>[/highlight]';
		$result = $this->service->parse($content, [$customTag]);

		// Library escapes content inside custom tags
		$this->assertStringContainsString('&lt;img', $result);
		$this->assertStringNotContainsString('<img src=x', $result);
	}

	public function testXssInCustomTagNoParseInnerIsEscaped(): void {
		$customTag = $this->createBBCode('raw', '<pre>{content}</pre>', true, false);
		$content = '[raw]<script>alert("xss")</script>[/raw]';
		$result = $this->service->parse($content, [$customTag]);

		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringContainsString('&lt;script&gt;', $result);
	}

	public function testXssDangerousProtocolsBlockedInCustomUrlParam(): void {
		$customTag = $this->createBBCode('link', '<a href="{url}">{content}</a>', true, true);

		$protocols = [
			'javascript:alert(1)',
			'vbscript:alert(1)',
			'data:text/html,<script>alert(1)</script>',
			'file:///etc/passwd',
		];

		foreach ($protocols as $protocol) {
			$content = "[link=$protocol]click[/link]";
			$result = $this->service->parse($content, [$customTag]);
			$this->assertStringContainsString('href=""', $result, "Dangerous protocol not blocked: $protocol");
		}
	}

	public function testXssCssInjectionInColorParamIsStripped(): void {
		$colorCode = $this->createBBCode('customcolor', '<span style="color:{color}">{content}</span>', true, true);
		$content = '[customcolor=red;font-weight:bold]text[/customcolor]';
		$result = $this->service->parse($content, [$colorCode]);

		$this->assertStringNotContainsString('font-weight:bold', $result);
	}

	private function createBBCode(string $tag, string $replacement, bool $enabled, bool $parseInner): BBCode {
		$bbCode = new BBCode();
		$bbCode->setTag($tag);
		$bbCode->setReplacement($replacement);
		$bbCode->setEnabled($enabled);
		$bbCode->setParseInner($parseInner);
		return $bbCode;
	}

	private function createAttachmentBBCode(): BBCode {
		$bbCode = new BBCode();
		$bbCode->setTag('attachment');
		$bbCode->setReplacement('');
		$bbCode->setEnabled(true);
		$bbCode->setParseInner(false);
		$bbCode->setSpecialHandler('attachment');
		return $bbCode;
	}
}
