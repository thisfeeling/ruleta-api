#!/bin/bash

# Transform the nginx configuration
node /assets/scripts/prestart.mjs /assets/nginx.template.conf /etc/nginx.conf

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
