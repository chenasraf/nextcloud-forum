<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Service\QueryParser;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use PHPUnit\Framework\TestCase;

class QueryParserTest extends TestCase {
	private QueryParser $parser;
	private IQueryBuilder $qb;
	private IExpressionBuilder $expr;
	private IFunctionBuilder $func;

	protected function setUp(): void {
		$this->parser = new QueryParser();
		$this->qb = $this->createMock(IQueryBuilder::class);
		$this->expr = $this->createMock(IExpressionBuilder::class);
		$this->func = $this->createMock(IFunctionBuilder::class);

		$this->qb->method('expr')
			->willReturn($this->expr);

		$this->qb->method('func')
			->willReturn($this->func);

		// Mock LOWER() function to return an IQueryFunction that represents "LOWER(field)"
		$this->func->method('lower')
			->willReturnCallback(function ($field) {
				$queryFunc = $this->createMock(IQueryFunction::class);
				// Make the IQueryFunction castable to string as "LOWER(field)"
				$queryFunc->method('__toString')->willReturn("LOWER($field)");
				return $queryFunc;
			});
	}

	public function testParseEmptyQueryReturnsNull(): void {
		$result = $this->parser->parse($this->qb, '', 'field');
		$this->assertNull($result);
	}

	public function testParseWhitespaceOnlyQueryReturnsNull(): void {
		$result = $this->parser->parse($this->qb, '   ', 'field');
		$this->assertNull($result);
	}

	public function testParseSingleWord(): void {
		$mockComposite = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->once())
			->method('createNamedParameter')
			->with('%test%')
			->willReturn(':dcValue1');

