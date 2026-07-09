<?php

namespace SMWDataTables\DataTables;

final class SearchBuilderMatcher {

	private array $columnLabels;

	public function __construct(
		private readonly array $context,
		private readonly DataTablesRequest $request
	) {
		$this->columnLabels = $this->columnLabels( $context );
	}

	public function matches( array $row ): bool {
		if ( !$this->request->hasSearchBuilder() ) {
			return true;
		}

		return $this->matchesNode( $this->request->searchBuilder, $row );
	}

	private function matchesNode( array $node, array $row ): bool {
		if ( isset( $node['criteria'] ) && is_array( $node['criteria'] ) ) {
			$matches = [];

			foreach ( $node['criteria'] as $criterion ) {
				if ( is_array( $criterion ) ) {
					$matches[] = $this->matchesNode( $criterion, $row );
				}
			}

			if ( $matches === [] ) {
				return true;
			}

			if ( strtoupper( (string)( $node['logic'] ?? 'AND' ) ) === 'OR' ) {
				return in_array( true, $matches, true );
			}

			return !in_array( false, $matches, true );
		}

		return $this->matchesCriterion( $node, $row );
	}

	private function matchesCriterion( array $criterion, array $row ): bool {
		$condition = (string)( $criterion['condition'] ?? '' );
		if ( $condition === '' ) {
			return true;
		}

		$columnIndex = $this->columnIndex( $criterion );
		if ( $columnIndex === null ) {
			return true;
		}

		$text = $this->cellText( $row[$columnIndex] ?? null );
		$type = (string)( $criterion['type'] ?? '' );
		$values = $this->values( $criterion );

		if ( $this->isDateCriterion( $type, $condition ) ) {
			return $this->matchesDate( $condition, $text, $values );
		}

		if ( $this->isNumberCriterion( $type, $condition ) ) {
			return $this->matchesNumber( $condition, $text, $values );
		}

		return $this->matchesString( $condition, $text, $values );
	}

	private function matchesString( string $condition, string $text, array $values ): bool {
		$needle = $this->caseFold( (string)( $values[0] ?? '' ) );
		$haystack = $this->caseFold( $text );
		$isEmpty = trim( $text ) === '';

		switch ( $condition ) {
			case 'empty':
				return $isEmpty;
			case 'notEmpty':
				return !$isEmpty;
			case 'equals':
				return $haystack === $needle;
			case 'not':
				return $haystack !== $needle;
			case 'contains':
				return $needle === '' || strpos( $haystack, $needle ) !== false;
			case 'notContains':
			case 'without':
				return $needle !== '' && strpos( $haystack, $needle ) === false;
			case 'startsWith':
				return $needle === '' || str_starts_with( $haystack, $needle );
			case 'notStartsWith':
				return $needle !== '' && !str_starts_with( $haystack, $needle );
			case 'endsWith':
				return $needle === '' || str_ends_with( $haystack, $needle );
			case 'notEndsWith':
				return $needle !== '' && !str_ends_with( $haystack, $needle );
			default:
				return true;
		}
	}

	private function matchesNumber( string $condition, string $text, array $values ): bool {
		$number = $this->numberValue( $text );
		$first = $this->numberValue( (string)( $values[0] ?? '' ) );
		$second = $this->numberValue( (string)( $values[1] ?? '' ) );
		$isEmpty = trim( $text ) === '';

		switch ( $condition ) {
			case 'empty':
				return $isEmpty;
			case 'notEmpty':
				return !$isEmpty;
			case 'equals':
				return $number !== null && $first !== null && $number === $first;
			case 'not':
				return $number !== null && $first !== null && $number !== $first;
			case 'gt':
				return $number !== null && $first !== null && $number > $first;
			case 'gte':
				return $number !== null && $first !== null && $number >= $first;
			case 'lt':
				return $number !== null && $first !== null && $number < $first;
			case 'lte':
				return $number !== null && $first !== null && $number <= $first;
			case 'between':
				return $number !== null && $first !== null && $second !== null
					&& $number >= min( $first, $second )
					&& $number <= max( $first, $second );
			case 'notBetween':
				return $number !== null && $first !== null && $second !== null
					&& (
						$number < min( $first, $second )
						|| $number > max( $first, $second )
					);
			default:
				return $this->matchesString( $condition, $text, $values );
		}
	}

