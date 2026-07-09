<?php

namespace SMWDataTables\DataTables;

final class DataTablesRequest {

	public function __construct(
		public readonly int $draw,
		public readonly int $start,
		public readonly int $length,
		public readonly array $columns,
		public readonly array $order,
		public readonly string $searchValue,
		public readonly array $searchBuilder = []
	) {
	}

	public static function fromArray( array $request ): self {
		return new self(
			(int)( $request['draw'] ?? 0 ),
			max( 0, (int)( $request['start'] ?? 0 ) ),
			(int)( $request['length'] ?? 25 ),
			is_array( $request['columns'] ?? null ) ? $request['columns'] : [],
			is_array( $request['order'] ?? null ) ? $request['order'] : [],
			(string)( $request['search']['value'] ?? '' ),
			is_array( $request['searchBuilder'] ?? null ) ? $request['searchBuilder'] : []
		);
	}

	public function hasFilters(): bool {
		return $this->searchValue !== '' || $this->hasSearchBuilder();
	}

	public function hasSearchBuilder(): bool {
		return $this->hasSearchBuilderNode( $this->searchBuilder );
	}

	public function sortColumns( array $fallbackNames = [] ): array {
		$sort = [];

		foreach ( $this->order as $orderSpec ) {
			$columnIndex = (int)( $orderSpec['column'] ?? -1 );
			$column = $this->columns[$columnIndex] ?? null;
			$name = $this->columnName(
				is_array( $column ) ? $column : [],
				is_array( $orderSpec ) ? $orderSpec : [],
				$fallbackNames,
				$columnIndex
			);

			if ( $name === null ) {
				continue;
			}

			$sort[] = [
				'name' => $name,
				'dir' => strtolower( (string)( $orderSpec['dir'] ?? 'asc' ) ) === 'desc' ? 'desc' : 'asc',
			];
		}

		return $sort;
	}

	private function columnName( array $column, array $orderSpec, array $fallbackNames, int $columnIndex ): ?string {
		$hasFallback = array_key_exists( $columnIndex, $fallbackNames );
		$fallback = $hasFallback ? $fallbackNames[$columnIndex] : null;

		if ( $hasFallback ) {
			return is_string( $fallback ) ? $fallback : null;
		}

		if ( array_key_exists( 'name', $column ) ) {
			return (string)$column['name'];
		}

		if ( array_key_exists( 'name', $orderSpec ) ) {
			return (string)$orderSpec['name'];
		}

		return null;
	}

	private function hasSearchBuilderNode( array $node ): bool {
		if ( isset( $node['criteria'] ) && is_array( $node['criteria'] ) ) {
			foreach ( $node['criteria'] as $criterion ) {
				if ( is_array( $criterion ) && $this->hasSearchBuilderNode( $criterion ) ) {
					return true;
				}
			}

			return false;
		}

		return isset( $node['condition'] )
			&& (
				array_key_exists( 'dataIdx', $node )
				|| array_key_exists( 'origData', $node )
				|| array_key_exists( 'data', $node )
			);
	}
}
