#!/bin/bash
# Context Type Testing Script
# Tests all context types with SEO ON/OFF combinations
# for TEST-main-street-health-facility-4040-msh-regression-msh-regression.webp

set -e

# Configuration
WP_CLI="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"
WP_PATH="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
FILENAME="TEST-main-street-health-facility-4040-msh-regression-msh-regression.webp"
OUTPUT_DIR="/tmp/msh-context-tests"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Context types to test
CONTEXTS=(
    "stock"
    "decorative"
    "facility"
    "team"
    "equipment"
    "testimonial"
    "clinical"
    "business"
    "service-icon"
    "brand_logo"
)

# Create output directory
mkdir -p "$OUTPUT_DIR"
LOG_FILE="$OUTPUT_DIR/test-run-${TIMESTAMP}.log"

echo "==================================================" | tee "$LOG_FILE"
echo "MSH Context Type Test Matrix" | tee -a "$LOG_FILE"
echo "Timestamp: $(date)" | tee -a "$LOG_FILE"
echo "File: $FILENAME" | tee -a "$LOG_FILE"
echo "==================================================" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Find attachment ID
echo "Finding attachment ID for: $FILENAME" | tee -a "$LOG_FILE"
ATTACHMENT_ID=$($WP_CLI db query "SELECT p.ID FROM wp_posts p JOIN wp_postmeta pm ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND pm.meta_key = '_wp_attached_file' AND pm.meta_value LIKE '%$FILENAME%' LIMIT 1;" --path="$WP_PATH" --skip-column-names 2>/dev/null)

if [ -z "$ATTACHMENT_ID" ]; then
    echo "ERROR: Could not find attachment with filename: $FILENAME" | tee -a "$LOG_FILE"
    echo "Searching for similar files..." | tee -a "$LOG_FILE"
    $WP_CLI db query "SELECT p.ID, pm.meta_value as filename FROM wp_posts p JOIN wp_postmeta pm ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND pm.meta_key = '_wp_attached_file' AND (pm.meta_value LIKE '%main-street%' OR pm.meta_value LIKE '%4040%' OR pm.meta_value LIKE '%regression%') LIMIT 10;" --path="$WP_PATH" 2>/dev/null | tee -a "$LOG_FILE"
    exit 1
fi

echo "✓ Found attachment ID: $ATTACHMENT_ID" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Function to reset metadata
reset_metadata() {
    echo "  → Resetting metadata..." | tee -a "$LOG_FILE"
    $WP_CLI post meta delete $ATTACHMENT_ID _msh_context --path="$WP_PATH" --quiet 2>/dev/null || true
    $WP_CLI post meta delete $ATTACHMENT_ID _msh_seo_mode --path="$WP_PATH" --quiet 2>/dev/null || true
    $WP_CLI post meta delete $ATTACHMENT_ID msh_optimized_date --path="$WP_PATH" --quiet 2>/dev/null || true
    $WP_CLI post meta delete $ATTACHMENT_ID _msh_ai_staged_meta --path="$WP_PATH" --quiet 2>/dev/null || true
}

