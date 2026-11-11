<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PHTC_Settings' ) ) {

	class PHTC_Settings {

		const OPTION = 'phtc_options';
		const GROUP  = 'phtc_settings_group';
		const PAGE   = 'phtc-settings';

		/**
		 * Default option values.
		 *
		 * @return array
		 */
		public static function defaults() {
			return array(
				'trigger_selectors'   => '.elementor-menu-cart__toggle_button',
				'auto_open'           => 'yes',
				'hide_elementor_cart' => 'yes',
				'social_shortcode'    => '',
			);
		}

		/**
		 * Get one option value.
		 *
		 * @param string $key Option key.
		 * @return string
		 */
		public static function get( $key ) {
			$saved   = get_option( self::OPTION, array() );
			$options = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
			return isset( $options[ $key ] ) ? (string) $options[ $key ] : '';
		}

		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
			add_action( 'admin_init', array( __CLASS__, 'register' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( PHTC_FILE ), array( __CLASS__, 'action_links' ) );
		}

		public static function action_links( $links ) {
			$url = admin_url( 'options-general.php?page=' . self::PAGE );
			array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'ph-tabbed-cart' ) . '</a>' );
			return $links;
		}

		public static function add_menu() {
			add_options_page(
				__( 'Tabbed Cart Panel', 'ph-tabbed-cart' ),
				__( 'Tabbed Cart', 'ph-tabbed-cart' ),
				'manage_options',
				self::PAGE,
				array( __CLASS__, 'render_page' )
			);
		}

		public static function register() {
			register_setting(
				self::GROUP,
				self::OPTION,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'default'           => self::defaults(),
				)
			);
		}

		/**
		 * Sanitize the submitted options.
		 *
		 * @param mixed $input Raw input.
		 * @return array
		 */
		public static function sanitize( $input ) {
			$input = is_array( $input ) ? $input : array();

			return array(
				'trigger_selectors'   => isset( $input['trigger_selectors'] ) ? sanitize_text_field( wp_unslash( $input['trigger_selectors'] ) ) : '',
				'auto_open'           => ! empty( $input['auto_open'] ) ? 'yes' : 'no',
				'hide_elementor_cart' => ! empty( $input['hide_elementor_cart'] ) ? 'yes' : 'no',
				'social_shortcode'    => isset( $input['social_shortcode'] ) ? sanitize_text_field( wp_unslash( $input['social_shortcode'] ) ) : '',
			);
		}

		public static function render_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$name = self::OPTION;
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Tabbed Cart Panel', 'ph-tabbed-cart' ); ?></h1>
				<form method="post" action="options.php">
					<?php settings_fields( self::GROUP ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="phtc_trigger_selectors"><?php echo esc_html__( 'Cart button selector(s)', 'ph-tabbed-cart' ); ?></label></th>
							<td>
								<input type="text" class="large-text" id="phtc_trigger_selectors" name="<?php echo esc_attr( $name ); ?>[trigger_selectors]" value="<?php echo esc_attr( self::get( 'trigger_selectors' ) ); ?>">
								<p class="description"><?php echo esc_html__( 'CSS selector(s) of the header cart button that should open the panel (comma separated). Default is the Elementor Menu Cart button. You can also add the CSS class phtc-open-cart, phtc-open-wishlist or phtc-open-account to any Elementor button or icon.', 'ph-tabbed-cart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Open after add to cart', 'ph-tabbed-cart' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto_open]" value="1" <?php checked( self::get( 'auto_open' ), 'yes' ); ?>>
									<?php echo esc_html__( 'Open the Cart tab automatically when a product is added (AJAX add to cart).', 'ph-tabbed-cart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Elementor cart popup', 'ph-tabbed-cart' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hide_elementor_cart]" value="1" <?php checked( self::get( 'hide_elementor_cart' ), 'yes' ); ?>>
									<?php echo esc_html__( 'Hide the old Elementor Menu Cart popup (recommended).', 'ph-tabbed-cart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="phtc_social_shortcode"><?php echo esc_html__( 'Social login shortcode', 'ph-tabbed-cart' ); ?></label></th>
							<td>
								<input type="text" class="large-text" id="phtc_social_shortcode" name="<?php echo esc_attr( $name ); ?>[social_shortcode]" value="<?php echo esc_attr( self::get( 'social_shortcode' ) ); ?>" placeholder="[nextend_social_login]">
								<p class="description"><?php echo esc_html__( 'Optional. Paste the shortcode of your social login plugin (Google / Facebook buttons). Leave empty to hide the "Sign with" section.', 'ph-tabbed-cart' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}
