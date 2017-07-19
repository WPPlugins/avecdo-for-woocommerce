<?php
if (isset($mesages) && count($mesages) > 0):
    foreach ($mesages as $type => $mesage):
        avecdoEchoNotice(implode('<br>', $mesage), $type, true);
    endforeach;
endif;
?>
<div class="avecdowrap">
    <div class="boxstatic mtop">
        <div class="box-layout">
            <div class="boxfull dyn static">
                <img class="right-sm-logo" src="<?php echo plugins_url('assets/images/avecdo-logo.png', dirname(__FILE__)); ?>" alt="avecdo logo"/>
                <h4><?php echo __('Your shop is connected.', 'avecdo-for-woocommerce'); ?></h4>
            </div>
            <div class="boxfull dyn static">
                <form method="post" action="<?php echo admin_url('admin.php?page=avecdo&activation=true'); ?>">
                    <input type="hidden" name="avecdo_submit_reset" value="1" />
                    <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('avecdo_activation_form'); ?>" />
                    <h4 class="subheader"><?php echo __('Activation key.', 'avecdo-for-woocommerce'); ?></h4>
                    <input type="text" name="avecdo_activation_key" value="<?php echo $activationKey; ?>" id="avecdo_activation_key" spellcheck="false" autocomplete="off">
                    <p class="txtcenter"><button type="submit" class="avecdobtn-primary avecdobtn"><?php echo __('Reset', 'avecdo-for-woocommerce'); ?></button></p>
                </form>
            </div>
        </div>
    </div>
</div>