<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Parse search queries with support for:
 * - Quoted phrases: "exact match"
 * - Boolean operators: AND, OR
 * - Parentheses for grouping: (term1 OR term2) AND term3
 * - Exclusions: -excluded_term
 */
class QueryParser {
	private const TOKEN_WORD = 'WORD';
	private const TOKEN_PHRASE = 'PHRASE';
	private const TOKEN_AND = 'AND';
	private const TOKEN_OR = 'OR';
	private const TOKEN_LEFT_PAREN = 'LEFT_PAREN';
	private const TOKEN_RIGHT_PAREN = 'RIGHT_PAREN';
	private const TOKEN_EXCLUDE = 'EXCLUDE';
	private const TOKEN_EOF = 'EOF';

	private array $tokens = [];
	private int $position = 0;

	/**
	 * Parse a search query and return a WHERE expression for QueryBuilder
	 *
	 * @param IQueryBuilder $qb QueryBuilder instance
	 * @param string $query Search query string
	 * @param string $field Database field to search in (e.g., 'title', 'content')
	 * @return ICompositeExpression|null WHERE expression, or null for empty query
	 */
	public function parse(IQueryBuilder $qb, string $query, string $field): ?ICompositeExpression {
		$query = trim($query);
		if (empty($query)) {
			return null;
		}

		$this->tokens = $this->tokenize($query);
		$this->position = 0;

		return $this->parseExpression($qb, $field);
	}

	/**
	 * Tokenize the search query into an array of tokens
	 *
	 * @param string $query Search query
	 * @return array<array{type: string, value: string}>
	 */
	private function tokenize(string $query): array {
		$tokens = [];
		$length = strlen($query);
		$i = 0;

		while ($i < $length) {
			// Skip whitespace
			if (ctype_space($query[$i])) {
				$i++;
				continue;
			}

			// Quoted phrase
			if ($query[$i] === '"') {
				$i++; // Skip opening quote
				$phrase = '';
				while ($i < $length && $query[$i] !== '"') {
					if ($query[$i] === '\\' && $i + 1 < $length && $query[$i + 1] === '"') {
						// Escaped quote
						$phrase .= '"';
						$i += 2;
					} else {
						$phrase .= $query[$i];
						$i++;
					}
				}
				$i++; // Skip closing quote
				$tokens[] = ['type' => self::TOKEN_PHRASE, 'value' => $phrase];
				continue;
			}

			// Left parenthesis
			if ($query[$i] === '(') {
				$tokens[] = ['type' => self::TOKEN_LEFT_PAREN, 'value' => '('];
				$i++;
				continue;
			}

			// Right parenthesis
			if ($query[$i] === ')') {
				$tokens[] = ['type' => self::TOKEN_RIGHT_PAREN, 'value' => ')'];
				$i++;
				continue;
			}

			// Word or operator
			$word = '';
			while ($i < $length && !ctype_space($query[$i]) && !in_array($query[$i], ['(', ')', '"'])) {
				$word .= $query[$i];
				$i++;
			}

			if (!empty($word)) {
				// Check for exclusion
				if ($word[0] === '-' && strlen($word) > 1) {
					$tokens[] = ['type' => self::TOKEN_EXCLUDE, 'value' => substr($word, 1)];
				} elseif (strtoupper($word) === 'AND') {
					$tokens[] = ['type' => self::TOKEN_AND, 'value' => 'AND'];
				} elseif (strtoupper($word) === 'OR') {
					$tokens[] = ['type' => self::TOKEN_OR, 'value' => 'OR'];
				} else {
					$tokens[] = ['type' => self::TOKEN_WORD, 'value' => $word];
				}
			}
		}

		$tokens[] = ['type' => self::TOKEN_EOF, 'value' => ''];
		return $tokens;
	}

	/**
	 * Parse an expression (handles AND/OR operators)
	 */
	private function parseExpression(IQueryBuilder $qb, string $field): ?ICompositeExpression {
		$left = $this->parseTerm($qb, $field);
		if ($left === null) {
			return null;
		}

		while ($this->currentToken()['type'] === self::TOKEN_AND || $this->currentToken()['type'] === self::TOKEN_OR) {
			$operator = $this->currentToken()['type'];
			$this->position++;

			$right = $this->parseTerm($qb, $field);
			if ($right === null) {
				return $left;
			}

			if ($operator === self::TOKEN_AND) {
				$left = $qb->expr()->andX($left, $right);
			} else { // OR
				$left = $qb->expr()->orX($left, $right);
			}
		}

		return $left;
	}

	/**
	 * Parse a term (handles words, phrases, exclusions, and parentheses)
	 */
	private function parseTerm(IQueryBuilder $qb, string $field): ?ICompositeExpression {
		$token = $this->currentToken();

		// Handle parentheses
		if ($token['type'] === self::TOKEN_LEFT_PAREN) {
			$this->position++; // Skip '('
			$expr = $this->parseExpression($qb, $field);
			if ($this->currentToken()['type'] === self::TOKEN_RIGHT_PAREN) {
				$this->position++; // Skip ')'
			}
			return $expr;
		}

		// Handle exclusion
		if ($token['type'] === self::TOKEN_EXCLUDE) {
			$this->position++;
			$value = '%' . $this->escapeWildcards(mb_strtolower($token['value'])) . '%';
			$lowerField = $qb->func()->lower($field);
			$notLike = $qb->expr()->notLike($lowerField, $qb->createNamedParameter($value));
			return $qb->expr()->andX($notLike);
		}

		// Handle word
		if ($token['type'] === self::TOKEN_WORD) {
			$this->position++;
			$value = '%' . $this->escapeWildcards(mb_strtolower($token['value'])) . '%';
			$lowerField = $qb->func()->lower($field);
			$like = $qb->expr()->like($lowerField, $qb->createNamedParameter($value));
			return $qb->expr()->andX($like);
		}

		// Handle phrase (exact match)
		if ($token['type'] === self::TOKEN_PHRASE) {
			$this->position++;
			$value = '%' . $this->escapeWildcards(mb_strtolower($token['value'])) . '%';
			$lowerField = $qb->func()->lower($field);
			$like = $qb->expr()->like($lowerField, $qb->createNamedParameter($value));
			return $qb->expr()->andX($like);
		}

		return null;
	}

	/**
	 * Get the current token
	 */
	private function currentToken(): array {
		if ($this->position >= count($this->tokens)) {
			return ['type' => self::TOKEN_EOF, 'value' => ''];
		}
		return $this->tokens[$this->position];
	}

	/**
	 * Escape wildcards in LIKE patterns
	 */
	private function escapeWildcards(string $value): string {
		return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
	}
}
