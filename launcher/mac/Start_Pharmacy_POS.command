#!/bin/bash
# 1-Click Launcher for macOS
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../.." && pwd )"
cd "$DIR"

PORT=8080

# Check if port is already listening for Pharmacy POS
if ! lsof -i :$PORT >/dev/null 2>&1; then
    echo "Starting Pharmacy POS background server on port $PORT..."
    nohup php artisan serve --host=127.0.0.1 --port=$PORT > storage/logs/server_launcher.log 2>&1 &
    sleep 2
fi

# Open Chrome in App mode (native window feel) or fallback to default browser
TARGET_URL="http://127.0.0.1:$PORT/pos"
if [ -d "/Applications/Google Chrome.app" ]; then
    open -na "Google Chrome" --args --app="$TARGET_URL"
else
    open "$TARGET_URL"
fi
