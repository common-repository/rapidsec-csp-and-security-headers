<?php
/**
 * The plugin settings file
 *
 * @package Rapidsec
 */

declare(strict_types=1);

namespace Rapidsec;

require_once 'config.php';
require_once 'AdditionalPlugins.php';

defined('ABSPATH') || die('No script kiddies please!');

/**
 * The CSP settings class
 */
class Settings extends Config
{
  /**
   * Store options in memory.
   *
   * @var array[]
   */

  private $options;
  /**
   * @var string
   */
  private $version;

  /**
   * @var Core
   */
  private $core;

  /**
   * Set up actions needed for the plugin's admin interface
   *
   * @param string $pluginfile __FILE__ path to the main plugin file.
   * @param Core $core
   *
   */
  public function __construct(string $pluginfile, Core $core)
  {
    $this->core = $core;

    $this->options = [
      'frontend' => array_merge(Config::rapidsec_get_option(RAPIDSEC_CONFIG_FRONTEND), [
        'title' => 'Frontend Policy',
        'description' => 'Set the policy to be used for visitors to the site\'s frontend.',
        'missing_token_error' => 'Frontend API Token is empty',
      ]),
      'admin' => array_merge(Config::rapidsec_get_option(RAPIDSEC_CONFIG_ADMIN), [
        'title' => 'Admin Policy',
        'description' => 'Set the policy to be used in the WordPress admin interface.',
        'missing_token_error' => 'Admin API Token is empty',
        'admin_path' => '/wp-admin',
      ]),
    ];

    if (!function_exists('get_plugin_data')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $this->version = $core->version;

    add_action('admin_init', [$this, 'csp_settings_init']);
    add_action('admin_menu', [$this, 'csp_admin_menu']);
    // If this is the first time we've enabled the plugin, setup default settings.
    register_activation_hook($pluginfile, [$this, 'first_time_activation']);

    add_action('admin_footer', [$this, 'rapidsec_test_token_javascript']);
    add_action('admin_footer', [$this, 'rapidsec_clear_cache_javascript']);
    add_action('admin_notices', [$this, 'rapidsec_add_admin_notice']);
    add_action('admin_enqueue_scripts', [$this, 'rapidsec_enqueue_styles']);
    add_action('admin_enqueue_scripts', [$this, 'rapidsec_enqueue_scripts']);
    add_action('admin_notices', [$this, 'rapidsec_add_banner_info']);

    #Filters
    add_filter('plugin_row_meta', [$this, 'add_plugin_metadata'], 10, 2);
    add_action('update_option_' . RAPIDSEC_CONNECTOR_CONFIG, [$this, 'on_connector_options_update'], 10, 2);

    $this->options = $this->core->pluginAdditions->apply_custom_options($this->options);
  }

  /**
   * Filters the array of row meta for each/specific plugin in the Plugins list table.
   * Appends additional links below each/specific plugin on the plugins page.
   *
   * @param array $links_array
   * @param string $plugin_file_name
   *
   * @return array
   */
  public function add_plugin_metadata(array $links_array, string $plugin_file_name): array
  {
    if (strpos($plugin_file_name, 'rapidsec') !== false) {
      $links_array[] = '<a href="https://rapidsec.com/pricing" target="_blank">' . __('Pricing', 'rapidsec') . '</a>';
      $links_array[] = '<a href="https://rapidsec.com/contact-us" target="_blank">' . __('Contact US', 'rapidsec') . '</a>';
    }

    return $links_array;
  }

  public function on_connector_options_update($old_value, $value)
  {
    if ($old_value['connection_type'] !== $value['connection_type']) {
      $this->core->run_clear_cache();
    }
  }

  /**
   * Runs on first activation, sets default settings
   *
   */
  public function first_time_activation(): void
  {
    foreach ($this->options as $key => $val) {
      if (Config::rapidsec_get_option('rapidsec_config_' . $key) === false) {
        $this->rapidsec_update_option('rapidsec_config_' . $key, [
          'token' => $val['token'],
          'polling_interval' => 15,
        ]);
      }
    }

    $this->rapidsec_update_option(RAPIDSEC_CONNECTOR_CONFIG, [
      'connection_type' => 'php',
    ]);

    set_transient(RAPIDSEC_ADMIN_NOTICE_ACTIVATION, true, 5);
  }

  /**
   * Registers the settings with WordPress
   *
   */
  public function csp_settings_init(): void
  {
    foreach ($this->options as $key => $option) {
      $this->csp_add_settings($key, __($option['title'], 'rapidsec'), __($option['description'], 'rapidsec'));
    }

    $this->register_connection_settings();
  }

  /**
   * Display all settings for the internal option called $name.
   *
   * @param string $name Current internal option, either 'admin' or 'frontend'.
   * @param string $title The title to use for the settings section.
   * @param string $description The description to use for the settings section.
   *
   */
  public function csp_add_settings(string $name, string $title, string $description): void
  {
    register_setting('csp', 'rapidsec_config_' . $name);

    add_settings_section(
      'csp_' . $name,
      $title,
      function () use ($description, $title) {
        ?>
              <h3> <?php echo $title; ?></h3>
              <p style="margin-top: 0"> <?php echo $description; ?></p>
				<?php
      },
      'csp'
    );

    add_settings_field(
      'csp_' . $name . '_token',
      __('Token', 'rapidsec'),
      function () use ($name, $description) {
        $value = $this->options[$name]['token'] ?? ''; ?>
              <div>
                <div style="display: flex">
                  <input name="rapidsec_config_<?php echo $name; ?>[token]" value="<?php echo esc_attr($value); ?>" />
                  <button type="button" style="margin-left: 1em"
                          class="button button-info rapidsec_config_<?php echo $name; ?>_button"
                          data-name="<?php echo $name; ?>"
                          onclick="rapidsec_run_test_token(this, '<?php echo $name; ?>')">
					  <?php esc_html_e(__('Check', 'rapidsec')); ?>
                  </button>
                </div>
                <span class="rapidsec-config-item-text rapidsec_config_<?php echo $name; ?>_text"></span>
              </div>
				<?php
      },
      'csp',
      'csp_' . $name
    );

    add_settings_field(
      'csp_' . $name . '_polling_interval',
      __('Polling interval ', 'rapidsec'),
      function () use ($name) {
        $interval_value = $this->options[$name]['polling_interval']; ?>
              <div style="display: flex">
                <label>
                  <input name="rapidsec_config_<?php echo $name; ?>[polling_interval]"
                         value="<?php echo esc_attr($interval_value); ?>" type="number" />
                </label>
                <span
                  style="display: flex;align-items: center;padding-left: 0.5em"><?php esc_html_e(__('Seconds', 'rapidsec')); ?></span>
              </div>
				<?php
      },
      'csp',
      'csp_' . $name
    );

    if (array_key_exists('admin_path', $this->options[$name])) {
      add_settings_field(
        'csp_' . $name . '_path',
        __('Admin path', 'rapidsec'),
        function () use ($name, $description) {
          $value = $this->options[$name]['admin_path'] ?? ''; ?>
                  <label>
                    <input name="rapidsec_config_<?php echo $name; ?>[admin_path]"
                           value="<?php echo esc_attr($value); ?>" required />
                  </label>
					<?php
        },
        'csp',
        'csp_' . $name
      );
    }
  }

  private function register_connection_settings()
  {
    $config = Config::rapidsec_get_option(RAPIDSEC_CONNECTOR_CONFIG);

    $type = $config['connection_type'] ?? 'htaccess';

    $items = [
      'php' => __('Use PHP to send headers (required for WooCommerce)', 'rapidsec'),
      'htaccess' => __('Use Apache (mod_headers) to send headers', 'rapidsec'),
    ];

    register_setting('csp', RAPIDSEC_CONNECTOR_CONFIG);

    add_settings_section('csp_connection', 'Connection', function () {}, 'csp');

    add_settings_field(
      'connection_type',
      '',
      function () use ($items, $type) {
        function print_element($key, $val, $type)
        {
          $subtitle = $key === 'htaccess' && stripos(getenv('SERVER_SOFTWARE'), 'Apache') !== false ? '<p>' . __('(Recommended, as we detected apache web server)', 'rapidsec') . '</p>' : ''; ?>
                  <div>
                    <input type="radio" name="<?php echo RAPIDSEC_CONNECTOR_CONFIG; ?>[connection_type]"
                           value="<?php echo $key; ?>" <?php checked($type, $key, true); ?>>
                    <label for='<?php echo RAPIDSEC_CONNECTOR_CONFIG; ?>[connection_type]'>
                      <div>
                        <p><?php echo $val; ?></p>
						  <?php echo $subtitle; ?>
                      </div>
                    </label>
                  </div>
					<?php
        } ?>
              <div>
                <table class="form-table rapidsec_connections_table">
                  <tbody>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <fieldset>
						  <?php foreach ($items as $key => $val) {
          print_element($key, $val, $type);
        } ?>
                      </fieldset>
                    </td>
                  </tr>
                  </tbody>
                </table>
              </div>
				<?php
      },
      'csp',
      'csp_connection'
    );
  }

  /**
   * Adds an entry in the sidebar
   */
  public function csp_admin_menu(): void
  {
    $iconUrl =
      'PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPHN2ZyB2aWV3Qm94PSIwIDAgNjAgMTAyIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj4KICA8ZGVmcz4KICAgIDxsaW5lYXJHcmFkaWVudCBpZD0i0JHQtdC30YvQvNGP0L3QvdGL0Llf0LPRgNCw0LTQuNC10L3Rgl82IiB4MT0iNDIyLjA4IiB5MT0iNDE2LjgyIiB4Mj0iOTA3LjQ4IiB5Mj0iMTI1Ny41NiIgZ3JhZGllbnRVbml0cz0idXNlclNwYWNlT25Vc2UiIGdyYWRpZW50VHJhbnNmb3JtPSJtYXRyaXgoMC4wNDUyMjUsIDAsIDAsIDAuMDQ1MjI1LCAtMS44MzM4MDMsIDE4LjM0MjUyOCkiPgogICAgICA8c3RvcCBvZmZzZXQ9IjAiIHN0eWxlPSJzdG9wLWNvbG9yOiByZ2IoMTU0LCAxNjAsIDE2NSk7Ii8+CiAgICAgIDxzdG9wIG9mZnNldD0iMC4xMyIgc3R5bGU9InN0b3AtY29sb3I6IHJnYigxNTQsIDE2MCwgMTY1KTsiLz4KICAgICAgPHN0b3Agb2Zmc2V0PSIwLjI4IiBzdHlsZT0ic3RvcC1jb2xvcjogcmdiKDE1NCwgMTYwLCAxNjUpOyIvPgogICAgICA8c3RvcCBvZmZzZXQ9IjAuNDQiIHN0eWxlPSJzdG9wLWNvbG9yOiByZ2IoMTU0LCAxNjAsIDE2NSk7Ii8+CiAgICAgIDxzdG9wIG9mZnNldD0iMC42MyIgc3R5bGU9InN0b3AtY29sb3I6IHJnYigxNTQsIDE2MCwgMTY1KTsiLz4KICAgICAgPHN0b3Agb2Zmc2V0PSIwLjg4IiBzdHlsZT0ic3RvcC1jb2xvcjogcmdiKDE1NCwgMTYwLCAxNjUpOyIvPgogICAgPC9saW5lYXJHcmFkaWVudD4KICAgIDxsaW5lYXJHcmFkaWVudCBpZD0i0JHQtdC30YvQvNGP0L3QvdGL0Llf0LPRgNCw0LTQuNC10L3Rgl82LTIiIHgxPSI5NzguMjYiIHkxPSI5ODIuOTkiIHgyPSI0OTIuODUiIHkyPSIxNDIuMjQiIGdyYWRpZW50VHJhbnNmb3JtPSJtYXRyaXgoMC4wMTkzMTcsIDAsIDAsIDAuMDE5MzE3LCAxLjA3MDY5OSwgMTkuMjkyMjQ0KSIgeGxpbms6aHJlZj0iI9CR0LXQt9GL0LzRj9C90L3Ri9C5X9Cz0YDQsNC00LjQtdC90YJfNiIvPgogICAgPHN0eWxlPi5jbHMtMXtmaWxsOnVybCgj0JHQtdC30YvQvNGP0L3QvdGL0Llf0LPRgNCw0LTQuNC10L3Rgl84OSk7fS5jbHMtMntmaWxsOnVybCgj0J3QvtCy0YvQuV/QvtCx0YDQsNC30LXRhl/Qs9GA0LDQtNC40LXQvdGC0LBfMSk7fS5jbHMtM3tmaWxsOnVybCgj0J3QvtCy0YvQuV/QvtCx0YDQsNC30LXRhl/Qs9GA0LDQtNC40LXQvdGC0LBfMS0yKTt9LmNscy00e2ZpbGw6dXJsKCPQkdC10LfRi9C80Y/QvdC90YvQuV/Qs9GA0LDQtNC40LXQvdGCXzYpO30uY2xzLTV7ZmlsbDp1cmwoI9CR0LXQt9GL0LzRj9C90L3Ri9C5X9Cz0YDQsNC00LjQtdC90YJfNi0yKTt9PC9zdHlsZT4KICA8L2RlZnM+CiAgPHBhdGggY2xhc3M9ImNscy0xIiBkPSJNIDI2LjQ5MSA0Ny43MSBMIDI5LjY1MyA0Ny41MjkgTCAyOS44MDIgMzAuNDM4IEwgMjMuOTAxIDQ1Ljc3NiBDIDIzLjg4IDQ2Ljg3NSAyNS4wMzQgNDcuNzk1IDI2LjQ5MSA0Ny43MSBaIiBzdHlsZT0iZmlsbDogcmdiKDE1NCwgMTYwLCAxNjUpOyIvPgogIDxwYXRoIGNsYXNzPSJjbHMtMiIgZD0iTSAzMy4yMDkgNTIuNDMxIEwgMjkuOTg4IDUyLjYyIEwgMjkuODMyIDY5LjU2MyBMIDM1Ljc5NCA1NC4xOTUgQyAzNS42OTkgNTMuMTczIDM0LjU5MiA1Mi4zNDkgMzMuMjA5IDUyLjQzMSBaIiBzdHlsZT0iZmlsbDogcmdiKDE1NCwgMTYwLCAxNjUpOyIvPgogIDxwYXRoIGNsYXNzPSJjbHMtMyIgZD0iTSAzNC44MzMgNDcuMjI0IEwgMjkuNjY2IDQ3LjUyOSBMIDI5LjY2MyA0Ny41MjkgTCAyNi40OTEgNDcuNzA5IEMgMjUuMDM0IDQ3Ljc5NSAyMy44OCA0Ni44NzUgMjMuOTAxIDQ1Ljc3NSBMIDIyLjI5OSA1MC41MzggQyAyMS44OTUgNTEuODIzIDIzLjE1OCA1My4wMjIgMjQuODE0IDUyLjkyNCBMIDI5Ljk4IDUyLjYyIEwgMjkuOTggNTIuNjIgTCAzMy4yMDcgNTIuNDMxIEMgMzQuNTkgNTIuMzQ5IDM1LjY5NyA1My4xNzMgMzUuNzkxIDU0LjE5NSBMIDM3LjM0NyA0OS42MDkgQyAzNy43NTEgNDguMzI4IDM2LjQ4OSA0Ny4xMjYgMzQuODMzIDQ3LjIyNCBaIiBzdHlsZT0iZmlsbDogcmdiKDE1NCwgMTYwLCAxNjUpOyIvPgogIDxwYXRoIGNsYXNzPSJjbHMtNCIgZD0iTSA1Ni40MDkgNTMuOTEzIEwgNTYuNDA5IDUzLjkxMyBDIDU2LjAzOSA1NC42MjEgNTUuNDc0IDU1LjIxIDU0Ljc4MSA1NS42MDkgTCA0Ny4yNTQgNTkuOTk5IEwgNDcuMjU0IDU5Ljk5OSBMIDI5LjgyMyA3MC4wNjMgTCAxMi4zOSA1OS45OTggTCAxMi4zOSA0MC4wMDIgTCAxMi4zOSA0MC4wMDIgTCA0Ljc4MiA0NC40MzMgQyA0LjEzMSA0NC44MDggMy41OTYgNDUuMzU1IDMuMjM2IDQ2LjAxNSBMIDMuMjM2IDYyLjkyIEMgMy4yMzYgNjQuMzgyIDQuMDE2IDY1LjczMyA1LjI4MiA2Ni40NjUgTCAxNy4wMTQgNzMuMjM5IEwgMjcuNzc4IDc5LjQ1MiBDIDI5LjA0NCA4MC4xODMgMzAuNjA0IDgwLjE4MyAzMS44NzEgNzkuNDUyIEwgNDMuNjE1IDcyLjY3MyBMIDU0LjM2NiA2Ni40NjUgQyA1NS42MzEgNjUuNzMzIDU2LjQxMiA2NC4zODIgNTYuNDEyIDYyLjkyIEwgNTYuNDEyIDUzLjkxIFoiIHN0eWxlPSIiLz4KICA8cGF0aCBjbGFzcz0iY2xzLTUiIGQ9Ik0gNTYuNDEgMzcuMDgxIEwgNTYuNDEgNTMuOTEzIEMgNTYuMDUyIDU0LjYxMyA1NS40OTggNTUuMTk2IDU0LjgxNyA1NS41OSBMIDQ3LjI1OSA1OS45OTggTCA0Ny4yNTkgNTkuOTk4IEwgNDcuMjU5IDQwLjAwMyBMIDI5LjgyMyAyOS45MzggTCAxMi4zOTIgNDAuMDAyIEwgMTIuMzkyIDQwLjAwMiBMIDQuNzg0IDQ0LjQzMyBDIDQuMTMyIDQ0LjgwOCAzLjU5OCA0NS4zNTUgMy4yMzcgNDYuMDE1IEMgMy4yMzggNDYuMDE2IDMuMjM4IDQ2LjAxNyAzLjIzNyA0Ni4wMTggTCAzLjIzNyAzNy4wODEgQyAzLjIzOCAzNS42MiA0LjAxNyAzNC4yNyA1LjI4MiAzMy41MzkgTCAxNS43ODcgMjcuNDczIEwgMjcuNzc2IDIwLjU0OSBDIDI5LjA0MyAxOS44MTggMzAuNjAyIDE5LjgxOCAzMS44NjkgMjAuNTQ5IEwgNDIuMzAzIDI2LjU3NCBMIDU0LjM2NCAzMy41MzkgQyA1NS42MjkgMzQuMjY5IDU2LjQwOSAzNS42MiA1Ni40MSAzNy4wODEgWiIgc3R5bGU9IiIvPgo8L3N2Zz4=';

    $admin_page = add_menu_page(
      'RapidSec CSP',
      'RapidSec',
      'manage_options',
      'rapidsec',

      function () {
        $message =
          'Get a token at <a target="_blank" href="https://rapidsec.com/">RapidSec</a> and See our Product Tour <a class="venobox" data-vbtype="video" data-autoplay="true" href="https://www.youtube.com/embed/SC5-PzuboQo">See video</a>'; ?>
              <div class="wrap">
                <div class="custom-row">
                  <div class="main-sec">
                    <h1><?php esc_html_e('RapidSec – CSP and Security Headers', 'rapidsec'); ?></h1>
                    <p><?php esc_html_e('This plugin helps you protect your WordPress site and admin panel from various client-side cyber attacks, such as XSS, formjacking and Magecart.', 'rapidsec'); ?></p>
                    <p><?php esc_html_e('It links with the Rapidsec service to automatically generate your Content-Security-Policy (CSP) and security headers, and monitor for attacks in realtime.', 'rapidsec'); ?>
						<?php echo '<a target="_blank" href="https://rapidsec.com/wordpress-security-headers-and-csp">' . __('Get more info', 'rapidsec') . '</a>'; ?></p>
                    <p><?php _e($message, 'rapidsec'); ?></p>
                    <form id="csp_settings" action='options.php' method='post' style='clear:both;'>
                      <div class="rapidsec_configs_main">
                        <div class="rapidsec_tokens_configs">
							<?php $this->tokens_parts(); ?>
                        </div>
						  <?php $this->connection_parts(); ?>
                      </div>
                      <div class="btn-flex-sec">
                        <p class="cache-clear">
                          <input type="button" id="clear_cache" name="clear_cache"
                                 title="<?php esc_html_e(__('Clear Cache', 'rapidsec')); ?>"
                                 value="<?php esc_html_e(__('Clear Cache', 'rapidsec')); ?>"
                                 onclick="rapidsec_run_clear_cache(this);" class="button button-info">
                        </p>
						  <?php submit_button(); ?>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
				<?php
      },
      'data:image/svg+xml;base64,' . $iconUrl,
      '58.5'
    );
  }

  private function tokens_parts()
  {
    settings_fields('csp');

    global $wp_settings_sections;

    foreach ((array) $wp_settings_sections['csp'] as $section) {
      if (!in_array($section['id'], ['csp_admin', 'csp_frontend', 'csp_checkout'])) {
        continue;
      } ?>
          <div class="rapidsec_panel <?php echo $section['id']; ?>">
			  <?php call_user_func($section['callback'], $section); ?>
            <table class="form-table rapidsec_tokens_table" role="presentation">
				<?php do_settings_fields('csp', $section['id']); ?>
            </table>
          </div>
			<?php
    }
  }

  private function connection_parts()
  {
    ?>
      <div class="rapidsec_panel">
        <h3><?php esc_html_e('Advanced configuration', 'rapidsec'); ?></h3>
        <div>
          <table class="hh-index-table">
            <thead>
            <tr>
              <th>Directive</th>
              <th>Value</th>
            </tr>
            </thead>
            <tbody>
            <tr class="active">
              <td>PHP version</td>
              <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr class="active">
              <td>Server Software</td>
              <td><?php echo getenv('SERVER_SOFTWARE'); ?></td>
            </tr>
            <tr class="active">
              <td>Server API</td>
              <td><?php echo PHP_SAPI; ?></td>
            </tr>
            </tbody>
          </table>
        </div>
        <div>
          <h3><?php _e('Connection mode', 'rapidsec'); ?></h3>
          <p
            class="description"><?php _e('Choose a method for sending of headers. Usually, the PHP method works perfectly. However, some third-party plugins like WP Super Cache may require switching to Apache method.', 'rapidsec'); ?></p>
			<?php do_settings_fields('csp', 'csp_connection'); ?>

        </div>
      </div>
		<?php
  }

  public function rapidsec_test_token_javascript()
  {
    ?>
      <script type="text/javascript">
        function rapidsec_run_test_token(button, name) {
          var token = document.querySelector('input[name="rapidsec_config_' + name + '[token]"]').value;
          var textNode = document.querySelector('span.rapidsec_config_' + name + '_text');

          var buttonStyles = {
            idle: 'margin-left: 1em;',
            fetching: 'margin-left: 1em; opacity: 0.5',
            success: 'margin-left: 1em; background-color: limegreen; color: white;',
            failed: 'margin-left: 1em; background-color: lightcoral; color: white;'
          };

          button.style = buttonStyles.idle;
          button.disabled = true;

          // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
          jQuery.ajax({
            type: 'POST', url: ajaxurl, data: {
              'action': 'rapidsec_test_token',
              'token': token
            }, success: function(response) {
              if (response.success) {
                button.style = buttonStyles.success;
                textNode.textContent = 'Success, got version ' + response.data.version;
              } else {
                button.style = buttonStyles.failed;
                textNode.textContent = "<?php esc_html_e(__('No configuration found with this token', 'rapidsec')); ?>";
              }
              button.disabled = false;
              var timeoutKey = 'rapidsec_fetch_timeout_' + name;

              if (window[timeoutKey]) {
                clearTimeout(window[timeoutKey]);
              }
              window[timeoutKey] = setTimeout(function() {
                button.style = buttonStyles.idle;
                if (response.success) {
                  textNode.textContent = '';
                }
              }, 10 * 1000);
            }
          });
        }
      </script> <?php
  }

  public function rapidsec_clear_cache_javascript()
  {
    ?>
      <script type="text/javascript">
        function rapidsec_run_clear_cache(button) {
          button.disabled = true;

          // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
          jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
              'action': 'rapidsec_clear_cache'
            },
            success: function(data) {
              if (data.success == true && data.data.status == 200) {
                location.reload();
              } else {
                button.disabled = false;
              }
            },
            error: function(error) {
              button.disabled = false;
              console.log(error);
            }
          });
        }
      </script> <?php
  }

  public function rapidsec_add_admin_notice()
  {
    $this->initialNoTokensNotifications();
    $this->clearedCacheNotifications();
    $this->missingTokensNotifications();
  }

  private function initialNoTokensNotifications()
  {
    $option = $this->options['admin']['token'];
    $option_front = $this->options['frontend']['token'];

    if ((empty($option) && empty($option_front)) || get_transient(RAPIDSEC_ADMIN_NOTICE_ACTIVATION)) {
      $cfg_message =
        "In order to activate the RapidSec CSP protection, open an account at <a target='_blank' href='https://rapidsec.com/'>RapidSec</a>. You should generate two project tokens: one for your front end site, and the other for your admin panel. <a target='_blank' href='https://rapidsec.com/wordpress-security-headers-and-csp'>More information</a> about integration.";
      echo '<div class="notice notice-warning is-dismissible"><p>' . __($cfg_message, 'rapidsec') . '</p></div>';
    }
    delete_transient(RAPIDSEC_ADMIN_NOTICE_ACTIVATION);
  }

  private function clearedCacheNotifications()
  {
    if (get_transient(RAPIDSEC_CACHE_ADMIN_NOTICE)) {
      $class = 'notice notice-success';
      $notice = 'All caches successfully emptied.';
      printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($notice));
    }

    delete_transient(RAPIDSEC_CACHE_ADMIN_NOTICE);
  }

  public function missingTokensNotifications()
  {
    $errors = [];

    foreach ($this->options as $key => $option) {
      if (empty($option['token'])) {
        array_push($errors, __($option['missing_token_error'], 'rapidsec'));
      }
    }

    $class = 'notice notice-error is-dismissible';

    if (!empty($errors)) {
      printf('<div class="%1$s">', esc_attr($class));
      printf('<p><b>%1$s</b></p>', esc_html(__('API Tokens are empty, please add new API Tokens.', 'rapidsec')));
      foreach ($errors as $error) {
        printf('<p>%1$s</p>', esc_html($error));
      }
      printf('</div>');
    }
  }

  public function rapidsec_enqueue_styles()
  {
    wp_enqueue_style('venobox-css', plugin_dir_url(__DIR__) . 'admin/css/venobox.min.css', [], $this->version, 'all');
    wp_enqueue_style('rapidsec_style', plugin_dir_url(__DIR__) . 'admin/css/style.css', [], $this->version, 'all');
  }

  public function rapidsec_enqueue_scripts()
  {
    wp_enqueue_script('venobox_js', plugin_dir_url(__DIR__) . 'admin/js/venobox.min.js', [], $this->version, true);
    wp_enqueue_script('rapidsec_js', plugin_dir_url(__DIR__) . 'admin/js/rapidsec.js', [], $this->version, true);

    $feedback = new Feedback();
  }

  public function rapidsec_add_banner_info()
  {
    $fetch_banner_info = Config::rapidsec_get_option(RAPIDSEC_BANNER_NOTICE_HIDE);

    if (empty($fetch_banner_info)) {
      $user = $this->get_display_name();
      $notification_template = '<div class="%1$s"><p><strong>%2$s</strong></p><p>%3$s</p></div>';
      $class = esc_attr('notice notice-success banner-info is-dismissible');
      $message =
        '<p>' .
        __('Hey', 'rapidsec') .
        ' ' .
        $user .
        ', ' .
        __('you have been using the RapidSec for a while now - that\'s great!', 'rapidsec') .
        '</p><p>' .
        __(
          'Could you do us a big favor and <strong>give us your review on <a href="https://wordpress.org/support/plugin/rapidsec-csp-and-security-headers/" target="_blank">WordPress.org</a></strong>? This will help us to increase our visibility and to develop even <strong>more features for you</strong>.',
          'rapidsec'
        ) .
        '</p><p>' .
        __('Thanks!', 'rapidsec') .
        '</p>';
      printf($notification_template, $class, 'Rapidsec', $message);
    }
  }

  public function get_display_name()
  {
    $user = wp_get_current_user();

    return $user->data->display_name;
  }
}

