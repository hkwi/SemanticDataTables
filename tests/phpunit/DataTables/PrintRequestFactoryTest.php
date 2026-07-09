<?php

namespace SMWDataTables\Tests\DataTables;

use SMW\DataValues\PropertyValue;
use SMW\DataValues\PropertyChainValue;
use SMW\Query\PrintRequest;
use SMWDataTables\DataTables\PrintRequestFactory;

/**
 * @covers \SMWDataTables\DataTables\PrintRequestFactory
 * @group semanticdatatables
 * @group semantic-mediawiki
 */
class PrintRequestFactoryTest extends \MediaWikiIntegrationTestCase {

	public function testPrintRequestUsesPropertyKeyWhenLabelIsAlias(): void {
		$requests = ( new PrintRequestFactory() )->newPrintRequests( [
			[
				'mode' => PrintRequest::PRINT_PROP,
				'label' => '素材名',
				'propertyKey' => 'サンプル素材',
				'outputFormat' => '',
				'parameters' => [],
			],
		] );

		$this->assertCount( 1, $requests );
		$this->assertSame( '素材名', $requests[0]->getCanonicalLabel() );

		$data = $requests[0]->getData();
		$this->assertInstanceOf( PropertyValue::class, $data );
		$this->assertSame( 'サンプル素材', $data->getDataItem()->getKey() );
	}

	public function testPrintRequestUsesCanonicalLabelWhenPropertyKeyIsMissing(): void {
		$requests = ( new PrintRequestFactory() )->newPrintRequests( [
			[
				'mode' => PrintRequest::PRINT_CHAIN,
				'label' => 'サンプル形',
				'canonicalLabel' => '-Has subobject.サンプル形',
				'propertyKey' => '',
				'outputFormat' => '',
				'parameters' => [],
			],
		] );

		$this->assertCount( 1, $requests );
		$this->assertSame( 'サンプル形', $requests[0]->getLabel() );

		$data = $requests[0]->getData();
		$this->assertInstanceOf( PropertyChainValue::class, $data );
		$this->assertSame( '-Has subobject.サンプル形', $data->getDataItem()->getString() );
	}

	public function testPrintRequestRestoresInverseProperty(): void {
		$requests = ( new PrintRequestFactory() )->newPrintRequests( [
			[
				'mode' => PrintRequest::PRINT_PROP,
				'label' => 'Title',
				'canonicalLabel' => '-Has subobject',
				'propertyKey' => '_SOBJ',
				'inverse' => true,
				'outputFormat' => '',
				'parameters' => [],
			],
		] );

		$this->assertCount( 1, $requests );
		$this->assertSame( 'Title', $requests[0]->getLabel() );

		$data = $requests[0]->getData();
		$this->assertInstanceOf( PropertyValue::class, $data );
		$this->assertSame( '_SOBJ', $data->getDataItem()->getKey() );
		$this->assertTrue( $data->getDataItem()->isInverse() );
	}

	public function testPrintRequestRestoresLegacyInversePropertyFromCanonicalLabel(): void {
		$requests = ( new PrintRequestFactory() )->newPrintRequests( [
			[
				'mode' => PrintRequest::PRINT_PROP,
				'label' => 'Title',
				'canonicalLabel' => '-Has subobject',
				'propertyKey' => '_SOBJ',
				'outputFormat' => '',
				'parameters' => [],
			],
		] );

		$data = $requests[0]->getData();
		$this->assertInstanceOf( PropertyValue::class, $data );
		$this->assertTrue( $data->getDataItem()->isInverse() );
	}
}
