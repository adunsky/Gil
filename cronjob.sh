#!/bin/sh
chmod 777 /tmp/Google_Client
cd /var/www/html/Gilamos
php calendarServ.php db=samgal > cal.log
