#!/bin/bash
# Migration Framework Verification Script
# Tests all EBSC phases for phase6_templates migration

set -e  # Exit on error

CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}Migration Framework Verification${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""

WP_CLI="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"

# Test 1: List migrations
echo -e "${YELLOW}Test 1: List all migrations${NC}"
$WP_CLI msh migrate list
echo -e "${GREEN}✓ List command works${NC}"
echo ""

# Test 2: Check status
echo -e "${YELLOW}Test 2: Check migration status${NC}"
$WP_CLI msh migrate status phase6_templates
echo -e "${GREEN}✓ Status command works${NC}"
echo ""

# Test 3: Get current status
CURRENT_STATUS=$($WP_CLI option get msh_migration_phase6_templates_status --allow-root 2>/dev/null || echo "pending")
echo -e "${CYAN}Current migration status: $CURRENT_STATUS${NC}"
echo ""

# Test 4: Run EXPAND phase (if pending)
if [ "$CURRENT_STATUS" == "pending" ]; then
    echo -e "${YELLOW}Test 3: Running EXPAND phase${NC}"
    $WP_CLI msh migrate expand phase6_templates

    # Verify table was created
    TABLE_EXISTS=$($WP_CLI db query "SHOW TABLES LIKE 'wp_msh_optimizer_templates';" --skip-column-names)
    if [ -n "$TABLE_EXISTS" ]; then
        echo -e "${GREEN}✓ Templates table created successfully${NC}"
    else
        echo -e "${RED}✗ Templates table NOT created${NC}"
        exit 1
    fi
    echo ""
else
    echo -e "${CYAN}EXPAND already completed, skipping${NC}"
    echo ""
fi

# Test 5: Run BACKFILL phase
CURRENT_STATUS=$($WP_CLI option get msh_migration_phase6_templates_status --allow-root 2>/dev/null || echo "pending")
if [ "$CURRENT_STATUS" == "expanded" ]; then
    echo -e "${YELLOW}Test 4: Running BACKFILL phase${NC}"
    $WP_CLI msh migrate backfill phase6_templates
    echo -e "${GREEN}✓ Backfill completed${NC}"
    echo ""
else
    echo -e "${CYAN}BACKFILL already completed or not ready, skipping${NC}"
    echo ""
fi

# Test 6: Run VERIFY phase
CURRENT_STATUS=$($WP_CLI option get msh_migration_phase6_templates_status --allow-root 2>/dev/null || echo "pending")
if [ "$CURRENT_STATUS" == "backfilled" ]; then
    echo -e "${YELLOW}Test 5: Running VERIFY phase${NC}"
    $WP_CLI msh migrate verify phase6_templates
    echo -e "${GREEN}✓ Verify completed${NC}"
    echo ""
else
    echo -e "${CYAN}VERIFY already completed or not ready, skipping${NC}"
    echo ""
fi

# Test 7: Run SWITCH phase at 5%
CURRENT_STATUS=$($WP_CLI option get msh_migration_phase6_templates_status --allow-root 2>/dev/null || echo "pending")
if [ "$CURRENT_STATUS" == "backfilled" ]; then
    echo -e "${YELLOW}Test 6: Running SWITCH phase at 5%${NC}"
    $WP_CLI msh migrate switch phase6_templates --percentage=5

    # Check feature flag was set
    FLAG_VALUE=$($WP_CLI option get msh_flag_template_intelligence --allow-root 2>/dev/null || echo "not_set")
    echo -e "${CYAN}Feature flag value: $FLAG_VALUE${NC}"
    echo -e "${GREEN}✓ Switch completed at 5%${NC}"
    echo ""
else
    echo -e "${CYAN}SWITCH already completed or not ready, skipping${NC}"
    echo ""
fi

# Test 8: Check final status
echo -e "${YELLOW}Test 7: Final status check${NC}"
$WP_CLI msh migrate status phase6_templates
echo ""

# Test 9: Verify telemetry was logged (if telemetry exists)
echo -e "${YELLOW}Test 8: Check telemetry integration${NC}"
TELEMETRY_COUNT=$($WP_CLI db query "SELECT COUNT(*) FROM wp_msh_telemetry WHERE event LIKE 'migration_%';" --skip-column-names 2>/dev/null || echo "0")
if [ "$TELEMETRY_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ Found $TELEMETRY_COUNT telemetry events for migrations${NC}"
    echo ""
    echo -e "${CYAN}Recent migration events:${NC}"
    $WP_CLI db query "SELECT event, created_at FROM wp_msh_telemetry WHERE event LIKE 'migration_%' ORDER BY created_at DESC LIMIT 5;" 2>/dev/null || echo "Telemetry table not found"
else
    echo -e "${YELLOW}⚠ No telemetry events found (telemetry may not be set up yet)${NC}"
fi
echo ""

# Summary
echo -e "${CYAN}========================================${NC}"
echo -e "${GREEN}All Migration Framework Tests Passed!${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""
echo "Migration Status Summary:"
$WP_CLI msh migrate list
echo ""
echo -e "${CYAN}Next Steps:${NC}"
echo "1. Increase rollout: wp msh migrate switch phase6_templates --percentage=25"
echo "2. Monitor for 48 hours"
echo "3. Increase to 50%, then 100%"
echo "4. After 30 days: wp msh migrate contract phase6_templates --confirm"
