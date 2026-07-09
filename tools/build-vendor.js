'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const vendorDir = path.join( root, 'resources', 'vendor', 'datatables' );

const files = [
	{
		from: path.join( root, 'node_modules', 'datatables.net', 'js', 'dataTables.min.js' ),
		to: path.join( vendorDir, 'dataTables.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-dt', 'js', 'dataTables.dataTables.min.js' ),
		to: path.join( vendorDir, 'dataTables.dataTables.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-dt', 'css', 'dataTables.dataTables.min.css' ),
		to: path.join( vendorDir, 'dataTables.dataTables.min.css' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-datetime', 'dist', 'dataTables.dateTime.min.js' ),
		to: path.join( vendorDir, 'dataTables.dateTime.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-datetime', 'dist', 'dataTables.dateTime.min.css' ),
		to: path.join( vendorDir, 'dataTables.dateTime.min.css' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-buttons', 'js', 'dataTables.buttons.min.js' ),
		to: path.join( vendorDir, 'dataTables.buttons.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-buttons-dt', 'js', 'buttons.dataTables.min.js' ),
		to: path.join( vendorDir, 'buttons.dataTables.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-buttons', 'js', 'buttons.html5.min.js' ),
		to: path.join( vendorDir, 'buttons.html5.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-buttons', 'js', 'buttons.print.min.js' ),
		to: path.join( vendorDir, 'buttons.print.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-buttons', 'js', 'buttons.colVis.min.js' ),
		to: path.join( vendorDir, 'buttons.colVis.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-buttons-dt', 'css', 'buttons.dataTables.min.css' ),
		to: path.join( vendorDir, 'buttons.dataTables.min.css' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-responsive', 'js', 'dataTables.responsive.min.js' ),
		to: path.join( vendorDir, 'dataTables.responsive.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-responsive-dt', 'js', 'responsive.dataTables.min.js' ),
		to: path.join( vendorDir, 'responsive.dataTables.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-responsive-dt', 'css', 'responsive.dataTables.min.css' ),
		to: path.join( vendorDir, 'responsive.dataTables.min.css' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-searchbuilder', 'js', 'dataTables.searchBuilder.min.js' ),
		to: path.join( vendorDir, 'dataTables.searchBuilder.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-searchbuilder-dt', 'js', 'searchBuilder.dataTables.min.js' ),
		to: path.join( vendorDir, 'searchBuilder.dataTables.min.js' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-searchbuilder-dt', 'css', 'searchBuilder.dataTables.min.css' ),
		to: path.join( vendorDir, 'searchBuilder.dataTables.min.css' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net', 'License.txt' ),
		to: path.join( vendorDir, 'datatables.net.License.txt' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-dt', 'License.txt' ),
		to: path.join( vendorDir, 'datatables.net-dt.License.txt' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-datetime', 'license.txt' ),
		to: path.join( vendorDir, 'datatables.net-datetime.License.txt' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-buttons', 'License.txt' ),
		to: path.join( vendorDir, 'datatables.net-buttons.License.txt' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-buttons-dt', 'License.txt' ),
		to: path.join( vendorDir, 'datatables.net-buttons-dt.License.txt' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-responsive', 'License.txt' ),
		to: path.join( vendorDir, 'datatables.net-responsive.License.txt' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-responsive-dt', 'License.txt' ),
		to: path.join( vendorDir, 'datatables.net-responsive-dt.License.txt' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-searchbuilder', 'License.txt' ),
		to: path.join( vendorDir, 'datatables.net-searchbuilder.License.txt' )
	},
	{
		from: path.join( root, 'node_modules', 'datatables.net-searchbuilder-dt', 'License.txt' ),
		to: path.join( vendorDir, 'datatables.net-searchbuilder-dt.License.txt' )
	}
];

function readPackage( name ) {
	return JSON.parse(
		fs.readFileSync( path.join( root, 'node_modules', name, 'package.json' ), 'utf8' )
	);
}

fs.mkdirSync( vendorDir, { recursive: true } );

for ( const file of files ) {
	if ( !fs.existsSync( file.from ) ) {
		throw new Error( `Missing vendor source: ${ file.from }` );
	}

	fs.copyFileSync( file.from, file.to );
}

fs.writeFileSync(
	path.join( vendorDir, 'manifest.json' ),
	JSON.stringify( {
		generatedBy: 'tools/build-vendor.js',
		packages: {
			'datatables.net': readPackage( 'datatables.net' ).version,
			'datatables.net-dt': readPackage( 'datatables.net-dt' ).version,
			'datatables.net-datetime': readPackage( 'datatables.net-datetime' ).version,
			'datatables.net-buttons': readPackage( 'datatables.net-buttons' ).version,
			'datatables.net-buttons-dt': readPackage( 'datatables.net-buttons-dt' ).version,
			'datatables.net-responsive': readPackage( 'datatables.net-responsive' ).version,
			'datatables.net-responsive-dt': readPackage( 'datatables.net-responsive-dt' ).version,
			'datatables.net-searchbuilder': readPackage( 'datatables.net-searchbuilder' ).version,
			'datatables.net-searchbuilder-dt': readPackage( 'datatables.net-searchbuilder-dt' ).version
		},
		files: files.map( ( file ) => path.basename( file.to ) )
	}, null, '\t' ) + '\n'
);
