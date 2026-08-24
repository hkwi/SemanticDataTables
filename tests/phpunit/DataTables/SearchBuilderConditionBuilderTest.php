<?php

namespace SMWDataTables\Tests\DataTables;

use PHPUnit\Framework\TestCase;
use SMW\Query\PrintRequest;
use SMWDataTables\DataTables\DataTablesRequest;
use SMWDataTables\DataTables\SearchBuilderConditionBuilder;

/**
 * @covers \SMWDataTables\DataTables\SearchBuilderConditionBuilder
 * @group semanticdatatables
 * @group semantic-mediawiki
 */
class SearchBuilderConditionBuilderTest extends TestCase {

	public function testContainsConditionSupportsInversePropertyChain(): void {
		$context = [
			'printouts' => [
				[
					'mode' => PrintRequest::PRINT_CHAIN,
					'label' => 'Owner',
					'propertyKey' => '-Has parent.Owner name',
					'parameters' => [],
				],
			],
		];
		$request = new DataTablesRequest(
			1,
			0,
			25,
			[ [ 'data' => 0, 'name' => '-Has parent.Owner name' ] ],
			[],
			'',
			[
				'logic' => 'AND',
				'criteria' => [
					[
						'dataIdx' => 0,
						'data' => 'Owner',
						'condition' => 'contains',
						'type' => 'string',
						'value1' => 'Sample value',
					],
				],
			]
		);

		$this->assertSame(
			'[[-Has parent.Owner name::~*Sample value*]]',
			( new SearchBuilderConditionBuilder( $context, $request ) )->conditions()
		);
	}

	public function testPropertyChainWithTemplateFallsBackToRowFiltering(): void {
		$context = [
			'printouts' => [
				[
					'mode' => PrintRequest::PRINT_CHAIN,
					'label' => 'Owner',
					'propertyKey' => '-Has parent.Owner name',
					'parameters' => [ 'template' => 'SampleTemplate' ],
				],
			],
		];
		$request = new DataTablesRequest(
			1,
			0,
			25,
			[],
			[],
			'',
			[
				'logic' => 'AND',
				'criteria' => [
					[
						'dataIdx' => 0,
						'condition' => 'contains',
						'value1' => 'Sample value',
					],
				],
			]
		);

		$this->assertNull(
			( new SearchBuilderConditionBuilder( $context, $request ) )->conditions()
		);
	}
}
