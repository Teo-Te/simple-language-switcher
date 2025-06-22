<?php
$manager = new SLS_Language_Manager();
$languages = $manager->get_languages();
$common_flags = $manager->get_common_flags();
?>

<div class="wrap sls-admin-page">
    <h1>Language Switcher Settings</h1>
    
    <form id="language-form">
        <table class="wp-list-table widefat fixed striped sls-languages-table">
            <thead>
                <tr>
                    <th>Language Code</th>
                    <th>Language Name</th>
                    <th>Locale</th>
                    <th>Flag</th>
                    <th>Preview</th>
                    <th>Active</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="languages-table">
                <?php 
                $statuses = $manager->get_language_statuses();
                foreach ($languages as $locale => $lang): 
                    $status = isset($statuses[$locale]) ? $statuses[$locale] : null;
                ?>
                <tr>
                    <td><input type="text" value="<?php echo esc_attr($lang['code']); ?>" class="lang-code" maxlength="10"></td>
                    <td><input type="text" value="<?php echo esc_attr($lang['name']); ?>" class="lang-name"></td>
                    <td><input type="text" value="<?php echo esc_attr($lang['locale']); ?>" class="lang-locale"></td>
                    <td>
                        <select class="lang-flag">
                            <option value="">Select Flag</option>
                            <?php foreach ($common_flags as $flag_code => $flag_entity): ?>
                            <option value="<?php echo esc_attr($flag_entity); ?>" 
                                    <?php selected($lang['flag'], $flag_entity); ?>>
                                <?php echo $flag_entity; ?> <?php echo strtoupper($flag_code); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="custom" <?php selected(!in_array($lang['flag'], $common_flags) && !empty($lang['flag'])); ?>>Custom...</option>
                        </select>
                        <input type="text" class="custom-flag" 
                               style="<?php echo (!in_array($lang['flag'], $common_flags) && !empty($lang['flag'])) ? 'display:block;' : 'display:none;'; ?>" 
                               placeholder="&#127482;&#127480;" 
                               value="<?php echo (!in_array($lang['flag'], $common_flags) && !empty($lang['flag'])) ? esc_attr($lang['flag']) : ''; ?>">
                    </td>
                    <td class="flag-preview"><?php echo $lang['flag']; ?></td>
                    <td><input type="checkbox" <?php checked($lang['active']); ?> class="lang-active"></td>
                    <td class="status-cell">
                        <?php if ($status): ?>
                            <?php if ($status['installed']): ?>
                                <span style="color: green;">✓ Installed</span>
                            <?php elseif ($status['available']): ?>
                                <span style="color: orange;">⚠ Available</span>
                            <?php else: ?>
                                <span style="color: red;">✗ Not available</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><button type="button" class="button remove-lang">Remove</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="sls-form-buttons">
            <button type="button" id="add-language" class="button">Add Language</button>
            <button type="submit" class="button button-primary">Save Languages</button>
        </div>
    </form>
    
    <div class="flag-help">
        <h3>Common Flag HTML Entities:</h3>
        <ul>
            <?php foreach ($common_flags as $code => $entity): ?>
            <li><?php echo $entity; ?> <?php echo strtoupper($code); ?> - <code><?php echo esc_html($entity); ?></code></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
// Pass PHP data to JavaScript
var sls_admin_data = {
    common_flags: <?php echo json_encode($common_flags); ?>,
    nonce: '<?php echo wp_create_nonce('sls_admin_nonce'); ?>'
};
</script>