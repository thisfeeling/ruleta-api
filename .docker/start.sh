#!/bin/bash
# start.sh

# Transform the nginx configuration
node /assets/scripts/prestart.mjs /assets/nginx.template.conf /etc/nginx.conf

# Ensure nginx log dir and files exist so nginx can open them
mkdir -p /var/log/nginx
# create common log files and pid file if missing
touch /var/log/nginx/error.log /var/log/nginx/access.log /var/log/nginx/nginx.pid /var/log/nginx-error.log /var/log/nginx-access.log || true
# ensure ownership (www-data may not exist in some contexts)
if getent passwd www-data >/dev/null 2>&1; then
    chown -R www-data:www-data /var/log/nginx || true
fi

# Test nginx configuration and surface errors early
if ! nginx -t -c /etc/nginx.conf > /var/log/nginx-test.log 2>&1; then
    echo "Nginx config test failed. Dumping /var/log/nginx-test.log:"
    cat /var/log/nginx-test.log
    exit 1
else
    echo "Nginx config OK"
fi

# Start supervisor
supervisord -c /etc/supervisord.conf -n
