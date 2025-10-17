#!/bin/bash
# i18n Audit Script for MSH Image Optimizer
# Finds hardcoded English strings that need translation

echo "==================================="
echo "MSH Image Optimizer - i18n Audit"
echo "==================================="
echo ""

echo "📊 CURRENT STATUS:"
echo "-------------------"
echo -n "Translation functions used: "
grep -r "__(\|_e(\|esc_html__(\|esc_attr__(\|esc_html_e(\|_n(" msh-image-optimizer/admin/*.php msh-image-optimizer/includes/*.php 2>/dev/null | wc -l
echo ""

echo "🔍 HARDCODED STRINGS TO FIX:"
echo "----------------------------"
echo ""

echo "1. JavaScript strings in admin files:"
grep -n "\.text\|\.html\|\.append\|\.prepend\|\.val(" msh-image-optimizer/assets/js/*.js | grep -E "'[A-Z][a-zA-Z ]+'" | head -20
echo ""

echo "2. Button text in admin PHP:"
grep -n "<button\|button-text\|aria-label" msh-image-optimizer/admin/*.php | grep -v "esc_attr__\|__(" | head -15
echo ""

echo "3. Inline English text in HTML:"
grep -n ">[A-Z][a-zA-Z][a-zA-Z ]*<" msh-image-optimizer/admin/image-optimizer-admin.php | grep -v "<?php\|esc_html_\|__(" | head -20
echo ""

echo "4. Status messages and alerts:"
grep -n "success\|error\|warning\|info" msh-image-optimizer/includes/*.php | grep "'\|\"" | grep -E "[A-Z][a-z]+ " | grep -v "__(\|_e(" | head -15
echo ""

echo "5. WP-CLI command strings:"
grep -n "WP_CLI::success\|WP_CLI::error\|WP_CLI::warning\|WP_CLI::log" msh-image-optimizer/includes/class-msh-*-cli.php | head -15
echo ""

echo "✅ FILES TO CHECK:"
echo "------------------"
find msh-image-optimizer -name "*.php" -o -name "*.js" | grep -E "admin/|includes/" | head -30