class Feedback
{
  public function __construct()
  {
    $defaultReasons = [
      'suddenly-stopped-working' => __('The plugin suddenly stopped working', 'rapidsec'),
      'plugin-broke-site' => __('The plugin broke my site', 'rapidsec'),
      'no-longer-needed' => __('I don\'t need this plugin any more', 'rapidsec'),
      'plugin-not-working' => __('I couldn\'t get the plugin to work', 'rapidsec'),
      'found-better-plugin' => __('I found a better plugin', 'rapidsec'),
      'temporary-deactivation' => __('It\'s a temporary deactivation, I\'m troubleshooting', 'rapidsec'),
      'other' => __('Other', 'rapidsec'),
    ];

    $texts = [
      'quick_feedback' => __('Quick Feedback', 'rapidsec'),
      'foreword' => __('If you would be kind enough, please tell us why you\'re deactivating?', 'rapidsec'),
      'skip_and_deactivate' => __('Skip &amp; Deactivate', 'rapidsec'),
      'submit_and_deactivate' => __('Submit &amp; Deactivate', 'rapidsec'),
      'please_wait' => __('Please wait', 'rapidsec'),
      'thank_you' => __('Thank you!', 'rapidsec'),
    ];

    // Send plugin data
    wp_localize_script('rapidsec_js', 'deactivate_feedback_form_reasons', $defaultReasons);
    wp_localize_script('rapidsec_js', 'deactivate_feedback_form_text', $texts);
  }
}