	private function matchesDate( string $condition, string $text, array $values ): bool {
		$date = $this->dateValue( $text );
		$first = $this->dateValue( (string)( $values[0] ?? '' ) );
		$second = $this->dateValue( (string)( $values[1] ?? '' ) );
		$isEmpty = trim( $text ) === '';

		switch ( $condition ) {
			case 'empty':
				return $isEmpty;
			case 'notEmpty':
				return !$isEmpty;
			case 'equals':
				return $date !== null && $first !== null && $date === $first;
			case 'not':
				return $date !== null && $first !== null && $date !== $first;
			case 'after':
			case 'gt':
				return $date !== null && $first !== null && $date > $first;
			case 'gte':
				return $date !== null && $first !== null && $date >= $first;
			case 'before':
			case 'lt':
				return $date !== null && $first !== null && $date < $first;
			case 'lte':
				return $date !== null && $first !== null && $date <= $first;
			case 'between':
				return $date !== null && $first !== null && $second !== null
					&& $date >= min( $first, $second )
					&& $date <= max( $first, $second );
			case 'notBetween':
				return $date !== null && $first !== null && $second !== null
					&& (
						$date < min( $first, $second )
						|| $date > max( $first, $second )
					);
			default:
				return $this->matchesString( $condition, $text, $values );
		}
	}

	private function columnIndex( array $criterion ): ?int {
		foreach ( [ 'dataIdx', 'origData', 'data' ] as $key ) {
			if ( array_key_exists( $key, $criterion ) && $this->isNonNegativeInteger( $criterion[$key] ) ) {
				return (int)$criterion[$key];
			}
		}

		$data = $this->normalColumnToken( (string)( $criterion['data'] ?? '' ) );
		if ( $data === '' ) {
			return null;
		}

		foreach ( $this->request->columns as $index => $column ) {
			if ( !is_array( $column ) ) {
				continue;
			}

			foreach ( [ 'title', 'name', 'data' ] as $key ) {
				if (
					array_key_exists( $key, $column )
					&& $this->normalColumnToken( (string)$column[$key] ) === $data
				) {
					return (int)$index;
				}
			}
		}

		foreach ( $this->columnLabels as $index => $labels ) {
			if ( in_array( $data, $labels, true ) ) {
				return $index;
			}
		}

		return null;
	}

	private function values( array $criterion ): array {
		$values = [];

		foreach ( [ 'value1', 'value2' ] as $key ) {
			if ( array_key_exists( $key, $criterion ) ) {
				$values[] = $this->scalarValue( $criterion[$key] );
			}
		}

		if ( $values === [] && array_key_exists( 'value', $criterion ) ) {
			$value = $criterion['value'];
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					$values[] = $this->scalarValue( $item );
				}
			} else {
				$values[] = $this->scalarValue( $value );
			}
		}

		return $values;
	}

	private function scalarValue( mixed $value ): string {
		if ( is_array( $value ) ) {
			return implode( ' ', array_map( [ $this, 'scalarValue' ], $value ) );
		}

		return (string)$value;
	}

	private function cellText( mixed $cell ): string {
		if ( is_array( $cell ) ) {
			$text = (string)( $cell['filter'] ?? '' );
			if ( $text !== '' ) {
				return $text;
			}

			return html_entity_decode(
				strip_tags( (string)( $cell['display'] ?? '' ) ),
				ENT_QUOTES | ENT_HTML5,
				'UTF-8'
			);
		}

		return $cell === null ? '' : (string)$cell;
	}

	private function numberValue( string $text ): ?float {
		$value = str_replace( [ ',', ' ' ], '', trim( $text ) );

		return is_numeric( $value ) ? (float)$value : null;
	}

	private function dateValue( string $text ): ?string {
		$value = trim( $text );
		if ( $value === '' ) {
			return null;
		}

		$timestamp = strtotime( $value );
		if ( $timestamp === false ) {
			return null;
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	private function isDateCriterion( string $type, string $condition ): bool {
		return str_contains( $type, 'date' )
			|| in_array( $condition, [ 'after', 'before' ], true );
	}

	private function isNumberCriterion( string $type, string $condition ): bool {
		return str_contains( $type, 'num' )
			|| in_array( $condition, [ 'gt', 'gte', 'lt', 'lte' ], true );
	}

	private function columnLabels( array $context ): array {
		$labels = [];

		foreach ( $context['printouts'] ?? [] as $index => $printout ) {
			if ( !is_array( $printout ) ) {
				continue;
			}

			foreach ( [ 'label', 'canonicalLabel', 'propertyKey' ] as $key ) {
				$label = $this->normalColumnToken( (string)( $printout[$key] ?? '' ) );
				if ( $label !== '' ) {
					$labels[(int)$index][] = $label;
				}
			}
		}

		return array_map( 'array_values', array_map( 'array_unique', $labels ) );
	}

	private function normalColumnToken( string $value ): string {
		return trim( html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	private function caseFold( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	private function isNonNegativeInteger( mixed $value ): bool {
		return ( is_int( $value ) && $value >= 0 )
			|| ( is_string( $value ) && ctype_digit( $value ) );
	}
}
