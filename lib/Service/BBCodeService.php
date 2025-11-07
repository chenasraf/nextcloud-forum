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
				function ($matches) use ($replacement, $params, &$protectedContent, &$placeholderIndex) {
					// Replace this BBCode but don't allow nested parsing
					$result = $this->replaceBBCode($matches, $replacement, $params);

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
				function ($matches) use ($replacement, $params) {
					return $this->replaceBBCode($matches, $replacement, $params);
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
		// Build pattern to capture each parameter
		$paramPattern = '';
		foreach ($params as $param) {
			$escapedParam = preg_quote($param, '/');
			// Match: param="value" or param='value'
			$paramPattern .= '(?:.*?' . $escapedParam . '=["\']([^"\']*)["\'])?';
		}

		return '/\[' . $escapedTag . $paramPattern . '.*?\](.*?)\[\/' . $escapedTag . '\]/s';
	}

	/**
	 * Replace a single BBCode match with its HTML replacement
	 *
	 * @param array<string> $matches Regex matches
	 * @param string $replacement The replacement template
	 * @param array<string> $params Array of parameter names
	 * @return string The replaced HTML
	 */
	private function replaceBBCode(array $matches, string $replacement, array $params): string {
		// The content is always the last match
		$content = end($matches);

		// Start with the replacement template
		$result = $replacement;

		// Replace {content} with the actual content
		$result = str_replace('{content}', $content, $result);

		// Replace parameter placeholders with their values
		foreach ($params as $index => $param) {
			// Parameter values are in matches starting from index 1
			$value = $matches[$index + 1] ?? '';
			$result = str_replace('{' . $param . '}', $value, $result);
		}

		return $result;
	}
}
