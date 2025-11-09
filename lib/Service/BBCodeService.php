<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use ChrisKonnertz\BBCode\BBCode as BBCodeParser;
use OCA\Forum\Db\BBCode;
use OCA\Forum\Db\BBCodeMapper;
use Psr\Log\LoggerInterface;

class BBCodeService {
	private ?BBCodeParser $parser = null;

	public function __construct(
		private BBCodeMapper $bbCodeMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Parse content with BBCode tags
	 *
	 * @param string $content The content to parse
	 * @param array<BBCode> $bbCodes Array of BBCode entities to use for parsing
	 * @return string The parsed content with BBCodes replaced by HTML
	 */
	public function parse(string $content, array $bbCodes): string {
		$parser = $this->getParser($bbCodes);

		// Preprocess [code] blocks to prevent nl2br and trim whitespace
		// The built-in [code] tag wraps content in <pre><code>, so we don't want <br/> tags inside
		$codePlaceholders = [];
		$content = preg_replace_callback('/\[code\](.*?)\[\/code\]/s', function ($matches) use (&$codePlaceholders) {
			$placeholder = '___CODE_BLOCK_' . count($codePlaceholders) . '___';
			// Trim leading and trailing newlines, then HTML-escape
			$innerContent = trim($matches[1], "\r\n");
			$innerContent = htmlspecialchars($innerContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$codePlaceholders[$placeholder] = '<pre><code>' . $innerContent . '</code></pre>';
			return $placeholder;
		}, $content);

		// Preprocess disabled tags - escape them so they appear as literal text
		$disabledPlaceholders = [];
		foreach ($bbCodes as $bbCode) {
			if ($bbCode->getEnabled()) {
				continue;
			}

			$tag = $bbCode->getTag();
			// Match [tag]content[/tag] or [tag=param]content[/tag]
			$pattern = '/\[' . preg_quote($tag, '/') . '(=[^\]]+)?\](.*?)\[\/' . preg_quote($tag, '/') . '\]/s';

			$content = preg_replace_callback($pattern, function ($matches) use (&$disabledPlaceholders, $tag) {
				$placeholder = '___DISABLED_BBCODE_' . count($disabledPlaceholders) . '___';
				// Store the original tag text to restore it after parsing
				$disabledPlaceholders[$placeholder] = $matches[0];
				return $placeholder;
			}, $content);
		}

		// Preprocess tags with parseInner = false
		// We need to protect their content from being parsed
		$placeholders = [];
		foreach ($bbCodes as $bbCode) {
			if (!$bbCode->getEnabled() || $bbCode->getParseInner()) {
				continue;
			}

			$tag = $bbCode->getTag();
			// Match [tag]content[/tag] or [tag=param]content[/tag]
			$pattern = '/\[' . preg_quote($tag, '/') . '(?:=[^\]]+)?\](.*?)\[\/' . preg_quote($tag, '/') . '\]/s';

			$content = preg_replace_callback($pattern, function ($matches) use (&$placeholders, $bbCode) {
				$placeholder = '___BBCODE_PLACEHOLDER_' . count($placeholders) . '___';

				// Process the tag manually without parsing inner content
				$tag = $bbCode->getTag();
				$replacement = $bbCode->getReplacement();
				$innerContent = $matches[1];

				// If the replacement wraps in <pre>, trim leading/trailing newlines
				if (stripos($replacement, '<pre>') !== false) {
					$innerContent = trim($innerContent, "\r\n");
				}

				// HTML-escape the inner content to prevent any HTML injection
				$innerContent = htmlspecialchars($innerContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

				// Replace {content} with the escaped inner content
				$html = str_replace('{content}', $innerContent, $replacement);

				// Extract and process parameters if present
				$params = $this->extractParameters($replacement);
				foreach ($params as $param) {
					// Extract parameter value from the opening tag
					preg_match('/\[' . preg_quote($tag, '/') . '=([^\]]+)\]/', $matches[0], $paramMatches);
					$value = $paramMatches[1] ?? '';
					$value = $this->sanitizeParameterValue($param, $value);
					$html = str_replace('{' . $param . '}', $value, $html);
				}

				$placeholders[$placeholder] = $html;
				return $placeholder;
			}, $content);
		}

		// Render BBCode
		// Note: The library's render() method has $escape = true and $keepLines = true by default
		// which handles HTML escaping and newline conversion
		try {
			$html = $parser->render($content);

			// Replace code block placeholders (must be done before other placeholders to avoid double-escaping)
			foreach ($codePlaceholders as $placeholder => $replacement) {
				$html = str_replace($placeholder, $replacement, $html);
			}

			// Replace placeholders back
			foreach ($placeholders as $placeholder => $replacement) {
				$html = str_replace($placeholder, $replacement, $html);
			}

			// Restore disabled tags as literal text
			foreach ($disabledPlaceholders as $placeholder => $original) {
				$html = str_replace($placeholder, htmlspecialchars($original, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $html);
			}

			return $html;
		} catch (\Exception $e) {
			$this->logger->error('BBCode parsing error: ' . $e->getMessage());
			// Return escaped content as fallback
			return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
	}

	/**
	 * Parse content using all enabled BBCodes from the database
	 *
	 * @param string $content The content to parse
	 * @return string The parsed content with BBCodes replaced by HTML
	 */
	public function parseWithEnabled(string $content): string {
		$bbCodes = $this->bbCodeMapper->findAllEnabled();
		return $this->parse($content, $bbCodes);
	}

	/**
	 * Get or create the BBCode parser instance with custom tags
	 *
	 * @param array<BBCode> $bbCodes Array of custom BBCode entities
	 * @return BBCodeParser
	 */
	private function getParser(array $bbCodes): BBCodeParser {
		// Create a new parser instance each time to ensure fresh state
		$parser = new BBCodeParser();

		// Register custom BBCodes from database
		foreach ($bbCodes as $bbCode) {
			// Skip disabled tags (handled in preprocessing)
			if (!$bbCode->getEnabled()) {
				continue;
			}

			// Tags with parseInner = false are handled in preprocessing, don't register them
			if (!$bbCode->getParseInner()) {
				continue;
			}

			$tag = $bbCode->getTag();
			$replacement = $bbCode->getReplacement();

			// Extract the opening and closing HTML from the replacement template
			// Our templates use {content} as a placeholder, e.g., "<code>{content}</code>"
			$parts = explode('{content}', $replacement);
			$openingHtml = $parts[0] ?? '';
			$closingHtml = $parts[1] ?? '';

			// Extract parameters from replacement template
			$params = $this->extractParameters($replacement);

			// Add the custom tag
			$parser->addTag($tag, function ($tagObj, &$html, $openingTag) use ($openingHtml, $closingHtml, $params) {
				if ($tagObj->opening) {
					// Opening tag - process parameters and return opening HTML
					$result = $openingHtml;

					// Replace parameters using the property value from the opening tag
					foreach ($params as $param) {
						// For opening tags, use $tagObj->property; $openingTag is null here
						$value = $tagObj->property ?? '';
						// Sanitize parameter value
						$value = $this->sanitizeParameterValue($param, $value);
						$result = str_replace('{' . $param . '}', $value, $result);
					}

					return $result;
				} else {
					// Closing tag - return closing HTML
					return $closingHtml;
				}
			});
		}

		return $parser;
	}

	/**
	 * Extract parameter names from a replacement template
	 * Returns array of parameter names (excluding 'content')
	 *
	 * @param string $replacement The replacement template
	 * @return array<string> Array of parameter names
	 */
	private function extractParameters(string $replacement): array {
		$params = [];
		// Match all {param} patterns
		if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $replacement, $matches)) {
			foreach ($matches[1] as $param) {
				if ($param !== 'content') {
					$params[] = $param;
				}
			}
		}
		return array_unique($params);
	}

	/**
	 * Sanitize a parameter value to prevent XSS/injection attacks
	 *
	 * @param string $paramName The parameter name
	 * @param string $value The parameter value
	 * @return string The sanitized value (empty string if invalid)
	 */
	private function sanitizeParameterValue(string $paramName, string $value): string {
		// Trim whitespace
		$value = trim($value);

		// Parameter-specific validation
		switch ($paramName) {
			case 'color':
				// Validate color values - only allow valid CSS colors
				// Hex colors: #RGB or #RRGGBB
				if (preg_match('/^#[0-9a-fA-F]{3}$/', $value) || preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
					return $value;
				}
				// Named colors (basic validation - alphanumeric only)
				if (preg_match('/^[a-z]+$/i', $value)) {
					return $value;
				}
				// RGB/RGBA: rgb(r, g, b) or rgba(r, g, b, a)
				if (preg_match('/^rgba?\s*\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[\d.]+\s*)?\)$/i', $value)) {
					return $value;
				}
				// HSL/HSLA: hsl(h, s%, l%) or hsla(h, s%, l%, a)
				if (preg_match('/^hsla?\s*\(\s*\d+\s*,\s*\d+%\s*,\s*\d+%\s*(?:,\s*[\d.]+\s*)?\)$/i', $value)) {
					return $value;
				}
				// Invalid color - return empty to remove the attribute
				return '';

			case 'url':
			case 'href':
			case 'src':
				// Block dangerous protocols
				$dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:', 'about:'];
				foreach ($dangerousProtocols as $protocol) {
					if (stripos($value, $protocol) === 0) {
						return ''; // Invalid URL - return empty
					}
				}
				// Only allow http://, https://, //, or relative paths
				if (preg_match('/^(https?:\/\/|\/\/|\/|[a-z0-9.-]+)/i', $value)) {
					return $value;
				}
				return '';

			default:
				// For unknown parameters, strip any characters that could break out of HTML attributes
				// Remove quotes, angle brackets, and other dangerous characters
				$value = str_replace(['"', "'", '<', '>', '`', '\\'], '', $value);
				// Also remove semicolons to prevent CSS injection in style attributes
				$value = str_replace(';', '', $value);
				return $value;
		}
	}
}
