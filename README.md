# phpInit  
Initialize a PHP page controller app with autoload, important PHP settings, a custom error handler, and PHPMailer access.  

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
Why Front Controller? [Why framework?](https://toys.lerdorf.com/the-no-framework-php-mvc-framework) Not all apps need all that. Minimal overhead, maximum performance and flexibility.  

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
Set appropriate .env vars to instantiate. Then access with $emailer->getPhpMailer() or just email using $emailer->send().  