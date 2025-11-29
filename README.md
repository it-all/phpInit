# phpInit  
Initialize a PHP page controller app with autoload, important PHP settings, a custom error handler, optional PHPMailer access, optional PostGreSQL database connection (PHP pgsql extension required).  

## Installation & Usage  
Clone this repo.  
Install the Composer dependencies:  
```
php composer.phar update
```
Copy .env.example to .env, and edit the settings.  
Create public directory on the same level as src. Create index.php, or any php file, and add this line at the top:  
```
require __DIR__ . '/../src/init.php';
```
Your class files can be autoloaded anywhere under the src directory. It's recommended to create a Domain directory under src for your include files.  

To upgrade:
```
git fetch
git merge
```

## Why Page Controller?  
Small and medium sized web apps may not need the overhead of Front Controlled frameworks(https://toys.lerdorf.com/the-no-framework-php-mvc-framework). Request routing in frameworks adds a layer of abstraction and complexity that can be eliminated by a simple Page Controller model, with only 1 required file at the top of each page, providing configuration and access to commonly used features.  

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
Set appropriate .env vars to instantiate a helpful service layer object called $emailer. Then access with $emailer->getPhpMailer() or just email using $emailer->send().  

## PostGreSQL Database  
Set the connection string var in .env to instantiate a helpful service layer object called $postgres, which includes a Query Builder. Use that to run queries, or the connection constant PG_CONN to build your own with native PHP functions.  