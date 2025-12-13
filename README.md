# Pageflow  
Pageflow initializes a PHP page controller app with important PHP settings, a custom error handler, optional PHPMailer access, optional PostgreSQL database connection (PHP pgsql extension required).  

## What This Is (Layperson Overview)
- Pageflow is a small helper you include at the top of your PHP pages to set things up safely and consistently.
- It loads your configuration from a `.env` file, prepares error handling (so problems are logged and optionally emailed), and can connect to a PostgreSQL database.
- It also provides a simple “Query Builder” to run database queries with parameters in a safer way.

Think of it like the “starter switch” for your app: you add Pageflow, and it handles the essentials (config, errors, email, database) so the rest of your page can focus on your app’s logic.

## Why Page Controller?  
Small and medium sized web apps may not need the overhead of front controlled [frameworks](https://toys.lerdorf.com/the-no-framework-php-mvc-framework). Request routing in front controllers adds a layer of abstraction and complexity that can be eliminated by a simple page controller model, with only 1 required file at the top of each page to provide configuration and access to commonly used features.  

## Requirements  
PHP 7.1+  

## Installation & Usage  
```
$ composer require it-all/pageflow
```
Copy .env.example to .env in your top level directory, and edit the settings.  

Add the following code to the top of your php file(s):  
```
use Pageflow\Pageflow;

require __DIR__ . '/../vendor/autoload.php';

$pageflow = Pageflow::getInstance();
$pageflow->init();
```
Or create an init.php file with the code above and require it at the top of your php file(s).  

## What It Sets Up For You
- Configuration: Reads `.env` values (like “is this a live server?”) and makes them available.
- Error Handling: Logs errors, optionally shows them on dev servers, and can email error details to a webmaster.
- Email: Provides a small service wrapper around PHPMailer so your pages can send emails easily.
- Database: Optionally connects to PostgreSQL and offers a few helper classes for building queries.

## High-Level Architecture
- `Pageflow\Pageflow`: Main singleton that initializes environment, error handling, emailer, and database connection.
- `Infrastructure\Utilities\ThrowableHandler`: Centralized error/exception handler. Writes logs and can email errors.
- `Infrastructure\Utilities\PHPMailerService`: Thin wrapper for PHPMailer with environment-controlled behaviors.
- `Infrastructure\Database\PostgresService`: Manages the PostgreSQL connection and metadata helper queries.
- `Infrastructure\Database\Query\*Builder`: Query helpers (select/insert/update) using positional parameters `$1`, `$2`, ...

## Example: Running a Safe Query
```php
use Pageflow\Infrastructure\Database\Query\QueryBuilder;

$q = new QueryBuilder("SELECT id, name FROM users WHERE email = $1", $email);
$result = $q->execute();
// Then fetch rows via pg_fetch_all($result) as needed
```
This uses positional parameters to avoid SQL injection. Identifiers (like table and column names) cannot be parameterized and must be trusted/validated by your code.

## phpdotenv
To access phpdotenv in order to validate your own environmental variables:  
```
$dotEnv = $pageflow->getDotEnv();
```

## Custom Error Handler  
Handles as many PHP errors and uncaught exceptions as possible. Provides a stack trace to help debug.  
1. Display  
- Does not display errors on production server.  
- Optionally displays errors on test servers.  

2. Log  
- Logs to file configured in .env.  

3. Email  
- Emails errors to webmaster configured in .env.  
- Only emails if less than 10 emails have been sent in the past hour (otherwise emails on every page load can cause server slowdown).  


## PHPMailer  
Set appropriate .env vars to instantiate a helpful service layer object called $emailer. Then access with:
```
$emailer = $pageflow->getEmailer();
```
then:
```
$emailer->send('Subject', 'Body', ['self@example.com']);
```

## PostgreSQL Database  
Set the connection string var in .env to instantiate a helpful service layer object, which includes a Query Builder. Use that to run queries, or the connection resource to query using native PHP pg functions.  
```
$postgres = $pageflow->getPostgres();
$pgConn = $pageflow->getPgConn();
```

## Security & Best Practices (Overview)
- Parameterize values: The Query Builders use `$1`, `$2`, ... for values — keep using them. Do not embed raw user input into SQL strings.
- Validate identifiers: Table/column names are concatenated as identifiers and cannot be parameterized. Validate them against allowlists.
- Limit raw SQL fragments: `SelectBuilder` allows an extra clause without parameters. Only use server-generated, trusted strings here.
- Protect environment files: `.env` contains secrets — keep it out of version control and production public paths.
- Email throttling: Error emails are limited to 10 per hour — keep webmaster email secure and monitor logs.

See `REVIEW.md` for specific lines flagged for security review.