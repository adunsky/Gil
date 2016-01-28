#!/bin/sh
mkdir /tmp/Google_Client
chmod 777 /tmp/Google_Client/
cd /var/www/html/Gilamos
php calendarServ.php db=samgal &
php calendarServ.php db=karnit & 
php calendarServ.php db=demo & 
php calendarServ.php db=instdel & 
php calendarServ.php db=arava & 
php backup.php db=samgal interval=1 &
