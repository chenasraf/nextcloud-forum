<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\BBCode;
use OCA\Forum\Db\BBCodeMapper;
use Psr\Log\LoggerInterface;

class BBCodeService {
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
		// First, HTML escape the entire content to prevent XSS
		$escapedContent = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Separate BBCodes into those that parse inner content and those that don't
		$noParseInner = [];
		$parseInner = [];

		foreach ($bbCodes as $bbCode) {
			if (!$bbCode->getEnabled()) {
				continue;
			}

			if ($bbCode->getParseInner()) {
				$parseInner[] = $bbCode;
			} else {
				$noParseInner[] = $bbCode;
			}
		}

		// Storage for protected content (BBCodes that don't parse inner)
		$protectedContent = [];
		$placeholderIndex = 0;

		// First pass: Process BBCodes that don't parse inner content
		// Replace them with placeholders to protect from further processing
		foreach ($noParseInner as $bbCode) {
			$tag = $bbCode->getTag();
			$replacement = $bbCode->getReplacement();
			$params = $this->extractParameters($replacement);
			$pattern = $this->buildPattern($tag, $params);

			$escapedContent = preg_replace_callback(
				$pattern,
				function ($matches) use ($replacement, $params, $tag, &$protectedContent, &$placeholderIndex) {
					// // Convert newlines to <br /> in the content before replacing
					// $contentIndex = count($matches) - 1;
					// $matches[$contentIndex] = nl2br($matches[$contentIndex]);

					// Replace this BBCode but don't allow nested parsing
					$result = $this->replaceBBCode($matches, $replacement, $params, $tag);

					// Store the result and use a placeholder
					$placeholder = "___BBCODE_PROTECTED_{$placeholderIndex}___";
					$protectedContent[$placeholder] = $result;
					$placeholderIndex++;

					return $placeholder;
				},
				$escapedContent
			);
		}

		// Second pass: Process BBCodes that do parse inner content
		foreach ($parseInner as $bbCode) {
			$tag = $bbCode->getTag();
			$replacement = $bbCode->getReplacement();
			$params = $this->extractParameters($replacement);
			$pattern = $this->buildPattern($tag, $params);

			$escapedContent = preg_replace_callback(
				$pattern,
				function ($matches) use ($replacement, $params, $tag) {
					return $this->replaceBBCode($matches, $replacement, $params, $tag);
				},
				$escapedContent
			);
		}

		// Convert newlines to <br /> tags
		$escapedContent = nl2br($escapedContent);

		// Restore protected content
		foreach ($protectedContent as $placeholder => $content) {
			$escapedContent = str_replace($placeholder, $content, $escapedContent);
		}

		return $escapedContent;
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
	 * Build a regex pattern for matching a BBCode tag with parameters
	 *
	 * @param string $tag The BBCode tag name
	 * @param array<string> $params Array of parameter names
	 * @return string The regex pattern
	 */
	private function buildPattern(string $tag, array $params): string {
		$escapedTag = preg_quote($tag, '/');

		if (empty($params)) {
			// Simple tag without parameters: [tag]content[/tag]
			return '/\[' . $escapedTag . '\](.*?)\[\/' . $escapedTag . '\]/s';
		}

		// Tag with parameters: [tag param1="value1" param2="value2"]content[/tag]
		// Note: Content is already HTML-escaped, so quotes become &quot;, &#039;, or &apos;
		// Build pattern to capture each parameter
		$paramPattern = '';
		$isFirst = true;
		$quotePattern = '(?:&quot;|&#039;|&apos;)';

		foreach ($params as $param) {
			$escapedParam = preg_quote($param, '/');

			if ($isFirst) {
				// First parameter: if it matches the tag name, support shorthand [tag="value"]
				// Otherwise require explicit [tag param="value"]
				if ($param === $tag) {
					// Support both [color="red"] and [color color="red"]
					$paramPattern .= '(?:';
					$paramPattern .= '\s+' . $escapedParam . '=' . $quotePattern . '(.*?)' . $quotePattern;  // Explicit
					$paramPattern .= '|';
					$paramPattern .= '=' . $quotePattern . '(.*?)' . $quotePattern;  // Shorthand
					$paramPattern .= ')';
				} else {
					// Regular first parameter
					$paramPattern .= '\s+' . $escapedParam . '=' . $quotePattern . '(.*?)' . $quotePattern;
				}
				$isFirst = false;
			} else {
				// Subsequent parameters are always optional
				$paramPattern .= '(?:\s+' . $escapedParam . '=' . $quotePattern . '(.*?)' . $quotePattern . ')?';
			}
		}

		return '/\[' . $escapedTag . $paramPattern . '\](.*?)\[\/' . $escapedTag . '\]/s';
	}

	/**
	 * Replace a single BBCode match with its HTML replacement
	 *
	 * @param array<string> $matches Regex matches
	 * @param string $replacement The replacement template
	 * @param array<string> $params Array of parameter names
	 * @param string $tag The BBCode tag name
	 * @return string The replaced HTML
	 */
	private function replaceBBCode(array $matches, string $replacement, array $params, string $tag): string {
		// The content is always the last match
		$content = end($matches);

		// Start with the replacement template
		$result = $replacement;

		// Replace {content} with the actual content
		$result = str_replace('{content}', $content, $result);

		// Replace parameter placeholders with their values
		$matchIndex = 1;
		foreach ($params as $paramIndex => $param) {
			$value = '';

			// First parameter might have shorthand syntax (two capture groups)
			if ($paramIndex === 0 && $param === $tag) {
				// Check both capture groups (explicit and shorthand)
				$value = ($matches[$matchIndex] ?? '') ?: ($matches[$matchIndex + 1] ?? '');
				$matchIndex += 2; // Skip both capture groups
			} else {
				// Regular parameter
				$value = $matches[$matchIndex] ?? '';
				$matchIndex++;
			}

			// Sanitize the parameter value to prevent injection attacks
			$value = $this->sanitizeParameterValue($param, $value);

			$result = str_replace('{' . $param . '}', $value, $result);
		}

		return $result;
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
