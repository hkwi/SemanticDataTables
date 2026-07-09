# Architecture

SemanticDataTables keeps the browser/API contract aligned with DataTables'
DOM source and Ajax range request modes.

## Assets

The extension bundles DataTables 2.3.8 from the npm packages `datatables.net`
and `datatables.net-dt`, plus the locked Buttons, DateTime, and SearchBuilder
packages used by supported options. `ext.semanticDataTables.vendor` loads
DataTables core, styling integrations, extension JavaScript, and extension CSS.
`ext.semanticDataTables` depends on that vendor module and contains only this
extension's initialization code.

## Request

DataTables can read data from the rendered HTML table body or through its
`ajax` option. Ajax tables use DataTables' server-side processing protocol so
clicking a sort header requests the sorted range instead of downloading the
entire query result.

By default, `noajax=false` matches the SRF `datatables` format: the browser
enables DataTables server-side processing and wraps DataTables' request object
in a single MediaWiki API parameter. The wrapper is only for MediaWiki API
parameter validation. The payload is still DataTables request state, not SRF
query state:

```json
{
  "serverSide": true,
  "request": {
    "draw": 1,
    "start": 0,
    "length": 25,
    "columns": [],
    "order": [],
    "search": { "value": "" }
  }
}
```

The DataTables request state is applied to the SMW query. Paging maps to
`limit` and `offset`, search filters the formatted row data, and column ordering
maps to SMW `sort` and `order` query parameters. Printout aliases are not used
for sorting; the generated column configuration keeps the underlying SMW
property name in `columns.name`. The main-label column uses an empty sort key,
which SMW interprets as page-title ordering.

With `noajax=true`, the result printer renders a normal `<tbody>` and no API
request is made by DataTables. The result printer re-runs the SMW query without
the original display window so DataTables' client-side paging, search, and
ordering operate on the full query result that was rendered into the table body.

The old `datatables-ajax` and `datatables-serverSide` parameters are still
recognized for older queries, but they no longer control loading. Use `noajax`
as the public switch for disabling Ajax loading.

## Context

The initial result printer render creates a signed context token. The token
contains the SMW query conditions, result printouts, and selected format
parameters. The browser treats the token as opaque.

Printout parameters are part of the signed context. This keeps per-cell
rendering consistent across DOM source, `ajax`, and `serverSide` modes. The
row formatter currently uses:

- `template` to expand each cell value through a MediaWiki template.
- `datatables-columns.type` to pass an explicit DataTables column type.

Display extension parameters are passed through as normal DataTables options:

- `datatables-buttons=copy,csv` becomes `buttons: [ 'copy', 'csv' ]`.
- `datatables-dom=QBlrtip` becomes the legacy `dom` option. DataTables 2 still
  maps this to its legacy feature registry, where Buttons registers `B` and
  SearchBuilder registers `Q`.

For ajax-backed tables, the extension replaces the built-in `csv` and
`csvHtml5` button actions. DataTables Buttons can only export rows already held
by the browser in server-side processing mode, so the replacement action calls
the SemanticDataTables API with `exportAll=true`. The API reuses the signed
query context and current DataTables search/order state, fetches the unpaged
result set, and the browser writes the returned plain cell values to CSV.

## Response

For ajax-backed tables, the API returns the fields DataTables expects for
server-side processing:

```json
{
  "draw": 1,
  "recordsTotal": 100,
  "recordsFiltered": 100,
  "data": []
}
```

`recordsTotal` and `recordsFiltered` are calculated with SMW count queries.
Those count queries remove display-window parameters such as `limit`, `offset`,
`sort`, and `order`, so pagination reflects the total number of matching
entities rather than the number of rows returned by the initial inline `#ask`.

Rows contain orthogonal cell data:

```json
[
  [
    { "display": "<a>Page</a>", "filter": "Page", "sort": "Page" }
  ]
]
```

This matches DataTables `columns.render` usage and avoids custom draw handling.
