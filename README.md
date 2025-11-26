# phpInit  
Initialize a PHP page controller app with important PHP settings and a custom error handler.  

## Usage  
Create public directory on the same level as src. Create index.php, or any php file, and add this line at the top:  
```
require __DIR__ . '/../src/init.php';
```

## Why Page Controller?  
Why Front Controller? [Why Framework?](https://toys.lerdorf.com/the-no-framework-php-mvc-framework). Not all apps need all that. Minimal overhead, maximum performance and flexibility.  

## Custom Error Handler  
Handles as many PHP errors as possible.  
1. Display  
- Does not display errors on production server.  
- Optionally displays errors on test servers.  

2. Log  
- Logs to file configured in .env.  

3. Email  
Coming Soon. Will use PHPMailer and limit # of emails per hour (I learned this the hard way, as errors on every page load caused my server to bog down with thousands of email sends).  
