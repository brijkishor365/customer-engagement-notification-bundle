#!/bin/bash
# Test runner script for CustomerEngagementNotificationBundle
# This script finds and runs PHPUnit from various possible locations

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}CEP Bundle Test Runner${NC}"
echo "========================"

# Find PHPUnit in various locations
PHPUNIT_PATHS=(
    "../../../vendor/bin/phpunit"  # When bundle is in vendor/qburst/customer-engagement-notification-bundle
    "../../vendor/bin/phpunit"     # When bundle is in src/CustomerEngagementNotificationBundle
    "./vendor/bin/phpunit"         # When in project root
    "phpunit"                      # System-wide installation
)

PHPUNIT_CMD=""
for path in "${PHPUNIT_PATHS[@]}"; do
    if command -v "$path" >/dev/null 2>&1; then
        PHPUNIT_CMD="$path"
        break
    fi
done

if [ -z "$PHPUNIT_CMD" ]; then
    echo -e "${RED}Error: PHPUnit not found.${NC}"
    echo ""
    echo "Please install PHPUnit:"
    echo "  composer require --dev phpunit/phpunit"
    echo ""
    echo "Or install globally:"
    echo "  wget https://phar.phpunit.de/phpunit.phar"
    echo "  chmod +x phpunit.phar"
    echo "  sudo mv phpunit.phar /usr/local/bin/phpunit"
    exit 1
fi

echo -e "${YELLOW}Using PHPUnit: $PHPUNIT_CMD${NC}"
echo ""

# Run tests
echo "Running tests..."
$PHPUNIT_CMD "$@"

echo ""
echo -e "${GREEN}Tests completed!${NC}"