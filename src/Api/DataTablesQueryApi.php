<?php

namespace SMWDataTables\Api;

use ApiBase;
use SMW\Services\ServicesFactory;
use SMWDataTables\Context\ContextToken;
use SMWDataTables\DataTables\DataTablesRequest;
use SMWDataTables\DataTables\QueryRunner;

final class DataTablesQueryApi extends ApiBase {

	public function execute(): void {
		$params = $this->extractRequestParams();
		$context = ( new ContextToken() )->decode( $params['context'] );
		$request = DataTablesRequest::fromArray( json_decode( $params['request'], true ) ?: [] );

		$runner = new QueryRunner( ServicesFactory::getInstance()->getStore() );
		if ( $params['exportAll'] ) {
			$response = $runner->runExport( $context, $request );
		} elseif ( $params['serverSide'] ) {
			$response = $runner->runServerSide( $context, $request );
		} else {
			$response = $runner->runClientSide( $context );
		}

		$result = $this->getResult();
		foreach ( [ 'draw', 'recordsTotal', 'recordsFiltered' ] as $key ) {
			if ( array_key_exists( $key, $response ) ) {
				$result->addValue( null, $key, $response[$key] );
			}
		}
		$result->addValue( null, 'data', $response['data'] );
	}

	protected function getAllowedParams(): array {
		return [
			'context' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'request' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'serverSide' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
			'exportAll' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
		];
	}

	protected function getExamples() {
		return false;
	}
}
