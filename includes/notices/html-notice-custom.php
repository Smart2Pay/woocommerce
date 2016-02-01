<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( !empty( $notice_arr ) and is_array( $notice_arr ) )
{
	?>
	<div id="<?php echo $notice_arr['message_id']?>" class="woocommerce-message wc-connect <?php echo $notice_arr['notice_type']?>">
		<p>
			<?php echo $notice_arr['message'] ?>
		</p>
	</div>
	<?php
}
