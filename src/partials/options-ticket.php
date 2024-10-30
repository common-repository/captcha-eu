<div class="wrap captcha-at">
    <?php include 'header.php'; ?>

    <div class="settings-content">

        <form id="captcha-at-form-ticket" method="post" action="">
            <div id="captcha-at-notices">
            <?php
                // check if errors occured & add notice panel
                if (! empty($configMessages)) {
                    foreach ($configMessages as $error) {
                        echo $options->panelMSG($error->type, $error->msg);
                    }
                }
    ?>
            </div>

            <!-- REST Key -->
            <?php
        echo $options->settingsPanel([
            $options->wrapInDiv('header', [
                $options->fieldTitle(__('Open Ticket', 'captcha-eu'), ''),
            ]),
            $options->wrapInDiv('content', [
                $options->fieldInputTextarea('message', $messageValue, __('Message *', 'captcha-eu')),
            ]),
        ]);
    ?>

            <?php submit_button(__('Send', 'captcha-eu'), 'primary', 'btn-submit', true, ['id' => 'submit_button']); ?>
        </form>
    </div>
</div>
