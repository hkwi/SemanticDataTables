<?php

namespace SMWDataTables;

use SMWDataTables\ResultPrinters\DataTablesResultPrinter;

final class Extension {

	public static function onExtensionFunction(): void {
		$GLOBALS['smwgResultFormats']['datatables-native'] = DataTablesResultPrinter::class;

		if ( isset( $GLOBALS['smwgResultAliases'] ) ) {
			$GLOBALS['smwgResultAliases']['datatables-native'] = [
				'datatable-native',
				'semantic datatables',
			];
		}
	}
}

