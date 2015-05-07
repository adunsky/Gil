#!/bin/sh
mkdir /tmp/Google_Client
sudo chmod 777 /tmp/Google_Client/
cd /var/www/html/Gilamos
php calendarServ.php db=samgal > cal.log
