/* PH Tabbed Cart Panel - front-end logic. Everything lives inside this closure. */
(function () {
	'use strict';

	function phtcInit() {

	var cfg = window.phtcData;
	var root = document.getElementById('phtc-root');
	if (!cfg || !root) {
		return;
	}

	var tabs = root.querySelectorAll('[data-phtc-tab]');
	var panes = root.querySelectorAll('[data-phtc-pane]');
	var nonce = '';
	var isOpen = false;
	var lastFocus = null;
	var activeTab = 'cart';
	var requestId = 0;
	var warmed = false;
	var lastHtml = { cart: null, wishlist: null, account: null };

	function qs(sel) {
		return root.querySelector(sel);
	}

	function hasTab(name) {
		return !!qs('[data-phtc-tab="' + name + '"]');
	}

	function pane(name) {
		return qs('[data-phtc-pane="' + name + '"]');
	}

	function buildUrl(url) {
		return url + (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
	}

	/* ---------- Tabs ---------- */

	function setTab(name) {
		if (!hasTab(name)) {
			name = 'cart';
		}
		activeTab = name;
		Array.prototype.forEach.call(tabs, function (tab) {
			var on = tab.getAttribute('data-phtc-tab') === name;
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.setAttribute('tabindex', on ? '0' : '-1');
		});
		Array.prototype.forEach.call(panes, function (p) {
			p.hidden = p.getAttribute('data-phtc-pane') !== name;
		});
	}

	/* ---------- Rendering ---------- */

	function setPane(name, html) {
		var el = pane(name);
		if (!el || typeof html !== 'string') {
			return;
		}
		// Only touch the DOM when the markup really changed (keeps typed login values).
		if (lastHtml[name] !== html) {
			el.innerHTML = html;
			lastHtml[name] = html;
		}
	}

	function setCount(name, value) {
		var el = qs('[data-phtc-count="' + name + '"]');
		if (el) {
			el.textContent = String(value);
		}
	}

	function render(data) {
		if (!data) {
			return;
		}
		nonce = data.nonce || nonce;
		setPane('cart', data.cart_html);
		setPane('wishlist', data.wishlist_html);
		setPane('account', data.account_html);
		setCount('cart', data.cart_count || 0);
		setCount('wishlist', data.wishlist_count || 0);
	}

	function showError() {
		var el = pane(activeTab);
		if (!el) {
			return;
		}
		var wrap = document.createElement('div');
		wrap.className = 'phtc-empty';
		var p = document.createElement('p');
		p.textContent = cfg.i18n.error;
		wrap.appendChild(p);
		el.innerHTML = '';
		el.appendChild(wrap);
		lastHtml[activeTab] = null;
	}

	/* ---------- Network ---------- */

	function load() {
		var id = ++requestId;
		root.classList.add('phtc-root--loading');

		return fetch(buildUrl(cfg.urls.panel), {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' }
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (res) {
				if (id !== requestId) {
					return;
				}
				root.classList.remove('phtc-root--loading');
				if (res && res.success) {
					render(res.data);
				} else {
					showError();
				}
			})
			.catch(function () {
				if (id === requestId) {
					root.classList.remove('phtc-root--loading');
					showError();
				}
			});
	}

	function post(url, params) {
		var body = new FormData();
		body.append('nonce', nonce);
		Object.keys(params).forEach(function (key) {
			body.append(key, params[key]);
		});
		return fetch(url, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) {
			return r.json();
		});
	}

	function refreshWooFragments() {
		if (window.jQuery) {
			window.jQuery(document.body).trigger('wc_fragment_refresh');
		}
	}

	/* ---------- Open / close ---------- */

	function focusables() {
		var panel = qs('.phtc-panel');
		var list = panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])');
		return Array.prototype.filter.call(list, function (el) {
			return el.offsetParent !== null;
		});
	}

	function open(tab) {
		if (!isOpen) {
			isOpen = true;
			lastFocus = document.activeElement;
			root.classList.add('phtc-root--open');
			root.setAttribute('aria-hidden', 'false');
			document.documentElement.classList.add('phtc-lock');
			window.setTimeout(function () {
				var closeBtn = qs('.phtc-close');
				if (closeBtn) {
					closeBtn.focus();
				}
			}, 60);
		}
		setTab(tab || activeTab);
		load();
	}

	function close() {
		if (!isOpen) {
			return;
		}
		isOpen = false;
		root.classList.remove('phtc-root--open');
		root.setAttribute('aria-hidden', 'true');
		document.documentElement.classList.remove('phtc-lock');
		if (lastFocus && typeof lastFocus.focus === 'function') {
			lastFocus.focus();
		}
	}

	/* ---------- Triggers (header cart button, custom classes) ---------- */

	function matchesCustomSelector(el) {
		if (!cfg.selectors) {
			return false;
		}
		try {
			return !!el.closest(cfg.selectors);
		} catch (err) {
			return false;
		}
	}

	function triggerTab(el) {
		if (!el || !el.closest) {
			return null;
		}
		if (el.closest('.phtc-open-wishlist, a[href="#phtc-wishlist"]')) {
			return 'wishlist';
		}
		if (el.closest('.phtc-open-account, a[href="#phtc-account"]')) {
			return 'account';
		}
		if (el.closest('.phtc-open-cart, .phtc-open, a[href="#phtc-cart"]') || matchesCustomSelector(el)) {
			return 'cart';
		}
		return null;
	}

	// Capture phase so Elementor's own cart toggle never gets the click.
	document.addEventListener(
		'click',
		function (e) {
			var tab = triggerTab(e.target);
			if (!tab) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			open(tab);
		},
		true
	);

	// Warm up the data on first hover / touch so the panel opens already filled.
	function warm(e) {
		if (warmed || isOpen || !triggerTab(e.target)) {
			return;
		}
		warmed = true;
		load();
	}
	document.addEventListener('mouseover', warm, { passive: true });
	document.addEventListener('touchstart', warm, { passive: true });

	/* ---------- Clicks inside the panel ---------- */

	root.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || !t.closest) {
			return;
		}
		var el;

		if (t.closest('[data-phtc-close]')) {
			close();
			return;
		}

		el = t.closest('[data-phtc-tab]');
		if (el) {
			setTab(el.getAttribute('data-phtc-tab'));
			return;
		}

		el = t.closest('[data-phtc-cart-remove]');
		if (el) {
			e.preventDefault();
			removeCartItem(el);
			return;
		}

		el = t.closest('[data-phtc-wl-remove]');
		if (el) {
			e.preventDefault();
			removeWishlistItem(el);
			return;
		}

		el = t.closest('[data-phtc-wl-add]');
		if (el) {
			e.preventDefault();
			addWishlistItemToCart(el);
		}
	});

	function busy(btn, on) {
		var row = btn.closest('.phtc-item');
		if (row) {
			row.classList.toggle('phtc-item--busy', on);
		}
		btn.disabled = on;
	}

	function removeCartItem(btn) {
		busy(btn, true);
		post(cfg.urls.removeCart, { key: btn.getAttribute('data-phtc-cart-remove') })
			.then(function (res) {
				if (res && res.success) {
					render(res.data);
					refreshWooFragments();
				} else {
					busy(btn, false);
				}
			})
			.catch(function () {
				busy(btn, false);
			});
	}

	function removeWishlistItem(btn) {
		busy(btn, true);
		post(cfg.urls.removeWishlist, { product_id: btn.getAttribute('data-phtc-wl-remove') })
			.then(function (res) {
				if (res && res.success) {
					render(res.data);
				} else {
					busy(btn, false);
				}
			})
			.catch(function () {
				busy(btn, false);
			});
	}

	function addWishlistItemToCart(btn) {
		busy(btn, true);
		post(cfg.urls.addToCart, { product_id: btn.getAttribute('data-phtc-wl-add'), quantity: 1 })
			.then(function (res) {
				busy(btn, false);
				if (res && res.error) {
					if (res.product_url) {
						window.location.href = res.product_url;
					}
					return;
				}
				if (window.jQuery) {
					window.jQuery(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, window.jQuery(btn)]);
				}
				setTab('cart');
				load();
			})
			.catch(function () {
				busy(btn, false);
			});
	}

	/* ---------- Login form ---------- */

	root.addEventListener('submit', function (e) {
		var form = e.target && e.target.closest ? e.target.closest('.phtc-login') : null;
		if (!form) {
			return;
		}
		e.preventDefault();

		var errBox = form.querySelector('.phtc-error');
		var submit = form.querySelector('[type="submit"]');
		var body = new FormData(form);
		body.append('nonce', nonce);

		errBox.hidden = true;
		submit.disabled = true;

		fetch(cfg.urls.login, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) {
				return r.json();
			})
			.then(function (res) {
				if (res && res.success) {
					window.location.reload();
					return;
				}
				errBox.textContent = (res && res.data && res.data.message) || cfg.i18n.error;
				errBox.hidden = false;
				submit.disabled = false;
			})
			.catch(function () {
				errBox.textContent = cfg.i18n.error;
				errBox.hidden = false;
				submit.disabled = false;
			});
	});

	/* ---------- Keyboard ---------- */

	document.addEventListener('keydown', function (e) {
		if (!isOpen) {
			return;
		}

		if (e.key === 'Escape') {
			close();
			return;
		}

		if (e.key === 'Tab') {
			var items = focusables();
			if (!items.length) {
				return;
			}
			var first = items[0];
			var last = items[items.length - 1];
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
			return;
		}

		if ((e.key === 'ArrowRight' || e.key === 'ArrowLeft') && e.target.closest && e.target.closest('.phtc-tabs')) {
			var list = Array.prototype.slice.call(tabs);
			var idx = list.indexOf(e.target.closest('[data-phtc-tab]'));
			if (idx === -1) {
				return;
			}
			var next = list[(idx + (e.key === 'ArrowRight' ? 1 : list.length - 1)) % list.length];
			setTab(next.getAttribute('data-phtc-tab'));
			next.focus();
		}
	});

	/* ---------- WooCommerce / YITH events ---------- */

	if (window.jQuery) {
		var $body = window.jQuery(document.body);

		$body.on('added_to_cart', function () {
			if (cfg.autoOpen || isOpen) {
				open('cart');
			}
		});

		$body.on('removed_from_cart added_to_wishlist removed_from_wishlist', function () {
			if (isOpen) {
				load();
			}
		});
	}
}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', phtcInit);
	} else {
		phtcInit();
	}
})();
