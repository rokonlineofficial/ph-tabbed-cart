<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PHTC_Panel' ) ) {

	class PHTC_Panel {

		const NONCE_ACTION = 'phtc_ajax';

		public static function init() {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'wp_footer', array( __CLASS__, 'render_shell' ), 5 );

			// WooCommerce lightweight AJAX endpoints (?wc-ajax=...), never page-cached.
			add_action( 'wc_ajax_phtc_get_panel', array( __CLASS__, 'ajax_get_panel' ) );
			add_action( 'wc_ajax_phtc_login', array( __CLASS__, 'ajax_login' ) );
			add_action( 'wc_ajax_phtc_remove_cart_item', array( __CLASS__, 'ajax_remove_cart_item' ) );
			add_action( 'wc_ajax_phtc_remove_wishlist_item', array( __CLASS__, 'ajax_remove_wishlist_item' ) );
		}

		/* ------------------------------------------------------------------
		 * Front-end loading
		 * ------------------------------------------------------------------ */

		private static function should_render() {
			if ( is_admin() || is_feed() || is_customize_preview() ) {
				return false;
			}
			// Keep the Elementor editor / preview clean.
			if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return false;
			}
			if ( class_exists( '\Elementor\Plugin' ) ) {
				$elementor = \Elementor\Plugin::$instance;
				if ( isset( $elementor->preview ) && is_object( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
					return false;
				}
			}
			return (bool) apply_filters( 'phtc_should_render', true );
		}

		private static function has_wishlist() {
			return function_exists( 'YITH_WCWL' );
		}

		public static function enqueue() {
			if ( ! self::should_render() ) {
				return;
			}

			wp_enqueue_style( 'phtc-panel', PHTC_URL . 'assets/css/phtc-panel.css', array(), PHTC_VERSION );

			if ( 'yes' === PHTC_Settings::get( 'hide_elementor_cart' ) ) {
				wp_add_inline_style( 'phtc-panel', '.elementor-menu-cart__container{display:none !important;}' );
			}

			wp_enqueue_script(
				'phtc-panel',
				PHTC_URL . 'assets/js/phtc-panel.js',
				array( 'jquery' ),
				PHTC_VERSION,
				array( 'in_footer' => true )
			);

			wp_localize_script(
				'phtc-panel',
				'phtcData',
				array(
					'urls'      => array(
						'panel'          => WC_AJAX::get_endpoint( 'phtc_get_panel' ),
						'login'          => WC_AJAX::get_endpoint( 'phtc_login' ),
						'removeCart'     => WC_AJAX::get_endpoint( 'phtc_remove_cart_item' ),
						'removeWishlist' => WC_AJAX::get_endpoint( 'phtc_remove_wishlist_item' ),
						'addToCart'      => WC_AJAX::get_endpoint( 'add_to_cart' ),
					),
					'selectors' => PHTC_Settings::get( 'trigger_selectors' ),
					'autoOpen'  => ( 'yes' === PHTC_Settings::get( 'auto_open' ) ),
					'i18n'      => array(
						'error' => __( 'Something went wrong. Please try again.', 'ph-tabbed-cart' ),
					),
				)
			);
		}

		/* ------------------------------------------------------------------
		 * Static shell (contains no user specific data, so page caching is safe)
		 * ------------------------------------------------------------------ */

		private static function icon_close() {
			return '<svg class="phtc-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M5 5l14 14M19 5L5 19" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
		}

		private static function icon_remove() {
			return '<svg class="phtc-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8.6 8.6l6.8 6.8M15.4 8.6l-6.8 6.8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
		}

		public static function render_shell() {
			if ( ! self::should_render() ) {
				return;
			}
			$has_wishlist = self::has_wishlist();
			?>
			<div id="phtc-root" class="phtc-root" aria-hidden="true">
				<div class="phtc-overlay" data-phtc-close></div>
				<aside class="phtc-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Wishlist, account and cart', 'ph-tabbed-cart' ); ?>" tabindex="-1">
					<div class="phtc-head">
						<button type="button" class="phtc-close" data-phtc-close>
							<?php echo self::icon_close(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span><?php echo esc_html__( 'Close', 'ph-tabbed-cart' ); ?></span>
						</button>
					</div>

					<div class="phtc-tabs" role="tablist">
						<?php if ( $has_wishlist ) : ?>
							<button type="button" role="tab" id="phtc-tab-wishlist" class="phtc-tab" data-phtc-tab="wishlist" aria-selected="false" aria-controls="phtc-pane-wishlist" tabindex="-1">
								<?php echo esc_html__( 'Wishlist', 'ph-tabbed-cart' ); ?>(<span class="phtc-count" data-phtc-count="wishlist">0</span>)
							</button>
						<?php endif; ?>
						<button type="button" role="tab" id="phtc-tab-account" class="phtc-tab" data-phtc-tab="account" aria-selected="false" aria-controls="phtc-pane-account" tabindex="-1">
							<?php echo esc_html__( 'My Account', 'ph-tabbed-cart' ); ?>
						</button>
						<button type="button" role="tab" id="phtc-tab-cart" class="phtc-tab" data-phtc-tab="cart" aria-selected="true" aria-controls="phtc-pane-cart" tabindex="0">
							<?php echo esc_html__( 'Cart', 'ph-tabbed-cart' ); ?>(<span class="phtc-count" data-phtc-count="cart">0</span>)
						</button>
					</div>

					<div class="phtc-body">
						<?php if ( $has_wishlist ) : ?>
							<div id="phtc-pane-wishlist" class="phtc-pane" role="tabpanel" aria-labelledby="phtc-tab-wishlist" data-phtc-pane="wishlist" hidden></div>
						<?php endif; ?>
						<div id="phtc-pane-account" class="phtc-pane" role="tabpanel" aria-labelledby="phtc-tab-account" data-phtc-pane="account" hidden></div>
						<div id="phtc-pane-cart" class="phtc-pane" role="tabpanel" aria-labelledby="phtc-tab-cart" data-phtc-pane="cart"></div>
					</div>
				</aside>
			</div>
			<?php
		}

		/* ------------------------------------------------------------------
		 * AJAX helpers
		 * ------------------------------------------------------------------ */

		private static function ensure_cart() {
			if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
				wc_load_cart();
			}
		}

		private static function verify_nonce() {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Session expired. Please close and reopen the panel, then try again.', 'ph-tabbed-cart' ) ),
					403
				);
			}
		}

		/**
		 * Everything the front-end needs to paint the panel.
		 */
		private static function collect() {
			$wishlist = self::wishlist_data();

			return array(
				'cart_count'     => ( WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0,
				'wishlist_count' => count( $wishlist['products'] ),
				'cart_html'      => self::cart_html(),
				'wishlist_html'  => self::has_wishlist() ? self::wishlist_html( $wishlist ) : '',
				'account_html'   => self::account_html(),
				'logged_in'      => is_user_logged_in(),
				'nonce'          => wp_create_nonce( self::NONCE_ACTION ),
			);
		}

		public static function ajax_get_panel() {
			self::ensure_cart();
			wp_send_json_success( self::collect() );
		}

		public static function ajax_login() {
			self::verify_nonce();

			if ( is_user_logged_in() ) {
				wp_send_json_success( array( 'reload' => true ) );
			}

			$login = isset( $_POST['log'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['log'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$pass  = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

			if ( '' === $login || '' === $pass ) {
				wp_send_json_error( array( 'message' => __( 'Please enter your email address and password.', 'ph-tabbed-cart' ) ) );
			}

			$user = wp_signon(
				array(
					'user_login'    => $login,
					'user_password' => $pass,
					'remember'      => ! empty( $_POST['rememberme'] ), // phpcs:ignore WordPress.Security.NonceVerification
				),
				is_ssl()
			);

			if ( is_wp_error( $user ) ) {
				wp_send_json_error( array( 'message' => wp_strip_all_tags( $user->get_error_message() ) ) );
			}

			wp_send_json_success( array( 'reload' => true ) );
		}

		public static function ajax_remove_cart_item() {
			self::verify_nonce();
			self::ensure_cart();

			$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			if ( '' !== $key && WC()->cart ) {
				$items = WC()->cart->get_cart();
				if ( isset( $items[ $key ] ) ) {
					WC()->cart->remove_cart_item( $key );
					WC()->cart->calculate_totals();
				}
			}

			wp_send_json_success( self::collect() );
		}

		public static function ajax_remove_wishlist_item() {
			self::verify_nonce();
			self::ensure_cart();

			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

			if ( $product_id && self::has_wishlist() ) {
				$removed  = false;
				$wishlist = self::get_wishlist();

				// 1) Official YITH API.
				try {
					if ( method_exists( YITH_WCWL(), 'remove' ) ) {
						$args = array( 'remove_from_wishlist' => $product_id );
						if ( $wishlist && method_exists( $wishlist, 'get_id' ) ) {
							$args['wishlist_id'] = $wishlist->get_id();
						}
						YITH_WCWL()->remove( $args );
						$removed = true;
					}
				} catch ( Throwable $e ) {
					$removed = false;
				}

				// 2) Fallback: remove straight from the wishlist object.
				if ( ! $removed && $wishlist && method_exists( $wishlist, 'remove_product' ) ) {
					try {
						$wishlist->remove_product( $product_id );
						if ( method_exists( $wishlist, 'save' ) ) {
							$wishlist->save();
						}
					} catch ( Throwable $e ) {
						// Ignore, the refreshed list below shows the real state.
						unset( $e );
					}
				}
			}

			wp_send_json_success( self::collect() );
		}

		/* ------------------------------------------------------------------
		 * Cart tab
		 * ------------------------------------------------------------------ */

		private static function cart_html() {
			$cart = WC()->cart;

			if ( ! $cart || $cart->is_empty() ) {
				$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
				return '<div class="phtc-empty"><p>' . esc_html__( 'Your cart is empty.', 'ph-tabbed-cart' ) . '</p>'
					. '<a class="phtc-btn phtc-btn--dark" href="' . esc_url( $shop ) . '">' . esc_html__( 'Continue shopping', 'ph-tabbed-cart' ) . '</a></div>';
			}

			ob_start();
			echo '<ul class="phtc-items">';

			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$product    = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

				if ( ! $product || ! $product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
					continue;
				}

				$name      = apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key );
				$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
				$permalink = apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
				$price     = apply_filters( 'woocommerce_cart_item_price', $cart->get_product_price( $product ), $cart_item, $cart_item_key );

				$name_plain = wp_strip_all_tags( $name );
				?>
				<li class="phtc-item" data-product-id="<?php echo esc_attr( $product_id ); ?>">
					<?php if ( $permalink ) : ?>
						<a class="phtc-item__img" href="<?php echo esc_url( $permalink ); ?>"><?php echo wp_kses_post( $thumbnail ); ?></a>
					<?php else : ?>
						<span class="phtc-item__img"><?php echo wp_kses_post( $thumbnail ); ?></span>
					<?php endif; ?>

					<div class="phtc-item__body">
						<?php if ( $permalink ) : ?>
							<a class="phtc-item__name" href="<?php echo esc_url( $permalink ); ?>"><?php echo wp_kses_post( $name ); ?></a>
						<?php else : ?>
							<span class="phtc-item__name"><?php echo wp_kses_post( $name ); ?></span>
						<?php endif; ?>

						<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

						<div class="phtc-item__price">
							<?php
							echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput
								'woocommerce_widget_cart_item_quantity',
								'<span class="phtc-qty">' . esc_html( $cart_item['quantity'] ) . ' &times; ' . $price . '</span>',
								$cart_item,
								$cart_item_key
							);
							?>
						</div>
					</div>

					<button type="button" class="phtc-item__remove" data-phtc-cart-remove="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s product name */ __( 'Remove %s from cart', 'ph-tabbed-cart' ), $name_plain ) ); ?>">
						<?php echo self::icon_remove(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
				</li>
				<?php
			}

			echo '</ul>';
			?>
			<div class="phtc-subtotal">
				<strong><?php echo esc_html__( 'Subtotal:', 'ph-tabbed-cart' ); ?></strong>
				<span><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></span>
			</div>
			<div class="phtc-actions">
				<a class="phtc-btn phtc-btn--dark" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html__( 'View Cart', 'ph-tabbed-cart' ); ?></a>
				<a class="phtc-btn phtc-btn--gold" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php echo esc_html__( 'Checkout', 'ph-tabbed-cart' ); ?></a>
			</div>
			<?php
			return ob_get_clean();
		}

		/* ------------------------------------------------------------------
		 * Wishlist tab (YITH WooCommerce Wishlist)
		 * ------------------------------------------------------------------ */

		private static function get_wishlist() {
			if ( ! self::has_wishlist() || ! class_exists( 'YITH_WCWL_Wishlist_Factory' ) ) {
				return null;
			}
			try {
				if ( method_exists( 'YITH_WCWL_Wishlist_Factory', 'get_default_wishlist' ) ) {
					$wishlist = YITH_WCWL_Wishlist_Factory::get_default_wishlist();
					if ( $wishlist ) {
						return $wishlist;
					}
				}
				if ( method_exists( 'YITH_WCWL_Wishlist_Factory', 'get_current_wishlist' ) ) {
					return YITH_WCWL_Wishlist_Factory::get_current_wishlist();
				}
			} catch ( Throwable $e ) {
				return null;
			}
			return null;
		}

		/**
		 * @return array{products: WC_Product[], url: string}
		 */
		private static function wishlist_data() {
			$data = array(
				'products' => array(),
				'url'      => '',
			);

			if ( ! self::has_wishlist() ) {
				return $data;
			}

			try {
				$wishlist = self::get_wishlist();
				if ( $wishlist && method_exists( $wishlist, 'get_items' ) ) {
					foreach ( (array) $wishlist->get_items() as $item ) {
						$product = ( is_object( $item ) && method_exists( $item, 'get_product' ) ) ? $item->get_product() : false;
						if ( $product instanceof WC_Product && $product->exists() ) {
							$data['products'][] = $product;
						}
					}
				}
				if ( method_exists( YITH_WCWL(), 'get_wishlist_url' ) ) {
					$data['url'] = (string) YITH_WCWL()->get_wishlist_url();
				}
			} catch ( Throwable $e ) {
				unset( $e );
			}

			return $data;
		}

		private static function wishlist_html( $data ) {
			if ( empty( $data['products'] ) ) {
				$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
				return '<div class="phtc-empty"><p>' . esc_html__( 'Your wishlist is empty.', 'ph-tabbed-cart' ) . '</p>'
					. '<a class="phtc-btn phtc-btn--dark" href="' . esc_url( $shop ) . '">' . esc_html__( 'Continue shopping', 'ph-tabbed-cart' ) . '</a></div>';
			}

			ob_start();
			echo '<ul class="phtc-items">';

			foreach ( $data['products'] as $product ) {
				$id        = $product->get_id();
				$permalink = $product->get_permalink();
				$name      = $product->get_name();
				?>
				<li class="phtc-item" data-product-id="<?php echo esc_attr( $id ); ?>">
					<a class="phtc-item__img" href="<?php echo esc_url( $permalink ); ?>"><?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?></a>
					<div class="phtc-item__body">
						<a class="phtc-item__name" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $name ); ?></a>
						<div class="phtc-item__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
						<div class="phtc-item__actions">
							<?php if ( ! $product->is_in_stock() ) : ?>
								<span class="phtc-stock"><?php echo esc_html__( 'Out of stock', 'ph-tabbed-cart' ); ?></span>
							<?php elseif ( $product->is_type( 'simple' ) && $product->is_purchasable() ) : ?>
								<button type="button" class="phtc-btn phtc-btn--dark phtc-btn--sm" data-phtc-wl-add="<?php echo esc_attr( $id ); ?>"><?php echo esc_html__( 'Add to cart', 'ph-tabbed-cart' ); ?></button>
							<?php else : ?>
								<a class="phtc-btn phtc-btn--dark phtc-btn--sm" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html__( 'Select options', 'ph-tabbed-cart' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<button type="button" class="phtc-item__remove" data-phtc-wl-remove="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s product name */ __( 'Remove %s from wishlist', 'ph-tabbed-cart' ), $name ) ); ?>">
						<?php echo self::icon_remove(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
				</li>
				<?php
			}

			echo '</ul>';

			if ( ! empty( $data['url'] ) ) {
				echo '<div class="phtc-actions"><a class="phtc-btn phtc-btn--gold" href="' . esc_url( $data['url'] ) . '">' . esc_html__( 'View full wishlist', 'ph-tabbed-cart' ) . '</a></div>';
			}

			return ob_get_clean();
		}

		/* ------------------------------------------------------------------
		 * My Account tab
		 * ------------------------------------------------------------------ */

		private static function account_html() {
			$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();

			ob_start();

			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();
				?>
				<div class="phtc-account">
					<h3 class="phtc-h"><?php echo esc_html__( 'MY ACCOUNT', 'ph-tabbed-cart' ); ?></h3>
					<p class="phtc-welcome">
						<?php
						/* translators: %s: customer display name */
						echo esc_html( sprintf( __( 'Hello, %s', 'ph-tabbed-cart' ), $user->display_name ) );
						?>
					</p>
					<?php if ( function_exists( 'wc_get_account_menu_items' ) ) : ?>
						<ul class="phtc-account-links">
							<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
								<li><a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"><?php echo esc_html( $label ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<a class="phtc-btn phtc-btn--dark" href="<?php echo esc_url( $account_url ); ?>"><?php echo esc_html__( 'Open my account', 'ph-tabbed-cart' ); ?></a>
					<?php endif; ?>
				</div>
				<?php
				return ob_get_clean();
			}

			$lost_url  = function_exists( 'wc_lostpassword_url' ) ? wc_lostpassword_url() : wp_lostpassword_url();
			$social    = trim( PHTC_Settings::get( 'social_shortcode' ) );
			$site_name = get_bloginfo( 'name' );
			?>
			<div class="phtc-account">
				<h3 class="phtc-h"><?php echo esc_html__( 'SIGN IN', 'ph-tabbed-cart' ); ?></h3>
				<p class="phtc-welcome">
					<?php
					/* translators: %s: site name */
					echo esc_html( sprintf( __( 'Welcome to %s', 'ph-tabbed-cart' ), $site_name ) );
					?>
				</p>

				<form class="phtc-login" novalidate>
					<div class="phtc-field">
						<label for="phtc-log"><?php echo esc_html__( 'Email Address', 'ph-tabbed-cart' ); ?>:<span class="phtc-req">*</span></label>
						<input type="text" id="phtc-log" name="log" autocomplete="username" placeholder="<?php echo esc_attr__( 'Email Address', 'ph-tabbed-cart' ); ?>">
					</div>
					<div class="phtc-field">
						<label for="phtc-pwd"><?php echo esc_html__( 'Password', 'ph-tabbed-cart' ); ?>:<span class="phtc-req">*</span></label>
						<input type="password" id="phtc-pwd" name="pwd" autocomplete="current-password" placeholder="<?php echo esc_attr__( 'Password', 'ph-tabbed-cart' ); ?>">
					</div>
					<label class="phtc-check"><input type="checkbox" name="rememberme" value="1"> <?php echo esc_html__( 'Remember me', 'ph-tabbed-cart' ); ?></label>
					<div class="phtc-error" role="alert" hidden></div>
					<button type="submit" class="phtc-btn phtc-btn--dark phtc-btn--wide"><?php echo esc_html__( 'Sign In', 'ph-tabbed-cart' ); ?></button>
				</form>

				<a class="phtc-link" href="<?php echo esc_url( $lost_url ); ?>"><?php echo esc_html__( 'Forgot your password?', 'ph-tabbed-cart' ); ?></a>

				<?php if ( '' !== $social ) : ?>
					<div class="phtc-social">
						<p><?php echo esc_html__( 'Sign with:', 'ph-tabbed-cart' ); ?></p>
						<?php echo do_shortcode( $social ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				<?php endif; ?>

				<p class="phtc-new"><?php echo esc_html__( 'New Customer?', 'ph-tabbed-cart' ); ?></p>
				<a class="phtc-btn phtc-btn--gold phtc-btn--wide" href="<?php echo esc_url( $account_url ); ?>"><?php echo esc_html__( 'Register', 'ph-tabbed-cart' ); ?></a>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}
