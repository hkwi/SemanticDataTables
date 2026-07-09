<?php

namespace SMWDataTables\DataTables;

use SMW\Store;
use SMW\Query\PrintRequest;
use SMWQueryProcessor;

final class QueryRunner {

	public function __construct(
		private readonly Store $store
	) {
	}

	public function run( array $context, DataTablesRequest $request ): array {
		return $this->runServerSide( $context, $request );
	}

	public function runServerSide( array $context, DataTablesRequest $request ): array {
		return $this->runServerSideQuery( $context, $request, false );
	}

	public function runExport( array $context, DataTablesRequest $request ): array {
		return $this->runServerSideQuery( $context, $request, true );
	}

	private function runServerSideQuery( array $context, DataTablesRequest $request, bool $exportAll ): array {
		$printRequests = ( new PrintRequestFactory() )->newPrintRequests( $context['printouts'] ?? [] );
		$baseConditions = (string)( $context['conditions'] ?? '' );
		$searchBuilderConditions = $request->hasSearchBuilder()
			? ( new SearchBuilderConditionBuilder( $context, $request ) )->conditions()
			: '';
		$filterSearchBuilderRows = $request->hasSearchBuilder() && $searchBuilderConditions === null;
		$queryConditions = $searchBuilderConditions === null
			? $baseConditions
			: $this->appendConditions( $baseConditions, $searchBuilderConditions );
		$hasRowFilters = $request->searchValue !== '' || $filterSearchBuilderRows;
		$recordsTotal = $this->countResults( $context, $baseConditions );

		if ( $searchBuilderConditions !== '' && !$hasRowFilters ) {
			$recordsFiltered = $this->countResults( $context, $queryConditions );
		} else {
			$recordsFiltered = $hasRowFilters ? null : $recordsTotal;
		}

		$fullFetchLimit = $recordsFiltered ?? $recordsTotal;
		$parameters = $exportAll || $hasRowFilters
			? $this->filteredServerSideParameters( $context, $request, $fullFetchLimit )
			: $this->serverSideParameters( $context, $request );
		$queryParams = SMWQueryProcessor::getProcessedParams( $parameters, [] );

		$query = SMWQueryProcessor::createQuery(
			$queryConditions,
			$queryParams,
			SMWQueryProcessor::INLINE_QUERY,
			'',
			$printRequests
		);
		if ( $exportAll || $hasRowFilters ) {
			$query->setUnboundLimit( $this->clientSideLimit( $fullFetchLimit ) );
		}

		$result = $this->store->getQueryResult( $query );
		$rows = ( new RowFormatter( $this->linkParameter( $parameters ) ) )
			->rows( $result, $printRequests );

		if ( $hasRowFilters ) {
			$rows = $this->filterRows( $rows, $request, $context, $filterSearchBuilderRows );
			$recordsFiltered = count( $rows );
			if ( !$exportAll ) {
				$rows = $this->pageRows( $rows, $request );
			}
		}

		return [
			'draw' => $request->draw,
			'recordsTotal' => $recordsTotal,
			'recordsFiltered' => $recordsFiltered ?? $recordsTotal,
			'data' => $rows,
		];
	}

	public function runClientSide( array $context ): array {
		$conditions = (string)( $context['conditions'] ?? '' );
		$total = $this->countResults( $context, $conditions );
		$parameters = $this->clientSideParameters( $context, $total );
		$queryParams = SMWQueryProcessor::getProcessedParams( $parameters, [] );
		$printRequests = ( new PrintRequestFactory() )->newPrintRequests( $context['printouts'] ?? [] );

		$query = SMWQueryProcessor::createQuery(
			$conditions,
			$queryParams,
			SMWQueryProcessor::INLINE_QUERY,
			'',
			$printRequests
		);
		$query->setUnboundLimit( $this->clientSideLimit( $total ) );

		$result = $this->store->getQueryResult( $query );

		return [
			'data' => ( new RowFormatter( $this->linkParameter( $parameters ) ) )
				->rows( $result, $printRequests ),
		];
	}

