<?php

namespace SMWDataTables\DataTables;

use SMW\Query\PrintRequest;

final class SearchBuilderConditionBuilder {

	private array $columnLabels;

	public function __construct(
		private readonly array $context,
		private readonly DataTablesRequest $request
	) {
		$this->columnLabels = $this->columnLabels( $context );
	}

	public function conditions(): ?string {
		if ( !$this->request->hasSearchBuilder() ) {
			return '';
		}

		$conditions = $this->conditionsForNode( $this->request->searchBuilder );
		if ( $conditions === null ) {
			return null;
		}

		return implode( "\n", $conditions );
	}

	private function conditionsForNode( array $node ): ?array {
		if ( isset( $node['criteria'] ) && is_array( $node['criteria'] ) ) {
			if ( strtoupper( (string)( $node['logic'] ?? 'AND' ) ) !== 'AND' ) {
				return null;
			}

			$conditions = [];
			foreach ( $node['criteria'] as $criterion ) {
				if ( !is_array( $criterion ) ) {
					continue;
				}

				$criterionConditions = $this->conditionsForNode( $criterion );
				if ( $criterionConditions === null ) {
					return null;
				}

				array_push( $conditions, ...$criterionConditions );
			}

			return $conditions;
		}

		return $this->conditionsForCriterion( $node );
	}

	private function conditionsForCriterion( array $criterion ): ?array {
		$condition = (string)( $criterion['condition'] ?? '' );
		if ( $condition === '' ) {
			return [];
		}

		$property = $this->propertyForCriterion( $criterion );
		if ( $property === null ) {
			return null;
		}

		$values = $this->values( $criterion );
		$first = (string)( $values[0] ?? '' );
		$second = (string)( $values[1] ?? '' );

		switch ( $condition ) {
			case 'notEmpty':
				return [ $this->askCondition( $property, '+' ) ];
			case 'empty':
				return null;
			case 'equals':
				return $this->singleValueCondition( $property, '', $first );
			case 'not':
				return $this->singleValueCondition( $property, '!', $first );
			case 'contains':
				return $this->singleValueCondition( $property, '~*', $first, '*' );
			case 'notContains':
			case 'without':
				return $this->singleValueCondition( $property, '!~*', $first, '*' );
			case 'startsWith':
				return $this->singleValueCondition( $property, '~', $first, '*' );
			case 'notStartsWith':
				return $this->singleValueCondition( $property, '!~', $first, '*' );
			case 'endsWith':
				return $this->singleValueCondition( $property, '~*', $first );
			case 'notEndsWith':
				return $this->singleValueCondition( $property, '!~*', $first );
			case 'gt':
			case 'after':
				return $this->singleValueCondition( $property, '>', $first );
			case 'gte':
				return $this->singleValueCondition( $property, '>=', $first );
			case 'lt':
			case 'before':
				return $this->singleValueCondition( $property, '<', $first );
			case 'lte':
				return $this->singleValueCondition( $property, '<=', $first );
			case 'between':
				if ( !$this->isSafeValue( $first ) || !$this->isSafeValue( $second ) ) {
					return null;
				}

				return [
					$this->askCondition( $property, '>=' . $first ),
					$this->askCondition( $property, '<=' . $second ),
				];
			default:
				return null;
		}
	}

	private function singleValueCondition(
		string $property,
		string $operator,
		string $value,
		string $suffix = ''
	): ?array {
		if ( !$this->isSafeValue( $value ) ) {
			return null;
		}

		return [ $this->askCondition( $property, $operator . $value . $suffix ) ];
	}

	private function askCondition( string $property, string $comparatorAndValue ): string {
		return '[[' . $property . '::' . $comparatorAndValue . ']]';
	}

	private function propertyForCriterion( array $criterion ): ?string {
		$columnIndex = $this->columnIndex( $criterion );
		if ( $columnIndex === null ) {
			return null;
		}

		$printout = $this->context['printouts'][$columnIndex] ?? null;
		if ( !is_array( $printout ) ) {
			return null;
		}

		$mode = (int)( $printout['mode'] ?? PrintRequest::PRINT_PROP );
		$property = (string)( $printout['propertyKey'] ?? '' );
		$parameters = is_array( $printout['parameters'] ?? null ) ? $printout['parameters'] : [];

		if (
			$mode !== PrintRequest::PRINT_PROP
			|| $property === ''
			|| isset( $parameters['template'] )
			|| !$this->isSafeProperty( $property )
		) {
			return null;
		}

		return $property;
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

	private function isSafeProperty( string $property ): bool {
		return $property !== '' && strpbrk( $property, "[]|\n\r" ) === false;
	}

	private function isSafeValue( string $value ): bool {
		return $value !== '' && strpbrk( $value, "[]|\n\r" ) === false;
	}

	private function isNonNegativeInteger( mixed $value ): bool {
		return ( is_int( $value ) && $value >= 0 )
			|| ( is_string( $value ) && ctype_digit( $value ) );
	}
}
