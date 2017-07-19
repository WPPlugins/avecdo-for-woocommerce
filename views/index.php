<div class="avecdowrap">
    <?php
    if (isset($mesages) && count($mesages) > 0):
        foreach ($mesages as $type => $mesage):
            avecdoEchoNotice(implode('<br>', $mesage), $type, true);
        endforeach;
    endif;
    if (!$activation): ?>
        <div class="boxfull logo"></div>
        <div class="boxstatic">
            <div class="boxhalf left">
                <h4><?php echo __('New to avecdo?', 'avecdo-for-woocommerce') ?></h4>
                <p><?php echo __('Before you can activate your plugin. You have to go to app.avecdo.com/register, create a profile and add a shop.', 'avecdo-for-woocommerce') ?></p>
            </div>
            <div class="boxhalf right">
                <h4><?php echo __('Are you already a member?', 'avecdo-for-woocommerce') ?></h4>
                <p><?php echo __("If you're already a member of avecdo, then click here to activate your plugin. You can find the activation key in 'view more' on the shop setup at app.avecdo.com/channel.", 'avecdo-for-woocommerce') ?></p>
                
            </div>
        </div>
        <div class="boxstatic">
            <div class="boxhalf left"><p class="txtcenter"><a href="https://app.avecdo.com/register" target="_blank" class="avecdobtn-primary avecdobtn">Register</a></p></div>
            <div class="boxhalf right"><p class="txtcenter"><a href="<?php echo admin_url('admin.php?page=avecdo&activation=true'); ?>" class="avecdobtn-primary avecdobtn">Connect your shop</a></p></div>
        </div>
    <?php else: ?>
        <div class="boxfull activation-flow"></div>
        <div class="boxstatic">
            <div class="box-layout">
                <div class="boxfull dyn static">
                    <img class="right-sm-logo" src="<?php echo plugins_url('assets/images/avecdo-logo.png', dirname(__FILE__)); ?>" alt="avecdo logo"/>
                    <h4><?php echo __('Connect your shop.', 'avecdo-for-woocommerce') ?></h4>
                </div>
                <div class="boxfull dyn static">
                    <form method="post" action="<?php echo admin_url('admin.php?page=avecdo&activation=true'); ?>">
                        <input type="hidden" name="avecdo_submit_activation" value="1" />
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('avecdo_activation_form'); ?>" />
                        <h4 class="subheader"><?php echo __('Activation key.', 'avecdo-for-woocommerce') ?></h4>
                        <input type="text" name="avecdo_activation_key" value="" id="avecdo_activation_key" spellcheck="false" autocomplete="off">
                        <p class="txtcenter"><button type="submit" class="avecdobtn-primary avecdobtn"><?php echo __('Connect', 'avecdo-for-woocommerce') ?></button></p>
                    </form>
                    <h4 class="subheader"><?php echo __('How to activate the plugin.', 'avecdo-for-woocommerce') ?></h4>
                    <ul class="hlp-list">
                        <li><?php echo __('First of all, you need to create a profile at', 'avecdo-for-woocommerce') ?> <a href="https://app.avecdo.com/register" target="_blank">app.avecdo.com.</a></li>
                        <li><?php echo __('After completing the profile sign-up, you need to add your shop.', 'avecdo-for-woocommerce') ?></li>
                        <li><?php echo __('In the first step of the shop sign up, you choose WooCommerce.', 'avecdo-for-woocommerce') ?></li>
                        <li><?php echo __('On the next step you can see an activation key, you have to copy this', 'avecdo-for-woocommerce') ?></li>
                        <li><?php echo __('Paste the activation key into the input field above and click Connect', 'avecdo-for-woocommerce') ?></li>
                        <li><?php echo __('Once activated, go back to avecdo where you should receive a confirmation. This may take up to a minute.', 'avecdo-for-woocommerce') ?></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
