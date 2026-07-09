<?php

namespace SMWDataTables\ResultPrinters;

use MediaWiki\Html\Html;
use RequestContext;
use SMW\DataValues\PropertyChainValue;
use SMW\DataValues\PropertyValue;
use SMW\Query\PrintRequest;
use SMW\Query\QueryResult;
use SMW\Query\ResultPrinters\ResultPrinter;
use SMW\Services\ServicesFactory;
use SMWDataTables\Context\ContextToken;
use SMWDataTables\Context\QueryContextFactory;
use SMWDataTables\DataTables\QueryRunner;
use SMWOutputs;

final class DataTablesResultPrinter extends ResultPrinter {

	public function getName(): string {
		return $this->msg( 'semanticdatatables-printername' )->text();
	}

	public function getParamDefinitions( array $definitions ): array {
		$params = parent::getParamDefinitions( $definitions );

		$params['class'] = [
			'type' => 'string',
			'message' => 'semanticdatatables-paramdesc-class',
			'default' => '',
		];

		$params['noajax'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-noajax',
			'default' => false,
		];

		$params['datatables-pageLength'] = [
			'type' => 'integer',
			'message' => 'semanticdatatables-paramdesc-datatables-pageLength',
			'default' => 25,
		];

		$params['datatables-lengthMenu'] = [
			'type' => 'string',
			'message' => 'semanticdatatables-paramdesc-datatables-lengthMenu',
			'default' => '10,25,50,100',
		];

		$params['datatables-searching'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-datatables-searching',
			'default' => true,
		];

		$params['datatables-ordering'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-datatables-ordering',
			'default' => true,
		];

		$params['datatables-paging'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-datatables-paging',
			'default' => true,
		];

		$params['datatables-autoWidth'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-datatables-autoWidth',
			'default' => false,
		];

		$params['datatables-scrollX'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-datatables-scrollX',
			'default' => false,
		];

		$params['datatables-responsive'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-datatables-responsive',
			'default' => true,
		];

		$params['datatables-buttons'] = [
			'type' => 'string',
			'message' => 'semanticdatatables-paramdesc-datatables-buttons',
			'default' => '',
		];

		$params['datatables-dom'] = [
			'type' => 'string',
			'message' => 'semanticdatatables-paramdesc-datatables-dom',
			'default' => '',
		];

		$params['datatables-ajax'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-datatables-ajax',
			'default' => false,
		];

		$params['datatables-serverSide'] = [
			'type' => 'boolean',
			'message' => 'semanticdatatables-paramdesc-datatables-serverSide',
			'default' => false,
		];

		return $params;
	}

	protected function getResultText( QueryResult $res, $outputMode ): string {
		$this->isHTML = true;

		RequestContext::getMain()->getOutput()->addModuleStyles( 'ext.semanticDataTables.styles' );
		SMWOutputs::requireResource( 'ext.semanticDataTables' );

		$id = 'semantic-datatables-' . bin2hex( random_bytes( 8 ) );
		$ajax = $this->ajaxEnabled();
		$options = $this->dataTablesOptions();
		$context = ( new QueryContextFactory() )->newContext( $res, $this->params, $options );
		$config = [
			'context' => ( new ContextToken() )->encode( $context ),
			'ajax' => $ajax,
			'columns' => $this->columns( $res->getPrintRequests() ),
			'serverSide' => $ajax,
			'options' => $options,
		];
		$bodyRows = $ajax
			? []
			: ( new QueryRunner( ServicesFactory::getInstance()->getStore() ) )->runClientSide( $context )['data'];

		RequestContext::getMain()->getOutput()->addJsConfigVars( [ $id => $config ] );

		return Html::rawElement(
			'div',
			[ 'class' => 'semantic-datatables-container datatables-container' ],
			$this->tableHtml( $id, $res->getPrintRequests(), $bodyRows, $ajax, $config )
		);
	}

	private function dataTablesOptions(): array {
		$options = [
			'pageLength' => (int)$this->params['datatables-pageLength'],
			'lengthMenu' => $this->intList( $this->params['datatables-lengthMenu'] ),
			'searching' => (bool)$this->params['datatables-searching'],
			'ordering' => (bool)$this->params['datatables-ordering'],
			'paging' => (bool)$this->params['datatables-paging'],
			'autoWidth' => (bool)$this->params['datatables-autoWidth'],
			'scrollX' => (bool)$this->params['datatables-scrollX'],
			'responsive' => (bool)$this->params['datatables-responsive'],
		];

		$buttons = $this->stringList( $this->params['datatables-buttons'] );
		$dom = trim( (string)$this->params['datatables-dom'] );

		if ( $buttons !== [] ) {
			$options['buttons'] = $buttons;
			$options['dom'] = $dom === '' ? 'Blfrtip' : $dom;
		} elseif ( $dom !== '' ) {
			$options['dom'] = $dom;
		}

		return $options;
	}

