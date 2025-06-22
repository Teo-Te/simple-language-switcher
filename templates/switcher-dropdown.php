<?php
if (!isset($languages) || !isset($current_locale)) return;

$active_languages = array_filter($languages, function($lang) {
    return $lang['active'];
});

if (count($active_languages) <= 1) return;

$switcher_display = new SLS_Switcher_Display();
$current_lang = isset($languages[$current_locale]) ? $languages[$current_locale] : reset($languages);
?>

<div class="sls-language-switcher" data-current-locale="<?php echo esc_attr($current_locale); ?>">
    <div class="sls-current-language">
        <span class="sls-flag"><?php echo $current_lang['flag']; ?></span>
        <span class="sls-name"><?php echo $current_lang['name']; ?></span>
        <span class="sls-arrow">▼</span>
    </div>
    
    <div class="sls-dropdown">
        <?php foreach ($active_languages as $locale => $lang): ?>
            <?php if ($locale !== $current_locale): ?>
                <a href="<?php echo $switcher_display->get_language_url($locale); ?>" 
                   class="sls-language-option" 
                   data-locale="<?php echo esc_attr($locale); ?>">
                    <span class="sls-flag"><?php echo $lang['flag']; ?></span>
                    <span class="sls-name"><?php echo $lang['name']; ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
