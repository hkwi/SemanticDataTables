<?php

namespace SMWDataTables\Tests;

/**
 * @coversNothing
 * @group semanticdatatables
 */
class ExtensionJsonTest extends \PHPUnit\Framework\TestCase {

	public function testResourceModulesResolveFromExtensionDirectory(): void {
		$extensionRoot = dirname( __DIR__, 2 );
		$extensionJson = json_decode(
			file_get_contents( $extensionRoot . '/extension.json' ),
			true
		);

		$this->assertSame(
			[
				'localBasePath' => '',
				'remoteExtPath' => 'SemanticDataTables',
			],
			$extensionJson['ResourceFileModulePaths'] ?? null
		);

		foreach ( $extensionJson['ResourceModules'] as $module ) {
			foreach ( [ 'scripts', 'styles' ] as $assetType ) {
				foreach ( $module[$assetType] ?? [] as $assetPath ) {
					$this->assertFileExists( $extensionRoot . '/' . $assetPath );
				}
			}
		}
	}

	public function testSemanticDataTablesStylesUseStyleOnlyModule(): void {
		$extensionRoot = dirname( __DIR__, 2 );
		$extensionJson = json_decode(
			file_get_contents( $extensionRoot . '/extension.json' ),
			true
		);
		$modules = $extensionJson['ResourceModules'];

		$this->assertArrayHasKey( 'ext.semanticDataTables.styles', $modules );
		$this->assertArrayNotHasKey( 'scripts', $modules['ext.semanticDataTables.styles'] );
		$this->assertContains(
			'resources/vendor/datatables/dataTables.dataTables.min.css',
			$modules['ext.semanticDataTables.styles']['styles']
		);
		$this->assertContains(
			'resources/vendor/datatables/responsive.dataTables.min.css',
			$modules['ext.semanticDataTables.styles']['styles']
		);
		$this->assertContains(
			'resources/ext.semanticDataTables.less',
			$modules['ext.semanticDataTables.styles']['styles']
		);
		$this->assertContains(
			'resources/vendor/datatables/dataTables.responsive.min.js',
			$modules['ext.semanticDataTables.vendor']['scripts']
		);
		$this->assertContains(
			'resources/vendor/datatables/responsive.dataTables.min.js',
			$modules['ext.semanticDataTables.vendor']['scripts']
		);
		$this->assertContains(
			'ext.semanticDataTables.styles',
			$modules['ext.semanticDataTables']['dependencies']
		);
		$this->assertArrayNotHasKey( 'styles', $modules['ext.semanticDataTables.vendor'] );
	}
}