	private function serverSideParameters( array $context, DataTablesRequest $request ): array {
		$parameters = $this->queryParameters( $context );
		$sort = $request->sortColumns( $this->sortColumnNames( $context ) );

		$parameters['format'] = 'datatables-native';
		$parameters['offset'] = $request->start;
		$parameters['limit'] = $request->length < 0 ? 10000 : $request->length;

		if ( $sort ) {
			$parameters['sort'] = implode( ',', array_column( $sort, 'name' ) );
			$parameters['order'] = implode( ',', array_column( $sort, 'dir' ) );
		}

		return $parameters;
	}

	private function sortColumnNames( array $context ): array {
		$names = [];
		$previousNames = [];

		foreach ( $context['printouts'] ?? [] as $index => $printout ) {
			if ( !is_array( $printout ) ) {
				continue;
			}

			$configured = $this->configuredSortName( $printout );
			if ( $configured !== null ) {
				$names[$index] = $configured;
				$previousNames[] = $configured;
				continue;
			}

			$mode = (int)( $printout['mode'] ?? PrintRequest::PRINT_PROP );
			if ( $mode === PrintRequest::PRINT_THIS ) {
				$names[$index] = $index === 0 ? '' : null;
				$previousNames[] = $names[$index];
				continue;
			}

			$propertyKey = (string)( $printout['propertyKey'] ?? '' );
			$label = (string)( $printout['label'] ?? '' );
			$canonicalLabel = (string)( $printout['canonicalLabel'] ?? '' );
			$parameters = is_array( $printout['parameters'] ?? null ) ? $printout['parameters'] : [];
			$isDerivedRepeatedProperty = $propertyKey !== ''
				&& isset( $parameters['template'] )
				&& in_array( $propertyKey, $previousNames, true );

			if ( $isDerivedRepeatedProperty ) {
				$names[$index] = null;
			} else {
				$names[$index] = $propertyKey !== ''
					? $propertyKey
					: ( $canonicalLabel !== '' ? $canonicalLabel : ( $label !== '' ? $label : null ) );
			}

			$previousNames[] = $names[$index];
		}

		return $names;
	}

	private function configuredSortName( array $printout ): ?string {
		$parameters = is_array( $printout['parameters'] ?? null ) ? $printout['parameters'] : [];

		foreach ( [ 'datatables-columns.name', 'datatables-sort', 'datatables-sort-property' ] as $key ) {
			if ( array_key_exists( $key, $parameters ) && is_string( $parameters[$key] ) ) {
				return trim( $parameters[$key] );
			}
		}

		return null;
	}

	private function clientSideParameters( array $context, int $total ): array {
		$parameters = $this->queryParameters( $context );

		$parameters['format'] = 'datatables-native';
		$parameters['limit'] = $this->clientSideLimit( $total );
		unset( $parameters['offset'] );

		return $parameters;
	}

	private function clientSideLimit( int $total ): int {
		return $total > 0 ? $total : 10000;
	}

	private function filteredServerSideParameters( array $context, DataTablesRequest $request, int $total ): array {
		$parameters = $this->serverSideParameters( $context, $request );

		$parameters['offset'] = 0;
		$parameters['limit'] = $this->clientSideLimit( $total );

		return $parameters;
	}

	private function linkParameter( array $parameters ): ?string {
		return is_string( $parameters['link'] ?? null ) ? $parameters['link'] : null;
	}

	private function countResults( array $context, string $conditions ): int {
		$queryParams = SMWQueryProcessor::getProcessedParams( $this->countParameters( $context ), [] );
		$countQuery = SMWQueryProcessor::createQuery(
			$conditions,
			$queryParams,
			SMWQueryProcessor::INLINE_QUERY,
			'',
			[]
		);
		$countResult = $this->store->getQueryResult( $countQuery );
		$countValue = method_exists( $countResult, 'getCountValue' )
			? $countResult->getCountValue()
			: null;

		if ( $countValue !== null ) {
			return max( 0, (int)$countValue );
		}

		return max( 0, $countResult->getCount() );
	}

