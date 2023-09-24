# GLPI-cron
Fast awaking agents for deployment.

**How to install**
Copy crond.php to /glpi/front
Run "chown www-data:www-data crond.php"
Add to cron: "crontab -u www-data -e"
Or test output by running "/bin/php crond.php"
