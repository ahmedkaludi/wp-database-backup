<?php
/**
 * BFCM promo banner for the Backups admin screen.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $wpdbbkp_bfcm_end ) ) {
	return;
}

$wpdbbkp_bfcm_remaining = max( 0, (int) $wpdbbkp_bfcm_end - time() );
$wpdbbkp_bfcm_days      = (int) floor( $wpdbbkp_bfcm_remaining / DAY_IN_SECONDS );
$wpdbbkp_bfcm_hours     = (int) floor( ( $wpdbbkp_bfcm_remaining % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
$wpdbbkp_bfcm_mins      = (int) floor( ( $wpdbbkp_bfcm_remaining % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
$wpdbbkp_bfcm_secs      = (int) ( $wpdbbkp_bfcm_remaining % MINUTE_IN_SECONDS );
?>
<div id="wpdbbkp-bfcm-banner" class="wpdbbkp-bfcm" role="region" aria-label="<?php esc_attr_e( 'Black Friday sale', 'wpdbbkp' ); ?>">
	<div class="wpdbbkp-bfcm__layout">
		<div class="wpdbbkp-bfcm__offer">
			<div class="wpdbbkp-bfcm__pill">
				<svg class="wpdbbkp-bfcm__icon" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9.2 1 3.2 9h4.1L6.4 15 13 7H8.7L9.2 1z"/></svg>
				<?php esc_html_e( 'BLACK FRIDAY / CYBER MONDAY / CHRISTMAS SALE', 'wpdbbkp' ); ?>
			</div>
			<div class="wpdbbkp-bfcm__offer-row">
				<div class="wpdbbkp-bfcm__badge">
					<span class="wpdbbkp-bfcm__badge-num">60%</span>
					<span class="wpdbbkp-bfcm__badge-off"><?php esc_html_e( 'OFF', 'wpdbbkp' ); ?></span>
				</div>
				<div class="wpdbbkp-bfcm__copyblock">
					<h2 class="wpdbbkp-bfcm__title"><?php esc_html_e( 'BIGGEST SALE IN OUR HISTORY — NEVER BEFORE SEEN!', 'wpdbbkp' ); ?></h2>
					<p class="wpdbbkp-bfcm__sub"><?php esc_html_e( 'Sale is here! 60% off on Everything for a very limited time!!!', 'wpdbbkp' ); ?></p>
				</div>
				<button type="button" class="wpdbbkp-bfcm__code" aria-label="<?php esc_attr_e( 'Copy coupon code BFCM2026', 'wpdbbkp' ); ?>">
					<span class="wpdbbkp-bfcm__code-label"><?php esc_html_e( 'CODE:', 'wpdbbkp' ); ?> <strong>BFCM2026</strong></span>
					<svg class="wpdbbkp-bfcm__code-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><rect x="8" y="8" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M6 16H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
					<span class="wpdbbkp-bfcm__copied"><?php esc_html_e( 'Copied', 'wpdbbkp' ); ?></span>
				</button>
			</div>
		</div>
		<div class="wpdbbkp-bfcm__aside">
			<div class="wpdbbkp-bfcm__timer-wrap">
				<div class="wpdbbkp-bfcm__expires">
					<svg class="wpdbbkp-bfcm__icon" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="6.2" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M8 4.5V8l2.4 1.6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
					<?php esc_html_e( 'OFFER EXPIRES IN:', 'wpdbbkp' ); ?>
				</div>
				<div class="wpdbbkp-bfcm__timer">
					<div class="wpdbbkp-bfcm__unit">
						<span class="wpdbbkp-bfcm__num" data-unit="days"><?php echo esc_html( sprintf( '%02d', $wpdbbkp_bfcm_days ) ); ?></span>
						<span class="wpdbbkp-bfcm__unit-label"><?php esc_html_e( 'DAYS', 'wpdbbkp' ); ?></span>
					</div>
					<span class="wpdbbkp-bfcm__colon" aria-hidden="true">:</span>
					<div class="wpdbbkp-bfcm__unit">
						<span class="wpdbbkp-bfcm__num" data-unit="hours"><?php echo esc_html( sprintf( '%02d', $wpdbbkp_bfcm_hours ) ); ?></span>
						<span class="wpdbbkp-bfcm__unit-label"><?php esc_html_e( 'HOURS', 'wpdbbkp' ); ?></span>
					</div>
					<span class="wpdbbkp-bfcm__colon" aria-hidden="true">:</span>
					<div class="wpdbbkp-bfcm__unit">
						<span class="wpdbbkp-bfcm__num" data-unit="mins"><?php echo esc_html( sprintf( '%02d', $wpdbbkp_bfcm_mins ) ); ?></span>
						<span class="wpdbbkp-bfcm__unit-label"><?php esc_html_e( 'MINS', 'wpdbbkp' ); ?></span>
					</div>
					<span class="wpdbbkp-bfcm__colon" aria-hidden="true">:</span>
					<div class="wpdbbkp-bfcm__unit">
						<span class="wpdbbkp-bfcm__num" data-unit="secs"><?php echo esc_html( sprintf( '%02d', $wpdbbkp_bfcm_secs ) ); ?></span>
						<span class="wpdbbkp-bfcm__unit-label"><?php esc_html_e( 'SECS', 'wpdbbkp' ); ?></span>
					</div>
				</div>
			</div>
			<div class="wpdbbkp-bfcm__action">
				<a class="wpdbbkp-bfcm__cta" href="<?php echo esc_url( 'https://backupforwp.com/bfcm-2026/' ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Claim The Offer Now', 'wpdbbkp' ); ?>
					<svg class="wpdbbkp-bfcm__icon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9.2 1 3.2 9h4.1L6.4 15 13 7H8.7L9.2 1z"/></svg>
				</a>
				<div class="wpdbbkp-bfcm__guarantee">
					<svg class="wpdbbkp-bfcm__icon" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false"><rect x="3.2" y="7" width="9.6" height="7" rx="1.4" fill="none" stroke="currentColor" stroke-width="1.3"/><path d="M5.2 7V5.2a2.8 2.8 0 0 1 5.6 0V7" fill="none" stroke="currentColor" stroke-width="1.3"/></svg>
					<?php esc_html_e( '30-Day Money Back Guarantee', 'wpdbbkp' ); ?>
				</div>
			</div>
		</div>
	</div>
	<button type="button" class="wpdbbkp-bfcm__dismiss" aria-label="<?php esc_attr_e( 'Dismiss for 7 days', 'wpdbbkp' ); ?>">
		<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
	</button>
</div>
