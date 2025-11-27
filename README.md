# phpInit  
Initialize a PHP page controller app with autoload, important PHP settings, and a custom error handler.  

## Usage  
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
Your class files can be autoloaded anywhere under the src directory. It's recommended to create a Domain directory for your include files.  

To Upgrade:
```
git pull
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
Coming Soon. Will use PHPMailer and limit # of emails per hour (otherwise errors on every page load can cause server slowdown).  