	private function appendConditions( string $baseConditions, string $additionalConditions ): string {
		if ( $additionalConditions === '' ) {
			return $baseConditions;
		}

		if ( trim( $baseConditions ) === '' ) {
			return $additionalConditions;
		}

		return rtrim( $baseConditions ) . "\n" . $additionalConditions;
	}

	private function filterRows(
		array $rows,
		DataTablesRequest $request,
		array $context = [],
		bool $filterSearchBuilderRows = true
	): array {
		$terms = $this->searchTerms( $request->searchValue );
		$searchBuilderMatcher = $filterSearchBuilderRows && $request->hasSearchBuilder()
			? new SearchBuilderMatcher( $context, $request )
			: null;

		if ( $terms === [] && $searchBuilderMatcher === null ) {
			return $rows;
		}

		return array_values( array_filter(
			$rows,
			function ( array $row ) use ( $request, $terms, $searchBuilderMatcher ): bool {
				$haystack = $this->searchableRowText( $row, $request );

				foreach ( $terms as $term ) {
					if ( strpos( $haystack, $term ) === false ) {
						return false;
					}
				}

				if ( $searchBuilderMatcher !== null && !$searchBuilderMatcher->matches( $row ) ) {
					return false;
				}

				return true;
			}
		) );
	}

	private function searchTerms( string $searchValue ): array {
		$search = $this->caseFold( trim( $searchValue ) );
		if ( $search === '' ) {
			return [];
		}

		return preg_split( '/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
	}

	private function searchableRowText( array $row, DataTablesRequest $request ): string {
		$parts = [];
		$columns = $request->columns === [] ? array_keys( $row ) : $request->columns;

		foreach ( $columns as $fallbackIndex => $column ) {
			if ( is_array( $column ) ) {
				if ( array_key_exists( 'searchable', $column ) && !$column['searchable'] ) {
					continue;
				}
				$index = $column['data'] ?? $fallbackIndex;
			} else {
				$index = $column;
			}

			if ( !is_int( $index ) && !ctype_digit( (string)$index ) ) {
				continue;
			}

			$cell = $row[(int)$index] ?? null;
			if ( !is_array( $cell ) ) {
				continue;
			}

			$text = (string)( $cell['filter'] ?? '' );
			if ( $text === '' ) {
				$text = html_entity_decode(
					strip_tags( (string)( $cell['display'] ?? '' ) ),
					ENT_QUOTES | ENT_HTML5,
					'UTF-8'
				);
			}
			$parts[] = $text;
		}

		return $this->caseFold( implode( ' ', $parts ) );
	}

	private function caseFold( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	private function pageRows( array $rows, DataTablesRequest $request ): array {
		if ( $request->length < 0 ) {
			return array_slice( $rows, $request->start );
		}

		return array_slice( $rows, $request->start, $request->length );
	}

	private function countParameters( array $context ): array {
		$parameters = $this->queryParameters( $context );

		unset( $parameters['limit'], $parameters['offset'], $parameters['sort'], $parameters['order'] );
		$parameters['format'] = 'count';

		return $parameters;
	}

	private function queryParameters( array $context ): array {
		$parameters = is_array( $context['parameters'] ?? null ) ? $context['parameters'] : [];

		if ( is_array( $parameters['sortkeys'] ?? null ) ) {
			$hasRawSort = array_key_exists( 'sort', $parameters ) || array_key_exists( 'order', $parameters );

			if ( !$hasRawSort && $parameters['sortkeys'] !== [] ) {
				$parameters['sort'] = implode( ',', array_keys( $parameters['sortkeys'] ) );
				$parameters['order'] = implode( ',', array_map(
					static fn ( $order ): string => strtolower( (string)$order ),
					array_values( $parameters['sortkeys'] )
				) );
			}

			unset( $parameters['sortkeys'] );
		}

		return $parameters;
	}
}
