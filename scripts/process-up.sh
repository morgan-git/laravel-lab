#!/bin/bash

# Run from the project root no matter where the script is invoked from, so
# the relative log path below always resolves correctly.
cd "$(git rev-parse --show-toplevel 2>/dev/null || dirname "$0")" || exit 1

RESTART=false
if [[ "$1" == "-r" || "$1" == "--restart" ]]; then
    RESTART=true
    echo "Restart flag set: killing existing processes first."
fi

# Helper: given a pgrep pattern and a start command, either skip, restart, or start fresh.
start_process() {
    local name="$1"
    local pattern="$2"
    local start_cmd="$3"

    if pgrep -f "$pattern" > /dev/null; then
        if [ "$RESTART" = true ]; then
            echo "Restarting $name..."
            pkill -f "$pattern"
            sleep 1
            eval "$start_cmd"
        else
            echo "$name is already running. Skipping."
        fi
    else
        echo "Starting $name..."
        eval "$start_cmd"
    fi
}

# nohup keeps each process alive if the terminal window is closed.
# The queue listener's --timeout matches SyncFeedSource's $timeout (300s),
# and its output is kept in a log so a stop leaves evidence behind.
start_process "Queue Worker" "queue:listen" "nohup php artisan queue:listen --timeout=300 >> storage/logs/queue-listener.log 2>&1 &"
start_process "Scheduler" "schedule:work" "nohup php artisan schedule:work > /dev/null 2>&1 &"
start_process "Vite" "vite" "nohup npm run dev > /dev/null 2>&1 &"

# MailPit uses a plain process name match (pgrep, not pgrep -f), keep that distinction.
if pgrep "mailpit" > /dev/null; then
    if [ "$RESTART" = true ]; then
        echo "Restarting MailPit..."
        pkill "mailpit"
        sleep 1
        nohup mailpit > /dev/null 2>&1 &
    else
        echo "MailPit is already running. Skipping."
    fi
else
    echo "Starting MailPit..."
    nohup mailpit > /dev/null 2>&1 &
fi

disown -a
