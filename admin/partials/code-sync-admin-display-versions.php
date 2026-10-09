<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://bueroblanko.de
 * @since      1.0.0
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/admin/partials
 */
?>

<div class="wrap">
    <h1>BB Basic</h1>
    <div class="plugin-version-info">
        <div class="version-card">
            <h3>PHP Version</h3>
            <p class="version-number"><?php echo PHP_VERSION; ?></p>
        </div>
        <div class="version-card">
            <h3>WordPress Version</h3>
            <p class="version-number"><?php echo esc_html( get_bloginfo('version')); ?></p>
        </div>
        <div class="version-card">
            <h3>Divi Theme</h3>
            <p class="version-number"><?php echo esc_html($theme_version); ?></p>
        </div>
        <div class="version-card">
            <h3>BB Basic</h3>
            <?php $code_sync_header = get_file_data( CODE_SYNC_PLUGIN_FILE, array( 'Version' => 'Version' ) ); ?>
            <p class="version-number"><?php echo esc_html( $code_sync_header['Version'] ); ?></p>
            <p>Update-Kanal: <?php echo 'main' === CODE_SYNC_UPDATE_BRANCH ? 'Test (main)' : 'Live (live)'; ?></p>
        </div>
    </div>
    <?php if ( $this->is_bueroblanko_user() ) : ?>
    <div class="bb-devmodus">
        <h2>Developer-Modus</h2>
        <?php if ( ! empty( $code_sync_devmodus_meldung ) ) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html( $code_sync_devmodus_meldung ); ?></p></div>
        <?php endif; ?>
        <?php foreach ( Code_Sync_Developer_Modus::anzeige() as $code_sync_zeile ) : ?>
        <p><?php echo esc_html( $code_sync_zeile ); ?></p>
        <?php endforeach; ?>
        <?php
        $code_sync_namen = array(
            'live'  => 'Auf Live schalten (nur lesend)',
            'sleep' => 'Auf Sleep schalten (Novamira deaktivieren)',
            'clear' => 'Clear (Novamira löschen)',
        );
        $code_sync_stufen = Code_Sync_Developer_Modus::niedrigere_stufen();
        ?>
        <?php if ( $code_sync_stufen ) : ?>
        <form method="post">
            <?php wp_nonce_field( 'bb_devmodus', 'bb_devmodus_nonce' ); ?>
            <?php foreach ( $code_sync_stufen as $code_sync_stufe ) : ?>
            <button type="submit" name="bb_devmodus_stufe" value="<?php echo esc_attr( $code_sync_stufe ); ?>" class="button<?php echo 'clear' === $code_sync_stufe ? '' : ' button-secondary'; ?>"<?php echo 'clear' === $code_sync_stufe ? ' onclick="return confirm(\'Novamira und Novamira Pro von dieser Seite löschen?\');"' : ''; ?>><?php echo esc_html( $code_sync_namen[ $code_sync_stufe ] ); ?></button>
            <?php endforeach; ?>
        </form>
        <?php endif; ?>
        <p class="description">Herunterschalten geht hier. Einschalten oder auf eine höhere Stufe nur per FTP über die Schalter-Datei wp-content/bb-developer-modus.php, damit ein fremdes Admin-Konto den Developer-Modus nicht einschalten kann.</p>
    </div>
    <?php endif; ?>
   
