<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="message" class="updated woocommerce-message wc-connect">
	<p>
		<?php echo WC_s2p()->__( 'Smart2Pay plugin installed with success.' ); ?>
		<a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_smart2pay' )?>"><?php echo WC_s2p()->__( 'Configure Smart2Pay plugin' ); ?></a>
	</p>
</div>
