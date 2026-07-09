<?php

namespace SMWDataTables\DataTables;

use SMW\DataValueFactory;
use SMW\DataValues\PropertyChainValue;
use SMW\Query\PrintRequest;

final class PrintRequestFactory {

	public function newPrintRequests( array $printouts ): array {
		$factory = DataValueFactory::getInstance();
		$requests = [];

		foreach ( $printouts as $printout ) {
			$mode = (int)( $printout['mode'] ?? PrintRequest::PRINT_PROP );
			$label = (string)( $printout['label'] ?? '' );
			$propertyKey = (string)( $printout['propertyKey'] ?? '' );
			$canonicalLabel = (string)( $printout['canonicalLabel'] ?? '' );
			$key = $propertyKey !== '' ? $propertyKey : ( $canonicalLabel !== '' ? $canonicalLabel : $label );
			$data = null;

			if ( $mode === PrintRequest::PRINT_PROP ) {
				$data = $factory->newPropertyValueByLabel( $key );
				if ( $this->isInversePrintout( $printout ) && method_exists( $data, 'setInverse' ) ) {
					$data->setInverse( true );
				}
			} elseif ( $mode === PrintRequest::PRINT_CHAIN ) {
				$data = $factory->newDataValueByType( PropertyChainValue::TYPE_ID );
				$data->setUserValue( $key );
			}

			$requests[] = new PrintRequest(
				$mode,
				$label,
				$data,
				(string)( $printout['outputFormat'] ?? '' ),
				is_array( $printout['parameters'] ?? null ) ? $printout['parameters'] : []
			);
		}

		return $requests;
	}

	private function isInversePrintout( array $printout ): bool {
		if ( array_key_exists( 'inverse', $printout ) ) {
			$inverse = $printout['inverse'];
			return $inverse === true
				|| $inverse === 1
				|| ( is_string( $inverse ) && in_array( strtolower( $inverse ), [ '1', 'true' ], true ) );
		}

		foreach ( [ 'canonicalLabel', 'propertyKey' ] as $key ) {
			$value = $printout[$key] ?? '';
			if ( is_string( $value ) && str_starts_with( $value, '-' ) ) {
				return true;
			}
		}

		return false;
	}
}
