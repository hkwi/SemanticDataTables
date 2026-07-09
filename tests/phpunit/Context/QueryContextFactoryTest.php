<?php

namespace SMWDataTables\Tests\Context;

use SMW\DataValueFactory;
use SMW\DataValues\PropertyChainValue;
use SMW\Query\PrintRequest;
use SMW\Query\QueryResult;
use SMWDataTables\Context\QueryContextFactory;

/**
 * @covers \SMWDataTables\Context\QueryContextFactory
 * @group semanticdatatables
 * @group semantic-mediawiki
 */
class QueryContextFactoryTest extends \MediaWikiIntegrationTestCase {

	public function testPrintoutContextKeepsAliasAndCanonicalLabelSeparate(): void {
		$chain = DataValueFactory::getInstance()->newDataValueByType( PropertyChainValue::TYPE_ID );
		$chain->setUserValue( '-Has subobject.サンプル形' );
		$this->assertTrue( $chain->isValid() );

		$context = ( new QueryContextFactory() )->newContext(
			$this->newQueryResult( [
				new PrintRequest( PrintRequest::PRINT_CHAIN, 'サンプル形', $chain ),
			] ),
			[],
			[]
		);

		$this->assertSame( 'サンプル形', $context['printouts'][0]['label'] );
		$this->assertSame( '-Has subobject.サンプル形', $context['printouts'][0]['canonicalLabel'] );
		$this->assertSame( '-Has subobject.サンプル形', $context['printouts'][0]['propertyKey'] );
		$this->assertArrayHasKey( 'typeID', $context['printouts'][0] );
	}

	public function testPrintoutContextKeepsInversePropertyFlag(): void {
		$property = DataValueFactory::getInstance()->newPropertyValueByLabel( '-Has subobject' );
		$this->assertTrue( $property->isValid() );

		$context = ( new QueryContextFactory() )->newContext(
			$this->newQueryResult( [
				new PrintRequest( PrintRequest::PRINT_PROP, 'Title', $property ),
			] ),
			[],
			[]
		);

		$this->assertSame( 'Title', $context['printouts'][0]['label'] );
		$this->assertSame( '-Has subobject', $context['printouts'][0]['canonicalLabel'] );
		$this->assertSame( '_SOBJ', $context['printouts'][0]['propertyKey'] );
		$this->assertTrue( $context['printouts'][0]['inverse'] );
	}

	private function newQueryResult( array $printRequests ): QueryResult {
		$query = $this->getMockBuilder( \SMWQuery::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'toArray' ] )
			->getMock();

		$query->method( 'toArray' )
			->willReturn( [
				'conditions' => '[[Category:Test]]',
				'parameters' => [],
			] );

		$queryResult = $this->getMockBuilder( QueryResult::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getQuery', 'getPrintRequests' ] )
			->getMock();

		$queryResult->method( 'getQuery' )
			->willReturn( $query );

		$queryResult->method( 'getPrintRequests' )
			->willReturn( $printRequests );

		return $queryResult;
	}
}
