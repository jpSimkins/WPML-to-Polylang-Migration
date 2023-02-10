# WPML to Polylang Migration

This plugin will migrate your data from WPML to Polylang.

I wrote this plugin due to not liking the official plugin as it has too many limitations, useless dependencies, didn't
import properly with large datasets, and didn't have an option to use cron for the import process. Mainly, it was
impossible to run it in a cloud environment.

I have built this plugin based on the official plugin and modified/restructured to work as a cron single run task in
WordPress.

---

## How to use

1. Deactivate all WPML plugins
1. Activate Polylang or Polylang Pro
1. Activate this plugin
1. Navigate to Tools->WPML to Polylang Migration
1. Click Import if all is green
    - I did allow languages to exist already, something that the official does not allow.
    - If the languages exist, it will not attempt to create them. So make sure you have the same languages setup if you
      already have them.
1. Wait for cron to trigger
    - If you don't use a true cron, just invoke it yourself by going to `domain.com/wp-cron.php?doing_wp_cron`
    - If you use WP CLI, you can run just this cron by invoking: `wp cron event run wpml_to_polylang_migration`
1. Verify the migration integrity by checking your data

---

## Warning

This is a major migration of data, you should create a backup of your data before you run this tool. Use at your own risk.
