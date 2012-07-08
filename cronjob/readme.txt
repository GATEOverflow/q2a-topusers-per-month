This folder is protected by htaccess so that no browser can access it.

you have to setup the cronjob so that it calls the script directly and not via http. 

"if the cron job executes the php file directly through the php interpreter, then no, it never goes through the webserver and any webserver settings or configurations are irrelevant.
But, if the cron job executes a web browser or something like wget, making an http request through the webserver, then yes, you'll have a problem. You could have the webserver allow requests from local ips.
The cron command should contain something like /full/path/to-q2a/qa-plugin/best-users-per-month/cronjob/cronjob.php"

If you NEED cronjob via HTML, use something like: 

<Files "cronjob.php">
  Order deny,allow
  Allow from name.of.this.machine
  Allow from another.authorized.name.net
  Allow from 127.0.0.1
  Deny from all
</Files>