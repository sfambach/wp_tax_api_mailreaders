<?php declare(strict_types=1);

/**
 * Plugin Name:       Taxmod Mail Readers
 * Description:       Reads the mails of AliExpress, Amazon, Conrad, Reichelt, Hermes and PayPal from the mailbox of Taxmod Mail and hands them to the inbox of the Taxonomy Modeller.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Stefan Fambach (SteFam), Claude (Anthropic AI)
 * Author URI:        https://www.fambach.net
 * License:           PolyForm Noncommercial 1.0.0
 * License URI:       https://polyformproject.org/licenses/noncommercial/1.0.0/
 * Text Domain:       taxapimailreaders
 * GitHub Plugin URI: sfambach/wp_tax_api_mailreaders
 * Primary Branch:    main
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */

if (! defined('ABSPATH')) {
    exit;
}

$taxapimailreadersAutoloader = __DIR__ . '/vendor/autoload.php';

if (! is_readable($taxapimailreadersAutoloader)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Taxmod Mail Readers:</strong> '
            . esc_html__('run "composer install" in the plugin folder — the autoloader is missing.', 'taxapimailreaders')
            . '</p></div>';
    });

    return;
}

require_once $taxapimailreadersAutoloader;

TaxApiMailReaders\WordPress\Plugin::boot();
