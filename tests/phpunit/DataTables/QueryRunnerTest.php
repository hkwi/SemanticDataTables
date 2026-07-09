<?php

namespace SMWDataTables\Tests\DataTables;

use ReflectionClass;
use SMW\DataValues\PropertyValue;
use SMW\Query\PrintRequest;
use SMW\Query\QueryResult;
use SMW\Store;
use SMWDataTables\DataTables\DataTablesRequest;
use SMWDataTables\DataTables\QueryRunner;

/**
 * @covers \SMWDataTables\DataTables\QueryRunner
 * @group semanticdatatables
 * @group semantic-mediawiki
 */
class QueryRunnerTest extends \MediaWikiIntegrationTestCase {

	public function testServerSideRecordsTotalUsesCountQueryValue(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		$response = ( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [
					'limit' => 10,
					'offset' => 20,
					'mainlabel' => 'Page',
				],
				'printouts' => [],
			],
			new DataTablesRequest( 7, 25, 25, [], [], '' )
		);

		$this->assertSame( 123, $response['recordsTotal'] );
		$this->assertSame( 123, $response['recordsFiltered'] );
		$this->assertSame( 7, $response['draw'] );
		$this->assertCount( 2, $queries );
		$this->assertSame( \SMWQuery::MODE_COUNT, $queries[0]->querymode );
		$this->assertSame( 0, $queries[0]->getOffset() );
		$this->assertSame( 25, $queries[1]->getLimit() );
		$this->assertSame( 25, $queries[1]->getOffset() );
	}

	public function testServerSideSearchFiltersFormattedRows(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		$response = ( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [],
			],
			new DataTablesRequest( 1, 0, 25, [], [], 'needle' )
		);

		$this->assertSame( 123, $response['recordsTotal'] );
		$this->assertSame( 0, $response['recordsFiltered'] );
		$this->assertCount( 2, $queries );
		$this->assertSame( \SMWQuery::MODE_COUNT, $queries[0]->querymode );
		$this->assertSame( 0, $queries[1]->getOffset() );
		$this->assertSame( 123, $queries[1]->getLimit() );
	}

	public function testServerSideRestoresInversePropertyPrintout(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 3 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Example]]',
				'parameters' => [],
				'printouts' => [
					[
						'mode' => PrintRequest::PRINT_PROP,
						'label' => 'Title',
						'canonicalLabel' => '-Has subobject',
						'propertyKey' => '_SOBJ',
						'inverse' => true,
						'outputFormat' => '',
						'parameters' => [],
					],
				],
			],
			new DataTablesRequest( 1, 0, 3, [], [], '' )
		);

		$this->assertCount( 2, $queries );
		$printRequests = $queries[1]->getExtraPrintouts();
		$this->assertCount( 1, $printRequests );

		$data = $printRequests[0]->getData();
		$this->assertInstanceOf( PropertyValue::class, $data );
		$this->assertSame( '_SOBJ', $data->getDataItem()->getKey() );
		$this->assertTrue( $data->getDataItem()->isInverse() );
	}

	public function testServerSideSearchBuilderAddsAskConditionsWhenSafe(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->countResult( 17 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		$response = ( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [
					[
						'label' => 'Status',
						'canonicalLabel' => 'Item status',
						'propertyKey' => 'Item status',
					],
				],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[ 'data' => 0, 'name' => 'Item status' ],
				],
				[],
				'',
				[
					'logic' => 'AND',
					'criteria' => [
						[
							'data' => 'Status',
							'condition' => 'equals',
							'type' => 'string',
							'value1' => 'Active',
						],
					],
				]
			)
		);

		$this->assertSame( 123, $response['recordsTotal'] );
		$this->assertSame( 17, $response['recordsFiltered'] );
		$this->assertCount( 3, $queries );
		$this->assertSame( \SMWQuery::MODE_COUNT, $queries[0]->querymode );
		$this->assertSame( \SMWQuery::MODE_COUNT, $queries[1]->querymode );
		$this->assertStringContainsString( 'Item status', $queries[1]->getQueryString() );
		$this->assertStringContainsString( 'Active', $queries[1]->getQueryString() );
		$this->assertStringContainsString( 'Item status', $queries[2]->getQueryString() );
		$this->assertStringContainsString( 'Active', $queries[2]->getQueryString() );
		$this->assertSame( 0, $queries[2]->getOffset() );
		$this->assertSame( 25, $queries[2]->getLimit() );
	}

	public function testServerSideSearchBuilderFallsBackToRowFilteringForTemplateColumns(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		$response = ( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [
					[
						'label' => 'Status',
						'canonicalLabel' => 'Item status',
						'propertyKey' => 'Item status',
						'parameters' => [ 'template' => 'SystemTypeBadge' ],
					],
				],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[ 'data' => 0, 'name' => 'Item status' ],
				],
				[],
				'',
				[
					'logic' => 'AND',
					'criteria' => [
						[
							'data' => 'Status',
							'condition' => 'equals',
							'type' => 'string',
							'value1' => 'Active',
						],
					],
				]
			)
		);

		$this->assertSame( 123, $response['recordsTotal'] );
		$this->assertSame( 0, $response['recordsFiltered'] );
		$this->assertCount( 2, $queries );
		$this->assertSame( \SMWQuery::MODE_COUNT, $queries[0]->querymode );
		$this->assertStringNotContainsString( 'Item status::Active', $queries[1]->getQueryString() );
		$this->assertSame( 0, $queries[1]->getOffset() );
		$this->assertSame( 123, $queries[1]->getLimit() );
	}

	public function testServerSideAppliesColumnOrderToQuery(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[ 'name' => 'Foo' ],
				],
				[
					[ 'column' => 0, 'dir' => 'desc' ],
				],
				''
			)
		);

		$this->assertCount( 2, $queries );
		$this->assertSame( 'DESC', $queries[1]->sortkeys['Foo'] );
	}

	public function testServerSideParametersConvertStoredSortKeysToRawSortOrder(): void {
		$parameters = $this->invokeServerSideParameters(
			[
				'parameters' => [
					'sortkeys' => [ 'Foo' => 'DESC' ],
					'mainlabel' => 'Page',
				],
				'printouts' => [],
			],
			new DataTablesRequest( 1, 5, 25, [], [], '' )
		);

		$this->assertArrayNotHasKey( 'sortkeys', $parameters );
		$this->assertSame( 'Foo', $parameters['sort'] );
		$this->assertSame( 'desc', $parameters['order'] );
		$this->assertSame( 5, $parameters['offset'] );
		$this->assertSame( 25, $parameters['limit'] );
	}

	public function testServerSideParametersPreferRequestOrderOverStoredSortKeys(): void {
		$parameters = $this->invokeServerSideParameters(
			[
				'parameters' => [
					'sortkeys' => [ 'Old' => 'DESC' ],
				],
				'printouts' => [
					[
						'mode' => PrintRequest::PRINT_PROP,
						'label' => 'New',
						'propertyKey' => 'New',
						'parameters' => [],
					],
				],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[],
				],
				[
					[ 'column' => 0, 'dir' => 'asc' ],
				],
				''
			)
		);

		$this->assertArrayNotHasKey( 'sortkeys', $parameters );
		$this->assertSame( 'New', $parameters['sort'] );
		$this->assertSame( 'asc', $parameters['order'] );
	}

	public function testCountParametersDropStoredSortKeysAndRawSortOrder(): void {
		$parameters = $this->invokeCountParameters( [
			'parameters' => [
				'limit' => 10,
				'offset' => 20,
				'sortkeys' => [ 'Foo' => 'DESC' ],
				'sort' => 'Bar',
				'order' => 'asc',
				'mainlabel' => 'Page',
			],
		] );

		$this->assertArrayNotHasKey( 'limit', $parameters );
		$this->assertArrayNotHasKey( 'offset', $parameters );
		$this->assertArrayNotHasKey( 'sortkeys', $parameters );
		$this->assertArrayNotHasKey( 'sort', $parameters );
		$this->assertArrayNotHasKey( 'order', $parameters );
		$this->assertSame( 'count', $parameters['format'] );
	}

	public function testServerSideAppliesMainLabelColumnOrderToQuery(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[ 'name' => '' ],
				],
				[
					[ 'column' => 0, 'dir' => 'asc' ],
				],
				''
			)
		);

		$this->assertCount( 2, $queries );
		$this->assertArrayHasKey( '', $queries[1]->sortkeys );
		$this->assertSame( 'ASC', $queries[1]->sortkeys[''] );
	}

	public function testServerSideFallsBackToContextPrintoutForColumnOrder(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [
					[
						'mode' => PrintRequest::PRINT_PROP,
						'label' => '観察日',
						'propertyKey' => '観察日',
						'outputFormat' => '-F[Y-m-d]',
						'parameters' => [],
					],
				],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[],
				],
				[
					[ 'column' => 0, 'dir' => 'asc' ],
				],
				''
			)
		);

		$this->assertCount( 2, $queries );
		$this->assertSame( 'ASC', $queries[1]->sortkeys['観察日'] );
	}

	public function testServerSideSkipsRepeatedTemplatePropertyForFallbackColumnOrder(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [
					[
						'mode' => PrintRequest::PRINT_PROP,
						'label' => 'サンプル名',
						'propertyKey' => 'サンプル図鑑',
						'outputFormat' => '',
						'parameters' => [ 'template' => 'サンプル図鑑表示ボタン' ],
					],
					[
						'mode' => PrintRequest::PRINT_PROP,
						'label' => '観察日',
						'propertyKey' => 'サンプル図鑑',
						'outputFormat' => '',
						'parameters' => [ 'template' => 'サンプル図鑑観察時刻' ],
					],
				],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[],
					[],
				],
				[
					[ 'column' => 1, 'dir' => 'asc' ],
				],
				''
			)
		);

		$this->assertCount( 2, $queries );
		$this->assertSame( [], $queries[1]->sortkeys );
	}

	public function testServerSideUsesConfiguredSortNameForRepeatedTemplateFallbackColumnOrder(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [
					[
						'mode' => PrintRequest::PRINT_PROP,
						'label' => 'サンプル名',
						'propertyKey' => 'サンプル図鑑',
						'outputFormat' => '',
						'parameters' => [ 'template' => 'サンプル図鑑表示ボタン' ],
					],
					[
						'mode' => PrintRequest::PRINT_PROP,
						'label' => '観察日',
						'propertyKey' => 'サンプル図鑑',
						'outputFormat' => '',
						'parameters' => [
							'template' => 'サンプル図鑑観察時刻',
							'datatables-columns.name' => '観察日',
						],
					],
				],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[],
					[],
				],
				[
					[ 'column' => 1, 'dir' => 'asc' ],
				],
				''
			)
		);

		$this->assertCount( 2, $queries );
		$this->assertSame( 'ASC', $queries[1]->sortkeys['観察日'] );
	}

	public function testServerSideSkipsRepeatedMainLabelAliasForFallbackColumnOrder(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [
					[
						'mode' => PrintRequest::PRINT_THIS,
						'label' => 'サンプル名',
						'propertyKey' => null,
						'outputFormat' => '',
						'parameters' => [],
					],
					[
						'mode' => PrintRequest::PRINT_THIS,
						'label' => '観察日',
						'propertyKey' => null,
						'outputFormat' => '',
						'parameters' => [ 'template' => 'サンプル図鑑観察時刻' ],
					],
				],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[ 'name' => '' ],
					[ 'name' => '' ],
				],
				[
					[ 'column' => 1, 'dir' => 'asc' ],
				],
				''
			)
		);

		$this->assertCount( 2, $queries );
		$this->assertSame( [], $queries[1]->sortkeys );
	}

	public function testServerSideIgnoresRequestNameForNonOrderableFallbackColumn(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runServerSide(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
				'printouts' => [
					[
						'mode' => PrintRequest::PRINT_THIS,
						'label' => 'サンプル名',
						'propertyKey' => null,
						'outputFormat' => '',
						'parameters' => [],
					],
					[
						'mode' => PrintRequest::PRINT_THIS,
						'label' => '観察日',
						'propertyKey' => null,
						'outputFormat' => '',
						'parameters' => [ 'template' => 'サンプル図鑑観察時刻' ],
					],
				],
			],
			new DataTablesRequest(
				1,
				0,
				25,
				[
					[ 'name' => '' ],
					[ 'name' => '観察日' ],
				],
				[
					[ 'column' => 1, 'dir' => 'asc' ],
				],
				''
			)
		);

		$this->assertCount( 2, $queries );
		$this->assertSame( [], $queries[1]->sortkeys );
	}

	public function testClientSideAjaxIgnoresOriginalAskLimitForFetchLimit(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		( new QueryRunner( $store ) )->runClientSide( [
			'conditions' => '[[Category:Test]]',
			'parameters' => [
				'limit' => 10,
				'offset' => 20,
			],
			'printouts' => [],
		] );

		$this->assertCount( 2, $queries );
		$this->assertSame( \SMWQuery::MODE_COUNT, $queries[0]->querymode );
		$this->assertSame( 123, $queries[1]->getLimit() );
		$this->assertSame( 0, $queries[1]->getOffset() );
	}

	public function testExportIgnoresRequestPagingForFetchLimit(): void {
		$queries = [];
		$store = $this->storeWithResults(
			[
				$this->countResult( 123 ),
				$this->emptyInstanceResult(),
			],
			$queries
		);

		$response = ( new QueryRunner( $store ) )->runExport(
			[
				'conditions' => '[[Category:Test]]',
				'parameters' => [
					'limit' => 10,
					'offset' => 20,
				],
				'printouts' => [],
			],
			new DataTablesRequest( 7, 50, 25, [], [], '' )
		);

		$this->assertSame( 123, $response['recordsTotal'] );
		$this->assertSame( 123, $response['recordsFiltered'] );
		$this->assertCount( 2, $queries );
		$this->assertSame( \SMWQuery::MODE_COUNT, $queries[0]->querymode );
		$this->assertSame( 123, $queries[1]->getLimit() );
		$this->assertSame( 0, $queries[1]->getOffset() );
	}

	public function testGlobalSearchUsesFormattedSearchText(): void {
		$queries = [];
		$rows = $this->invokeFilterRows(
			new QueryRunner( $this->storeWithResults( [], $queries ) ),
			[
				[
					[ 'filter' => 'Example project' ],
					[ 'filter' => 'Active' ],
					[ 'filter' => 'Research team' ],
					[ 'filter' => 'Example project for shared equipment.' ],
				],
				[
					[ 'filter' => 'Cloud console' ],
					[ 'filter' => 'Priority' ],
					[ 'filter' => 'Cloud platform team' ],
					[ 'filter' => 'Vendor-provided console.' ],
				],
				[
					[ 'filter' => 'Bridge portal' ],
					[ 'filter' => 'Other' ],
					[ 'filter' => 'Cloud platform team' ],
					[ 'filter' => 'Portal proxy' ],
				],
			],
			new DataTablesRequest(
				4,
				0,
				25,
				[
					[
						'data' => 0,
						'name' => '',
						'searchable' => true,
					],
					[
						'data' => 1,
						'name' => 'Item description',
						'searchable' => true,
					],
					[
						'data' => 2,
						'name' => 'Item score',
						'searchable' => true,
					],
					[
						'data' => 3,
						'name' => 'Hidden note',
						'searchable' => false,
					],
				],
				[],
				'cloud'
			)
		);

		$this->assertCount( 2, $rows );
		$this->assertSame( 'Cloud console', $rows[0][0]['filter'] );
		$this->assertSame( 'Bridge portal', $rows[1][0]['filter'] );
	}

	public function testSearchBuilderFiltersFormattedRows(): void {
		$queries = [];
		$rows = $this->invokeFilterRows(
			new QueryRunner( $this->storeWithResults( [], $queries ) ),
			[
				[
					[ 'filter' => 'Example project' ],
					[ 'filter' => 'Active' ],
					[ 'filter' => 'Research team' ],
					[ 'filter' => 'Example project for shared equipment.' ],
				],
				[
					[ 'filter' => 'Cloud console' ],
					[ 'filter' => 'Priority' ],
					[ 'filter' => 'Cloud platform team' ],
					[ 'filter' => 'Vendor-provided console.' ],
				],
				[
					[ 'filter' => 'Bridge portal' ],
					[ 'filter' => 'Active' ],
					[ 'filter' => 'Cloud platform team' ],
					[ 'filter' => 'Portal proxy' ],
				],
			],
			new DataTablesRequest(
				4,
				0,
				25,
				[
					[
						'data' => 0,
						'name' => '',
						'searchable' => true,
					],
					[
						'data' => 1,
						'name' => 'Item status',
						'searchable' => true,
					],
					[
						'data' => 2,
						'name' => 'Item owner',
						'searchable' => true,
					],
					[
						'data' => 3,
						'name' => 'Item description',
						'searchable' => true,
					],
				],
				[],
				'',
				[
					'logic' => 'AND',
					'criteria' => [
						[
							'dataIdx' => 1,
							'data' => 'Status',
							'condition' => 'equals',
							'type' => 'string',
							'value1' => 'Active',
						],
						[
							'data' => 'Description',
							'condition' => 'contains',
							'type' => 'string',
							'value1' => 'shared',
						],
					],
				]
			),
			[
				'printouts' => [
					[ 'label' => 'Title', 'canonicalLabel' => '', 'propertyKey' => null ],
					[ 'label' => 'Status', 'canonicalLabel' => 'Item status', 'propertyKey' => 'Item status' ],
					[ 'label' => 'Owner', 'canonicalLabel' => 'Item owner', 'propertyKey' => 'Item owner' ],
					[ 'label' => 'Description', 'canonicalLabel' => 'Item description', 'propertyKey' => 'Item description' ],
				],
			]
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Example project', $rows[0][0]['filter'] );
	}

	private function storeWithResults( array $results, array &$queries ): Store {
		$store = $this->getMockBuilder( Store::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getQueryResult' ] )
			->getMockForAbstractClass();

		$store->method( 'getQueryResult' )
			->willReturnCallback( function ( \SMWQuery $query ) use ( &$queries, &$results ) {
				$queries[] = $query;

				return array_shift( $results );
			} );

		return $store;
	}

	private function invokeFilterRows(
		QueryRunner $runner,
		array $rows,
		DataTablesRequest $request,
		array $context = []
	): array {
		$reflector = new ReflectionClass( $runner );
		$method = $reflector->getMethod( 'filterRows' );
		$method->setAccessible( true );

		return $method->invoke( $runner, $rows, $request, $context );
	}

	private function invokeServerSideParameters( array $context, DataTablesRequest $request ): array {
		$queries = [];
		$runner = new QueryRunner( $this->storeWithResults( [], $queries ) );
		$reflector = new ReflectionClass( $runner );
		$method = $reflector->getMethod( 'serverSideParameters' );
		$method->setAccessible( true );

		return $method->invoke( $runner, $context, $request );
	}

	private function invokeCountParameters( array $context ): array {
		$queries = [];
		$runner = new QueryRunner( $this->storeWithResults( [], $queries ) );
		$reflector = new ReflectionClass( $runner );
		$method = $reflector->getMethod( 'countParameters' );
		$method->setAccessible( true );

		return $method->invoke( $runner, $context );
	}

	private function countResult( int $count ): QueryResult {
		$result = $this->getMockBuilder( QueryResult::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getCountValue', 'getCount' ] )
			->getMock();

		$result->method( 'getCountValue' )
			->willReturn( $count );

		$result->method( 'getCount' )
			->willReturn( 0 );

		return $result;
	}

	private function emptyInstanceResult(): QueryResult {
		$result = $this->getMockBuilder( QueryResult::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getNext', 'getPrintRequests' ] )
			->getMock();

		$result->method( 'getNext' )
			->willReturn( false );

		$result->method( 'getPrintRequests' )
			->willReturn( [] );

		return $result;
	}
}
