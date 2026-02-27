#!/bin/bash
# WordPress Plugin Review - Tool Setup Script
# Installs PHPCS with WordPress Coding Standards, PHPStan, and PHPUnit

set -e

echo "=================================="
echo "WP Plugin Review - Tool Setup"
echo "=================================="

TOOLS_DIR="/home/claude/.wp-review-tools"
mkdir -p "$TOOLS_DIR"

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "📦 Installing PHP..."
    sudo apt-get update -qq
    sudo apt-get install -y -qq php-cli php-xml php-mbstring php-json php-curl php-tokenizer 2>/dev/null
fi

echo "✅ PHP $(php -v | head -1 | awk '{print $2}') available"

# Check if Composer is available
if ! command -v composer &> /dev/null; then
    echo "📦 Installing Composer..."
    cd /tmp
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --quiet
    sudo mv composer.phar /usr/local/bin/composer
    rm -f composer-setup.php
fi

echo "✅ Composer available"

# Install PHPCS + WordPress Coding Standards
if [ ! -f "$TOOLS_DIR/vendor/bin/phpcs" ]; then
    echo "📦 Installing PHPCS + WordPress Coding Standards..."
    cd "$TOOLS_DIR"
    
    composer init --no-interaction --name="wp-review/tools" --quiet 2>/dev/null || true
    composer require --dev \
        squizlabs/php_codesniffer:"^3.7" \
        wp-coding-standards/wpcs:"^3.0" \
        phpcompatibility/phpcompatibility-wp:"*" \
        dealerdirect/phpcodesniffer-composer-installer:"^1.0" \
        --quiet 2>/dev/null
    
    echo "✅ PHPCS + WPCS installed"
else
    echo "✅ PHPCS + WPCS already installed"
fi

# Verify WPCS standards are registered
echo ""
echo "Registered PHPCS standards:"
"$TOOLS_DIR/vendor/bin/phpcs" -i

# Install PHPStan
if [ ! -f "$TOOLS_DIR/vendor/bin/phpstan" ]; then
    echo "📦 Installing PHPStan..."
    cd "$TOOLS_DIR"
    composer require --dev phpstan/phpstan:"^1.10" --quiet 2>/dev/null
    echo "✅ PHPStan installed"
else
    echo "✅ PHPStan already installed"
fi

# Install PHPUnit
if [ ! -f "$TOOLS_DIR/vendor/bin/phpunit" ]; then
    echo "📦 Installing PHPUnit..."
    cd "$TOOLS_DIR"
    composer require --dev phpunit/phpunit:"^10.0" --quiet 2>/dev/null
    echo "✅ PHPUnit installed"
else
    echo "✅ PHPUnit already installed"
fi

# Create convenience symlinks
echo ""
echo "📋 Creating convenience aliases..."
mkdir -p /home/claude/.local/bin

ln -sf "$TOOLS_DIR/vendor/bin/phpcs" /home/claude/.local/bin/phpcs
ln -sf "$TOOLS_DIR/vendor/bin/phpcbf" /home/claude/.local/bin/phpcbf
ln -sf "$TOOLS_DIR/vendor/bin/phpstan" /home/claude/.local/bin/phpstan
ln -sf "$TOOLS_DIR/vendor/bin/phpunit" /home/claude/.local/bin/phpunit

export PATH="/home/claude/.local/bin:$PATH"

echo ""
echo "=================================="
echo "✅ All tools installed successfully!"
echo "=================================="
echo ""
echo "Available commands:"
echo "  phpcs --standard=WordPress /path/to/plugin"
echo "  phpcbf --standard=WordPress /path/to/plugin  (auto-fix)"
echo "  phpstan analyse --level=5 /path/to/plugin"
echo "  phpunit (run from plugin directory with phpunit.xml)"
echo ""
echo "PHPCS Standards available:"
"$TOOLS_DIR/vendor/bin/phpcs" -i
