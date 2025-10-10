#!/bin/bash
# Setup automatic background sync from GitHub every 5 minutes
# This ensures your Local test site always has the latest changes

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLIST_NAME="com.msh.image-optimizer.autosync"
PLIST_FILE="$HOME/Library/LaunchAgents/$PLIST_NAME.plist"

echo "🔧 Setting up automatic GitHub sync..."
echo ""

# Create LaunchAgent plist
cat > "$PLIST_FILE" << EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>$PLIST_NAME</string>

    <key>ProgramArguments</key>
    <array>
        <string>$SCRIPT_DIR/sync-from-github.sh</string>
    </array>

    <key>StartInterval</key>
    <integer>300</integer>

    <key>RunAtLoad</key>
    <true/>

    <key>StandardOutPath</key>
    <string>$HOME/Library/Logs/msh-autosync.log</string>

    <key>StandardErrorPath</key>
    <string>$HOME/Library/Logs/msh-autosync-error.log</string>
</dict>
</plist>
EOF

echo "✅ Created LaunchAgent configuration"

# Load the agent
launchctl unload "$PLIST_FILE" 2>/dev/null || true
launchctl load "$PLIST_FILE"

echo "✅ Auto-sync enabled!"
echo ""
echo "Configuration:"
echo "  - Checks GitHub: Every 5 minutes"
echo "  - Auto-pulls: If new commits found"
echo "  - Auto-syncs: To Local test site"
echo "  - Logs: ~/Library/Logs/msh-autosync.log"
echo ""
echo "Commands:"
echo "  • Check status: launchctl list | grep $PLIST_NAME"
echo "  • View logs:    tail -f ~/Library/Logs/msh-autosync.log"
echo "  • Disable:      launchctl unload $PLIST_FILE"
echo "  • Enable:       launchctl load $PLIST_FILE"
echo ""
echo "✅ Your test site will now auto-update when other AIs push to GitHub!"
echo ""