	/**
	 * @param PrintRequest[] $printRequests
	 */
	private function columns( array $printRequests ): array {
		$columns = [];
		$sortNames = [];

		foreach ( $printRequests as $index => $printRequest ) {
			$label = $this->displayLabel( $printRequest );
			$sortName = $this->sortName( $printRequest, $sortNames );
			$sortNames[] = $sortName;

			$column = [
				'data' => $index,
				'name' => $sortName ?? '',
				'title' => $label === '' ? '&nbsp;' : $label,
				'render' => [
					'_' => 'display',
					'display' => 'display',
					'filter' => 'filter',
					'sort' => 'sort',
				],
			];

			if ( $sortName === null ) {
				$column['orderable'] = false;
			}

			$type = $this->columnType( $printRequest );
			if ( $type !== '' ) {
				$column['type'] = $type;
			}

			$columns[] = $column;
		}

		return $columns;
	}

	private function displayLabel( PrintRequest $printRequest ): string {
		$label = $printRequest->getLabel();

		return $label === '' ? $printRequest->getCanonicalLabel() : $label;
	}

	private function sortName( PrintRequest $printRequest, array $previousSortNames = [] ): ?string {
		$configured = $this->configuredSortName( $printRequest->getParameters() );
		if ( $configured !== null ) {
			return $configured;
		}

		if ( $printRequest->getMode() === PrintRequest::PRINT_THIS ) {
			return $previousSortNames === [] ? '' : null;
		}

		$name = $this->propertyKey( $printRequest );
		if ( $name !== null ) {
			$parameters = $printRequest->getParameters();
			if ( isset( $parameters['template'] ) && in_array( $name, $previousSortNames, true ) ) {
				return null;
			}

			return $name;
		}

		return $printRequest->getCanonicalLabel();
	}

	private function configuredSortName( array $parameters ): ?string {
		foreach ( [ 'datatables-columns.name', 'datatables-sort', 'datatables-sort-property' ] as $key ) {
			if ( array_key_exists( $key, $parameters ) && is_string( $parameters[$key] ) ) {
				return trim( $parameters[$key] );
			}
		}

		return null;
	}

	private function propertyKey( PrintRequest $printRequest ): ?string {
		$data = $printRequest->getData();

		if ( $data instanceof PropertyValue ) {
			return $data->getDataItem()->getKey();
		}

		if ( $data instanceof PropertyChainValue ) {
			$dataItem = $data->getDataItem();
			if ( is_object( $dataItem ) && method_exists( $dataItem, 'getString' ) ) {
				return $dataItem->getString();
			}
		}

		return null;
	}

	private function columnType( PrintRequest $printRequest ): string {
		$parameters = $printRequest->getParameters();
		$type = $parameters['datatables-columns.type'] ?? $parameters['datatables-type'] ?? '';

		return is_string( $type ) ? trim( $type ) : '';
	}

	/**
	 * @param PrintRequest[] $printRequests
	 */
	private function tableHtml(
		string $id,
		array $printRequests,
		array $bodyRows,
		bool $ajax,
		array $config
	): string {
		$headers = '';

		foreach ( $printRequests as $printRequest ) {
			$label = $this->displayLabel( $printRequest );
			$headers .= $label === ''
				? Html::rawElement( 'th', [], '&nbsp;' )
				: Html::element( 'th', [], $label );
		}

		$body = $ajax
			? ''
			: Html::rawElement( 'tbody', [], $this->bodyRows( $bodyRows ) );

		return Html::rawElement(
			'table',
			[
				'id' => $id,
				'class' => trim( 'semantic-datatables display ' . $this->params['class'] ),
				'width' => '100%',
				'data-sdt-config' => $this->jsonAttribute( $config ),
			],
			Html::rawElement( 'thead', [], Html::rawElement( 'tr', [], $headers ) ) . $body
		);
	}

	private function jsonAttribute( array $value ): string {
		$json = json_encode(
			$value,
			JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG |
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		if ( !is_string( $json ) ) {
			throw new \RuntimeException( 'Unable to encode DataTables table configuration.' );
		}

		return $json;
	}

	private function bodyRows( array $rowsData ): string {
		$rows = '';

		foreach ( $rowsData as $row ) {
			$cells = '';

			foreach ( $row as $cell ) {
				$cells .= Html::rawElement(
					'td',
					[
						'data-search' => (string)( $cell['filter'] ?? '' ),
						'data-order' => (string)( $cell['sort'] ?? '' ),
					],
					(string)( $cell['display'] ?? '&nbsp;' )
				);
			}

			$rows .= Html::rawElement( 'tr', [], $cells );
		}

		return $rows;
	}

	private function intList( string $csv ): array {
		return array_map(
			'intval',
			preg_split( '/\s*,\s*/', $csv, -1, PREG_SPLIT_NO_EMPTY )
		);
	}

	private function stringList( string $csv ): array {
		return array_values( array_filter(
			array_map(
				'trim',
				preg_split( '/,/', $csv, -1, PREG_SPLIT_NO_EMPTY )
			),
			static function ( string $value ): bool {
				return $value !== '';
			}
		) );
	}

	private function ajaxEnabled(): bool {
		return empty( $this->params['noajax'] );
	}
}
