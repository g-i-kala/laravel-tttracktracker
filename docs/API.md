# API / Routes

> Status: planned. Only the default Laravel welcome route exists.

## Web routes

| Method | URI                                         | Controller@action (planned)          |
| ------ | ------------------------------------------- | ------------------------------------ |
| GET    | `/`                                         | DashboardController@index            |
| GET    | `/imports`                                  | ImportBatchController@index          |
| GET    | `/imports/create`                           | ImportBatchController@create         |
| POST   | `/imports`                                  | ImportBatchController@store          |
| GET    | `/imports/{batch}`                          | ImportBatchController@show           |
| GET    | `/imports/{batch}/review`                   | ImportBatchController@review         |
| POST   | `/imports/{batch}/parse`                    | ImportBatchController@parse          |
| POST   | `/imports/{batch}/youtube/search`           | YoutubeSearchController@search       |
| PATCH  | `/items/{item}`                             | ImportItemController@update          |
| POST   | `/items/{item}/youtube/select`              | ImportItemController@selectCandidate |
| POST   | `/items/{item}/youtube/custom`              | ImportItemController@customLink      |
| POST   | `/items/{item}/toggle-export`               | ImportItemController@toggleExport    |
| POST   | `/imports/{batch}/exports/supersimple-txt`  | ExportController@supersimpleTxt      |
| GET    | `/exports/{export}/download`                | ExportController@download            |

Route-model binding: `{batch}` → ImportBatch, `{item}` → ImportItem, `{export}` → ExportFile.

## JSON endpoints used by Vue components

To be documented as implemented (request/response examples here).