		$this->expr->expects($this->once())
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), ':dcValue1')
			->willReturn('LOWER(field) LIKE :dcValue1');

		$this->expr->expects($this->once())
			->method('andX')
			->with('LOWER(field) LIKE :dcValue1')
			->willReturn($mockComposite);

		$result = $this->parser->parse($this->qb, 'test', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseQuotedPhrase(): void {
		$mockComposite = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->once())
			->method('createNamedParameter')
			->with('%exact phrase%')
			->willReturn(':dcValue1');

		$this->expr->expects($this->once())
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), ':dcValue1')
			->willReturn('LOWER(field) LIKE :dcValue1');

		$this->expr->expects($this->once())
			->method('andX')
			->with('LOWER(field) LIKE :dcValue1')
			->willReturn($mockComposite);

		$result = $this->parser->parse($this->qb, '"exact phrase"', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseQuotedPhraseWithEscapedQuote(): void {
		$mockComposite = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->once())
			->method('createNamedParameter')
			->with('%phrase with " quote%')
			->willReturn(':dcValue1');

		$this->expr->expects($this->once())
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), ':dcValue1')
			->willReturn('LOWER(field) LIKE :dcValue1');

		$this->expr->expects($this->once())
			->method('andX')
			->with('LOWER(field) LIKE :dcValue1')
			->willReturn($mockComposite);

		$result = $this->parser->parse($this->qb, '"phrase with \" quote"', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseExclusion(): void {
		$mockComposite = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->once())
			->method('createNamedParameter')
			->with('%excluded%')
			->willReturn(':dcValue1');

		$this->expr->expects($this->once())
			->method('notLike')
			->with($this->isInstanceOf(IQueryFunction::class), ':dcValue1')
			->willReturn('LOWER(field) NOT LIKE :dcValue1');

		$this->expr->expects($this->once())
			->method('andX')
			->with('LOWER(field) NOT LIKE :dcValue1')
			->willReturn($mockComposite);

		$result = $this->parser->parse($this->qb, '-excluded', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseAndOperator(): void {
		$mockComposite1 = $this->createMock(ICompositeExpression::class);
		$mockComposite2 = $this->createMock(ICompositeExpression::class);
		$mockComposite3 = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->exactly(2))
			->method('createNamedParameter')
			->willReturnOnConsecutiveCalls(':dcValue1', ':dcValue2');

		$this->expr->expects($this->exactly(2))
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), $this->anything())
			->willReturnOnConsecutiveCalls('LOWER(field) LIKE :dcValue1', 'LOWER(field) LIKE :dcValue2');

		$this->expr->expects($this->exactly(3))
			->method('andX')
			->willReturnOnConsecutiveCalls($mockComposite1, $mockComposite2, $mockComposite3);

		$result = $this->parser->parse($this->qb, 'term1 AND term2', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseOrOperator(): void {
		$mockComposite1 = $this->createMock(ICompositeExpression::class);
		$mockComposite2 = $this->createMock(ICompositeExpression::class);
		$mockComposite3 = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->exactly(2))
			->method('createNamedParameter')
			->willReturnOnConsecutiveCalls(':dcValue1', ':dcValue2');

		$this->expr->expects($this->exactly(2))
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), $this->anything())
			->willReturnOnConsecutiveCalls('LOWER(field) LIKE :dcValue1', 'LOWER(field) LIKE :dcValue2');

		$this->expr->expects($this->exactly(2))
			->method('andX')
			->willReturnOnConsecutiveCalls($mockComposite1, $mockComposite2);

		$this->expr->expects($this->once())
			->method('orX')
			->with($mockComposite1, $mockComposite2)
			->willReturn($mockComposite3);

		$result = $this->parser->parse($this->qb, 'term1 OR term2', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseParentheses(): void {
		$mockComposite1 = $this->createMock(ICompositeExpression::class);
		$mockComposite2 = $this->createMock(ICompositeExpression::class);
		$mockComposite3 = $this->createMock(ICompositeExpression::class);
		$mockComposite4 = $this->createMock(ICompositeExpression::class);
		$mockComposite5 = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->exactly(3))
			->method('createNamedParameter')
			->willReturnOnConsecutiveCalls(':dcValue1', ':dcValue2', ':dcValue3');

		$this->expr->expects($this->exactly(3))
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), $this->anything())
			->willReturnOnConsecutiveCalls('LOWER(field) LIKE :dcValue1', 'LOWER(field) LIKE :dcValue2', 'LOWER(field) LIKE :dcValue3');

		// andX is called: (1) for term1, (2) for term2, (3) for term3, (4) to combine OR result with term3
		$this->expr->expects($this->exactly(4))
			->method('andX')
			->willReturnOnConsecutiveCalls($mockComposite1, $mockComposite2, $mockComposite4, $mockComposite5);

		$this->expr->expects($this->once())
			->method('orX')
			->with($mockComposite1, $mockComposite2)
			->willReturn($mockComposite3);

		$result = $this->parser->parse($this->qb, '(term1 OR term2) AND term3', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseComplexQuery(): void {
		// Test: (foo OR "bar baz") AND -excluded
		$mockComposite1 = $this->createMock(ICompositeExpression::class);
		$mockComposite2 = $this->createMock(ICompositeExpression::class);
		$mockComposite3 = $this->createMock(ICompositeExpression::class);
		$mockComposite4 = $this->createMock(ICompositeExpression::class);
		$mockComposite5 = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->exactly(3))
			->method('createNamedParameter')
			->willReturnOnConsecutiveCalls(':dcValue1', ':dcValue2', ':dcValue3');

		$this->expr->expects($this->exactly(2))
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), $this->anything())
			->willReturnOnConsecutiveCalls('LOWER(field) LIKE :dcValue1', 'LOWER(field) LIKE :dcValue2');

		$this->expr->expects($this->once())
			->method('notLike')
			->with($this->isInstanceOf(IQueryFunction::class), ':dcValue3')
			->willReturn('LOWER(field) NOT LIKE :dcValue3');

		$this->expr->expects($this->exactly(4))
			->method('andX')
			->willReturnOnConsecutiveCalls($mockComposite1, $mockComposite2, $mockComposite4, $mockComposite5);

		$this->expr->expects($this->once())
			->method('orX')
			->with($mockComposite1, $mockComposite2)
			->willReturn($mockComposite3);

		$result = $this->parser->parse($this->qb, '(foo OR "bar baz") AND -excluded', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseWildcardEscaping(): void {
		$mockComposite = $this->createMock(ICompositeExpression::class);

		// Test that wildcards in search terms are escaped
		$this->qb->expects($this->once())
			->method('createNamedParameter')
			->with('%test\\%value\\_with\\\\slash%')
			->willReturn(':dcValue1');

		$this->expr->expects($this->once())
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), ':dcValue1')
			->willReturn('LOWER(field) LIKE :dcValue1');

		$this->expr->expects($this->once())
			->method('andX')
			->with('LOWER(field) LIKE :dcValue1')
			->willReturn($mockComposite);

		$result = $this->parser->parse($this->qb, 'test%value_with\\slash', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseCaseInsensitiveOperators(): void {
		$mockComposite1 = $this->createMock(ICompositeExpression::class);
		$mockComposite2 = $this->createMock(ICompositeExpression::class);
		$mockComposite3 = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->exactly(2))
			->method('createNamedParameter')
			->willReturnOnConsecutiveCalls(':dcValue1', ':dcValue2');

		$this->expr->expects($this->exactly(2))
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), $this->anything())
			->willReturnOnConsecutiveCalls('LOWER(field) LIKE :dcValue1', 'LOWER(field) LIKE :dcValue2');

		$this->expr->expects($this->exactly(2))
			->method('andX')
			->willReturnOnConsecutiveCalls($mockComposite1, $mockComposite2);

		$this->expr->expects($this->once())
			->method('orX')
			->with($mockComposite1, $mockComposite2)
			->willReturn($mockComposite3);

		// Test lowercase 'or'
		$result = $this->parser->parse($this->qb, 'term1 or term2', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseUnmatchedParenthesisHandledGracefully(): void {
		$mockComposite = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->atLeastOnce())
			->method('createNamedParameter')
			->willReturn(':dcValue1');

		$this->expr->expects($this->atLeastOnce())
			->method('like')
			->willReturn('field LIKE :dcValue1');

		$this->expr->expects($this->atLeastOnce())
			->method('andX')
			->willReturn($mockComposite);

		// Parser should handle unmatched parentheses gracefully
		$result = $this->parser->parse($this->qb, '(term1 OR term2', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseMultipleWordsWithExplicitAnd(): void {
		$mockComposite1 = $this->createMock(ICompositeExpression::class);
		$mockComposite2 = $this->createMock(ICompositeExpression::class);
		$mockComposite3 = $this->createMock(ICompositeExpression::class);

		$this->qb->expects($this->exactly(2))
			->method('createNamedParameter')
			->willReturnOnConsecutiveCalls(':dcValue1', ':dcValue2');

		$this->expr->expects($this->exactly(2))
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), $this->anything())
			->willReturnOnConsecutiveCalls('LOWER(field) LIKE :dcValue1', 'LOWER(field) LIKE :dcValue2');

		// andX is called: (1) for word1, (2) for word2, (3) to combine them
		$this->expr->expects($this->exactly(3))
			->method('andX')
			->willReturnOnConsecutiveCalls($mockComposite1, $mockComposite2, $mockComposite3);

		// With explicit AND operator
		$result = $this->parser->parse($this->qb, 'word1 AND word2', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}

	public function testParseMixedCaseSearch(): void {
		$mockComposite = $this->createMock(ICompositeExpression::class);

		// Search value should be converted to lowercase
		$this->qb->expects($this->once())
			->method('createNamedParameter')
			->with('%nextcloud%') // lowercase
			->willReturn(':dcValue1');

		$this->expr->expects($this->once())
			->method('like')
			->with($this->isInstanceOf(IQueryFunction::class), ':dcValue1')
			->willReturn('LOWER(field) LIKE :dcValue1');

		$this->expr->expects($this->once())
			->method('andX')
			->with('LOWER(field) LIKE :dcValue1')
			->willReturn($mockComposite);

		// Search with mixed case - should be converted to lowercase
		$result = $this->parser->parse($this->qb, 'NextCloud', 'field');
		$this->assertInstanceOf(ICompositeExpression::class, $result);
	}
}
