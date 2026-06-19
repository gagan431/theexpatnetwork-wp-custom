<?php
/**
 * Plugin Name: Germany Job Offer Checker
 * Description: Adds a Germany Job Offer Checker calculator via shortcode [germany_job_offer_checker].
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * Author: The Expat Network
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GOC_VERSION')) {
    define('GOC_VERSION', '1.0.0');
}

/**
 * Returns a cache-busting version string for an asset.
 * Falls back to the plugin version if the file is missing, instead of
 * letting filemtime() throw a PHP warning on every page load.
 */
if (!function_exists('goc_asset_version')) {
    function goc_asset_version($absolute_path) {
        return file_exists($absolute_path) ? filemtime($absolute_path) : GOC_VERSION;
    }
}

/**
 * Register + enqueue the calculator's CSS/JS on the standard
 * 'wp_enqueue_scripts' hook, which always fires before wp_head() prints
 * <link>/<script> tags.
 *
 * Previously these were enqueued from inside the shortcode callback,
 * which runs while the_content() is rendered in the page body -- i.e.
 * AFTER wp_head() (and its wp_print_styles() call) has already fired in
 * most themes. That meant the stylesheet could silently fail to load
 * depending on theme/cache-plugin timing. Enqueuing here removes that
 * dependency on *when* the shortcode happens to run.
 *
 * The combined CSS+JS payload is a few KB, so loading it site-wide on
 * the front end is a deliberate, low-cost trade-off for reliability
 * across pages, widgets, and page-builder contexts where detecting
 * shortcode presence in advance isn't reliable.
 */
if (!function_exists('goc_register_assets')) {
    function goc_register_assets() {
        $plugin_url  = plugin_dir_url(__FILE__);
        $plugin_path = plugin_dir_path(__FILE__);

        $style_path  = $plugin_path . 'assets/goc-style.css';
        $script_path = $plugin_path . 'assets/goc-script.js';

        wp_register_style(
            'goc-style',
            $plugin_url . 'assets/goc-style.css',
            array(),
            goc_asset_version($style_path)
        );

        wp_register_script(
            'goc-script',
            $plugin_url . 'assets/goc-script.js',
            array(),
            goc_asset_version($script_path),
            true
        );

        wp_localize_script('goc-script', 'GOC_PLUGIN', array(
            'dataUrl'      => $plugin_url . 'assets/scoring-data.json',
            // Filterable so a site can point this at a different page
            // (or disable it) without editing the JS bundle.
            'checklistUrl' => apply_filters('goc_checklist_url', home_url('/germany-job-offer-checklist/')),
            // Primary v1.0 conversion URL. Filterable for staging or page URL changes.
            'reviewUrl'    => apply_filters('goc_review_url', home_url('/services/germany-job-offer-review/')),
        ));

        wp_enqueue_style('goc-style');
        wp_enqueue_script('goc-script');
    }
}
add_action('wp_enqueue_scripts', 'goc_register_assets');

if (!function_exists('goc_shortcode')) {
    function goc_shortcode() {
        // A static counter gives every rendered instance of the shortcode
        // a unique id prefix. Without this, two copies of the shortcode on
        // the same page (e.g. one in content, one in a widget) would emit
        // duplicate element IDs -- invalid HTML, and it breaks <label for>
        // association because the browser always binds a label to the
        // *first* element with a matching id in the whole document.
        static $instance = 0;
        $instance++;
        $uid = 'goc-' . $instance;

        ob_start();
        ?>
        <section class="goc" aria-label="Germany Job Offer Checker">
          <noscript>
            <div class="goc-wrap">
              <div class="goc-noscript-notice">This calculator needs JavaScript to run. Please enable JavaScript in your browser to use it.</div>
            </div>
          </noscript>
          <div class="goc-hero">
            <div class="goc-wrap">
              <div class="goc-eyebrow">The Expat Network &middot; Germany-only MVP</div>
              <h2 class="goc-title">Germany Job Offer Checker</h2>
              <p class="goc-subtitle">Check whether your German job offer appears aligned with Blue Card salary thresholds, city costs, and role-level salary signals.</p>
              <div class="goc-notice">Educational readiness estimate &middot; Calibrated first for Berlin, Munich, and international tech/business roles &middot; Not legal, immigration, tax, financial, or career advice</div>
            </div>
          </div>

          <div class="goc-wrap goc-grid">
            <div class="goc-card goc-form-card">
              <h3>Check your offer</h3>
              <p class="goc-muted goc-small">Enter the core details. Your inputs are calculated locally in your browser.</p>

              <form class="goc-form" data-goc-form>
                <div class="goc-field">
                  <label for="<?php echo esc_attr($uid); ?>-salary">Gross annual offer in EUR</label>
                  <input type="number" id="<?php echo esc_attr($uid); ?>-salary" data-goc-field="salary" min="0" step="1" inputmode="numeric" placeholder="60000" autocomplete="off" required>
                </div>

                <div class="goc-row">
                  <div class="goc-field">
                    <label for="<?php echo esc_attr($uid); ?>-city">City</label>
                    <select id="<?php echo esc_attr($uid); ?>-city" data-goc-field="city" required></select>
                  </div>
                  <div class="goc-field">
                    <label for="<?php echo esc_attr($uid); ?>-role">Role</label>
                    <select id="<?php echo esc_attr($uid); ?>-role" data-goc-field="role" required></select>
                  </div>
                </div>

                <div class="goc-row">
                  <div class="goc-field">
                    <label for="<?php echo esc_attr($uid); ?>-experience">Experience</label>
                    <select id="<?php echo esc_attr($uid); ?>-experience" data-goc-field="experience" required>
                      <option value="0-2">0-2 years</option>
                      <option value="3-5">3-5 years</option>
                      <option value="6-10">6-10 years</option>
                      <option value="10+">10+ years</option>
                    </select>
                  </div>
                  <div class="goc-field">
                    <label for="<?php echo esc_attr($uid); ?>-household">Household</label>
                    <select id="<?php echo esc_attr($uid); ?>-household" data-goc-field="household" required>
                      <option value="single">Single</option>
                      <option value="coupleOneEarner">Couple, one earner</option>
                      <option value="coupleTwoEarners">Couple, two earners</option>
                      <option value="family">Family with children</option>
                    </select>
                  </div>
                </div>

                <div class="goc-field">
                  <label for="<?php echo esc_attr($uid); ?>-bluecard-relevant">Blue Card relevant?</label>
                  <select id="<?php echo esc_attr($uid); ?>-bluecard-relevant" data-goc-field="bluecardRelevant" required>
                    <option value="yes">Yes</option>
                    <option value="unsure">Not sure</option>
                    <option value="no">No</option>
                  </select>
                </div>

                <button type="submit" class="goc-button">Check my offer</button>
              </form>
            </div>

            <div class="goc-card goc-result-card" data-goc-result aria-live="polite" role="status">
              <div class="goc-placeholder">
                <h3>Your result will appear here</h3>
                <p>Use the form to generate Blue Card, affordability, market fairness, and experience-fit signals.</p>
              </div>
            </div>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('germany_job_offer_checker', 'goc_shortcode');
