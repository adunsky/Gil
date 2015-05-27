#!/bin/sh
mkdir /tmp/Google_Client
chmod 777 /tmp/Google_Client/
cd /var/www/html/Gilamos
php calendarServ.php db=samgal > cal.log
php calendarServ.php db=demo > calDemo.log
