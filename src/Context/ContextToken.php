<?php

namespace SMWDataTables\Context;

use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use RuntimeException;

final class ContextToken {

	public function encode( array $context ): string {
		$json = json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( !is_string( $json ) ) {
			throw new RuntimeException( 'Unable to encode DataTables context.' );
		}

		$body = $this->base64UrlEncode( $json );

		return $body . '.' . $this->sign( $body );
	}

	public function decode( string $token ): array {
		$parts = explode( '.', $token, 2 );

		if ( count( $parts ) !== 2 ) {
			throw new InvalidArgumentException( 'Malformed context token.' );
		}

		[ $body, $signature ] = $parts;
		if ( !hash_equals( $this->sign( $body ), $signature ) ) {
			throw new InvalidArgumentException( 'Invalid context token signature.' );
		}

		$payload = json_decode( $this->base64UrlDecode( $body ), true );
		if ( !is_array( $payload ) ) {
			throw new InvalidArgumentException( 'Context token payload is not an object.' );
		}

		return $payload;
	}

	private function sign( string $body ): string {
		return hash_hmac( 'sha256', $body, $this->getSecret() );
	}

	private function getSecret(): string {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$secret = $config->get( 'SecretKey' ) ?: ( $GLOBALS['wgSecretKey'] ?? '' );

		if ( !is_string( $secret ) || $secret === '' ) {
			throw new RuntimeException( 'Cannot sign DataTables context without $wgSecretKey.' );
		}

		return $secret;
	}

	private function base64UrlEncode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	private function base64UrlDecode( string $value ): string {
		$padding = strlen( $value ) % 4;
		if ( $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		$decoded = base64_decode( strtr( $value, '-_', '+/' ), true );
		if ( !is_string( $decoded ) ) {
			throw new InvalidArgumentException( 'Context token payload is not valid base64.' );
		}

		return $decoded;
	}
}
