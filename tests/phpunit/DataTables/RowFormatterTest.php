<?php

namespace SMWDataTables\Tests\DataTables;

use SMW\Query\PrintRequest;
use SMW\Query\QueryResult;
use SMWDataTables\DataTables\RowFormatter;

/**
 * @covers \SMWDataTables\DataTables\RowFormatter
 * @group semanticdatatables
 * @group semantic-mediawiki
 */
class RowFormatterTest extends \MediaWikiIntegrationTestCase {

	public function testRowsPassesLinkerWhenLinksAreEnabled(): void {
		$calls = [];

		$rows = ( new RowFormatter( 'all' ) )->rows(
			$this->queryResult( [
				$this->field( [
					$this->dataValue( $calls ),
				] ),
			] ),
			[
				new PrintRequest( PrintRequest::PRINT_THIS, 'Title' ),
			]
		);

		$this->assertStringContainsString( '<a ', $rows[0][0]['display'] );
		$this->assertSame( SMW_OUTPUT_HTML, $calls[0]['format'] );
		$this->assertNotNull( $calls[0]['linker'] );
	}

	public function testRowsSuppressesLinkerWhenLinksAreDisabled(): void {
		$calls = [];

		$rows = ( new RowFormatter( 'none' ) )->rows(
			$this->queryResult( [
				$this->field( [
					$this->dataValue( $calls ),
				] ),
			] ),
			[
				new PrintRequest( PrintRequest::PRINT_THIS, 'Title' ),
			]
		);

		$this->assertSame( 'Page', $rows[0][0]['display'] );
		$this->assertSame( SMW_OUTPUT_HTML, $calls[0]['format'] );
		$this->assertNull( $calls[0]['linker'] );
	}

	public function testSubjectLinkingDoesNotLinkPropertyColumns(): void {
		$subjectCalls = [];
		$propertyCalls = [];

		$rows = ( new RowFormatter( 'subject' ) )->rows(
			$this->queryResult( [
				$this->field( [
					$this->dataValue( $subjectCalls ),
				] ),
				$this->field( [
					$this->dataValue( $propertyCalls ),
				] ),
			] ),
			[
				new PrintRequest( PrintRequest::PRINT_THIS, 'Title' ),
				new PrintRequest( PrintRequest::PRINT_PROP, '関連ページ' ),
			]
		);

		$this->assertStringContainsString( '<a ', $rows[0][0]['display'] );
		$this->assertSame( 'Page', $rows[0][1]['display'] );
		$this->assertNotNull( $subjectCalls[0]['linker'] );
		$this->assertNull( $propertyCalls[0]['linker'] );
	}

	private function queryResult( array $rowFields ): QueryResult {
		$queryResult = $this->getMockBuilder( QueryResult::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getPrintRequests', 'getNext' ] )
			->getMock();

		$queryResult->method( 'getPrintRequests' )
			->willReturn( [] );

		$queryResult->method( 'getNext' )
			->willReturnOnConsecutiveCalls( $rowFields, false );

		return $queryResult;
	}

	private function field( array $dataValues ): object {
		return new class( $dataValues ) {
			private array $dataValues;

			public function __construct( array $dataValues ) {
				$this->dataValues = $dataValues;
			}

			public function getNextDataValue() {
				return array_shift( $this->dataValues ) ?? false;
			}
		};
	}

	private function dataValue( array &$calls ): object {
		return new class( $calls ) {
			private $calls;

			public function __construct( array &$calls ) {
				$this->calls =& $calls;
			}

			public function getShortText( int $format, $linker = null ): string {
				$this->calls[] = [
					'format' => $format,
					'linker' => $linker,
				];

				return $linker === null
					? 'Page'
					: '<a href="/index.php/Page">Page</a>';
			}
		};
	}
}
