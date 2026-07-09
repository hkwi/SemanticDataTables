<?php

namespace SMWDataTables\Context;

use SMW\DataValues\PropertyValue;
use SMW\DataValues\PropertyChainValue;
use SMW\Query\PrintRequest;
use SMW\Query\QueryResult;

final class QueryContextFactory {

	public function newContext( QueryResult $queryResult, array $params, array $options ): array {
		$query = $queryResult->getQuery();
		$ask = $query->toArray();

		return [
			'version' => 1,
			'conditions' => $ask['conditions'] ?? '',
			'parameters' => $this->queryParameters( $ask['parameters'] ?? [], $params ),
			'printouts' => $this->printouts( $queryResult->getPrintRequests() ),
			'options' => $options,
			'created' => time(),
		];
	}

	private function queryParameters( array $queryParameters, array $params ): array {
		foreach ( $params as $key => $value ) {
			if ( strpos( $key, 'datatables-' ) === 0 ) {
				continue;
			}

			if ( is_string( $value ) || is_int( $value ) || is_bool( $value ) ) {
				$queryParameters[$key] = $value;
			}
		}

		return $queryParameters;
	}

	/**
	 * @param PrintRequest[] $printRequests
	 */
	private function printouts( array $printRequests ): array {
		$printouts = [];

		foreach ( $printRequests as $printRequest ) {
			$data = $printRequest->getData();

			$printouts[] = [
				'mode' => $printRequest->getMode(),
				'label' => $this->displayLabel( $printRequest ),
				'canonicalLabel' => $printRequest->getCanonicalLabel(),
				'propertyKey' => $this->propertyKey( $printRequest ),
				'inverse' => $this->isInverseProperty( $printRequest ),
				'typeID' => $this->typeID( $printRequest ),
				'outputFormat' => $printRequest->getOutputFormat(),
				'parameters' => $printRequest->getParameters(),
			];
		}

		return $printouts;
	}

	private function displayLabel( PrintRequest $printRequest ): string {
		$label = $printRequest->getLabel();

		return $label === '' ? $printRequest->getCanonicalLabel() : $label;
	}

	private function propertyKey( PrintRequest $printRequest ): ?string {
		$data = $printRequest->getData();

		if ( $data instanceof PropertyValue ) {
			return $data->getDataItem()->getKey();
		}

		if ( $data instanceof PropertyChainValue ) {
			$dataItem = $data->getDataItem();
			return is_object( $dataItem ) && method_exists( $dataItem, 'getString' )
				? $dataItem->getString()
				: null;
		}

		return null;
	}

	private function isInverseProperty( PrintRequest $printRequest ): bool {
		if ( $printRequest->getMode() !== PrintRequest::PRINT_PROP ) {
			return false;
		}

		$data = $printRequest->getData();
		if ( !$data instanceof PropertyValue ) {
			return false;
		}

		$dataItem = $data->getDataItem();

		return is_object( $dataItem )
			&& method_exists( $dataItem, 'isInverse' )
			&& $dataItem->isInverse();
	}

	private function typeID( PrintRequest $printRequest ): string {
		if ( $printRequest->getMode() === PrintRequest::PRINT_THIS ) {
			return '_wpg';
		}

		if ( method_exists( $printRequest, 'getTypeID' ) ) {
			return (string)$printRequest->getTypeID();
		}

		return '';
	}
}
