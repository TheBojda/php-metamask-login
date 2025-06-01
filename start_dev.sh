#!/bin/bash

# Config
PHP_PORT=8000
DOC_ROOT="public"

# Start PHP server in background (accessible on all interfaces)
echo "Starting PHP server on http://0.0.0.0:$PHP_PORT..."
php -S 0.0.0.0:$PHP_PORT -t $DOC_ROOT
