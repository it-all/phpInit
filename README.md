# Pageflow  
Pageflow initializes a PHP page controller app with important PHP settings, a custom error handler, optional PHPMailer access, optional PostgreSQL database connection (PHP pgsql extension required).  

## Why Page Controller?  
Small and medium sized web apps may not need the overhead of front controlled [frameworks](https://toys.lerdorf.com/the-no-framework-php-mvc-framework). Request routing in front controllers adds a layer of abstraction and complexity that can be eliminated by a simple page controller model, with only 1 required file at the top of each page to provide initialization settings and access to commonly used features.  

## Requirements  
PHP 7.3+  

## Installation & Usage  
```
$ composer require it-all/pageflow
```
Copy .env.example to .env in your top level directory, and edit the settings.  

Add the following code to the top of your php file(s):  
```
use Pageflow\Pageflow;

define('ROOT_DIR',  dirname(__DIR__));
define('VENDOR_DIR', ROOT_DIR . '/vendor');
require VENDOR_DIR . '/autoload.php';

$pageflow = Pageflow::getInstance();
$pageflow->init(ROOT_DIR);
```
Or create an init.php file with the code above and require it at the top of your php file(s).  

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
- Throttles email rate to 10 per hour max (otherwise emails on every page load can cause server slowdown).  


## PHPMailer  
Set appropriate .env vars to instantiate a helpful service layer object called $emailer. Then access with:
```
$emailer = $pageflow->getEmailer();
```
then:
```
$emailer->send('Subject', 'Body', ['self@example.com']);
```

## PHP-Auth
Follow the [database table creation instructions](https://github.com/delight-im/PHP-Auth) to enable. Requires PDO.  

## PDO
Set the connection string var in .env to connect to a database using PDO. Note that this is required for Auth.  

## PostgreSQL Database  
Set the connection string var in .env to instantiate a helpful service layer object, which includes a Query Builder. Use that to run queries, or the connection resource to query using native PHP pg functions.  
```
$postgres = $pageflow->getPostgres();
$pgConn = $pageflow->getPgConn();
```

## Session
Set the SESSION_TTL_MINUTES in .env to start a secure PHP session.  