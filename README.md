# PDO_Wrapper
## Features
- Management of a single instance of the connection to the database (singleton).
- Build "querystring" PDO connection for multiple drivers database.
- Build LIMIT clause to databases such as SQL Server and Oracle.
- Getting fields table to validate fields no-existing.
- Getting primary key to accelerate record count of table.
- Sanitization data, using followings methods PDO: prepare() and bindValue().
- Catches all errors, and writes them to error log if configured to do so.
- Handles multiple inserts with a single query.
- Handles multiple updates through PDO transactions.
- Handles PDO transactions (begin, commit, rollback).
- Handles custom queries if one needs to do a join or second order query.
- Extension for queries much more custom and use them into jQueryDatatable plugin to server-side processing.