# Function to analyze and capture results
analyze_and_capture() {
    local context_type=$1
    local seo_mode=$2
    local test_label="${context_type}_seo_${seo_mode}"
    local result_file="$OUTPUT_DIR/${test_label}_${TIMESTAMP}.json"

    echo "" | tee -a "$LOG_FILE"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"
    echo "TEST: Context=$context_type | SEO=$seo_mode" | tee -a "$LOG_FILE"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" | tee -a "$LOG_FILE"

    # Set context type
    echo "  → Setting context: $context_type" | tee -a "$LOG_FILE"
    $WP_CLI post meta update $ATTACHMENT_ID _msh_context "$context_type" --path="$WP_PATH" --quiet

    # Set SEO mode
    echo "  → Setting SEO mode: $seo_mode" | tee -a "$LOG_FILE"
    $WP_CLI post meta update $ATTACHMENT_ID _msh_seo_mode "$seo_mode" --path="$WP_PATH" --quiet

    # Call the analyze_single_image method and capture generated_meta directly
    echo "  → Running analyze and capturing results..." | tee -a "$LOG_FILE"

    EVAL_CODE="
    \$plugin = MSH_Image_Optimizer::get_instance();
    if (method_exists(\$plugin, 'analyze_single_image')) {
        \$result = \$plugin->analyze_single_image($ATTACHMENT_ID);
        if (\$result && isset(\$result['generated_meta'])) {
            echo json_encode(\$result['generated_meta'], JSON_PRETTY_PRINT);
        } else {
            echo json_encode(array('error' => 'No generated_meta in result'));
        }
    } else {
        echo json_encode(array('error' => 'Method not found'));
    }
    "

    # Capture the output, filtering out WordPress notices but keeping all JSON lines
    STAGED_META=$($WP_CLI eval "$EVAL_CODE" --path="$WP_PATH" 2>&1 | grep -v "^Notice:" | grep -v "^<strong>" | sed '/^$/d')

    # Check if metadata was captured successfully
    if echo "$STAGED_META" | grep -q '"error"'; then
        echo "  ✗ Analyze failed: metadata generation error" | tee -a "$LOG_FILE"
        echo "$STAGED_META" | tee -a "$LOG_FILE"
    else
        echo "  ✓ Analyze completed successfully" | tee -a "$LOG_FILE"
    fi

    # Save to file
    echo "$STAGED_META" > "$result_file"

    # Display key fields
    echo "" | tee -a "$LOG_FILE"
    echo "  Results:" | tee -a "$LOG_FILE"
    echo "  ----------------------------------------" | tee -a "$LOG_FILE"

    if [ "$STAGED_META" != "{}" ] && ! echo "$STAGED_META" | grep -q '"error"'; then
        # Extract and display key fields using jq if available, otherwise show raw
        if command -v jq &> /dev/null; then
            echo "$STAGED_META" | jq -r '
                "  Title: " + (.title // "N/A") + "\n" +
                "  Alt: " + (.alt_text // "N/A") + "\n" +
                "  Caption: " + (.caption // "N/A") + "\n" +
                "  Description: " + (.description // "N/A") + "\n" +
                "  Filename: " + (.file_name_suggestion // "N/A")
            ' | tee -a "$LOG_FILE"
        else
            echo "  (Install jq for formatted output)" | tee -a "$LOG_FILE"
            echo "$STAGED_META" | head -c 500 | tee -a "$LOG_FILE"
            echo "..." | tee -a "$LOG_FILE"
        fi
    else
        echo "  ⚠ No metadata generated" | tee -a "$LOG_FILE"
    fi

    echo "  ----------------------------------------" | tee -a "$LOG_FILE"
    echo "  ✓ Saved to: $result_file" | tee -a "$LOG_FILE"

    # Small delay between tests
    sleep 1
}

# Run tests for each context type
TEST_COUNT=0
TOTAL_TESTS=$((${#CONTEXTS[@]} * 2))

for context in "${CONTEXTS[@]}"; do
    # Test with SEO ON
    ((TEST_COUNT++))
    echo "" | tee -a "$LOG_FILE"
    echo "Progress: $TEST_COUNT/$TOTAL_TESTS" | tee -a "$LOG_FILE"
    reset_metadata
    analyze_and_capture "$context" "1"

    # Test with SEO OFF
    ((TEST_COUNT++))
    echo "" | tee -a "$LOG_FILE"
    echo "Progress: $TEST_COUNT/$TOTAL_TESTS" | tee -a "$LOG_FILE"
    reset_metadata
    analyze_and_capture "$context" "0"
done

# Final summary
echo "" | tee -a "$LOG_FILE"
echo "==================================================" | tee -a "$LOG_FILE"
echo "TEST SUITE COMPLETED" | tee -a "$LOG_FILE"
echo "==================================================" | tee -a "$LOG_FILE"
echo "Total tests run: $TEST_COUNT" | tee -a "$LOG_FILE"
echo "Results directory: $OUTPUT_DIR" | tee -a "$LOG_FILE"
echo "Log file: $LOG_FILE" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Result files:" | tee -a "$LOG_FILE"
ls -lh "$OUTPUT_DIR"/*_${TIMESTAMP}.json | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "✓ All tests complete!" | tee -a "$LOG_FILE"

# Generate summary report
SUMMARY_FILE="$OUTPUT_DIR/SUMMARY_${TIMESTAMP}.md"
echo "# Context Type Test Summary" > "$SUMMARY_FILE"
echo "" >> "$SUMMARY_FILE"
echo "**Date:** $(date)" >> "$SUMMARY_FILE"
echo "**File:** $FILENAME" >> "$SUMMARY_FILE"
echo "**Attachment ID:** $ATTACHMENT_ID" >> "$SUMMARY_FILE"
echo "**Total Tests:** $TEST_COUNT" >> "$SUMMARY_FILE"
echo "" >> "$SUMMARY_FILE"
echo "## Test Matrix" >> "$SUMMARY_FILE"
echo "" >> "$SUMMARY_FILE"
echo "| Context Type | SEO Mode | Result File |" >> "$SUMMARY_FILE"
echo "|--------------|----------|-------------|" >> "$SUMMARY_FILE"

for context in "${CONTEXTS[@]}"; do
    echo "| $context | ON | ${context}_seo_1_${TIMESTAMP}.json |" >> "$SUMMARY_FILE"
    echo "| $context | OFF | ${context}_seo_0_${TIMESTAMP}.json |" >> "$SUMMARY_FILE"
done

echo "" >> "$SUMMARY_FILE"
echo "## Files" >> "$SUMMARY_FILE"
echo "" >> "$SUMMARY_FILE"
echo "All results saved to: \`$OUTPUT_DIR\`" >> "$SUMMARY_FILE"

echo "" | tee -a "$LOG_FILE"
echo "Summary report: $SUMMARY_FILE" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "To view a specific result:" | tee -a "$LOG_FILE"
echo "  cat $OUTPUT_DIR/<context_type>_seo_<0|1>_${TIMESTAMP}.json | jq" | tee -a "$LOG_FILE"
