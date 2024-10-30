<div class="wrap captcha-at">
    <?php include 'header.php'; ?>

    <div class="settings-content">

        <?php
            // check if errors occured & add notice panel
            if (! empty($configMessages)) {
                foreach ($configMessages as $error) {
                    echo $options->panelMSG($error->type, $error->msg);
                }
            }
    ?>

        <?php
        if (isset($apiData['plan']) && isset($apiData['user'])) {
            echo $options->settingsPanel([
                $options->wrapInDiv('header', [
                    $options->fieldTitle(__('User Information', 'captcha-eu')),
                ]),
                $options->fieldKeyValue($apiData['user']),
            ], 'info-user');
        }
        echo $options->settingsPanel([
            $options->wrapInDiv('header', [
                $options->fieldTitle(__('Plugin Information', 'captcha-eu')),
            ]),
            $options->fieldKeyValue($pluginInfo),
        ], 'info-plugin');
    ?>
        <div>
            <a class="button cpt-dashboard" target="_blank" href="<?php echo esc_url($urls->dashboard); ?>"><?php echo __('Dashboard', 'captcha-eu'); ?></a>
            <a class="button cpt-documentation" target="_blank" href="<?php echo esc_url($urls->documentation); ?>"><?php echo __('Documentation', 'captcha-eu'); ?></a>
        </div>
    </div>
</div>
