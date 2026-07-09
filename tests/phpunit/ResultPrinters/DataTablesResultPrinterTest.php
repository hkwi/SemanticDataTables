<?php

namespace SMWDataTables\Tests\ResultPrinters;

use ReflectionClass;
use SMW\DataValueFactory;
use SMW\DataValues\PropertyChainValue;
use SMW\Query\PrintRequest;
use SMW\Query\QueryResult;
use SMWDataTables\ResultPrinters\DataTablesResultPrinter;

/**
 * @covers \SMWDataTables\ResultPrinters\DataTablesResultPrinter
 * @group semanticdatatables
 * @group semantic-mediawiki
 */
class DataTablesResultPrinterTest extends \MediaWikiIntegrationTestCase {

	public function testGetResultTextReturnsHtmlString(): void {
		$this->overrideConfigValue( 'SecretKey', 'semantic-datatables-test-secret' );

		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$this->setPrinterParameters( $printer );

		$html = $this->invokeGetResultText(
			$printer,
			$this->newEmptyQueryResult(),
			SMW_OUTPUT_HTML
		);

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'semantic-datatables-container datatables-container', $html );
		$this->assertStringContainsString( '<table', $html );
		$this->assertStringContainsString( 'data-sdt-config=', $html );
		$this->assertEmbeddedTableConfig( $html );
		$this->assertStringNotContainsString( 'Array', $html );
		$this->assertPrinterIsHtml( $printer );
	}

	public function testNoAjaxParameterDefaultsToFalse(): void {
		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$definitions = $printer->getParamDefinitions( [] );

		$this->assertArrayHasKey( 'noajax', $definitions );
		$this->assertFalse( $definitions['noajax']['default'] );
	}

	public function testDefaultConfigEnablesServerSideProcessing(): void {
		$this->overrideConfigValue( 'SecretKey', 'semantic-datatables-test-secret' );

		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$this->setPrinterParameters( $printer );

		$html = $this->invokeGetResultText(
			$printer,
			$this->newEmptyQueryResult(),
			SMW_OUTPUT_HTML
		);

		$config = $this->embeddedTableConfig( $html );

		$this->assertTrue( $config['ajax'] );
		$this->assertTrue( $config['serverSide'] );
	}

	public function testNoAjaxConfigDisablesServerSideProcessing(): void {
		$this->overrideConfigValue( 'SecretKey', 'semantic-datatables-test-secret' );

		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$this->setPrinterParameters( $printer, [
			'noajax' => true,
		] );

		$html = $this->invokeGetResultText(
			$printer,
			$this->newEmptyQueryResult(),
			SMW_OUTPUT_HTML
		);

		$config = $this->embeddedTableConfig( $html );

		$this->assertFalse( $config['ajax'] );
		$this->assertFalse( $config['serverSide'] );
		$this->assertStringContainsString( '<tbody>', $html );
	}

	public function testColumnsUsePropertyKeyForSortName(): void {
		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$property = DataValueFactory::getInstance()->newPropertyValueByLabel( 'Foo' );
		$columns = $this->invokeColumns( $printer, [
			new PrintRequest( PrintRequest::PRINT_THIS, 'Page' ),
			new PrintRequest( PrintRequest::PRINT_PROP, 'Alias', $property ),
		] );

		$this->assertSame( '', $columns[0]['name'] );
		$this->assertSame( 'Foo', $columns[1]['name'] );
		$this->assertSame( 'Alias', $columns[1]['title'] );
	}

	public function testColumnsUsePropertyChainAliasForTitle(): void {
		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$chain = $this->newPropertyChainValue( '-Has subobject.サンプル色' );
		$columns = $this->invokeColumns( $printer, [
			new PrintRequest( PrintRequest::PRINT_CHAIN, 'サンプル色', $chain ),
		] );

		$this->assertSame( '-Has subobject.サンプル色', $columns[0]['name'] );
		$this->assertSame( 'サンプル色', $columns[0]['title'] );
	}

	public function testTableHeaderUsesPropertyChainAlias(): void {
		$this->overrideConfigValue( 'SecretKey', 'semantic-datatables-test-secret' );

		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$this->setPrinterParameters( $printer );
		$chain = $this->newPropertyChainValue( '-Has subobject.サンプル形' );

		$html = $this->invokeGetResultText(
			$printer,
			$this->newEmptyQueryResult( [
				new PrintRequest( PrintRequest::PRINT_CHAIN, 'サンプル形', $chain ),
			] ),
			SMW_OUTPUT_HTML
		);

		$this->assertStringContainsString( '<th>サンプル形</th>', $html );
		$this->assertStringNotContainsString( '<th>-Has subobject.サンプル形</th>', $html );
	}

	public function testRepeatedTemplatePrintoutIsNotServerSideOrderable(): void {
		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$property = DataValueFactory::getInstance()->newPropertyValueByLabel( 'サンプル図鑑' );
		$columns = $this->invokeColumns( $printer, [
			new PrintRequest(
				PrintRequest::PRINT_PROP,
				'サンプル名',
				$property,
				'',
				[ 'template' => 'サンプル図鑑表示ボタン' ]
			),
			new PrintRequest(
				PrintRequest::PRINT_PROP,
				'観察日',
				$property,
				'',
				[ 'template' => 'サンプル図鑑観察時刻' ]
			),
		] );

		$this->assertSame( 'サンプル図鑑', $columns[0]['name'] );
		$this->assertArrayNotHasKey( 'orderable', $columns[0] );
		$this->assertSame( '', $columns[1]['name'] );
		$this->assertFalse( $columns[1]['orderable'] );
	}

	public function testConfiguredSortNameOverridesPropertyKey(): void {
		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$property = DataValueFactory::getInstance()->newPropertyValueByLabel( 'サンプル図鑑' );
		$columns = $this->invokeColumns( $printer, [
			new PrintRequest(
				PrintRequest::PRINT_PROP,
				'観察日',
				$property,
				'',
				[ 'datatables-columns.name' => '観察日' ]
			),
		] );

		$this->assertSame( '観察日', $columns[0]['name'] );
	}

	public function testConfiguredSortNameKeepsRepeatedTemplatePrintoutOrderable(): void {
		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$property = DataValueFactory::getInstance()->newPropertyValueByLabel( 'サンプル図鑑' );
		$columns = $this->invokeColumns( $printer, [
			new PrintRequest(
				PrintRequest::PRINT_PROP,
				'サンプル名',
				$property,
				'',
				[ 'template' => 'サンプル図鑑表示ボタン' ]
			),
			new PrintRequest(
				PrintRequest::PRINT_PROP,
				'観察日',
				$property,
				'',
				[
					'template' => 'サンプル図鑑観察時刻',
					'datatables-columns.name' => '観察日',
				]
			),
		] );

		$this->assertSame( 'サンプル図鑑', $columns[0]['name'] );
		$this->assertSame( '観察日', $columns[1]['name'] );
		$this->assertArrayNotHasKey( 'orderable', $columns[1] );
	}

	public function testRepeatedMainLabelPrintoutIsNotServerSideOrderable(): void {
		$printer = new DataTablesResultPrinter( 'datatables-native' );
		$columns = $this->invokeColumns( $printer, [
			new PrintRequest( PrintRequest::PRINT_THIS, 'サンプル名' ),
			new PrintRequest(
				PrintRequest::PRINT_THIS,
				'観察日',
				null,
				'',
				[ 'template' => 'サンプル図鑑観察時刻' ]
			),
			new PrintRequest(
				PrintRequest::PRINT_THIS,
				'記録者',
				null,
				'',
				[ 'template' => 'サンプル図鑑記録者' ]
			),
		] );

		$this->assertSame( '', $columns[0]['name'] );
		$this->assertArrayNotHasKey( 'orderable', $columns[0] );
		$this->assertSame( '', $columns[1]['name'] );
		$this->assertFalse( $columns[1]['orderable'] );
		$this->assertSame( '', $columns[2]['name'] );
		$this->assertFalse( $columns[2]['orderable'] );
	}

	private function newPropertyChainValue( string $chain ): PropertyChainValue {
		$data = DataValueFactory::getInstance()->newDataValueByType( PropertyChainValue::TYPE_ID );
		$data->setUserValue( $chain );

		$this->assertTrue( $data->isValid() );
		$this->assertInstanceOf( PropertyChainValue::class, $data );

		return $data;
	}

	private function newEmptyQueryResult( array $printRequests = [] ): QueryResult {
		$query = $this->getMockBuilder( \SMWQuery::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'toArray', 'getOption' ] )
			->getMock();

		$query->method( 'toArray' )
			->willReturn( [
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
			] );

		$query->method( 'getOption' )
			->with( 'count' )
			->willReturn( 0 );

		$queryResult = $this->getMockBuilder( QueryResult::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getQuery', 'getCount', 'getPrintRequests', 'getNext' ] )
			->getMock();

		$queryResult->method( 'getQuery' )
			->willReturn( $query );

		$queryResult->method( 'getCount' )
			->willReturn( 0 );

		$queryResult->method( 'getPrintRequests' )
			->willReturn( $printRequests );

		$queryResult->method( 'getNext' )
			->willReturn( false );

		return $queryResult;
	}

	private function setPrinterParameters( DataTablesResultPrinter $printer, array $overrides = [] ): void {
		$reflector = new ReflectionClass( $printer );
		$params = $reflector->getProperty( 'params' );
		$params->setAccessible( true );
		$params->setValue( $printer, array_replace( [
			'class' => '',
			'noajax' => false,
			'datatables-pageLength' => 25,
			'datatables-lengthMenu' => '10,25,50,100',
			'datatables-searching' => true,
			'datatables-ordering' => true,
			'datatables-paging' => true,
			'datatables-autoWidth' => false,
			'datatables-scrollX' => false,
			'datatables-responsive' => true,
			'datatables-buttons' => '',
			'datatables-dom' => '',
			'datatables-ajax' => false,
			'datatables-serverSide' => false,
		], $overrides ) );
	}

	private function invokeGetResultText(
		DataTablesResultPrinter $printer,
		QueryResult $queryResult,
		int $outputMode
	): string {
		$reflector = new ReflectionClass( $printer );
		$method = $reflector->getMethod( 'getResultText' );
		$method->setAccessible( true );

		return $method->invoke( $printer, $queryResult, $outputMode );
	}

	private function invokeColumns( DataTablesResultPrinter $printer, array $printRequests ): array {
		$reflector = new ReflectionClass( $printer );
		$method = $reflector->getMethod( 'columns' );
		$method->setAccessible( true );

		return $method->invoke( $printer, $printRequests );
	}

	private function assertPrinterIsHtml( DataTablesResultPrinter $printer ): void {
		$reflector = new ReflectionClass( $printer );
		$isHTML = $reflector->getProperty( 'isHTML' );
		$isHTML->setAccessible( true );

		$this->assertTrue( $isHTML->getValue( $printer ) );
	}

	private function assertEmbeddedTableConfig( string $html ): void {
		$config = $this->embeddedTableConfig( $html );

		$this->assertArrayHasKey( 'context', $config );
		$this->assertTrue( $config['ajax'] );
		$this->assertTrue( $config['serverSide'] );
		$this->assertSame( 25, $config['options']['pageLength'] );
		$this->assertTrue( $config['options']['responsive'] );
	}

	private function embeddedTableConfig( string $html ): array {
		$this->assertMatchesRegularExpression( '/data-sdt-config="([^"]+)"/', $html );
		preg_match( '/data-sdt-config="([^"]+)"/', $html, $matches );

		$config = json_decode(
			html_entity_decode( $matches[1], ENT_QUOTES ),
			true
		);

		$this->assertIsArray( $config );

		return $config;
	}
}
