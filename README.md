# SemanticDataTables

SemanticDataTables is a MediaWiki extension prototype for rendering Semantic
MediaWiki query results with DataTables using DataTables' DOM source and Ajax
range requests.

The extension intentionally does not copy Semantic Result Formats' custom Ajax
wrapper. SRF is used as a reference for SMW result-printer integration and cell
formatting patterns only.

## Current Scope

- Adds the `datatables-native` SMW result format.
- Registers `ext.semanticdatatables.query` as a MediaWiki API module.
- Uses paged Ajax range requests by default.
- Supports the normal DataTables DOM source with `noajax=true`.
- Keeps `datatables-ajax` and `datatables-serverSide` as deprecated
  compatibility parameters.
- Supports per-printout `+template=...` cell rendering for DOM, `ajax`, and
  `serverSide` tables.
- Supports per-printout `+datatables-columns.type=...` for DataTables column
  type selection.
- Supports `datatables-buttons=...` and the DataTables legacy
  `datatables-dom=...` option, including `QBlrtip` when SearchBuilder is
  needed.
- Exports all matching rows for the `csv` button in Ajax mode instead of only
  the browser's current server-side page.
- Bundles DataTables 2.3.8, Buttons, DateTime, and SearchBuilder assets from
  locked npm packages.
- Uses a signed query context token so the browser does not send query and
  printout definitions back to the server as trusted state.

## Bundled Assets

The repository commits generated DataTables assets under
`resources/vendor/datatables/` so release archives can be installed without
`node_modules`.

To refresh the bundled assets from the locked npm dependencies:

```bash
npm ci
npm run build
```

The generated `resources/vendor/datatables/manifest.json` records the bundled
DataTables package versions.

## Example

```text
{{#ask:
 [[Category:Project]]
 |?Status
 |?Owner |+template=UserLink
 |?Updated#-F[Y-m-d] |+datatables-columns.type=date
 |format=datatables-native
 |limit=500
 |datatables-pageLength=25
 |datatables-buttons=copy,csv
 |datatables-dom=QBlrtip
}}
```

## Design Boundary

By default, the browser uses DataTables' `ajax` option and its server-side
processing protocol. This does not mean the browser receives the full query
result. The browser sends DataTables request state for the currently requested
range:

- `draw`
- `start`
- `length`
- `order`
- `columns`
- `search`

The server returns DataTables response state:

- `draw`
- `recordsTotal`
- `recordsFiltered`
- `data`

The SMW query context is restored from a signed token generated during result
printer rendering. Paging and column ordering are applied to the SMW query;
global search filters the formatted row data. The server returns the requested
slice of the full matching result set.

With `noajax=true`, the result printer renders a normal HTML table body and
DataTables reads it as a DOM source. The full query result is rendered into that
table body so DataTables' client-side paging, search, and column ordering
operate over the full rendered result.
