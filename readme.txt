=== KO - GF Checkbox to Hidden ===
Contributors: KO
Tags: gravity forms, checkbox, hidden field, mapping
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Maps checked values from specific Gravity Forms checkbox fields into target hidden fields before submission.

== Description ==

This plugin collects the checked values from configured **Gravity Forms checkbox fields** and writes them to configured **Hidden fields** as a comma‑separated string during `gform_pre_submission_{form_id}`.

**Defaults included** (you can change via a filter):
- Form **54** — Checkbox **12** → Hidden **16**
- Form **54** — Checkbox **13** → Hidden **17**

You can choose to store **labels** instead of **values** via filter configuration.

== Installation ==

1. Upload the ZIP via **Plugins → Add New → Upload Plugin** and activate.
2. Ensure your form has the target **Hidden** fields created (Single Line Text set to Hidden visibility).

== Configuration ==

By default, the plugin registers two mappings for Form 54. To change or extend mappings, hook into the filter in a site plugin or theme:

```
add_filter( 'ko_gf_checkbox_to_hidden_mappings', function( $maps ) {
    // Replace or extend with your own mappings.
    return array(
        array( 'form_id' => 54, 'source' => 12, 'target' => 16, 'use_labels' => false ),
        array( 'form_id' => 54, 'source' => 13, 'target' => 17, 'use_labels' => false ),
        // Example: array( 'form_id' => 2, 'source' => 5, 'target' => 9, 'use_labels' => true ),
    );
} );
```

- `use_labels: true` will store the **choice labels** instead of the values.

== Changelog ==

= 1.0.0 =
* Initial release.
